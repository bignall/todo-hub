<?php
/**
 * Sort order for open tasks: what needs doing first comes first.
 *
 * 1. Overdue tasks, then tasks due today (high priority first within each).
 * 2. Everything else by priority, raised as the due date gets close: due within
 *    SOON_DAYS days counts two levels higher, within WEEK_DAYS days one level higher.
 *    So a low-priority task due tomorrow sits with high-priority tasks that have no date.
 * 3. Ties go to the earlier due date and time (tasks with no date last; on the same day,
 *    tasks with a time before those without), then the older task.
 *
 * A task with a due time counts as overdue once that time has passed today.
 * $now is the current date and time in the site's timezone (its time only matters for
 * tasks with a due time).
 *
 * Plain PHP, no WordPress, so it can be unit tested.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class Ranking {

	public const SOON_DAYS = 3;
	public const WEEK_DAYS = 7;

	public const STATE_OVERDUE = 'overdue';
	public const STATE_TODAY   = 'today';
	public const STATE_SOON    = 'soon';
	public const STATE_WEEK    = 'week';
	public const STATE_LATER   = 'later';
	public const STATE_NONE    = 'none';

	/** Where a due date (and optional H:i time) stands relative to now. */
	public static function state( string $due, \DateTimeImmutable $now, int $soon = self::SOON_DAYS, int $week = self::WEEK_DAYS, string $time = '' ): string {
		if ( '' === $due ) {
			return self::STATE_NONE;
		}
		$days = DueDate::daysUntil( $due, $now );
		return match ( true ) {
			$days < 0      => self::STATE_OVERDUE,
			0 === $days && '' !== $time && $time < $now->format( 'H:i' ) => self::STATE_OVERDUE,
			0 === $days    => self::STATE_TODAY,
			$days <= $soon => self::STATE_SOON,
			$days <= $week => self::STATE_WEEK,
			default        => self::STATE_LATER,
		};
	}

	/** Sort weight: higher comes first. */
	public static function level( int $priority, string $due, \DateTimeImmutable $now, int $soon = self::SOON_DAYS, int $week = self::WEEK_DAYS, string $time = '' ): int {
		return match ( self::state( $due, $now, $soon, $week, $time ) ) {
			self::STATE_OVERDUE => 200 + $priority,
			self::STATE_TODAY   => 100 + $priority,
			self::STATE_SOON    => $priority + 2,
			self::STATE_WEEK    => $priority + 1,
			default             => $priority,
		};
	}

	/**
	 * Sort tasks, first to do first. Each task needs priority (int), due (Y-m-d or ''),
	 * created (any string that sorts by time, e.g. Y-m-d H:i:s) and id (int), and may have
	 * due_time (H:i or '').
	 *
	 * @template T of array{priority: int, due: string, created: string, id: int}
	 * @param list<T> $tasks Tasks to sort.
	 * @return list<T>
	 */
	public static function sort( array $tasks, \DateTimeImmutable $now, int $soon = self::SOON_DAYS, int $week = self::WEEK_DAYS ): array {
		$levels = array();
		foreach ( $tasks as $i => $task ) {
			$levels[ $i ] = self::level( $task['priority'], $task['due'], $now, $soon, $week, $task['due_time'] ?? '' );
		}
		$keys = array_keys( $tasks );
		usort(
			$keys,
			static function ( $a, $b ) use ( $tasks, $levels ): int {
				$ta = $tasks[ $a ];
				$tb = $tasks[ $b ];
				return ( $levels[ $b ] <=> $levels[ $a ] )
					?: self::compareDue( $ta['due'], $tb['due'] )
					?: self::compareTime( $ta['due_time'] ?? '', $tb['due_time'] ?? '' )
					?: ( $tb['priority'] <=> $ta['priority'] )
					?: ( $ta['created'] <=> $tb['created'] )
					?: ( $ta['id'] <=> $tb['id'] );
			}
		);
		return array_map( static fn ( $k ) => $tasks[ $k ], $keys );
	}

	/** Earlier dates first; no date last. */
	private static function compareDue( string $a, string $b ): int {
		if ( $a === $b ) {
			return 0;
		}
		if ( '' === $a ) {
			return 1;
		}
		if ( '' === $b ) {
			return -1;
		}
		return $a <=> $b;
	}

	/** Earlier times first; no time (any time that day) after timed tasks. */
	private static function compareTime( string $a, string $b ): int {
		return self::compareDue( $a, $b );
	}
}
