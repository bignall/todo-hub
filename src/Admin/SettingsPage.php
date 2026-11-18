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
				<h2><?php esc_html_e( 'This site', 'todo-hub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Role', 'todo-hub' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="hub" <?php checked( ! Settings::isConnector() ); ?> />
									<strong><?php esc_html_e( 'Hub', 'todo-hub' ); ?></strong> &mdash; <?php esc_html_e( 'keep the to-do list on this site.', 'todo-hub' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="connector" <?php checked( Settings::isConnector() ); ?> />
									<strong><?php esc_html_e( 'Connector', 'todo-hub' ); ?></strong> &mdash; <?php esc_html_e( 'send this site\'s tasks to a hub on another site.', 'todo-hub' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>

				<?php if ( Settings::isConnector() ) : ?>
					<h2><?php esc_html_e( 'Hub connection', 'todo-hub' ); ?></h2>
					<?php ConnectorPage::status( true ); ?>
					<p class="description">
						<?php esc_html_e( 'On the hub site: add a user with the "To-do Connector" role, then in that user\'s profile create an application password named after this site. Enter them here. The hub must use https.', 'todo-hub' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="tdh-hub-url"><?php esc_html_e( 'Hub address', 'todo-hub' ); ?></label></th>
							<td><input id="tdh-hub-url" type="url" class="regular-text" placeholder="https://todo.example.com" name="<?php echo esc_attr( $name ); ?>[hub_url]" value="<?php echo esc_attr( (string) Settings::get( 'hub_url' ) ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="tdh-hub-user"><?php esc_html_e( 'User name on the hub', 'todo-hub' ); ?></label></th>
							<td><input id="tdh-hub-user" type="text" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[hub_user]" value="<?php echo esc_attr( (string) Settings::get( 'hub_user' ) ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="tdh-hub-password"><?php esc_html_e( 'Application password', 'todo-hub' ); ?></label></th>
							<td>
								<input id="tdh-hub-password" type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[hub_password]" value="" placeholder="<?php echo esc_attr( '' !== (string) Settings::get( 'hub_password' ) ? __( 'Saved. Leave blank to keep it.', 'todo-hub' ) : 'xxxx xxxx xxxx xxxx xxxx xxxx' ); ?>" />
							</td>
						</tr>
					</table>
				<?php else : ?>
					<p class="description">
						<?php
						/* translators: %s: REST API address. */
						echo esc_html( sprintf( __( 'Other sites connect to this hub at %s.', 'todo-hub' ), home_url() ) );
						?>
					</p>
					<?php GooglePage::render(); ?>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Task list', 'todo-hub' ); ?></h2>
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
