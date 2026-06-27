<?php
namespace Arshid6Social;

/**
 * Cache purge utility — clears all WordPress & third-party caches.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

class Cache_Purge {

	public static function boot(): void {
		add_action( 'admin_post_arshid6social_purge_cache', array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices',               array( __CLASS__, 'notice' ) );
		add_action( 'admin_bar_menu',              array( __CLASS__, 'admin_bar_button' ), 100 );
	}

	/** Admin bar quick-purge button (top bar). */
	public static function admin_bar_button( \WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bar->add_node( array(
			'id'    => 'arshid6social-purge-cache',
			'title' => '🗑 Purge WPSN Cache',
			'href'  => wp_nonce_url(
				admin_url( 'admin-post.php?action=arshid6social_purge_cache' ),
				'arshid6social_purge_cache'
			),
			'meta'  => array( 'title' => __( 'Purge all 6Arshid Social Community caches', '6arshid social community' ) ),
		) );
	}

	/** Handle the purge request. */
	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', '6arshid social community' ), 403 );
		}
		check_admin_referer( 'arshid6social_purge_cache' );

		self::purge_all();

		wp_safe_redirect( add_query_arg( 'arshid6social_purged', '1', wp_get_referer() ?: admin_url() ) );
		exit;
	}

	/** Show success notice after redirect. */
	public static function notice(): void {
		if ( empty( $_GET['arshid6social_purged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( '6Arshid Social Community: all caches purged successfully.', '6arshid social community' )
			. '</p></div>';
	}

	/** Core purge logic — hits every cache layer we know about. */
	public static function purge_all(): void {

		// 1. WordPress object cache.
		wp_cache_flush();

		// 2. Rewrite rules.
		flush_rewrite_rules( false );

		// 3. Plugin transients.
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_ARSHID6SOCIAL_%'
			   OR option_name LIKE '_transient_timeout_ARSHID6SOCIAL_%'"
		);

		// 4. W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// 5. WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// 6. WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify();
		}

		// 7. LiteSpeed Cache.
		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			\LiteSpeed\Purge::purge_all();
		}

		// 8. Autoptimize.
		if ( class_exists( '\autoptimizeCache' ) ) {
			\autoptimizeCache::clearall();
		}

		// 9. SiteGround Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// 10. Kinsta / Redis Object Cache.
		if ( function_exists( 'kinsta_clear_cache' ) ) {
			kinsta_clear_cache();
		}

		// 11. WP Engine.
		if ( class_exists( '\WpeCommon' ) && method_exists( '\WpeCommon', 'purge_memcached' ) ) {
			\WpeCommon::purge_memcached();
			\WpeCommon::clear_maxcdn_cache();
		}

		// 12. Cloudflare (via official plugin option).
		if ( function_exists( 'cloudflare_purge_cache' ) ) {
			cloudflare_purge_cache();
		}

		// 13. Generic action hook so any other cache plugin can listen.
		do_action( 'arshid6social_cache_purged' );
		do_action( 'cachify_flush_cache' );       // Cachify.
		do_action( 'comet_cache_wipe_cache' );    // Comet Cache.
		do_action( 'swift_performance_cache_purge_all' ); // Swift Performance.

		// 14. Touch plugin asset files so filemtime-based version strings update.
		self::touch_assets();
	}

	/**
	 * Updates the mtime of all plugin CSS/JS assets so wp_enqueue_style/script
	 * generates new versioned URLs, forcing browsers to fetch fresh copies.
	 */
	public static function touch_assets(): void {
		$asset_files = array(
			'assets/css/social-network.css',
			'assets/css/social-network.min.css',
			'assets/css/rtl.css',
			'assets/css/stories.css',
			'assets/css/blocking.css',
			'assets/css/verification.css',
			'assets/engagement/css/engagement.css',
			'assets/engagement/css/social-share-external.css',
			'assets/js/social-network.js',
			'assets/js/social-network.min.js',
			'assets/js/messages.js',
			'assets/js/stories.js',
		);

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( ! $wp_filesystem ) {
			return;
		}
		$now = time();
		foreach ( $asset_files as $relative_path ) {
			$full_path = ARSHID6SOCIAL_PLUGIN_DIR . $relative_path;
			if ( $wp_filesystem->exists( $full_path ) ) {
				$wp_filesystem->touch( $full_path, $now );
			}
		}
	}
}
