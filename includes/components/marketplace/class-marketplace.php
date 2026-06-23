<?php
namespace Arshid6Social\Components\Marketplace;

/**
 * Marketplace component bootstrap.
 *
 * This class is the entry point for the entire Marketplace module.
 * It is only instantiated when arshid6social_marketplace_enabled is true (the Plugin
 * class adds it to the component_map under that condition), so disabling the
 * setting from the admin panel is enough to fully unload every hook, asset,
 * REST route, and cron callback — no stale code runs.
 *
 * IMPORTANT — payment disclaimer:
 * This plugin does NOT process, hold, or facilitate payments in any way.
 * Buyers and sellers arrange payment entirely between themselves via private
 * messages (peer-to-peer, like Facebook Marketplace). The plugin assumes no
 * liability for any transaction. See arshid6social_payment_gateways filter for the
 * extension point if a future add-on wishes to integrate an escrow service.
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Marketplace
 *
 * Boots the Marketplace module: runs DB migrations, registers cron hooks.
 * Sub-features (listings, browse, messaging integration, REST API, etc.) are
 * added in later steps as separate classes loaded from this constructor.
 */
class Marketplace {

	/**
	 * Initialises the component: DB migrations, hooks, cron events.
	 * Settings are wired up separately from Plugin::load_admin() so the admin
	 * tab is always visible regardless of whether the component is active.
	 */
	public function __construct() {
		Marketplace_DB::maybe_upgrade();
		new Marketplace_Listings();
		$this->schedule_events();
		$this->register_hooks();

		/**
		 * Fires after the Marketplace component has been fully bootstrapped.
		 * Use this to attach sub-feature classes (listings, messaging, REST, etc.)
		 * added in subsequent build steps.
		 *
		 * @since 1.0.0
		 */
		do_action( 'arshid6social_marketplace_loaded', $this );
	}

	// ── Hooks ────────────────────────────────────────────────────────────────

	private function register_hooks(): void {
		// Cron callbacks.
		add_action( 'arshid6social_marketplace_expire_listings',     array( $this, 'cron_expire_listings' ) );
		add_action( 'arshid6social_marketplace_saved_search_alerts', array( $this, 'cron_saved_search_alerts' ) );

		// Clear cron events when the marketplace is disabled via settings.
		add_action( 'update_option_arshid6social_marketplace_enabled', array( $this, 'on_enabled_toggle' ), 10, 2 );

		// Sitemap.
		add_action( 'init', array( $this, 'register_sitemap_provider' ) );
	}

	public function register_sitemap_provider(): void {
		if ( function_exists( 'wp_sitemaps_add_provider' ) ) {
			wp_sitemaps_add_provider( 'arshid6social_marketplace', new Marketplace_Sitemap_Provider() );
		}
	}

	// ── Cron ─────────────────────────────────────────────────────────────────

