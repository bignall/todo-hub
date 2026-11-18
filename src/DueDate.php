<?php
/**
 * Due dates are calendar days (Y-m-d) in the site's timezone, or '' for no due date, with an
 * optional time of day (H:i, 24-hour), or '' for any time that day.
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
		return self::parseWithTime( $value, $tz, $today )['date'];
	}

	/**
	 * Like parse(), but also keeps a time of day when one is given: "2026-11-20 14:30",
	 * "tomorrow 3pm", a timestamp or date object that isn't exactly midnight.
	 *
	 * @return array{date: string, time: string} Y-m-d and H:i; either may be ''.
	 */
	public static function parseWithTime( mixed $value, \DateTimeZone $tz, ?\DateTimeImmutable $today = null ): array {
		$none = array(
			'date' => '',
			'time' => '',
		);
		if ( is_int( $value ) ) {
			$value = ( new \DateTimeImmutable( '@' . $value ) )->setTimezone( $tz );
		}
		if ( $value instanceof \DateTimeInterface ) {
			$time = $value->format( 'H:i:s' );
			return array(
				'date' => $value->format( 'Y-m-d' ),
				'time' => '00:00:00' === $time ? '' : substr( $time, 0, 5 ),
			);
		}
		if ( ! is_string( $value ) ) {
			return $none;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return $none;
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? array(
				'date' => $value,
				'time' => '',
			) : $none;
		}
		// Check first: on PHP 8.1 and 8.2, modify() warns on text it can't read instead of throwing.
		$parsed = date_parse( $value );
		if ( ! is_array( $parsed ) || $parsed['error_count'] > 0 ) {
			return $none;
		}
		try {
			$base = ( $today ?? new \DateTimeImmutable( 'today', $tz ) )->setTimezone( $tz )->setTime( 0, 0 );
			$date = $base->modify( $value );
			if ( ! $date instanceof \DateTimeImmutable ) {
				return $none;
			}
			return array(
				'date' => $date->format( 'Y-m-d' ),
				'time' => self::mentionsTime( $value ) ? $date->format( 'H:i' ) : '',
			);
		} catch ( \Throwable ) {
			return $none;
		}
	}

	/** Whether text names a time of day ("14:30", "3pm", "noon"), not just a day ("tomorrow"). */
	private static function mentionsTime( string $text ): bool {
		return (bool) preg_match( '/\d:\d{2}|\d\s*[ap]\.?m\b|\b(noon|midnight)\b/i', $text );
	}

	/**
	 * Normalise a time of day to H:i (24-hour), or '' when empty or unreadable.
	 * Accepts "14:30", "14:30:00", "2:30 pm", "9am", "9 AM".
	 */
	public static function parseTime( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strtolower( str_replace( array( ' ', '.' ), '', trim( $value ) ) );
		if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?(?::\d{2})?(am|pm)?$/', $value, $m ) ) {
			return '';
		}
		$hour   = (int) $m[1];
		$minute = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
		$suffix = $m[3] ?? '';
		if ( '' === $suffix && ( ! isset( $m[2] ) || '' === $m[2] ) ) {
			return ''; // A bare number like "9" is too ambiguous.
		}
		if ( '' !== $suffix ) {
			if ( $hour < 1 || $hour > 12 ) {
				return '';
			}
			$hour = ( 12 === $hour ? 0 : $hour ) + ( 'pm' === $suffix ? 12 : 0 );
		}
		if ( $hour > 23 || $minute > 59 ) {
			return '';
		}
		return sprintf( '%02d:%02d', $hour, $minute );
	}

	/** Whole days from $today to $due: negative when overdue, 0 when due today. */
	public static function daysUntil( string $due, \DateTimeImmutable $today ): int {
		$from = new \DateTimeImmutable( $today->format( 'Y-m-d' ), new \DateTimeZone( 'UTC' ) );
		$to   = new \DateTimeImmutable( $due, new \DateTimeZone( 'UTC' ) );
		return (int) $from->diff( $to )->format( '%r%a' );
	}
}
