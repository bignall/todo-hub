<?php
/**
 * Changes waiting to be sent to the hub, in the order they were made.
 *
 * Every change goes through send(). When nothing is waiting it's sent straight away; when the
 * hub can't be reached (or something is already waiting, so the order is kept) it's added to
 * the outbox, which is sent every five minutes by WP-Cron and whenever another change is made.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Connector;

final class Outbox {

	public const OPTION    = 'tdh_outbox';
	public const CRON_HOOK = 'tdh_flush_outbox';
	private const LOCK     = 'tdh_outbox_lock';

	/** Give up on a change after this many tries (about two days at one try every five minutes). */
	private const MAX_ATTEMPTS = 600;

	public static function init(): void {
		add_action( self::CRON_HOOK, array( self::class, 'flush' ) );
	}

	/**
	 * Send a change to the hub, or keep it for later.
	 *
	 * @param string               $op     add, update, complete, reopen or remove.
	 * @param int|string|null      $target Task id or key (not for add).
	 * @param array<string, mixed> $args   Task fields for add and update.
	 * @return array<string, mixed>|true|\WP_Error The hub's answer when sent now, true when kept
	 *                                             for later (or there was nothing to do), an error
	 *                                             when the hub refused it.
	 */
	public static function send( string $op, int|string|null $target, array $args = array() ): array|bool|\WP_Error {
		$item = array(
			'op'       => $op,
			'target'   => $target,
			'args'     => $args,
			'attempts' => 0,
			'queued'   => time(),
			'error'    => '',
		);
		if ( 'add' === $op ) {
			$item['args'] += array( 'callback' => Client::callbackUrl() );
			$item['args']['site'] = Client::siteId();
		}

		if ( self::items() ) {
			self::push( $item );
			self::flush();
			return true;
		}

		$result = self::run( $item );
		if ( Client::OK === $result['outcome'] ) {
			return is_array( $result['data'] ) ? $result['data'] : true;
		}
		if ( Client::RETRY === $result['outcome'] ) {
			$item['attempts'] = 1;
			$item['error']    = $result['message'];
			self::push( $item );
			return true;
		}
		return new \WP_Error( 'todo_hub_refused', $result['message'], array( 'status' => $result['code'] ) );
	}

	/** Send what's waiting, oldest first, stopping at the first change the hub can't take yet. */
	public static function flush(): void {
		if ( get_transient( self::LOCK ) ) {
			return;
		}
		set_transient( self::LOCK, 1, MINUTE_IN_SECONDS );

		$items = self::items();
		while ( $items ) {
			$item   = $items[0];
			$result = self::run( $item );
			if ( Client::RETRY === $result['outcome'] ) {
				++$items[0]['attempts'];
				$items[0]['error'] = $result['message'];
				if ( $items[0]['attempts'] < self::MAX_ATTEMPTS ) {
					break;
				}
				$result['outcome'] = Client::FAIL;
			}
			array_shift( $items );
			if ( Client::FAIL === $result['outcome'] ) {
				/**
				 * Fires when a change could not be sent to the hub and was dropped.
				 *
				 * @param array<string, mixed> $item   The change: op, target, args.
				 * @param string               $reason Why.
				 */
				do_action( 'todo_hub_outbox_dropped', $item, $result['message'] );
			}
		}

		update_option( self::OPTION, $items, false );
		delete_transient( self::LOCK );
	}

	/**
	 * Waiting changes.
	 *
	 * @return list<array{op: string, target: int|string|null, args: array<string, mixed>, attempts: int, queued: int, error: string}>
	 */
	public static function items(): array {
		$items = get_option( self::OPTION, array() );
		return is_array( $items ) ? array_values( $items ) : array();
	}

	/** @param array<string, mixed> $item A change. */
	private static function push( array $item ): void {
		$items   = self::items();
		$items[] = $item;
		update_option( self::OPTION, $items, false );
	}

	/**
	 * Send one change.
	 *
	 * @param array<string, mixed> $item A change.
	 * @return array{outcome: string, code: int, data: mixed, message: string}
	 */
	private static function run( array $item ): array {
		if ( 'add' === $item['op'] ) {
			return Client::request( 'POST', '/tasks', $item['args'] );
		}

		$id = self::resolve( $item['target'] );
		if ( is_array( $id ) ) {
			return $id; // The lookup itself failed.
		}
		if ( 0 === $id ) {
			// No such open task: nothing to complete, change or remove.
			return array(
				'outcome' => Client::OK,
				'code'    => 404,
				'data'    => null,
				'message' => '',
			);
		}

		return match ( $item['op'] ) {
			'update'   => Client::request( 'PATCH', '/tasks/' . $id, $item['args'] ),
			'complete' => Client::request( 'POST', '/tasks/' . $id . '/complete' ),
			'reopen'   => Client::request( 'POST', '/tasks/' . $id . '/reopen' ),
			'remove'   => Client::request( 'DELETE', '/tasks/' . $id ),
			default    => array(
				'outcome' => Client::FAIL,
				'code'    => 0,
				'data'    => null,
				'message' => 'Unknown change: ' . $item['op'],
			),
		};
	}

	/**
	 * A task id from an id or a key. 0 when there's no such open task.
	 *
	 * @param int|string|null $target Id or key.
	 * @return int|array{outcome: string, code: int, data: mixed, message: string} The id, or the failed lookup.
	 */
	private static function resolve( int|string|null $target ): int|array {
		if ( null === $target ) {
			return 0;
		}
		if ( is_int( $target ) || ctype_digit( $target ) ) {
			return (int) $target;
		}
		$r = Client::request(
			'GET',
			'/tasks',
			null,
			array(
				'status'   => 'open',
				'site'     => Client::siteId(),
				'key'      => $target,
				'per_page' => 1,
			)
		);
		if ( Client::OK !== $r['outcome'] ) {
			return $r;
		}
		return is_array( $r['data'] ) && isset( $r['data'][0]['id'] ) ? (int) $r['data'][0]['id'] : 0;
	}
}
