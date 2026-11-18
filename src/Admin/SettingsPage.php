<?php
/**
 * To-do → Settings.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\Settings;

final class SettingsPage {

	public const SLUG = 'tdh-settings';

	public static function init(): void {
		// Settings are registered in Settings::init().
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'todo-hub' ) );
		}
		$name = Settings::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'To-do Settings', 'todo-hub' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'tdh_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Moving up the list', 'todo-hub' ); ?></th>
						<td>
							<p>
								<label>
									<?php esc_html_e( 'Tasks due within', 'todo-hub' ); ?>
									<input type="number" min="1" max="30" class="small-text" name="<?php echo esc_attr( $name ); ?>[soon_days]" value="<?php echo esc_attr( (string) Settings::get( 'soon_days' ) ); ?>" />
									<?php esc_html_e( 'days count as two priority levels higher.', 'todo-hub' ); ?>
								</label>
							</p>
							<p>
								<label>
									<?php esc_html_e( 'Tasks due within', 'todo-hub' ); ?>
									<input type="number" min="1" max="90" class="small-text" name="<?php echo esc_attr( $name ); ?>[week_days]" value="<?php echo esc_attr( (string) Settings::get( 'week_days' ) ); ?>" />
									<?php esc_html_e( 'days count as one priority level higher.', 'todo-hub' ); ?>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Overdue tasks and tasks due today always come first. So with the defaults, a low-priority task due in 3 days sits alongside high-priority tasks with no due date.', 'todo-hub' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Private site', 'todo-hub' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[private_site]" value="1" <?php checked( (bool) Settings::get( 'private_site' ) ); ?> />
								<?php esc_html_e( 'Send visitors who aren\'t logged in to the login page', 'todo-hub' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'For a site that exists only to hold your to-do list. Don\'t turn this on for a public site.', 'todo-hub' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'When deleting the plugin', 'todo-hub' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[remove_data]" value="1" <?php checked( (bool) Settings::get( 'remove_data' ) ); ?> />
								<?php esc_html_e( 'Also delete all tasks, categories and settings', 'todo-hub' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
