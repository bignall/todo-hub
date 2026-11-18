<?php
/**
 * The PHP API on a connected site: the same calls as on the hub, sent to the hub.
 *
 * Changes (add, update, complete, reopen, remove) go to the hub straight away. If the hub
 * can't be reached they wait in the outbox and are sent in order later, so a call never fails
 * just because the hub is down. Reads (get, open) ask the hub directly and come back empty
 * when it can't be reached.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Connector;

final class Remote {

	/**
	 * @param array<string, mixed> $args Task fields.
	 * @return int|\WP_Error The hub's task id, or 0 when the task is waiting in the outbox.
	 */
	public static function add( array $args ): int|\WP_Error {
		$result = Outbox::send( 'add', null, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return is_array( $result ) ? (int) ( $result['id'] ?? 0 ) : 0;
	}

	/** @param array<string, mixed> $args Fields to change. */
	public static function update( int|string $id_or_key, array $args ): bool|\WP_Error {
		$result = Outbox::send( 'update', $id_or_key, $args );
		return is_wp_error( $result ) ? $result : true;
	}

	public static function complete( int|string $id_or_key ): bool {
		return ! is_wp_error( Outbox::send( 'complete', $id_or_key ) );
	}

	public static function reopen( int $id ): bool {
		return ! is_wp_error( Outbox::send( 'reopen', $id ) );
	}

	public static function remove( int|string $id_or_key ): bool {
		return ! is_wp_error( Outbox::send( 'remove', $id_or_key ) );
	}

	/** @return array<string, mixed>|null */
	public static function get( int|string $id_or_key ): ?array {
		if ( is_int( $id_or_key ) || ctype_digit( $id_or_key ) ) {
			$r = Client::request( 'GET', '/tasks/' . (int) $id_or_key );
			return Client::OK === $r['outcome'] && is_array( $r['data'] ) ? $r['data'] : null;
		}
		return self::findByKey( (string) $id_or_key );
	}

	/**
	 * This site's open tasks on the hub, first to do first.
	 *
	 * @param array<string, mixed> $filters Optional: source, category.
	 * @return list<array<string, mixed>>
	 */
	public static function open( array $filters = array() ): array {
		$query = array_filter(
			array(
				'status'   => 'open',
				'site'     => Client::siteId(),
				'source'   => (string) ( $filters['source'] ?? '' ),
				'category' => (int) ( $filters['category'] ?? 0 ),
			)
		);
		$r     = Client::request( 'GET', '/tasks', null, $query );
		return Client::OK === $r['outcome'] && is_array( $r['data'] ) ? array_values( $r['data'] ) : array();
	}

	/**
	 * The open task with this key from this site.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function findByKey( string $key ): ?array {
		$r = Client::request(
			'GET',
			'/tasks',
			null,
			array(
				'status'   => 'open',
				'site'     => Client::siteId(),
				'key'      => $key,
				'per_page' => 1,
			)
		);
		return Client::OK === $r['outcome'] && is_array( $r['data'] ) && isset( $r['data'][0] ) && is_array( $r['data'][0] ) ? $r['data'][0] : null;
	}
}
