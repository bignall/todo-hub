<?php
/**
 * Words for due dates, priorities and repeats, shared by the admin screens.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub\Admin;

use TodoHub\DueDate;
use TodoHub\Priority;
use TodoHub\Ranking;
use TodoHub\Settings;

final class Format {

	/**
	 * "3 days overdue", "Overdue since 2:00 pm", "Due today", "Due today at 2:00 pm",
	 * "Due tomorrow", "Due Fri, Nov 21 at 9:00 am", "Due Jan 5, 2027".
	 */
	public static function due( string $due, string $time = '' ): string {
		if ( '' === $due ) {
			return '';
		}
		$now  = Settings::now();
		$days = DueDate::daysUntil( $due, $now );
		$at   = '' === $time ? '' : self::time( $due, $time );
		if ( $days < 0 ) {
			/* translators: %s: number of days. */
			return sprintf( _n( '%s day overdue', '%s days overdue', -$days, 'todo-hub' ), number_format_i18n( -$days ) );
		}
		if ( 0 === $days ) {
			if ( '' === $at ) {
				return __( 'Due today', 'todo-hub' );
			}
			/* translators: %s: a time of day. */
			return Ranking::STATE_OVERDUE === self::dueState( $due, $time ) ? sprintf( __( 'Overdue since %s', 'todo-hub' ), $at ) : sprintf( __( 'Due today at %s', 'todo-hub' ), $at );
		}
		if ( 1 === $days ) {
			/* translators: %s: a time of day. */
			return '' === $at ? __( 'Due tomorrow', 'todo-hub' ) : sprintf( __( 'Due tomorrow at %s', 'todo-hub' ), $at );
		}
		$timestamp = ( new \DateTimeImmutable( $due . ' 12:00', wp_timezone() ) )->getTimestamp();
		$format    = $days <= 6 ? _x( 'D, M j', 'due date this week', 'todo-hub' ) : ( substr( $due, 0, 4 ) === $now->format( 'Y' ) ? _x( 'M j', 'due date this year', 'todo-hub' ) : _x( 'M j, Y', 'due date', 'todo-hub' ) );
		$date      = wp_date( $format, $timestamp );
		/* translators: 1: a date, 2: a time of day. */
		return '' === $at ? sprintf( __( 'Due %s', 'todo-hub' ), $date ) : sprintf( __( 'Due %1$s at %2$s', 'todo-hub' ), $date, $at );
	}

	/** A due time (H:i) in the site's time format. */
	public static function time( string $due, string $time ): string {
		$moment = new \DateTimeImmutable( $due . ' ' . $time, wp_timezone() );
		return wp_date( (string) get_option( 'time_format', 'g:i a' ), $moment->getTimestamp() );
	}

	/** CSS modifier for a due date: overdue, today, soon, week, later or none. */
	public static function dueState( string $due, string $time = '' ): string {
		return Ranking::state( $due, Settings::now(), Settings::soonDays(), Settings::weekDays(), $time );
	}

	public static function priority( int $priority ): string {
		return match ( $priority ) {
			Priority::HIGH => __( 'High', 'todo-hub' ),
			Priority::LOW  => __( 'Low', 'todo-hub' ),
			default        => __( 'Normal', 'todo-hub' ),
		};
	}

	/**
	 * "Repeats weekly", "Repeats every 2 months".
	 *
	 * @param array{every: int, unit: string}|null $rule Repeat rule.
	 */
	public static function repeat( ?array $rule ): string {
		if ( null === $rule ) {
			return '';
		}
		if ( 1 === $rule['every'] ) {
			return match ( $rule['unit'] ) {
				'day'   => __( 'Repeats daily', 'todo-hub' ),
				'week'  => __( 'Repeats weekly', 'todo-hub' ),
				'month' => __( 'Repeats monthly', 'todo-hub' ),
				default => __( 'Repeats yearly', 'todo-hub' ),
			};
		}
		$n = number_format_i18n( $rule['every'] );
		return match ( $rule['unit'] ) {
			/* translators: %s: number of days. */
			'day'   => sprintf( __( 'Repeats every %s days', 'todo-hub' ), $n ),
			/* translators: %s: number of weeks. */
			'week'  => sprintf( __( 'Repeats every %s weeks', 'todo-hub' ), $n ),
			/* translators: %s: number of months. */
			'month' => sprintf( __( 'Repeats every %s months', 'todo-hub' ), $n ),
			/* translators: %s: number of years. */
			default => sprintf( __( 'Repeats every %s years', 'todo-hub' ), $n ),
		};
	}

	/** @return array<string, string> Unit => label, for the repeat dropdowns. */
	public static function units(): array {
		return array(
			''      => __( "Doesn't repeat", 'todo-hub' ),
			'day'   => __( 'day(s)', 'todo-hub' ),
			'week'  => __( 'week(s)', 'todo-hub' ),
			'month' => __( 'month(s)', 'todo-hub' ),
			'year'  => __( 'year(s)', 'todo-hub' ),
		);
	}
}
