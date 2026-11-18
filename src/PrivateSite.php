<?php
/**
 * Optional: send logged-out visitors to the login screen, for a hub site that should not be public.
 *
 * @package TodoHub
 */

declare(strict_types=1);

namespace TodoHub;

final class PrivateSite {

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'guard' ), 0 );
	}

	public static function guard(): void {
		if ( ! Settings::get( 'private_site' ) || is_user_logged_in() ) {
			return;
		}
		/**
		 * Filters whether this front-end request may be seen without logging in.
		 *
		 * @param bool $allowed Default false.
		 */
		if ( apply_filters( 'todo_hub_private_site_allow', false ) ) {
			return;
		}
		auth_redirect();
	}
}
