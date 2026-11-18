<?php
/**
 * The connection to Google: signing in with OAuth, keeping the access token fresh, and calling
 * the Calendar API.
 *
 * Uses the calendar.app.created scope: the plugin can only see and change calendars it created
 * itself (its "To-do" calendar), not the rest of your calendars.
 *
 * Your own OAuth client (Google Cloud → APIs & Services → Credentials, type "Web application")
 * provides the client ID and secret. The refresh token is kept in the tdh_google option; access
 * tokens are short-lived and cached in a transient.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Google;

use TodoHub\Settings;

final class Client {

	public const OPTION = 'tdh_google';
	public const SCOPE  = 'https://www.googleapis.com/auth/calendar.app.created';

	private const TOKEN_TRANSIENT = 'tdh_google_access';
	private const STATE_PREFIX    = 'tdh_google_state_';

	/**
	 * Google's addresses. Filterable so tests can point them at a stand-in server.
	 *
	 * @return array{auth: string, token: string, api: string, revoke: string}
	 */
	public static function urls(): array {
		/**
		 * Filters the Google addresses used for signing in and the Calendar API.
		 *
		 * @param array{auth: string, token: string, api: string, revoke: string} $urls
		 */
		return (array) apply_filters(
			'todo_hub_google_urls',
			array(
				'auth'   => 'https://accounts.google.com/o/oauth2/v2/auth',
				'token'  => 'https://oauth2.googleapis.com/token',
				'api'    => 'https://www.googleapis.com/calendar/v3',
				'revoke' => 'https://oauth2.googleapis.com/revoke',
			)
		);
	}

	/** Where Google sends you back after you allow access. Add this to the OAuth client. */
	public static function redirectUri(): string {
		return admin_url( 'admin-post.php?action=tdh_google_callback' );
	}

	public static function hasCredentials(): bool {
		return '' !== (string) Settings::get( 'google_client_id' ) && '' !== (string) Settings::get( 'google_client_secret' );
	}

	/** @return array{refresh_token?: string, calendar_id?: string, calendar_name?: string, connected?: int, error?: string} */
	public static function state(): array {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/** @param array<string, mixed> $changes Keys to change; null removes a key. */
	public static function saveState( array $changes ): void {
		$state = self::state();
		foreach ( $changes as $key => $value ) {
			if ( null === $value ) {
				unset( $state[ $key ] );
			} else {
				$state[ $key ] = $value;
			}
		}
		update_option( self::OPTION, $state, false );
	}

	public static function isConnected(): bool {
		$state = self::state();
		return ! empty( $state['refresh_token'] ) && ! empty( $state['calendar_id'] );
	}

	/** The Google page that asks you to allow access. */
	public static function authUrl(): string {
		$state = wp_generate_password( 32, false );
		set_transient( self::STATE_PREFIX . $state, get_current_user_id(), 15 * MINUTE_IN_SECONDS );
		return add_query_arg(
			array_map(
				'rawurlencode',
				array(
					'client_id'              => (string) Settings::get( 'google_client_id' ),
					'redirect_uri'           => self::redirectUri(),
					'response_type'          => 'code',
					'scope'                  => self::SCOPE,
					'access_type'            => 'offline',
					'prompt'                 => 'consent',
					'include_granted_scopes' => 'true',
					'state'                  => $state,
				)
			),
			self::urls()['auth']
		);
	}

	/** Whether the state Google sent back is one we made for this user (and use it up). */
	public static function checkState( string $state ): bool {
		$key  = self::STATE_PREFIX . preg_replace( '/[^A-Za-z0-9]/', '', $state );
		$user = get_transient( $key );
		delete_transient( $key );
		return false !== $user && (int) $user === get_current_user_id();
	}

	/** Swap the code Google sent back for tokens. */
	public static function exchangeCode( string $code ): bool|\WP_Error {
		$tokens = self::tokenRequest(
			array(
				'code'         => $code,
				'grant_type'   => 'authorization_code',
				'redirect_uri' => self::redirectUri(),
			)
		);
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}
		if ( empty( $tokens['refresh_token'] ) ) {
			return new \WP_Error( 'todo_hub_google_no_refresh', __( 'Google didn\'t give a refresh token. Remove To-do Hub\'s access in your Google account\'s security settings, then connect again.', 'todo-hub' ) );
		}
		self::saveState(
			array(
				'refresh_token' => (string) $tokens['refresh_token'],
				'connected'     => time(),
				'error'         => null,
			)
		);
		self::cacheAccessToken( $tokens );
		return true;
	}

	/** A valid access token, refreshing it when needed. */
	public static function accessToken(): string|\WP_Error {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$refresh = (string) ( self::state()['refresh_token'] ?? '' );
		if ( '' === $refresh ) {
			return new \WP_Error( 'todo_hub_google_not_connected', __( 'Google Calendar isn\'t connected.', 'todo-hub' ) );
		}
		$tokens = self::tokenRequest(
			array(
				'refresh_token' => $refresh,
				'grant_type'    => 'refresh_token',
			)
		);
		if ( is_wp_error( $tokens ) ) {
			if ( 'invalid_grant' === $tokens->get_error_code() ) {
				// Access was removed in the Google account, or the token expired: connect again.
				self::saveState(
					array(
						'refresh_token' => null,
						'error'         => __( 'Google no longer accepts this connection. Connect again.', 'todo-hub' ),
					)
				);
			}
			return $tokens;
		}
		return self::cacheAccessToken( $tokens );
	}

	/**
	 * Call the Calendar API.
	 *
	 * @param array<string, mixed>|null $body JSON body.
	 * @return array{code: int, data: array<string, mixed>|null}|\WP_Error The answer (any status), or an
	 *                                                                      error when Google couldn't be reached.
	 */
	public static function api( string $method, string $path, ?array $body = null ): array|\WP_Error {
		$token = self::accessToken();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'method'  => $method,
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		}
		$response = wp_remote_request( self::urls()['api'] . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			delete_transient( self::TOKEN_TRANSIENT ); // Expired early: the next call refreshes it.
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return array(
			'code' => $code,
			'data' => is_array( $data ) ? $data : null,
		);
	}

	/** Forget the connection, and tell Google to drop it too. */
	public static function disconnect(): void {
		$refresh = (string) ( self::state()['refresh_token'] ?? '' );
		if ( '' !== $refresh ) {
			wp_remote_post(
				self::urls()['revoke'],
				array(
					'timeout' => 5,
					'body'    => array( 'token' => $refresh ),
				)
			);
		}
		delete_transient( self::TOKEN_TRANSIENT );
		delete_option( self::OPTION );
	}

	/**
	 * @param array<string, string> $fields Form fields besides the client id and secret.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function tokenRequest( array $fields ): array|\WP_Error {
		$response = wp_remote_post(
			self::urls()['token'],
			array(
				'timeout' => 10,
				'body'    => $fields + array(
					'client_id'     => (string) Settings::get( 'google_client_id' ),
					'client_secret' => (string) Settings::get( 'google_client_secret' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$error = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : 'todo_hub_google_token';
			$text  = is_array( $data ) && isset( $data['error_description'] ) ? (string) $data['error_description'] : sprintf(
				/* translators: %d: HTTP status code. */
				__( 'Google answered with HTTP %d.', 'todo-hub' ),
				$code
			);
			return new \WP_Error( $error, $text );
		}
		return $data;
	}

	/** @param array<string, mixed> $tokens Token response. */
	private static function cacheAccessToken( array $tokens ): string {
		$token = (string) $tokens['access_token'];
		$life  = max( 60, (int) ( $tokens['expires_in'] ?? 3600 ) - 120 );
		set_transient( self::TOKEN_TRANSIENT, $token, $life );
		return $token;
	}
}
