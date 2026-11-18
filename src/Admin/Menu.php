<?php
/**
 * The To-do menu: Task List, All Tasks, Add Task, Categories, Settings.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\App;
use TodoHub\Tasks;
use TodoHub\Vendor\Bignall\WPFoundation\Plugin;

final class Menu {

	public const SLUG = 'todo-hub';

	public static function init( Plugin $plugin ): void {
		// Before post types add their submenus (priority 10), so the Task List comes first.
		add_action( 'admin_menu', array( self::class, 'menu' ), 9 );
		add_action( 'admin_menu', array( self::class, 'more' ), 11 );
		add_filter( 'parent_file', array( self::class, 'parentFile' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'plugin_action_links_' . $plugin->basename(), array( self::class, 'pluginLinks' ) );
	}

	public static function url( array $args = array() ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	public static function menu(): void {
		$count  = current_user_can( Tasks::CAPABILITY ) ? Tasks::dueNowCount() : 0;
		$bubble = $count ? ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>' : '';

		add_menu_page( __( 'To-do', 'todo-hub' ), __( 'To-do', 'todo-hub' ) . $bubble, Tasks::CAPABILITY, self::SLUG, array( ListPage::class, 'render' ), 'dashicons-yes-alt', 26 );
		add_submenu_page( self::SLUG, __( 'Task List', 'todo-hub' ), __( 'Task List', 'todo-hub' ), Tasks::CAPABILITY, self::SLUG, array( ListPage::class, 'render' ) );
	}

	public static function more(): void {
		add_submenu_page( self::SLUG, __( 'Add Task', 'todo-hub' ), __( 'Add Task', 'todo-hub' ), Tasks::CAPABILITY, 'post-new.php?post_type=' . Tasks::POST_TYPE );
		add_submenu_page( self::SLUG, __( 'Categories', 'todo-hub' ), __( 'Categories', 'todo-hub' ), Tasks::CAPABILITY, 'edit-tags.php?taxonomy=' . Tasks::TAXONOMY . '&post_type=' . Tasks::POST_TYPE );
		add_submenu_page( self::SLUG, __( 'To-do Settings', 'todo-hub' ), __( 'Settings', 'todo-hub' ), 'manage_options', SettingsPage::SLUG, array( SettingsPage::class, 'render' ) );
	}

	/** Keep the To-do menu open on the Categories screen. */
	public static function parentFile( string $parent_file ): string {
		global $current_screen;
		if ( $current_screen && Tasks::TAXONOMY === $current_screen->taxonomy ) {
			return self::SLUG;
		}
		return $parent_file;
	}

	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		$ours   = str_contains( $hook, self::SLUG ) || 'index.php' === $hook || ( $screen && Tasks::POST_TYPE === $screen->post_type );
		if ( $ours ) {
			wp_enqueue_style( 'tdh-admin', App::url( 'assets/admin.css' ), array(), App::version() );
		}
	}

	/**
	 * @param array<string, string> $links Action links.
	 * @return array<string, string>
	 */
	public static function pluginLinks( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Task List', 'todo-hub' ) . '</a>' );
		return $links;
	}
}