	private function schedule_events(): void {
		if ( ! wp_next_scheduled( 'arshid6social_marketplace_expire_listings' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 02:00:00' ), 'daily', 'arshid6social_marketplace_expire_listings' );
		}
		if ( ! wp_next_scheduled( 'arshid6social_marketplace_saved_search_alerts' ) ) {
			wp_schedule_event( time(), 'hourly', 'arshid6social_marketplace_saved_search_alerts' );
		}
	}

	/**
	 * Archives listings whose expires_at has passed.
	 * Implemented fully in Step 3 (Listing CRUD).
	 */
	public function cron_expire_listings(): void {
		// Step 3.
	}

	/**
	 * Matches new listings against saved searches and sends notifications.
	 * Implemented fully in Step 7 (Favorites + Saved Searches).
	 */
	public function cron_saved_search_alerts(): void {
		// Step 7.
	}

	/**
	 * Clears scheduled events when the marketplace is turned off.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public function on_enabled_toggle( $old_value, $new_value ): void {
		if ( ! $new_value ) {
			self::clear_cron_events();
		}
	}

	/**
	 * Unschedules all Marketplace cron jobs.
	 * Also called from uninstall.php.
	 */
	public static function clear_cron_events(): void {
		wp_clear_scheduled_hook( 'arshid6social_marketplace_expire_listings' );
		wp_clear_scheduled_hook( 'arshid6social_marketplace_saved_search_alerts' );
	}

	// ── REST API ─────────────────────────────────────────────────────────────

	/**
	 * Registers REST API routes for the Marketplace.
	 * Called by Plugin::register_rest_routes() on rest_api_init.
	 * Implemented in Step 10 (REST API).
	 */
	public function register_rest_routes(): void {
		// Step 10.
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Returns true when the Marketplace is enabled and its core dependency
	 * (the Messages component) is available.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return (bool) get_option( 'arshid6social_marketplace_enabled', false )
			&& null !== ARSHID6SOCIAL()->component( 'messages' );
	}

	/**
	 * Formats a price value for display using the admin-configured currency settings.
	 *
	 * @param float|string $price     Raw price (e.g. 1234.50).
	 * @param bool         $is_free   When true returns the localised "Free" label.
	 * @return string  e.g. "$1,234.50" or "£9.99" or "رایگان"
	 */
	public static function format_price( $price, bool $is_free = false ): string {
		if ( $is_free ) {
			return esc_html__( 'Free', 'social-network-6' );
		}

		$symbol    = (string) get_option( 'arshid6social_marketplace_currency_symbol',   '$' );
		$position  = (string) get_option( 'arshid6social_marketplace_currency_position', 'before' );
		$decimals  = (int)    get_option( 'arshid6social_marketplace_currency_decimals',  2 );
		$thousands = (string) get_option( 'arshid6social_marketplace_currency_thousands', ',' );
		$decimal_sep = '.';

		$formatted = number_format( (float) $price, $decimals, $decimal_sep, $thousands );

		return 'before' === $position
			? $symbol . $formatted
			: $formatted . $symbol;
	}

	/**
	 * Returns the Haversine great-circle distance in kilometres between two
	 * lat/lng pairs. Used for approximate distance display (never exact address).
	 *
	 * @param float $lat1 Latitude of point A.
	 * @param float $lng1 Longitude of point A.
	 * @param float $lat2 Latitude of point B.
	 * @param float $lng2 Longitude of point B.
	 * @return float Distance in km.
	 */
	public static function haversine_km( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$earth_km = 6371;
		$d_lat    = deg2rad( $lat2 - $lat1 );
		$d_lng    = deg2rad( $lng2 - $lng1 );
		$a        = sin( $d_lat / 2 ) ** 2
		          + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) ** 2;
		return $earth_km * 2 * asin( sqrt( $a ) );
	}

	/**
	 * Fuzzes precise coordinates to ~±0.01° (≈1 km) before any public output.
	 * Ensures the seller's exact location is never exposed via the REST API or
	 * page renders.
	 *
	 * @param float $lat Precise latitude.
	 * @param float $lng Precise longitude.
	 * @return float[] [fuzzed_lat, fuzzed_lng]
	 */
	public static function fuzz_coordinates( float $lat, float $lng ): array {
		$offset = 0.008; // ~0.8 km radius
		return array(
			round( $lat + ( ( wp_rand( 0, 1000 ) / 1000 - 0.5 ) * $offset * 2 ), 4 ),
			round( $lng + ( ( wp_rand( 0, 1000 ) / 1000 - 0.5 ) * $offset * 2 ), 4 ),
		);
	}

	/**
	 * Returns the bounding-box lat/lng limits for a given radius around a centre
	 * point. Used as a cheap SQL pre-filter before the more expensive Haversine
	 * distance check.
	 *
	 * @param float $lat    Centre latitude.
	 * @param float $lng    Centre longitude.
	 * @param float $radius Radius in kilometres.
	 * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
	 */
	public static function bounding_box( float $lat, float $lng, float $radius ): array {
		$lat_deg = $radius / 110.574;
		$lng_deg = $radius / ( 111.320 * cos( deg2rad( $lat ) ) );
		return array(
			'min_lat' => $lat - $lat_deg,
			'max_lat' => $lat + $lat_deg,
			'min_lng' => $lng - $lng_deg,
			'max_lng' => $lng + $lng_deg,
		);
	}
}
