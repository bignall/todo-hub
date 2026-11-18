<?php
/**
 * Task priorities: low, normal and high, stored as 1, 2 and 3.
 *
 * Plain PHP, no WordPress, so it can be unit tested.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class Priority {

	public const LOW    = 1;
	public const NORMAL = 2;
	public const HIGH   = 3;

	private const NAMES = array(
		self::LOW    => 'low',
		self::NORMAL => 'normal',
		self::HIGH   => 'high',
	);

	/**
	 * Accepts 1-3, '1'-'3', or a name: low, normal (also medium), high.
	 * Anything else is normal.
	 */
	public static function parse( mixed $value ): int {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$int = (int) $value;
			return isset( self::NAMES[ $int ] ) ? $int : self::NORMAL;
		}
		if ( is_string( $value ) ) {
			$name = strtolower( trim( $value ) );
			if ( 'medium' === $name ) {
				return self::NORMAL;
			}
			$found = array_search( $name, self::NAMES, true );
			if ( false !== $found ) {
				return $found;
			}
		}
		return self::NORMAL;
	}

	/** Machine name: low, normal or high. */
	public static function name( int $priority ): string {
		return self::NAMES[ $priority ] ?? 'normal';
	}

	/** @return array<int, string> Priority => machine name, highest first. */
	public static function all(): array {
		return array_reverse( self::NAMES, true );
	}
}
