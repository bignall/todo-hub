<?php
/**
 * HTTP calls from a connected site to the hub's REST API, logged in with an application password.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Connector;

use TodoHub\Settings;
use TodoHub\Tasks;

final class Client {

	/** The outcome of a call. */
	public const OK    = 'ok';
	public const RETRY = 'retry'; // The hub couldn't be reached or had a server error: try later.
	public const FAIL  = 'fail';  // The hub refused the call (bad login, bad data): don't retry.

	/** This site's address as the hub knows it, e.g. "rosinabignall.com". */
	public static function siteId(): string {
		return Tasks::cleanSite( home_url() );
	}

	/** Where the hub tells this site about completed tasks. */
	public static function callbackUrl(): string {
		return rest_url( Notify::NAMESPACE . '/notify' );
	}

	public static function isConfigured(): bool {
		return '' !== (string) Settings::get( 'hub_url' ) && '' !== (string) Settings::get( 'hub_user' ) && '' !== (string) Settings::get( 'hub_password' );
	}

	/**
	 * Call the hub.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Route under todo-hub/v1, e.g. "/tasks".
	 * @param array<string, mixed>|null $body   JSON body.
	 * @param array<string, mixed>      $query  Query arguments.
	 * @return array{outcome: string, code: int, data: mixed, message: string}
	 */
	public static function request( string $method, string $path, ?array $body = null, array $query = array() ): array {
		if ( ! self::isConfigured() ) {
			return self::result( self::RETRY, 0, null, __( 'The hub address, user name or application password is missing in To-do → Settings.', 'todo-hub' ) );
		}
		// ?rest_route= works whatever the hub's permalink settings are; /wp-json/ needs pretty permalinks.
		$query = array( 'rest_route' => '/todo-hub/v1' . $path ) + $query;
		$url   = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), Settings::get( 'hub_url' ) . '/' );
		$args = array(
			'method'      => $method,
			'timeout'     => 10,
			'redirection' => 2,
			'headers'     => array(
				'Authorization' => 'Basic ' . base64_encode( Settings::get( 'hub_user' ) . ':' . Settings::get( 'hub_password' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return self::result( self::RETRY, 0, null, $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 ) {
			return self::result( self::OK, $code, $data, '' );
		}
		$message = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : sprintf(
			/* translators: %d: HTTP status code. */
			__( 'The hub answered with HTTP %d.', 'todo-hub' ),
			$code
		);
		// 408 timeout and 429 too many requests are worth retrying, like server errors.
		$outcome = ( $code >= 500 || 408 === $code || 429 === $code ) ? self::RETRY : self::FAIL;
		return self::result( $outcome, $code, $data, $message );
	}

	/**
	 * @return array{outcome: string, code: int, data: mixed, message: string}
	 */
	private static function result( string $outcome, int $code, mixed $data, string $message ): array {
		return compact( 'outcome', 'code', 'data', 'message' );
	}
}
