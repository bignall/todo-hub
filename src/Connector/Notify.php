<?php
/**
 * POST /wp-json/todo-hub/v1/notify on a connected site: the hub calls this when one of this
 * site's tasks (with notify set) is completed or reopened.
 *
 * The call carries no secret and is not trusted: only the task id (and which change to expect)
 * is read from it. The task is then fetched from the hub with this site's own login, and the
 * completion hooks fire only if the hub says the task belongs to this site and really changed.
 * Each completion fires once.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Connector;

final class Notify {

	public const NAMESPACE = 'todo-hub/v1';

	/** Task id => completed time, for tasks whose completion hooks already fired here. */
	public const SEEN_OPTION = 'tdh_seen_completions';

	private const SEEN_LIMIT = 500;

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/notify',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle' ),
				// Anyone may knock; nothing happens unless the hub confirms (see above).
				'permission_callback' => '__return_true',
				'args'                => array(
					'task_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'event'   => array(
						'type' => 'string',
						'enum' => array( 'completed', 'reopened' ),
					),
				),
			)
		);
	}

	public static function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'task_id' );

		// Every call is checked with the hub. Skipping checks (to save requests) would let a forged
		// call made just before a real one hide the real completion.
		$r = Client::request( 'GET', '/tasks/' . $id );
		if ( Client::OK !== $r['outcome'] || ! is_array( $r['data'] ) ) {
			// Tell the hub to try again later if it couldn't be checked now.
			return new \WP_REST_Response( array( 'ok' => false ), Client::RETRY === $r['outcome'] ? 503 : 200 );
		}
		// If the hub doesn't show the change it announced yet (its save may not be visible to
		// other requests the instant it calls), ask it to try again shortly.
		$event  = (string) $request->get_param( 'event' );
		$status = (string) ( $r['data']['status'] ?? '' );
		if ( ( 'completed' === $event && 'done' !== $status ) || ( 'reopened' === $event && 'open' !== $status ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'reason' => 'not yet' ), 503 );
		}
		self::apply( $r['data'] );
		return new \WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * Fire the local hooks for a task as the hub reported it, once per change.
	 *
	 * @param array<string, mixed> $task The task from the hub.
	 */
	public static function apply( array $task ): void {
		if ( ( $task['site'] ?? '' ) !== Client::siteId() ) {
			return;
		}
		$id   = (int) ( $task['id'] ?? 0 );
		$seen = get_option( self::SEEN_OPTION, array() );
		$seen = is_array( $seen ) ? $seen : array();

		if ( 'done' === ( $task['status'] ?? '' ) ) {
			$stamp = (string) ( $task['completed'] ?? '' );
			if ( isset( $seen[ $id ] ) && $seen[ $id ] === $stamp ) {
				return;
			}
			$seen[ $id ] = $stamp;
			self::save( $seen );
			/** This hook is documented in src/Tasks.php. */
			do_action( 'todo_hub_task_completed', $task );
			if ( '' !== ( $task['source'] ?? '' ) ) {
				/** This hook is documented in src/Tasks.php. */
				do_action( 'todo_hub_task_completed_' . $task['source'], $task );
			}
			return;
		}

		if ( 'open' === ( $task['status'] ?? '' ) && isset( $seen[ $id ] ) ) {
			unset( $seen[ $id ] );
			self::save( $seen );
			/** This hook is documented in src/Tasks.php. */
			do_action( 'todo_hub_task_reopened', $task );
		}
	}

	/** @param array<int, string> $seen Completions already handled. */
	private static function save( array $seen ): void {
		if ( count( $seen ) > self::SEEN_LIMIT ) {
			$seen = array_slice( $seen, -self::SEEN_LIMIT, null, true );
		}
		update_option( self::SEEN_OPTION, $seen, false );
	}
}
