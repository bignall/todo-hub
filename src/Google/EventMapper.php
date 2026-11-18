<?php
/**
 * Turns a task into a Google Calendar event.
 *
 * A task with only a due date becomes an all-day event on that day. One with a due time becomes
 * an event of $minutes starting then. A done task (when done tasks are kept) gets a tick in front
 * of its title and stops showing as busy. The event's id is fixed for each task, so adding the
 * same task twice can't make two events.
 *
 * Plain PHP, no WordPress, so it can be unit tested.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Google;

final class EventMapper {

	public const DONE_MARK = '✓ ';

	/**
	 * The event id for a task: letters a-v and digits only, as Google requires, unique per site.
	 *
	 * @param string $site The site's address, so two hubs sharing a calendar don't collide.
	 */
	public static function eventId( string $site, int $task_id ): string {
		return 'tdh' . substr( md5( $site ), 0, 10 ) . 't' . $task_id;
	}

	/**
	 * The event for a task, or null when it shouldn't have one (no due date).
	 *
	 * @param array{id: int, title: string, notes: string, status: string, due: string, due_time: string, link: string, edit_url: string} $task
	 * @param string $timezone An IANA name (America/Denver) or an offset (+02:00, UTC+2).
	 * @param int    $minutes  Length of events for tasks with a due time.
	 * @return array<string, mixed>|null
	 */
	public static function event( array $task, string $timezone, int $minutes = 30 ): ?array {
		if ( '' === $task['due'] ) {
			return null;
		}
		$done    = 'done' === $task['status'];
		$summary = ( $done ? self::DONE_MARK : '' ) . $task['title'];

		$lines = array();
		$notes = trim( html_entity_decode( strip_tags( str_replace( array( '</li>', '<br>', '<br />', '</p>' ), "\n", $task['notes'] ) ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== $notes ) {
			$lines[] = $notes;
		}
		if ( '' !== $task['link'] ) {
			$lines[] = 'Go to it: ' . $task['link'];
		}
		if ( '' !== $task['edit_url'] ) {
			$lines[] = 'Task: ' . $task['edit_url'];
		}

		$event = array(
			'summary'      => $summary,
			'description'  => implode( "\n\n", $lines ),
			'status'       => 'confirmed',
			'transparency' => $done ? 'transparent' : 'opaque',
		);

		if ( '' === $task['due_time'] ) {
			$next             = ( new \DateTimeImmutable( $task['due'], new \DateTimeZone( 'UTC' ) ) )->modify( '+1 day' )->format( 'Y-m-d' );
			$event['start']   = array( 'date' => $task['due'] );
			$event['end']     = array( 'date' => $next );
			return $event;
		}

		$tz    = self::zone( $timezone );
		$start = new \DateTimeImmutable( $task['due'] . ' ' . $task['due_time'], $tz );
		$end   = $start->modify( '+' . max( 5, $minutes ) . ' minutes' );
		$named = self::isNamed( $timezone );

		$event['start'] = array( 'dateTime' => $start->format( 'Y-m-d\TH:i:sP' ) ) + ( $named ? array( 'timeZone' => $timezone ) : array() );
		$event['end']   = array( 'dateTime' => $end->format( 'Y-m-d\TH:i:sP' ) ) + ( $named ? array( 'timeZone' => $timezone ) : array() );
		return $event;
	}

	/** Whether a timezone string is an IANA name Google accepts (not an offset like +02:00). */
	public static function isNamed( string $timezone ): bool {
		return str_contains( $timezone, '/' ) || 'UTC' === $timezone;
	}

	private static function zone( string $timezone ): \DateTimeZone {
		$offset = preg_replace( '/^UTC/', '', $timezone );
		try {
			return new \DateTimeZone( '' === $offset ? 'UTC' : (string) $offset );
		} catch ( \Throwable ) {
			return new \DateTimeZone( 'UTC' );
		}
	}
}
