<?php
/**
 * To-do → Task List on a connected site: whether the hub can be reached, what's waiting to be
 * sent, and this site's open tasks on the hub (each links to the hub to work on it).
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\Connector\Client;
use TodoHub\Connector\Outbox;
use TodoHub\Connector\Remote;
use TodoHub\Priority;
use TodoHub\Settings;
use TodoHub\Tasks;

final class ConnectorPage {

	public static function init(): void {
		add_action( 'admin_post_tdh_flush_outbox', array( self::class, 'handleFlush' ) );
	}

	/**
	 * Ask the hub who we are (cached for a minute).
	 *
	 * @return array{outcome: string, code: int, data: mixed, message: string}
	 */
	public static function ping( bool $fresh = false ): array {
		$cached = get_transient( 'tdh_ping' );
		if ( ! $fresh && is_array( $cached ) ) {
			return $cached;
		}
		$result = Client::request( 'GET', '/ping' );
		set_transient( 'tdh_ping', $result, MINUTE_IN_SECONDS );
		return $result;
	}

	/** One line on the connection, for this page and the settings page. */
	public static function status( bool $fresh = false ): void {
		if ( ! Client::isConfigured() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Not connected yet. Enter the hub\'s address, a user name and an application password in To-do → Settings.', 'todo-hub' ) . '</p></div>';
			return;
		}
		$ping = self::ping( $fresh );
		if ( Client::OK === $ping['outcome'] && is_array( $ping['data'] ) && 'hub' === ( $ping['data']['mode'] ?? '' ) ) {
			echo '<div class="notice notice-success inline"><p>';
			/* translators: 1: hub address, 2: user name. */
			echo esc_html( sprintf( __( 'Connected to %1$s as %2$s.', 'todo-hub' ), (string) Settings::get( 'hub_url' ), (string) ( $ping['data']['user'] ?? '' ) ) );
			echo '</p></div>';
			return;
		}
		$why = Client::OK === $ping['outcome'] ? __( 'That site answered, but it isn\'t a To-do Hub in hub mode.', 'todo-hub' ) : $ping['message'];
		echo '<div class="notice notice-error inline"><p>';
		/* translators: %s: the reason. */
		echo esc_html( sprintf( __( 'Can\'t reach the hub: %s', 'todo-hub' ), $why ) );
		echo '</p></div>';
	}

	public static function render(): void {
		if ( ! current_user_can( Tasks::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view the task list.', 'todo-hub' ) );
		}
		$waiting = Outbox::items();
		$hub     = (string) Settings::get( 'hub_url' );
		?>
		<div class="wrap tdh">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'To-do', 'todo-hub' ); ?></h1>
			<?php if ( '' !== $hub ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( $hub . '/wp-admin/admin.php?page=' . Menu::SLUG ); ?>"><?php esc_html_e( 'Open the hub', 'todo-hub' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end" />
			<p>
				<?php
				/* translators: %s: this site's address. */
				echo esc_html( sprintf( __( 'This site sends its tasks to your To-do hub, where they\'re listed as coming from %s.', 'todo-hub' ), Client::siteId() ) );
				?>
			</p>
			<?php self::status(); ?>

			<?php if ( $waiting ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php
						/* translators: %s: number of changes. */
						echo esc_html( sprintf( _n( '%s change is waiting to be sent to the hub.', '%s changes are waiting to be sent to the hub.', count( $waiting ), 'todo-hub' ), number_format_i18n( count( $waiting ) ) ) );
						if ( '' !== $waiting[0]['error'] ) {
							/* translators: %s: error message. */
							echo ' ' . esc_html( sprintf( __( 'Last try: %s', 'todo-hub' ), $waiting[0]['error'] ) );
						}
						?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tdh_flush_outbox' ), 'tdh_flush_outbox' ) ); ?>"><?php esc_html_e( 'Send now', 'todo-hub' ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'This site\'s open tasks', 'todo-hub' ); ?></h2>
			<?php
			$tasks = Client::isConfigured() ? Remote::open() : array();
			if ( ! $tasks ) {
				echo '<p class="tdh-empty">' . esc_html__( 'No open tasks from this site.', 'todo-hub' ) . '</p>';
			} else {
				echo '<ul class="tdh-tasks">';
				foreach ( $tasks as $task ) {
					$state = Format::dueState( (string) $task['due'], (string) ( $task['due_time'] ?? '' ) );
					echo '<li class="tdh-task tdh-task--' . esc_attr( $state ) . '"><div class="tdh-task__main">';
					echo '<a class="tdh-task__title" href="' . esc_url( (string) $task['edit_url'] ) . '">' . esc_html( (string) $task['title'] ) . '</a>';
					echo '<span class="tdh-task__meta">';
					if ( Priority::NORMAL !== (int) $task['priority'] ) {
						echo '<span class="tdh-badge tdh-badge--' . esc_attr( Priority::name( (int) $task['priority'] ) ) . '">' . esc_html( Format::priority( (int) $task['priority'] ) ) . '</span>';
					}
					if ( '' !== (string) $task['due'] ) {
						echo '<span class="tdh-due tdh-due--' . esc_attr( $state ) . '">' . esc_html( Format::due( (string) $task['due'], (string) ( $task['due_time'] ?? '' ) ) ) . '</span>';
					}
					if ( '' !== (string) $task['source'] ) {
						/* translators: %s: plugin that added the task. */
						echo '<span class="tdh-source">' . esc_html( sprintf( __( 'from %s', 'todo-hub' ), (string) $task['source'] ) ) . '</span>';
					}
					echo '</span></div></li>';
				}
				echo '</ul>';
			}
			?>
		</div>
		<?php
	}

	public static function handleFlush(): void {
		if ( ! current_user_can( Tasks::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'todo-hub' ), 403 );
		}
		check_admin_referer( 'tdh_flush_outbox' );
		Outbox::flush();
		wp_safe_redirect( Menu::url() );
		exit;
	}
}
