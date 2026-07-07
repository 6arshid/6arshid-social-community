<?php
/**
 * Plugin Name:       6Arshid Social Community
 * Plugin URI:        https://6arshid.com/apps/wordpress/6arshid-social-community
 * Description:       A complete, secure, responsive, multilingual social network plugin for WordPress with profiles, activity streams, groups, messaging, notifications, and more.
 * Version:           1.5.7
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            6arshid
 * Author URI:        https://6arshid.com/
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       6arshid-social-community
 * Domain Path:       /languages
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

// Plugin version and constants.
if ( defined( 'ARSHID6SOCIAL_VERSION' ) ) {
	return; // Already loaded (duplicate plugin file — bail early).
}
define( 'ARSHID6SOCIAL_VERSION', '1.7.0' );
define( 'ARSHID6SOCIAL_ASSET_VER', '2.3.2' );
define( 'ARSHID6SOCIAL_DB_VERSION', '1.9.0' );
define( 'ARSHID6SOCIAL_PLUGIN_FILE', __FILE__ );
define( 'ARSHID6SOCIAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARSHID6SOCIAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ARSHID6SOCIAL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ARSHID6SOCIAL_INCLUDES_DIR', ARSHID6SOCIAL_PLUGIN_DIR . 'includes/' );
define( 'ARSHID6SOCIAL_TEMPLATES_DIR', ARSHID6SOCIAL_PLUGIN_DIR . 'templates/' );
define( 'ARSHID6SOCIAL_ASSETS_URL', ARSHID6SOCIAL_PLUGIN_URL . 'assets/' );
define( 'ARSHID6SOCIAL_TEXT_DOMAIN', '6arshid-social-community' );
define( 'ARSHID6SOCIAL_MIN_PHP', '8.1' );
define( 'ARSHID6SOCIAL_MIN_WP', '6.5' );

// PHP version gate — show admin notice instead of fatal error.
if ( version_compare( PHP_VERSION, ARSHID6SOCIAL_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					esc_html__( '6Arshid Social Community requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade.', '6arshid-social-community' ),
					esc_html( ARSHID6SOCIAL_MIN_PHP ),
					esc_html( PHP_VERSION )
				)
			);
		}
	);
	return;
}

// Autoloader.
require_once ARSHID6SOCIAL_INCLUDES_DIR . 'class-autoloader.php';
\Arshid6Social\Autoloader::register();

// Register the bundled 6Arshid Social Community FSE theme directory with WordPress.
// Priority 1 = before themes load their functions.php so the directory is
// already known when get_template_directory() is first called.
add_action( 'after_setup_theme', static function () {
	register_theme_directory( ARSHID6SOCIAL_PLUGIN_DIR . 'themes' );
}, 1 );

// Also register on plugins_loaded so AJAX requests (which may skip
// after_setup_theme) can still find the bundled theme.
add_action( 'plugins_loaded', static function () {
	register_theme_directory( ARSHID6SOCIAL_PLUGIN_DIR . 'themes' );
}, 1 );

// Activation / deactivation hooks must be registered before any class is instantiated.
register_activation_hook( __FILE__, array( '\Arshid6Social\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\Arshid6Social\Deactivator', 'deactivate' ) );

if ( ! function_exists( 'arshid6social' ) ) {
	/**
	 * Returns the main plugin instance (singleton).
	 *
	 * @return \Arshid6Social\Plugin
	 */
	function arshid6social() {
		return \Arshid6Social\Plugin::instance();
	}
}

if ( ! function_exists( 'arshid6social_eng' ) ) {
	/**
	 * Returns the Engagement Pack instance (singleton).
	 *
	 * @return \Arshid6Social\Engagement\Engagement
	 */
	function arshid6social_eng(): \Arshid6Social\Engagement\Engagement {
		return \Arshid6Social\Engagement\Engagement::instance();
	}
}

// Auto-set the activity page as the WordPress front page if no static front page
// has been chosen yet.  Runs once; respects any later manual change by the admin.
add_action( 'admin_init', static function () {
	// Already done once — skip.
	if ( get_option( 'arshid6social_frontpage_set' ) ) {
		return;
	}

	// Only act when WordPress is still in "latest posts" mode (no static front page).
	if ( 'posts' !== get_option( 'show_on_front', 'posts' ) ) {
		// Admin already chose a static front page — record that and never touch it again.
		update_option( 'arshid6social_frontpage_set', true );
		return;
	}

	$activity_page_id = (int) get_option( 'arshid6social_page_activity', 0 );
	if ( $activity_page_id && 'publish' === get_post_status( $activity_page_id ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $activity_page_id );
	}

	update_option( 'arshid6social_frontpage_set', true );
}, 5 );

// Flush stale DB-cached FSE templates for the sixarshidsocialcomunity theme so the
// file-based templates are always used after a theme update.
add_action( 'admin_init', static function () {
	$ver = 'socialnetworksix-tpl-v4';
	if ( get_option( 'arshid6social_tpl_flush' ) === $ver ) {
		return;
	}
	global $wpdb;
	$like = $wpdb->esc_like( 'sixarshidsocialcomunity//' ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->posts}
			 WHERE post_type IN ('wp_template','wp_template_part')
			   AND post_name LIKE %s",
			$like
		)
	);
	update_option( 'arshid6social_tpl_flush', $ver );
}, 1 );

// Boot on init so translated strings are not evaluated before WordPress loads
// textdomains in WP 6.7+, while still registering routes, shortcodes, and assets
// before their later lifecycle hooks run.
add_action( 'init', 'arshid6social', 0 );

// Boot the Engagement Pack after the main plugin is ready.
add_action( 'arshid6social_loaded', 'arshid6social_eng' );

// Run database upgrades whenever the stored DB version is behind the current one.
add_action( 'plugins_loaded', array( '\Arshid6Social\Activator', 'maybe_upgrade' ), 5 );
