<?php
/**
 * Dashboard widget: the first few tasks on the list.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\Tasks;

final class DashboardWidget {

	private const COUNT = 7;

	public static function init(): void {
		add_action( 'wp_dashboard_setup', array( self::class, 'register' ) );
	}

	public static function register(): void {
		if ( current_user_can( Tasks::CAPABILITY ) ) {
			wp_add_dashboard_widget( 'tdh_up_next', __( 'To-do: up next', 'todo-hub' ), array( self::class, 'render' ) );
		}
	}

	public static function render(): void {
		$all   = Tasks::open();
		$tasks = array_slice( $all, 0, self::COUNT );
		if ( ! $tasks ) {
			echo '<p>' . esc_html__( 'Nothing to do. Nice.', 'todo-hub' ) . '</p>';
		} else {
			echo '<ul class="tdh-mini">';
			foreach ( $tasks as $task ) {
				$state = Format::dueState( $task['due'] );
				echo '<li><a href="' . esc_url( $task['edit_url'] ) . '">' . esc_html( $task['title'] ) . '</a>';
				if ( '' !== $task['due'] ) {
					echo ' <span class="tdh-due tdh-due--' . esc_attr( $state ) . '">' . esc_html( Format::due( $task['due'] ) ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		$more = count( $all ) - count( $tasks );
		echo '<p><a href="' . esc_url( Menu::url() ) . '">';
		echo esc_html(
			$more > 0
				/* translators: %s: number of further tasks. */
				? sprintf( _n( 'See the whole list (%s more)', 'See the whole list (%s more)', $more, 'todo-hub' ), number_format_i18n( $more ) )
				: __( 'Open the task list', 'todo-hub' )
		);
		echo '</a></p>';
	}
}
