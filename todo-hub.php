<?php
/**
 * Plugin Name:       To-do Hub
 * Plugin URI:        https://bignall.github.io/todo-hub/
 * Description:       One central to-do list, sorted by what needs doing first: overdue and due-soon tasks rise to the top. Other plugins can add tasks and hear when they're done.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Arbe
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       todo-hub
 *
 * @package TodoHub
 */

defined( 'ABSPATH' ) || exit;

// Keep this file PHP 5.6-compatible so old hosts get a notice instead of a parse error.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'To-do Hub requires PHP 8.1 or newer.', 'todo-hub' ) . '</p></div>';
		}
	);
	return;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'To-do Hub is missing its dependencies. Run "composer install" in the plugin folder, or install a release zip.', 'todo-hub' ) . '</p></div>';
		}
	);
	return;
}

if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';
}
require_once __DIR__ . '/vendor/autoload.php';

define( 'TODO_HUB_FILE', __FILE__ );

TodoHub\Bootstrap::plugin( __FILE__ )->boot();
