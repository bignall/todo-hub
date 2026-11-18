<?php
/**
 * Plugin settings, stored in one option.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class Settings {

	public const OPTION = 'tdh_settings';

	/** @return array{soon_days: int, week_days: int, private_site: bool, remove_data: bool} */
	public static function defaults(): array {
		return array(
			'soon_days'    => Ranking::SOON_DAYS,
			'week_days'    => Ranking::WEEK_DAYS,
			'private_site' => false,
			'remove_data'  => false,
		);
	}

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		register_setting(
			'tdh_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function get( string $key ): mixed {
		$saved = get_option( self::OPTION, array() );
		$all   = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		return $all[ $key ] ?? null;
	}

	/**
	 * @param mixed $input Submitted values.
	 * @return array{soon_days: int, week_days: int, private_site: bool, remove_data: bool}
	 */
	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$soon  = max( 1, min( 30, absint( $input['soon_days'] ?? Ranking::SOON_DAYS ) ) );
		$week  = max( $soon, min( 90, absint( $input['week_days'] ?? Ranking::WEEK_DAYS ) ) );
		return array(
			'soon_days'    => $soon,
			'week_days'    => $week,
			'private_site' => ! empty( $input['private_site'] ),
			'remove_data'  => ! empty( $input['remove_data'] ),
		);
	}

	/** Today in the site's timezone. */
	public static function today(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'today', wp_timezone() );
	}

	public static function soonDays(): int {
		return (int) self::get( 'soon_days' );
	}

	public static function weekDays(): int {
		return (int) self::get( 'week_days' );
	}
}
