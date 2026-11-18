<?php
/**
 * Behind the todo_hub_*() functions: on the hub they work on the tasks stored here, on a
 * connected site they're sent to the hub. Callers don't need to know which.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

use TodoHub\Connector\Remote;

final class Api {

	/** @param array<string, mixed> $args Task fields. */
	public static function add( array $args ): int|\WP_Error {
		return Settings::isConnector() ? Remote::add( $args ) : Tasks::add( $args );
	}

	/** @param array<string, mixed> $args Fields to change. */
	public static function update( int|string $id_or_key, array $args ): bool|\WP_Error {
		if ( Settings::isConnector() ) {
			return Remote::update( $id_or_key, $args );
		}
		$id = Tasks::resolve( $id_or_key );
		return $id ? Tasks::update( $id, $args ) : new \WP_Error( 'todo_hub_not_found', __( 'That task does not exist.', 'todo-hub' ) );
	}

	public static function complete( int|string $id_or_key ): bool {
		if ( Settings::isConnector() ) {
			return Remote::complete( $id_or_key );
		}
		$id = Tasks::resolve( $id_or_key );
		return $id && Tasks::complete( $id );
	}

	public static function reopen( int $id ): bool {
		return Settings::isConnector() ? Remote::reopen( $id ) : Tasks::reopen( $id );
	}

	public static function remove( int|string $id_or_key ): bool {
		if ( Settings::isConnector() ) {
			return Remote::remove( $id_or_key );
		}
		$id = Tasks::resolve( $id_or_key );
		return $id && Tasks::remove( $id );
	}

	/** @return array<string, mixed>|null */
	public static function get( int|string $id_or_key ): ?array {
		if ( Settings::isConnector() ) {
			return Remote::get( $id_or_key );
		}
		$id = Tasks::resolve( $id_or_key );
		return $id ? Tasks::get( $id ) : null;
	}

	/**
	 * @param array<string, mixed> $filters Optional filters.
	 * @return list<array<string, mixed>>
	 */
	public static function open( array $filters = array() ): array {
		return Settings::isConnector() ? Remote::open( $filters ) : Tasks::open( $filters );
	}
}
