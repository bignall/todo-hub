<?php
/**
 * Tells a connected site when one of its tasks is completed or reopened.
 *
 * Only for tasks with notify set and a callback URL. The hub POSTs
 * {"event": "completed"|"reopened", "task_id": 123, "site": "...", "key": "..."} to the callback.
 * The message carries no secret: the connected site fetches the task back from the hub with its
 * own login to see what really happened, so a forged message can't fake a completion.
 *
 * A failed delivery is retried by WP-Cron: after 1, 5, 30 minutes, 2 and 12 hours, then dropped.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Hub;

use TodoHub\Tasks;

final class Webhooks {

	public const CRON_HOOK = 'tdh_deliver_webhook';

	/** Seconds to wait before each retry. */
	private const RETRY_DELAYS = array( 60, 300, 1800, 7200, 43200 );

	/** @var list<array{0: int, 1: string}> Deliveries to send at the end of this request. */
	private static array $pending = array();

	public static function init(): void {
		add_action( 'todo_hub_task_completed', array( self::class, 'onCompleted' ) );
		add_action( 'todo_hub_task_reopened', array( self::class, 'onReopened' ) );
		add_action( self::CRON_HOOK, array( self::class, 'deliver' ), 10, 3 );
		add_action( 'shutdown', array( self::class, 'flush' ) );
	}

	/** @param array<string, mixed>|null $task The task. */
	public static function onCompleted( ?array $task ): void {
		self::queue( $task, 'completed' );
	}

	/** @param array<string, mixed>|null $task The task. */
	public static function onReopened( ?array $task ): void {
		self::queue( $task, 'reopened' );
	}

	/** @param array<string, mixed>|null $task The task. */
	private static function queue( ?array $task, string $event ): void {
		if ( null === $task || empty( $task['notify'] ) || '' === self::callback( (int) $task['id'] ) ) {
			return;
		}
		self::$pending[] = array( (int) $task['id'], $event );
	}

	/** Send what this request queued, once the page has been built. */
	public static function flush(): void {
		$pending       = self::$pending;
		self::$pending = array();
		foreach ( $pending as [ $id, $event ] ) {
			self::deliver( $id, $event, 0 );
		}
	}

	public static function deliver( int $task_id, string $event, int $attempt = 0 ): void {
		$url = self::callback( $task_id );
		if ( '' === $url ) {
			return;
		}
		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => (string) wp_json_encode(
					array(
						'event'   => $event,
						'task_id' => $task_id,
						'site'    => (string) get_post_meta( $task_id, Tasks::META_SITE, true ),
						'key'     => (string) get_post_meta( $task_id, Tasks::META_KEY, true ),
						'hub'     => home_url(),
					)
				),
			)
		);
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return;
		}
		$delay = self::RETRY_DELAYS[ $attempt ] ?? null;
		if ( null !== $delay ) {
			wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $task_id, $event, $attempt + 1 ) );
		}
		/**
		 * Fires when a delivery to a connected site fails.
		 *
		 * @param int                    $task_id  Task id.
		 * @param string                 $event    completed or reopened.
		 * @param int                    $attempt  0 for the first try.
		 * @param array|\WP_Error        $response The HTTP response.
		 * @param bool                   $retrying Whether another try is scheduled.
		 */
		do_action( 'todo_hub_webhook_failed', $task_id, $event, $attempt, $response, null !== $delay );
	}

	private static function callback( int $task_id ): string {
		$url = (string) get_post_meta( $task_id, Tasks::META_CALLBACK, true );
		return '' !== $url && wp_http_validate_url( $url ) ? $url : '';
	}
}
