<?php
/**
 * The Google Calendar section of To-do → Settings (hub only), and the connect, callback,
 * disconnect and "Sync now" actions behind it.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\Google\Calendar;
use TodoHub\Google\Client;
use TodoHub\Settings;

final class GooglePage {

	public static function init(): void {
		add_action( 'admin_post_tdh_google_connect', array( self::class, 'connect' ) );
		add_action( 'admin_post_tdh_google_callback', array( self::class, 'callback' ) );
		add_action( 'admin_post_tdh_google_disconnect', array( self::class, 'disconnect' ) );
		add_action( 'admin_post_tdh_google_sync', array( self::class, 'syncNow' ) );
	}

	private static function settingsUrl( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ) . '#tdh-google';
	}

	private static function actionUrl( string $action ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action );
	}

	/** The section, inside the settings form. */
	public static function render(): void {
		$name  = Settings::OPTION;
		$state = Client::state();
		?>
		<h2 id="tdh-google"><?php esc_html_e( 'Google Calendar', 'todo-hub' ); ?></h2>
		<p><?php esc_html_e( 'Put tasks with a due date on a "To-do" calendar in your Google account. Tasks with a time become events at that time; the others are all-day events. Changes here are copied to the calendar; changes made in the calendar are not copied back.', 'todo-hub' ); ?></p>
		<?php self::notice(); ?>

		<?php if ( Client::isConnected() ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				/* translators: %s: calendar name. */
				echo esc_html( sprintf( __( 'Connected. Tasks go to the "%s" calendar in your Google account.', 'todo-hub' ), (string) ( $state['calendar_name'] ?? 'To-do' ) ) );
				$waiting = Calendar::waiting();
				if ( $waiting ) {
					/* translators: %s: number of changes. */
					echo ' ' . esc_html( sprintf( _n( '%s change is waiting to be sent again.', '%s changes are waiting to be sent again.', $waiting, 'todo-hub' ), number_format_i18n( $waiting ) ) );
				}
				?>
			</p>
			<?php if ( ! empty( $state['last_error'] ) ) : ?>
				<p>
					<?php
					/* translators: %s: error message from Google. */
					echo esc_html( sprintf( __( 'Last problem: %s', 'todo-hub' ), (string) $state['last_error'] ) );
					?>
				</p>
			<?php endif; ?>
			<p>
				<a class="button" href="<?php echo esc_url( self::actionUrl( 'tdh_google_sync' ) ); ?>"><?php esc_html_e( 'Sync now', 'todo-hub' ); ?></a>
				<a class="button-link-delete" style="margin-left:12px" href="<?php echo esc_url( self::actionUrl( 'tdh_google_disconnect' ) ); ?>"><?php esc_html_e( 'Disconnect', 'todo-hub' ); ?></a>
			</p></div>
		<?php elseif ( ! empty( $state['error'] ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( (string) $state['error'] ); ?></p></div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<?php if ( ! Client::isConnected() ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Set up', 'todo-hub' ); ?></th>
					<td>
						<ol class="tdh-steps">
							<li><?php echo wp_kses_post( __( 'In the <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud console</a>, create a project (or pick one) and enable the <strong>Google Calendar API</strong>.', 'todo-hub' ) ); ?></li>
							<li><?php esc_html_e( 'Under APIs & Services → OAuth consent screen (Google Auth Platform), set up the app for your own account, then set its publishing status to "In production". While it\'s "Testing", Google ends the connection after 7 days. You don\'t need Google\'s verification to use it yourself; Google shows an "unverified app" warning you can continue past.', 'todo-hub' ); ?></li>
							<li><?php esc_html_e( 'Under Credentials, create an OAuth client ID of type "Web application", and add this authorised redirect URI:', 'todo-hub' ); ?><br />
								<input type="text" class="large-text code" readonly value="<?php echo esc_attr( Client::redirectUri() ); ?>" onclick="this.select()" /></li>
							<li><?php esc_html_e( 'Paste the client ID and secret below, save, then press Connect.', 'todo-hub' ); ?></li>
						</ol>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="tdh-google-id"><?php esc_html_e( 'Client ID', 'todo-hub' ); ?></label></th>
				<td><input id="tdh-google-id" type="text" class="large-text code" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[google_client_id]" value="<?php echo esc_attr( (string) Settings::get( 'google_client_id' ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="tdh-google-secret"><?php esc_html_e( 'Client secret', 'todo-hub' ); ?></label></th>
				<td><input id="tdh-google-secret" type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[google_client_secret]" value="" placeholder="<?php echo esc_attr( '' !== (string) Settings::get( 'google_client_secret' ) ? __( 'Saved. Leave blank to keep it.', 'todo-hub' ) : '' ); ?>" /></td>
			</tr>
			<?php if ( ! Client::isConnected() ) : ?>
				<tr>
					<th scope="row"></th>
					<td>
						<?php if ( Client::hasCredentials() ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( self::actionUrl( 'tdh_google_connect' ) ); ?>"><?php esc_html_e( 'Connect Google Calendar', 'todo-hub' ); ?></a>
							<p class="description"><?php esc_html_e( 'Google asks you to allow To-do Hub to make its own calendar and manage events on it. It can\'t see your other calendars.', 'todo-hub' ); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Save the client ID and secret first.', 'todo-hub' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Done tasks', 'todo-hub' ); ?></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[calendar_done]" value="mark" <?php checked( 'mark', Settings::get( 'calendar_done' ) ); ?> /> <?php esc_html_e( 'Keep the event, with a ✓ in front, shown as free', 'todo-hub' ); ?></label><br />
					<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[calendar_done]" value="remove" <?php checked( 'remove', Settings::get( 'calendar_done' ) ); ?> /> <?php esc_html_e( 'Remove the event', 'todo-hub' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="tdh-google-minutes"><?php esc_html_e( 'Event length', 'todo-hub' ); ?></label></th>
				<td>
					<input id="tdh-google-minutes" type="number" min="5" max="480" class="small-text" name="<?php echo esc_attr( $name ); ?>[calendar_minutes]" value="<?php echo esc_attr( (string) Settings::get( 'calendar_minutes' ) ); ?>" />
					<?php esc_html_e( 'minutes, for tasks with a due time', 'todo-hub' ); ?>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$msg = isset( $_GET['tdh_google'] ) ? sanitize_key( $_GET['tdh_google'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$detail = isset( $_GET['detail'] ) ? sanitize_text_field( wp_unslash( $_GET['detail'] ) ) : '';
		$texts  = array(
			'connected'    => array( 'success', __( 'Google Calendar is connected, and your dated tasks are on their way to the "To-do" calendar.', 'todo-hub' ) ),
			'disconnected' => array( 'success', __( 'Disconnected. The "To-do" calendar stays in your Google account; delete it there if you don\'t want it.', 'todo-hub' ) ),
			'denied'       => array( 'warning', __( 'Google Calendar wasn\'t connected: access wasn\'t allowed.', 'todo-hub' ) ),
			'failed'       => array( 'error', __( 'Google Calendar couldn\'t be connected.', 'todo-hub' ) ),
			'synced'       => array( 'success', __( 'Synced with Google Calendar.', 'todo-hub' ) ),
		);
		if ( ! isset( $texts[ $msg ] ) ) {
			return;
		}
		[ $type, $text ] = $texts[ $msg ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p>' . esc_html( $text . ( '' !== $detail ? ' ' . $detail : '' ) ) . '</p></div>';
	}

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'todo-hub' ), 403 );
		}
		check_admin_referer( $action );
	}

	public static function connect(): void {
		self::guard( 'tdh_google_connect' );
		if ( ! Client::hasCredentials() ) {
			wp_safe_redirect( self::settingsUrl() );
			exit;
		}
		// Google's sign-in page is on another host, so wp_safe_redirect() would refuse it.
		wp_redirect( Client::authUrl() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	public static function callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'todo-hub' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked with the OAuth state instead.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$error = isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';
		// phpcs:enable
		if ( ! Client::checkState( $state ) ) {
			wp_die( esc_html__( 'This Google sign-in link has expired or wasn\'t started from this site. Go back to To-do → Settings and press Connect again.', 'todo-hub' ), 400 );
		}
		if ( '' !== $error || '' === $code ) {
			wp_safe_redirect( self::settingsUrl( array( 'tdh_google' => 'denied' ) ) );
			exit;
		}
		$result = Client::exchangeCode( $code );
		if ( ! is_wp_error( $result ) && empty( Client::state()['calendar_id'] ) ) {
			$result = Calendar::createCalendar();
		}
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				self::settingsUrl(
					array(
						'tdh_google' => 'failed',
						'detail'     => rawurlencode( $result->get_error_message() ),
					)
				)
			);
			exit;
		}
		Calendar::syncAll();
		wp_safe_redirect( self::settingsUrl( array( 'tdh_google' => 'connected' ) ) );
		exit;
	}

	public static function disconnect(): void {
		self::guard( 'tdh_google_disconnect' );
		Client::disconnect();
		Calendar::forgetEvents();
		wp_safe_redirect( self::settingsUrl( array( 'tdh_google' => 'disconnected' ) ) );
		exit;
	}

	public static function syncNow(): void {
		self::guard( 'tdh_google_sync' );
		Calendar::retry();
		$result = Calendar::syncAll();
		$detail = $result['failed']
			/* translators: %s: number of tasks. */
			? sprintf( _n( '%s task couldn\'t be sent and will be tried again.', '%s tasks couldn\'t be sent and will be tried again.', $result['failed'], 'todo-hub' ), number_format_i18n( $result['failed'] ) )
			: '';
		wp_safe_redirect(
			self::settingsUrl(
				array(
					'tdh_google' => 'synced',
					'detail'     => rawurlencode( $detail ),
				)
			)
		);
		exit;
	}
}
