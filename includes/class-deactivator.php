<?php
namespace Arshid6Social;

/**
 * Plugin deactivation handler.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 *
 * Cleans up scheduled events and flushes rewrite rules on deactivation.
 * Tables and options are preserved (uninstall.php handles permanent removal).
 */
class Deactivator {

	/**
	 * Entry point called by register_deactivation_hook().
	 */
	public static function deactivate(): void {
		self::clear_scheduled_events();
		self::maybe_revert_bundled_theme();
		flush_rewrite_rules();

		do_action( 'arshid6social_deactivated' );
	}

	/**
	 * If the active theme is the plugin-bundled "sixarshidsocialcomunity", switch to the
	 * default WordPress theme so the site doesn't break when the plugin is off.
	 */
	private static function maybe_revert_bundled_theme(): void {
		if ( get_stylesheet() !== '6arshid-social-community' ) {
			return;
		}

		// Find a safe fallback: use the previously active theme if stored,
		// otherwise fall back to twentytwentyfour → twentytwentythree → twentytwentytwo.
		$previous = get_option( 'arshid6social_theme_before_activation', '' );
		if ( $previous && wp_get_theme( $previous )->exists() ) {
			switch_theme( $previous );
			return;
		}

		foreach ( array( 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone' ) as $fallback ) {
			if ( wp_get_theme( $fallback )->exists() ) {
				switch_theme( $fallback );
				return;
			}
		}
	}

	/**
	 * Unschedules all plugin-owned WP-Cron hooks.
	 */
	private static function clear_scheduled_events(): void {
		$hooks = array(
			'arshid6social_daily_digest',
			'arshid6social_weekly_digest',
			'arshid6social_cleanup_transients',
		);

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}
