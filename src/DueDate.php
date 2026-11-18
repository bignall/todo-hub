<?php
/**
 * Due dates are calendar days (Y-m-d) in the site's timezone, or '' for no due date.
 *
 * Plain PHP, no WordPress, so it can be unit tested.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class DueDate {

	/**
	 * Normalise a due date to Y-m-d, or '' when empty or unreadable.
	 *
	 * Accepts a Y-m-d string, a DateTimeInterface (its own calendar day is used), a Unix
	 * timestamp (read in $tz), or a relative phrase such as "tomorrow" or "next friday"
	 * (read against $today).
	 */
	public static function parse( mixed $value, \DateTimeZone $tz, ?\DateTimeImmutable $today = null ): string {
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( 'Y-m-d' );
		}
		if ( is_int( $value ) ) {
			return ( new \DateTimeImmutable( '@' . $value ) )->setTimezone( $tz )->format( 'Y-m-d' );
		}
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
		}
		// Check first: on PHP 8.1 and 8.2, modify() warns on text it can't read instead of throwing.
		$parsed = date_parse( $value );
		if ( ! is_array( $parsed ) || $parsed['error_count'] > 0 ) {
			return '';
		}
		try {
			$base = $today ?? new \DateTimeImmutable( 'today', $tz );
			$date = $base->setTimezone( $tz )->modify( $value );
			return $date instanceof \DateTimeImmutable ? $date->format( 'Y-m-d' ) : '';
		} catch ( \Throwable ) {
			return '';
		}
	}

	/** Whole days from $today to $due: negative when overdue, 0 when due today. */
	public static function daysUntil( string $due, \DateTimeImmutable $today ): int {
		$from = new \DateTimeImmutable( $today->format( 'Y-m-d' ), new \DateTimeZone( 'UTC' ) );
		$to   = new \DateTimeImmutable( $due, new \DateTimeZone( 'UTC' ) );
		return (int) $from->diff( $to )->format( '%r%a' );
	}
}
