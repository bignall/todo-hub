<?php
/**
 * Repeating tasks: "every N days, weeks, months or years".
 *
 * A rule is array{every: int, unit: string} or null for a one-off task. When a repeating
 * task is completed, the next copy is due one step after the old due date, moved on until
 * it's after today, so finishing late doesn't leave the next copy already overdue.
 * A task with no due date repeats one step from the day it was completed.
 *
 * Plain PHP, no WordPress, so it can be unit tested.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class Recurrence {

	public const UNITS = array( 'day', 'week', 'month', 'year' );

	/** Most steps taken to move the next due date past today (about 27 years of daily steps). */
	private const MAX_STEPS = 10000;

	/**
	 * Normalise a rule. Accepts array{every?: int, unit: string}, or a word:
	 * daily, weekly, monthly, yearly (also annually), fortnightly, biweekly.
	 *
	 * @return array{every: int, unit: string}|null
	 */
	public static function parse( mixed $value ): ?array {
		if ( is_string( $value ) ) {
			$value = match ( strtolower( trim( $value ) ) ) {
				'daily'                   => array( 'every' => 1, 'unit' => 'day' ),
				'weekly'                  => array( 'every' => 1, 'unit' => 'week' ),
				'fortnightly', 'biweekly' => array( 'every' => 2, 'unit' => 'week' ),
				'monthly'                 => array( 'every' => 1, 'unit' => 'month' ),
				'yearly', 'annually'      => array( 'every' => 1, 'unit' => 'year' ),
				default                   => null,
			};
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$unit  = is_string( $value['unit'] ?? null ) ? rtrim( strtolower( $value['unit'] ), 's' ) : '';
		$every = isset( $value['every'] ) && is_numeric( $value['every'] ) ? (int) $value['every'] : 1;
		if ( ! in_array( $unit, self::UNITS, true ) || $every < 1 || $every > 999 ) {
			return null;
		}
		return array(
			'every' => $every,
			'unit'  => $unit,
		);
	}

	/**
	 * Due date (Y-m-d) of the next copy.
	 *
	 * @param string                           $due   The completed task's due date, or ''.
	 * @param array{every: int, unit: string} $rule  Rule from parse().
	 * @param \DateTimeImmutable               $today Today in the site's timezone.
	 */
	public static function next( string $due, array $rule, \DateTimeImmutable $today ): string {
		$today_ymd = $today->format( 'Y-m-d' );
		$base      = '' === $due ? $today_ymd : $due;
		$utc       = new \DateTimeZone( 'UTC' );
		$start     = new \DateTimeImmutable( $base, $utc );

		for ( $k = 1; $k <= self::MAX_STEPS; $k++ ) {
			$next = self::step( $start, $rule, $k )->format( 'Y-m-d' );
			if ( $next > $today_ymd ) {
				return $next;
			}
		}
		return self::step( new \DateTimeImmutable( $today_ymd, $utc ), $rule, 1 )->format( 'Y-m-d' );
	}

	/**
	 * $start moved $k steps on. Months and years keep the start's day of the month, or the
	 * month's last day when it's shorter (31 Jan, monthly: 28/29 Feb, 31 Mar, 30 Apr...).
	 *
	 * @param array{every: int, unit: string} $rule Rule from parse().
	 */
	private static function step( \DateTimeImmutable $start, array $rule, int $k ): \DateTimeImmutable {
		$n = $rule['every'] * $k;
		if ( 'day' === $rule['unit'] || 'week' === $rule['unit'] ) {
			$days = 'week' === $rule['unit'] ? $n * 7 : $n;
			return $start->modify( "+{$days} days" );
		}
		$months = 'year' === $rule['unit'] ? $n * 12 : $n;
		$total  = (int) $start->format( 'Y' ) * 12 + (int) $start->format( 'n' ) - 1 + $months;
		$year   = intdiv( $total, 12 );
		$month  = $total % 12 + 1;
		$last   = (int) $start->setDate( $year, $month, 1 )->format( 't' );
		return $start->setDate( $year, $month, min( (int) $start->format( 'j' ), $last ) );
	}
}
