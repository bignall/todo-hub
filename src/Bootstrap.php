<?php
/**
 * Plugin definition: lifecycle handled by WP Foundation.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

use TodoHub\Vendor\Bignall\WPFoundation\Plugin;

final class Bootstrap {

	public const PREFIX = 'tdh';

	public static function plugin( string $file ): Plugin {
		return Plugin::create( $file, self::PREFIX )
			->requires( php: '8.1', wp: '6.4' )
			->options( array( Settings::OPTION => Settings::defaults() ), merge: true )
			->capabilities( array( 'administrator' => array( Tasks::CAPABILITY ) ) )
			->postTypes( Tasks::POST_TYPE )
			->taxonomies( Tasks::TAXONOMY )
			->deactivationSurvey()
			->deleteDataOnUninstall( static fn (): bool => (bool) Settings::get( 'remove_data' ) )
			->onReady( static fn ( Plugin $plugin ) => App::boot( $plugin ) );
	}
}
