<?php
/**
 * Keeps a "To-do" Google calendar in step with the task list (one way: tasks → calendar).
 *
 * - An open task with a due date has an event (all day, or at its due time).
 * - A done task's event is marked with a tick and shown as free, or removed (Settings).
 * - A task with no due date, in the trash or deleted has no event.
 *
 * Changes are sent at the end of the request that made them. Anything Google couldn't take is
 * retried every five minutes, and once a day every task is checked again. Each task's event is
 * only sent again when it would look different, so the daily check is cheap.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Google;

use TodoHub\Settings;
use TodoHub\Tasks;

final class Calendar {

	public const RETRY_OPTION = 'tdh_google_retry';
	public const DAILY_HOOK   = 'tdh_google_daily';
	public const RETRY_HOOK   = 'tdh_google_retry';

	public const META_EVENT = '_tdh_gcal_event';
	public const META_HASH  = '_tdh_gcal_hash';

	/** @var array<int, true> Tasks changed in this request. */
	private static array $dirty = array();

	public static function init(): void {
		add_action( 'todo_hub_task_changed', array( self::class, 'markDirty' ) );
		add_action( 'wp_after_insert_post', array( self::class, 'onSaved' ), 30, 2 );
		add_action( 'before_delete_post', array( self::class, 'onDelete' ), 10, 2 );
		add_action( 'shutdown', array( self::class, 'flush' ) );
		add_action( self::RETRY_HOOK, array( self::class, 'retry' ) );
		add_action( self::DAILY_HOOK, array( self::class, 'syncAll' ) );
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'onSettingsChanged' ), 10, 2 );
	}

	/**
	 * How done tasks show and how long events are decide what every event looks like: when they
	 * change, check every task again in the background.
	 *
	 * @param mixed $old Old settings.
	 * @param mixed $new New settings.
	 */
	public static function onSettingsChanged( mixed $old, mixed $new ): void {
		$old = is_array( $old ) ? $old : array();
		$new = is_array( $new ) ? $new : array();
		foreach ( array( 'calendar_done', 'calendar_minutes' ) as $key ) {
			if ( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) && self::enabled() ) {
				wp_schedule_single_event( time() + 30, self::DAILY_HOOK, array( 'settings' ) );
				return;
			}
		}
	}

	public static function enabled(): bool {
		return ! Settings::isConnector() && Client::isConnected();
	}

	public static function markDirty( int $task_id ): void {
		self::$dirty[ $task_id ] = true;
	}

	public static function onSaved( int $post_id, \WP_Post $post ): void {
		if ( Tasks::POST_TYPE === $post->post_type && 'auto-draft' !== $post->post_status ) {
			self::$dirty[ $post_id ] = true;
		}
	}

	/** The task's meta goes with it, so delete its event now. */
	public static function onDelete( int $post_id, ?\WP_Post $post = null ): void {
		if ( ! $post || Tasks::POST_TYPE !== $post->post_type || ! self::enabled() ) {
			return;
		}
		unset( self::$dirty[ $post_id ] );
		$event = (string) get_post_meta( $post_id, self::META_EVENT, true );
		if ( '' !== $event && ! self::deleteEvent( $event ) ) {
			self::queueRetry( 'delete:' . $event );
		}
	}

	public static function flush(): void {
		$dirty       = array_keys( self::$dirty );
		self::$dirty = array();
		if ( ! $dirty || ! self::enabled() ) {
			return;
		}
		foreach ( $dirty as $id ) {
			if ( ! self::sync( (int) $id ) ) {
				self::queueRetry( (string) $id );
			}
		}
	}

	/** Every five minutes: what Google couldn't take earlier. */
	public static function retry(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$items = get_option( self::RETRY_OPTION, array() );
		$items = is_array( $items ) ? array_values( array_unique( array_map( 'strval', $items ) ) ) : array();
		$left  = array();
		foreach ( $items as $item ) {
			$ok = str_starts_with( $item, 'delete:' ) ? self::deleteEvent( substr( $item, 7 ) ) : self::sync( (int) $item );
			if ( ! $ok ) {
				$left[] = $item;
			}
		}
		update_option( self::RETRY_OPTION, $left, false );
	}

	/**
	 * Daily, and from "Sync now": check every task that has, or should have, an event.
	 *
	 * @return array{synced: int, failed: int}
	 */
	public static function syncAll(): array {
		$out = array(
			'synced' => 0,
			'failed' => 0,
		);
		if ( ! self::enabled() ) {
			return $out;
		}
		$ids = get_posts(
			array(
				'post_type'        => Tasks::POST_TYPE,
				'post_status'      => array( Tasks::STATUS_OPEN, Tasks::STATUS_DONE, 'trash' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'OR',
					array(
						'key'     => Tasks::META_DUE,
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'key'     => self::META_EVENT,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			if ( self::sync( (int) $id ) ) {
				++$out['synced'];
			} else {
				++$out['failed'];
				self::queueRetry( (string) $id );
			}
		}
		return $out;
	}

	/** Make one task's event match the task. False when Google couldn't be reached or refused. */
	public static function sync( int $task_id ): bool {
		$task  = Tasks::get( $task_id );
		$event = (string) get_post_meta( $task_id, self::META_EVENT, true );

		$wanted = null;
		if ( null !== $task && 'trash' !== $task['status'] && ( 'done' !== $task['status'] || 'mark' === Settings::get( 'calendar_done' ) ) ) {
			$wanted = EventMapper::event( $task, wp_timezone_string(), (int) Settings::get( 'calendar_minutes' ) );
		}

		if ( null === $wanted ) {
			if ( '' === $event ) {
				return true;
			}
			if ( ! self::deleteEvent( $event ) ) {
				return false;
			}
			delete_post_meta( $task_id, self::META_EVENT );
			delete_post_meta( $task_id, self::META_HASH );
			return true;
		}

		$hash = md5( (string) wp_json_encode( $wanted ) );
		if ( '' !== $event && get_post_meta( $task_id, self::META_HASH, true ) === $hash ) {
			return true; // Already looks like this.
		}

		$id   = '' !== $event ? $event : EventMapper::eventId( Tasks::cleanSite( home_url() ), $task_id );
		$path = '/calendars/' . rawurlencode( self::calendarId() ) . '/events';

		// Update the event; if it isn't there, add it (with its fixed id, so a repeat can't duplicate).
		$r = Client::api( 'PUT', $path . '/' . rawurlencode( $id ), $wanted );
		if ( ! is_wp_error( $r ) && in_array( $r['code'], array( 404, 410 ), true ) ) {
			$r = Client::api( 'POST', $path, $wanted + array( 'id' => $id ) );
		}
		if ( is_wp_error( $r ) || $r['code'] < 200 || $r['code'] >= 300 ) {
			self::noteError( $r );
			return false;
		}
		update_post_meta( $task_id, self::META_EVENT, $id );
		update_post_meta( $task_id, self::META_HASH, $hash );
		if ( isset( Client::state()['last_error'] ) ) {
			Client::saveState( array( 'last_error' => null, 'last_error_at' => null ) );
		}
		return true;
	}

	/** Create the plugin's own calendar, in the site's timezone. */
	public static function createCalendar(): true|\WP_Error {
		$name = __( 'To-do', 'todo-hub' );
		$body = array( 'summary' => $name );
		if ( EventMapper::isNamed( wp_timezone_string() ) ) {
			$body['timeZone'] = wp_timezone_string();
		}
		$r = Client::api( 'POST', '/calendars', $body );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( $r['code'] < 200 || $r['code'] >= 300 || empty( $r['data']['id'] ) ) {
			return new \WP_Error( 'todo_hub_google_calendar', self::message( $r ) );
		}
		Client::saveState(
			array(
				'calendar_id'   => (string) $r['data']['id'],
				'calendar_name' => $name,
			)
		);
		return true;
	}

	/** Forget which events belong to which tasks (after disconnecting). */
	public static function forgetEvents(): void {
		delete_post_meta_by_key( self::META_EVENT );
		delete_post_meta_by_key( self::META_HASH );
		delete_option( self::RETRY_OPTION );
	}

	/** How many changes are waiting to be sent again. */
	public static function waiting(): int {
		$items = get_option( self::RETRY_OPTION, array() );
		return is_array( $items ) ? count( $items ) : 0;
	}

	private static function calendarId(): string {
		return (string) ( Client::state()['calendar_id'] ?? '' );
	}

	private static function deleteEvent( string $event ): bool {
		$r = Client::api( 'DELETE', '/calendars/' . rawurlencode( self::calendarId() ) . '/events/' . rawurlencode( $event ) );
		if ( is_wp_error( $r ) ) {
			self::noteError( $r );
			return false;
		}
		// Gone already (404, or 410 once it's been deleted) is just as good.
		return ( $r['code'] >= 200 && $r['code'] < 300 ) || in_array( $r['code'], array( 404, 410 ), true );
	}

	private static function queueRetry( string $item ): void {
		$items   = get_option( self::RETRY_OPTION, array() );
		$items   = is_array( $items ) ? $items : array();
		$items[] = $item;
		update_option( self::RETRY_OPTION, array_values( array_unique( $items ) ), false );
	}

	/** @param array{code: int, data: mixed}|\WP_Error $r A failed call. */
	private static function noteError( array|\WP_Error $r ): void {
		Client::saveState(
			array(
				'last_error'    => self::message( $r ),
				'last_error_at' => time(),
			)
		);
	}

	/** @param array{code: int, data: mixed}|\WP_Error $r A call's result. */
	private static function message( array|\WP_Error $r ): string {
		if ( is_wp_error( $r ) ) {
			return $r->get_error_message();
		}
		$data = is_array( $r['data'] ) ? $r['data'] : array();
		if ( isset( $data['error']['message'] ) ) {
			return (string) $data['error']['message'];
		}
		/* translators: %d: HTTP status code. */
		return sprintf( __( 'Google answered with HTTP %d.', 'todo-hub' ), $r['code'] );
	}
}
