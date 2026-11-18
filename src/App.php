<?php
/**
 * Wires the plugin's runtime services once WP Foundation says it's ready.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

use TodoHub\Vendor\Bignall\WPFoundation\Plugin;

final class App {

	private static ?Plugin $plugin = null;

	public static function boot( Plugin $plugin ): void {
		self::$plugin = $plugin;

		load_plugin_textdomain( 'todo-hub', false, dirname( $plugin->basename() ) . '/languages' );

		Tasks::init();
		Settings::init();
		Hooks::init();
		PrivateSite::init();

		if ( Settings::isConnector() ) {
			Connector\Notify::init();
			Connector\Outbox::init();
		} else {
			Hub\RestApi::init();
			Hub\Webhooks::init();
			Google\Calendar::init();
		}

		if ( is_admin() ) {
			Admin\Menu::init( $plugin );
			Admin\ListPage::init();
			Admin\ConnectorPage::init();
			Admin\TaskEditor::init();
			Admin\SettingsPage::init();
			Admin\GooglePage::init();
			Admin\DashboardWidget::init();
		}
	}

	public static function plugin(): Plugin {
		if ( null === self::$plugin ) {
			throw new \LogicException( 'To-do Hub is not ready yet.' );
		}
		return self::$plugin;
	}

	public static function url( string $path = '' ): string {
		return plugins_url( $path, TODO_HUB_FILE );
	}

	public static function version(): string {
		return null !== self::$plugin ? self::$plugin->currentVersion() : '0';
	}
}
