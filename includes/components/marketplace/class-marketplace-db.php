<?php
namespace Arshid6Social\Components\Marketplace;

/**
 * Marketplace database schema and migrations.
 *
 * Manages its own version option (arshid6social_marketplace_db_version) so the
 * Marketplace stays fully standalone and never forces a global DB-version bump.
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Marketplace_DB
 *
 * All table names use the ARSHID6SOCIAL_ prefix (e.g. wp_arshid6social_listings) to keep
 * marketplace tables clearly separated from core sn_ tables.
 */
class Marketplace_DB {

	const MARKETPLACE_DB_VERSION = '1.0.1';

	/**
	 * Runs on every request when the Marketplace component is active.
	 * Creates/upgrades tables only when the stored version is behind.
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( 'arshid6social_marketplace_db_version', '0.0.0' );
		if ( version_compare( $stored, self::MARKETPLACE_DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();              // dbDelta handles new columns safely
		self::migrate_thread_type_column(); // idempotent
		self::seed_default_categories();    // only if table is empty
		self::seed_default_options();       // add_option — never overwrites

		// 1.0.1: backfill uid for rows created before uid column existed
		if ( version_compare( $stored, '1.0.1', '<' ) ) {
			self::backfill_listing_uids();
		}

		update_option( 'arshid6social_marketplace_db_version', self::MARKETPLACE_DB_VERSION );
	}

	/**
	 * Generates a uid for any existing listing that has an empty uid.
	 */
	private static function backfill_listing_uids(): void {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}arshid6social_listings WHERE uid = '' OR uid IS NULL"
		) ?: array();

		foreach ( $rows as $row ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"{$wpdb->prefix}arshid6social_listings",
				array( 'uid' => self::generate_uid() ),
				array( 'id'  => $row->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Generates a URL-safe unique identifier (12 lowercase hex chars).
	 *
	 * @return string e.g. "a3f9b2c1d4e5"
	 */
	public static function generate_uid(): string {
		return substr( bin2hex( random_bytes( 8 ) ), 0, 12 );
	}

	// ── Table creation ───────────────────────────────────────────────────────

	/**
	 * Creates all Marketplace tables via dbDelta (idempotent).
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = array();

		// ── Listings ─────────────────────────────────────────────────────────
		// price stored as DECIMAL(15,4) — avoids float errors, supports 0-4
		// decimal-place currencies; multiply/divide by currency_decimals in UI.
		// item_condition avoids collision with the MySQL CONDITION keyword.
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_listings (
			id               bigint(20) unsigned  NOT NULL AUTO_INCREMENT,
			uid              varchar(12)          NOT NULL DEFAULT '',
			seller_id        bigint(20) unsigned  NOT NULL,
			title            varchar(200)         NOT NULL DEFAULT '',
			description      longtext             NOT NULL,
			price            DECIMAL(15,4)        NOT NULL DEFAULT '0.0000',
			currency         varchar(10)          NOT NULL DEFAULT '',
			item_condition   varchar(20)          NOT NULL DEFAULT 'used',
			category_id      bigint(20) unsigned  NOT NULL DEFAULT 0,
			location_city    varchar(100)         NOT NULL DEFAULT '',
			location_country varchar(10)          NOT NULL DEFAULT '',
			lat              DECIMAL(10,7)                 DEFAULT NULL,
			lng              DECIMAL(10,7)                 DEFAULT NULL,
			status           varchar(20)          NOT NULL DEFAULT 'draft',
			is_negotiable    tinyint(1)           NOT NULL DEFAULT 0,
			is_free          tinyint(1)           NOT NULL DEFAULT 0,
			views            bigint(20) unsigned  NOT NULL DEFAULT 0,
			created_at       datetime             NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at       datetime             NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at       datetime                      DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uid (uid),
			KEY status_category (status, category_id),
			KEY seller_id (seller_id),
			KEY lat_lng (lat, lng),
			KEY expires_at (expires_at),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		// ── Listing media ─────────────────────────────────────────────────────
		// attachment_id links to WP Media Library; file_url/file_path are the
		// actual stored file so listings survive media library purges.
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_listing_media (
			id            bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id    bigint(20) unsigned NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			file_url      varchar(2083)       NOT NULL DEFAULT '',
			file_path     varchar(500)        NOT NULL DEFAULT '',
			is_primary    tinyint(1)          NOT NULL DEFAULT 0,
			sort_order    int(11)             NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY listing_id (listing_id),
			KEY is_primary (is_primary)
		) $charset_collate;";

		// ── Categories ────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_categories (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_id  bigint(20) unsigned NOT NULL DEFAULT 0,
			name       varchar(150)        NOT NULL DEFAULT '',
			slug       varchar(150)        NOT NULL DEFAULT '',
			icon       varchar(100)        NOT NULL DEFAULT '',
			sort_order int(11)             NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY parent_id (parent_id)
		) $charset_collate;";

		// ── Favorites ─────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_favorites (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id    bigint(20) unsigned NOT NULL,
			listing_id bigint(20) unsigned NOT NULL,
			created_at datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_listing (user_id, listing_id),
			KEY user_id (user_id),
			KEY listing_id (listing_id)
		) $charset_collate;";

		// ── Reviews ───────────────────────────────────────────────────────────
		// One review per buyer per listing; moderated before display.
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_reviews (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id bigint(20) unsigned NOT NULL,
			seller_id  bigint(20) unsigned NOT NULL,
			buyer_id   bigint(20) unsigned NOT NULL,
			rating     tinyint(1)          NOT NULL DEFAULT 5,
			comment    text                NOT NULL,
			status     varchar(20)         NOT NULL DEFAULT 'pending',
			created_at datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY listing_buyer (listing_id, buyer_id),
			KEY seller_id (seller_id),
			KEY buyer_id (buyer_id),
			KEY status (status)
		) $charset_collate;";

		// ── Offers ────────────────────────────────────────────────────────────
		// Optional haggling record; money never flows through the plugin.
		// message_thread_id links to wp_sn_messages_threads for context.
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_offers (
			id                bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			listing_id        bigint(20) unsigned NOT NULL,
			buyer_id          bigint(20) unsigned NOT NULL,
			amount            DECIMAL(15,4)       NOT NULL DEFAULT '0.0000',
			status            varchar(20)         NOT NULL DEFAULT 'pending',
			message_thread_id bigint(20) unsigned          DEFAULT NULL,
			note              text,
			created_at        datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY listing_id (listing_id),
			KEY buyer_id (buyer_id),
			KEY status (status)
		) $charset_collate;";

		// ── Saved searches ────────────────────────────────────────────────────
		// query_json stores the serialised filter state for re-running searches
		// and for the cron alert job to match new listings against.
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_saved_searches (
			id            bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id       bigint(20) unsigned NOT NULL,
			label         varchar(100)        NOT NULL DEFAULT '',
			query_json    longtext            NOT NULL,
			notify        tinyint(1)          NOT NULL DEFAULT 0,
			last_notified datetime                     DEFAULT NULL,
			created_at    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY notify (notify)
		) $charset_collate;";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	// ── Migrations on shared tables ──────────────────────────────────────────

	/**
	 * Adds a `type` column to wp_sn_messages_threads so marketplace
	 * conversations can be differentiated from regular DMs.
	 * Default value 'default' leaves all existing rows unaffected.
	 */
	private static function migrate_thread_type_column(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'sn_messages_threads';

		$has_col = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'type'",
			DB_NAME,
			$table
		) );

		if ( ! $has_col ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"ALTER TABLE `{$table}`
				 ADD COLUMN `type` varchar(30) NOT NULL DEFAULT 'default' AFTER `is_group`,
				 ADD KEY `type` (`type`)"
			);
		}
	}

	// ── Seeders ──────────────────────────────────────────────────────────────

	/**
	 * Seeds a set of built-in top-level categories if the table is empty.
	 */
	private static function seed_default_categories(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_categories';

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $count > 0 ) {
			return;
		}

		$categories = array(
			array( 'name' => 'Vehicles',     'slug' => 'vehicles',     'icon' => '🚗', 'sort_order' => 1 ),
			array( 'name' => 'Property',     'slug' => 'property',     'icon' => '🏠', 'sort_order' => 2 ),
			array( 'name' => 'Electronics',  'slug' => 'electronics',  'icon' => '📱', 'sort_order' => 3 ),
			array( 'name' => 'Home & Garden','slug' => 'home-garden',  'icon' => '🏡', 'sort_order' => 4 ),
			array( 'name' => 'Fashion',      'slug' => 'fashion',      'icon' => '👗', 'sort_order' => 5 ),
			array( 'name' => 'Jobs',         'slug' => 'jobs',         'icon' => '💼', 'sort_order' => 6 ),
			array( 'name' => 'Services',     'slug' => 'services',     'icon' => '🔧', 'sort_order' => 7 ),
			array( 'name' => 'Free Stuff',   'slug' => 'free',         'icon' => '🎁', 'sort_order' => 8 ),
			array( 'name' => 'Other',        'slug' => 'other',        'icon' => '📦', 'sort_order' => 9 ),
		);

		foreach ( $categories as $cat ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'parent_id'  => 0,
					'name'       => $cat['name'],
					'slug'       => $cat['slug'],
					'icon'       => $cat['icon'],
					'sort_order' => $cat['sort_order'],
				),
				array( '%d', '%s', '%s', '%s', '%d' )
			);
		}
	}

	/**
	 * Seeds default Marketplace options (skips any already set).
	 */
	private static function seed_default_options(): void {
		$defaults = array(
			'arshid6social_marketplace_enabled'            => false,
			'arshid6social_marketplace_slug'               => 'marketplace',
			'arshid6social_marketplace_currency_symbol'    => '$',
			'arshid6social_marketplace_currency_position'  => 'before',
			'arshid6social_marketplace_currency_decimals'  => 2,
			'arshid6social_marketplace_currency_thousands' => ',',
			'arshid6social_marketplace_max_photos'         => 10,
			'arshid6social_marketplace_max_photo_size_mb'  => 5,
			'arshid6social_marketplace_expiry_days'        => 30,
			'arshid6social_marketplace_moderation'         => 'auto',
			'arshid6social_marketplace_require_verified'   => false,
			'arshid6social_marketplace_auto_hide_threshold'=> 3,
			'arshid6social_marketplace_banned_words'       => '',
			'arshid6social_marketplace_allow_guests'       => true,
			'arshid6social_marketplace_max_active_listings'=> 20,
			'arshid6social_marketplace_daily_new_listings' => 5,
			'arshid6social_marketplace_safety_tips'        => __( 'Meet in a public place. Inspect the item before paying. Never share personal financial information.', '6arshid social community' ),
			'arshid6social_marketplace_prohibited_policy'  => __( 'The following items are prohibited: weapons, illegal substances, counterfeit goods, and any other items prohibited by local law.', '6arshid social community' ),
			'arshid6social_marketplace_as_homepage'        => false,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	// ── Uninstall cleanup ────────────────────────────────────────────────────

	/**
	 * Drops all Marketplace tables and removes options.
	 * Called by uninstall.php when 'delete data on uninstall' is enabled.
	 *
	 * Also removes the `type` column added to sn_messages_threads, restoring
	 * the core table to its original schema.
	 *
	 * @param bool $delete_data Whether to delete user data (from plugin setting).
	 */
	public static function cleanup( bool $delete_data = false ): void {
		if ( ! $delete_data ) {
			return;
		}

		global $wpdb;

		$marketplace_tables = array(
			$wpdb->prefix . 'arshid6social_listings',
			$wpdb->prefix . 'arshid6social_listing_media',
			$wpdb->prefix . 'arshid6social_categories',
			$wpdb->prefix . 'arshid6social_favorites',
			$wpdb->prefix . 'arshid6social_reviews',
			$wpdb->prefix . 'arshid6social_offers',
			$wpdb->prefix . 'arshid6social_saved_searches',
		);

		foreach ( $marketplace_tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// Remove the type column from the shared messages table.
		$threads_table = $wpdb->prefix . 'sn_messages_threads';
		$has_col       = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'type'",
			DB_NAME,
			$threads_table
		) );
		if ( $has_col ) {
			$wpdb->query( "ALTER TABLE `{$threads_table}` DROP COLUMN `type`, DROP KEY `type`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// Remove all marketplace options.
		$option_prefix = 'arshid6social_marketplace';
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $option_prefix ) . '%'
		) );
	}
}
