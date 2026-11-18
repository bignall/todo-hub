<?php
/**
 * Action versions of the PHP API, for plugins that don't want to check whether To-do Hub
 * is active: when it isn't, these actions simply do nothing.
 *
 *     do_action( 'todo_hub_add_task', [ 'title' => 'Renew the SSL certificate', 'due' => '2026-12-01' ] );
 *     do_action( 'todo_hub_complete_task', 'my-plugin:renew-ssl' );
 *     do_action( 'todo_hub_remove_task', 'my-plugin:renew-ssl' );
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class Hooks {

	public static function init(): void {
		add_action( 'todo_hub_add_task', array( self::class, 'add' ) );
		add_action( 'todo_hub_complete_task', array( self::class, 'complete' ) );
		add_action( 'todo_hub_remove_task', array( self::class, 'remove' ) );
	}

	public static function add( mixed $args ): void {
		if ( is_array( $args ) ) {
			Api::add( $args );
		}
	}

	public static function complete( mixed $id_or_key ): void {
		if ( is_int( $id_or_key ) || is_string( $id_or_key ) ) {
			Api::complete( $id_or_key );
		}
	}

	public static function remove( mixed $id_or_key ): void {
		if ( is_int( $id_or_key ) || is_string( $id_or_key ) ) {
			Api::remove( $id_or_key );
		}
	}
}
