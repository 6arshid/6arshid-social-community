<?php
namespace Arshid6Social;

/**
 * Plugin activation handler.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 *
 * Runs on plugin activation: creates/updates database tables, seeds defaults,
 * registers custom capabilities, and flushes rewrite rules.
 */
class Activator {

	/**
	 * Runs on every page load: creates missing tables and seeds new options when
	 * the stored DB version is behind the current ARSHID6SOCIAL_DB_VERSION constant.
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( 'arshid6social_db_version', '0.0.0' );
		if ( version_compare( $stored, ARSHID6SOCIAL_DB_VERSION, '<' ) ) {
			// Must run FIRST: renames legacy wp_sn_* tables to wp_arshid6social_*
			// so every later migration and create_tables() acts on the new names.
			self::rename_legacy_tables();
			self::migrate_blocks_table();
			self::migrate_activity_uid_column();
			self::migrate_reports_attachment_column();
			self::migrate_groups_suspension_columns();
			self::migrate_group_images_to_protected_dir();
			self::purge_suspended_users_standard_attachments();
			self::migrate_messages_edit_columns();
			self::deduplicate_notifications();
			self::create_tables();
			self::seed_default_options();
			self::create_pages();
			self::protect_upload_directory();
			update_option( 'arshid6social_db_version', ARSHID6SOCIAL_DB_VERSION );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Remove duplicate notifications, keeping the most recent per
	 * (user_id, item_id, secondary_item_id, component_name, component_action).
	 */
	public static function deduplicate_notifications(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_notifications';

		// Keep the row with the highest id (most recent insert) for each unique group.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- interpolated identifiers are $wpdb->prefix table names / whitelisted clauses; values bound via prepare() placeholders.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE n FROM {$table} n
			 INNER JOIN {$table} keep_row
			   ON  keep_row.user_id           = n.user_id
			   AND keep_row.item_id           = n.item_id
			   AND keep_row.secondary_item_id = n.secondary_item_id
			   AND keep_row.component_name    = n.component_name
			   AND keep_row.component_action  = n.component_action
			   AND keep_row.id > n.id"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Returns the legacy → current table-name map (suffixes, without $wpdb->prefix).
	 *
	 * These are the tables that historically shipped with the 2-char `sn_`
	 * secondary prefix and are migrated to the distinctive `arshid6social_`
	 * prefix used everywhere else in the plugin.
	 *
	 * @return array<string, string> old suffix => new suffix.
	 */
	private static function legacy_table_map(): array {
		return array(
			'sn_activity'              => 'arshid6social_activity',
			'sn_activity_media'        => 'arshid6social_activity_media',
			'sn_activity_meta'         => 'arshid6social_activity_meta',
			'sn_activity_reactions'    => 'arshid6social_activity_reactions',
			'sn_ads'                   => 'arshid6social_ads',
			'sn_audit_log'             => 'arshid6social_audit_log',
			'sn_blocks'                => 'arshid6social_blocks',
			'sn_bookmark_collections'  => 'arshid6social_bookmark_collections',
			'sn_bookmarks'             => 'arshid6social_bookmarks',
			'sn_close_friends'         => 'arshid6social_close_friends',
			'sn_follow'                => 'arshid6social_follow',
			'sn_friends'               => 'arshid6social_friends',
			'sn_groups'                => 'arshid6social_groups',
			'sn_groups_groupmeta'      => 'arshid6social_groups_groupmeta',
			'sn_groups_members'        => 'arshid6social_groups_members',
			'sn_hashtag_follows'       => 'arshid6social_hashtag_follows',
			'sn_hashtag_relations'     => 'arshid6social_hashtag_relations',
			'sn_hashtags'              => 'arshid6social_hashtags',
			'sn_invitations'           => 'arshid6social_invitations',
			'sn_messages'              => 'arshid6social_messages',
			'sn_messages_hidden'       => 'arshid6social_messages_hidden',
			'sn_messages_meta'         => 'arshid6social_messages_meta',
			'sn_messages_recipients'   => 'arshid6social_messages_recipients',
			'sn_messages_threads'      => 'arshid6social_messages_threads',
			'sn_muted_stories'         => 'arshid6social_muted_stories',
			'sn_notifications'         => 'arshid6social_notifications',
			'sn_poll_options'          => 'arshid6social_poll_options',
			'sn_poll_votes'            => 'arshid6social_poll_votes',
			'sn_polls'                 => 'arshid6social_polls',
			'sn_post_tag_coords'       => 'arshid6social_post_tag_coords',
			'sn_post_tags'             => 'arshid6social_post_tags',
			'sn_reports'               => 'arshid6social_reports',
			'sn_shares'                => 'arshid6social_shares',
			'sn_sticky'                => 'arshid6social_sticky',
			'sn_stories'               => 'arshid6social_stories',
			'sn_story_highlights'      => 'arshid6social_story_highlights',
			'sn_story_items'           => 'arshid6social_story_items',
			'sn_story_reactions'       => 'arshid6social_story_reactions',
			'sn_story_views'           => 'arshid6social_story_views',
			'sn_verification_requests' => 'arshid6social_verification_requests',
			'sn_verifications'         => 'arshid6social_verifications',
			'sn_xprofile_data'         => 'arshid6social_xprofile_data',
			'sn_xprofile_fields'       => 'arshid6social_xprofile_fields',
			'sn_xprofile_groups'       => 'arshid6social_xprofile_groups',
		);
	}

	/**
	 * Renames legacy `{$wpdb->prefix}sn_*` tables to `{$wpdb->prefix}arshid6social_*`.
	 *
	 * Idempotent and safe to re-run: a table is only renamed when the old table
	 * exists AND the new table does not, so partially-migrated installs finish
	 * cleanly and fully-migrated / fresh installs are a no-op. Table identifiers
	 * cannot be bound as SQL parameters, so each name is built from a fixed
	 * in-code whitelist plus $wpdb->prefix — never from user input.
	 */
	public static function rename_legacy_tables(): void {
		global $wpdb;

		foreach ( self::legacy_table_map() as $old_suffix => $new_suffix ) {
			$old = $wpdb->prefix . $old_suffix;
			$new = $wpdb->prefix . $new_suffix;

			$old_exists = (int) $wpdb->get_var(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
					DB_NAME,
					$old
				)
			);
			if ( ! $old_exists ) {
				continue; // Nothing to migrate (fresh install or already renamed).
			}

			$new_exists = (int) $wpdb->get_var(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
					DB_NAME,
					$new
				)
			);
			if ( $new_exists ) {
				continue; // Target already present; do not clobber it.
			}

			// $old/$new are whitelisted identifiers built from $wpdb->prefix.
			$wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Adds the `attachment_url` column to arshid6social_reports if it does not exist yet.
	 */
	public static function migrate_reports_attachment_column(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_reports';

		$has_col = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'attachment_url'",
				DB_NAME,
				$table
			)
		);

		if ( ! $has_col ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `attachment_url` varchar(500) NOT NULL DEFAULT '' AFTER `notes`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Adds the `is_suspended` and `suspend_reason` columns to arshid6social_groups if they do not exist yet.
	 */
	public static function migrate_groups_suspension_columns(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_groups';

		$has_suspended = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'is_suspended'",
				DB_NAME,
				$table
			)
		);

		if ( ! $has_suspended ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `is_suspended` tinyint(1) NOT NULL DEFAULT 0, ADD COLUMN `suspend_reason` varchar(300) NOT NULL DEFAULT '', ADD KEY `is_suspended` (`is_suspended`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Adds is_edited/edited_at columns to arshid6social_messages and creates arshid6social_messages_hidden table.
	 */
	public static function migrate_messages_edit_columns(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_messages';

		$has_edited = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'is_edited'",
				DB_NAME,
				$table
			)
		);

		if ( ! $has_edited ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `is_edited` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_deleted`, ADD COLUMN `edited_at` datetime DEFAULT NULL AFTER `is_edited`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}

		$hidden_table    = $wpdb->prefix . 'arshid6social_messages_hidden';
		$charset_collate = $wpdb->get_charset_collate();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- interpolated identifiers are $wpdb->prefix table names / whitelisted clauses; values bound via prepare() placeholders.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"CREATE TABLE IF NOT EXISTS `{$hidden_table}` (
				id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				message_id bigint(20) unsigned NOT NULL,
				user_id    bigint(20) unsigned NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY message_user (message_id, user_id)
			) {$charset_collate}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix + $wpdb->get_charset_collate(), not user input.
		);
	}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	/**
	 * Adds the `uid` column to arshid6social_activity if it does not exist yet.
	 * Existing rows get a blank uid; they are backfilled lazily on first permalink access.
	 */
	public static function migrate_activity_uid_column(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_activity';

		$has_col = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'uid'",
				DB_NAME,
				$table
			)
		);

		if ( ! $has_col ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `uid` varchar(23) NOT NULL DEFAULT '' AFTER `privacy`, ADD KEY `uid` (`uid`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Migrates the arshid6social_blocks table from old column names (user_id/blocked_user_id)
	 * to new names (blocker_id/blocked_id) and adds reason column.
	 */
	public static function migrate_blocks_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'arshid6social_blocks';

		// Check whether old column name still exists.
		$has_old = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'user_id'",
				DB_NAME,
				$table
			)
		);

		if ( ! $has_old ) {
			return;
		}

		// Rename user_id → blocker_id, blocked_user_id → blocked_id.
		$wpdb->query( "ALTER TABLE `{$table}` CHANGE COLUMN `user_id` `blocker_id` bigint(20) unsigned NOT NULL, CHANGE COLUMN `blocked_user_id` `blocked_id` bigint(20) unsigned NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery

		// Add reason column if absent.
		$has_reason = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reason'",
				DB_NAME,
				$table
			)
		);
		if ( ! $has_reason ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `reason` text NULL AFTER `blocked_id`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}

		// Rebuild indexes.
		$has_old_idx = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'user_blocked'",
				DB_NAME,
				$table
			)
		);
		if ( $has_old_idx ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `user_blocked`, ADD UNIQUE KEY `blocker_blocked` (`blocker_id`, `blocked_id`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}
		$has_uid_idx = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'user_id'",
				DB_NAME,
				$table
			)
		);
		if ( $has_uid_idx ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `user_id`, ADD KEY `blocker_id` (`blocker_id`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}
		$has_buid_idx = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
			 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'blocked_user_id'",
				DB_NAME,
				$table
			)
		);
		if ( $has_buid_idx ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `blocked_user_id`, ADD KEY `blocked_id` (`blocked_id`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Entry point called by register_activation_hook().
	 */
	public static function activate(): void {
		self::check_requirements();
		// Rename any legacy wp_sn_* tables before create_tables() so an existing
		// site that deactivates/reactivates after upgrading keeps all its data
		// instead of getting a fresh empty set of wp_arshid6social_* tables.
		self::rename_legacy_tables();
		self::create_tables();
		self::seed_default_options();
		self::create_pages();
		self::add_capabilities();
		self::schedule_events();
		self::protect_upload_directory();

		// Flag for setup wizard redirect.
		if ( ! get_option( 'arshid6social_setup_complete' ) ) {
			set_transient( 'arshid6social_setup_redirect', true, 30 );
		}

		flush_rewrite_rules();

		/**
		 * Fires after the plugin has been fully activated.
		 */
		do_action( 'arshid6social_activated' );
	}

	/**
	 * Creates the required front-end pages if they don't already exist.
	 *
	 * Each page gets a shortcode as its content so the plugin can render it.
	 * Page IDs are stored in options so they can be deleted on uninstall.
	 */
	public static function create_pages(): void {
		$pages = array(
			'members'         => array(
				'title'     => __( 'Members', '6arshid-social-community' ),
				'slug'      => 'members',
				'shortcode' => '[arshid6social_members]',
				'option'    => 'arshid6social_page_members',
			),
			'activity'        => array(
				'title'     => __( 'Activity', '6arshid-social-community' ),
				'slug'      => 'activity',
				'shortcode' => '[arshid6social_activity]',
				'option'    => 'arshid6social_page_activity',
			),
			'groups'          => array(
				'title'     => __( 'Groups', '6arshid-social-community' ),
				'slug'      => 'groups',
				'shortcode' => '[arshid6social_groups]',
				'option'    => 'arshid6social_page_groups',
			),
			'messages'        => array(
				'title'     => __( 'Messages', '6arshid-social-community' ),
				'slug'      => 'messages',
				'shortcode' => '[arshid6social_messages]',
				'option'    => 'arshid6social_page_messages',
			),
			'notifications'   => array(
				'title'     => __( 'Notifications', '6arshid-social-community' ),
				'slug'      => 'notifications',
				'shortcode' => '[arshid6social_notifications]',
				'option'    => 'arshid6social_page_notifications',
			),
			'dashboard'       => array(
				'title'     => __( 'Dashboard', '6arshid-social-community' ),
				'slug'      => 'dashboard',
				'shortcode' => '[arshid6social_dashboard]',
				'option'    => 'arshid6social_page_dashboard',
			),
			'home'            => array(
				'title'     => __( 'Home', '6arshid-social-community' ),
				'slug'      => 'home',
				'shortcode' => '[arshid6social_home]',
				'option'    => 'arshid6social_page_home',
				'template'  => 'home-splash',
			),
		);

		foreach ( $pages as $key => $page ) {
			$existing_id = (int) get_option( $page['option'], 0 );

			// Skip if we already stored a valid page ID (but still enforce template for home).
			if ( $existing_id && 'publish' === get_post_status( $existing_id ) ) {
				if ( 'home' === $key ) {
					update_post_meta( $existing_id, '_wp_page_template', 'home-splash' );
					self::set_front_page( $existing_id );
				}
				continue;
			}

			// Also skip if a page with this slug already exists (created manually).
			$existing = get_page_by_path( $page['slug'] );
			if ( $existing && 'publish' === $existing->post_status ) {
				update_option( $page['option'], $existing->ID );
				if ( 'home' === $key ) {
					self::set_front_page( $existing->ID );
				}
				continue;
			}

			$insert_args = array(
				'post_title'     => $page['title'],
				'post_name'      => $page['slug'],
				'post_content'   => $page['shortcode'],
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			);

			if ( ! empty( $page['template'] ) ) {
				$insert_args['meta_input'] = array( '_wp_page_template' => $page['template'] );
			}

			$page_id = wp_insert_post( $insert_args );

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_option( $page['option'], $page_id );
				if ( 'home' === $key ) {
					self::set_front_page( $page_id );
				}
			}
		}
	}

	/**
	 * Sets the WordPress front page to the given page ID.
	 * Only changes the setting if it hasn't been customised by the site owner
	 * (i.e. still showing "latest posts" or pointing to a page that no longer exists).
	 *
	 * @param int $page_id
	 */
	private static function set_front_page( int $page_id ): void {
		$current_front = (int) get_option( 'page_on_front', 0 );
		$current_show  = (string) get_option( 'show_on_front', 'posts' );

		// Already pointing at a valid published page that isn't ours — respect it.
		if ( 'page' === $current_show && $current_front && $current_front !== $page_id
			&& 'publish' === get_post_status( $current_front ) ) {
			return;
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
	}

	/**
	 * Verifies PHP and WP version requirements before proceeding.
	 */
	private static function check_requirements(): void {
		global $wp_version;

		if ( version_compare( PHP_VERSION, ARSHID6SOCIAL_MIN_PHP, '<' ) ) {
			wp_die(
				sprintf(
					/* translators: %s: required PHP version */
					esc_html__( '6Arshid Social Community requires PHP %s or higher.', '6arshid-social-community' ),
					esc_html( ARSHID6SOCIAL_MIN_PHP )
				)
			);
		}

		if ( version_compare( $wp_version, ARSHID6SOCIAL_MIN_WP, '<' ) ) {
			wp_die(
				sprintf(
					/* translators: %s: required WordPress version */
					esc_html__( '6Arshid Social Community requires WordPress %s or higher.', '6arshid-social-community' ),
					esc_html( ARSHID6SOCIAL_MIN_WP )
				)
			);
		}
	}

	/**
	 * Creates all custom database tables using dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$tables = array();

		// ── xProfile field groups ───────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_xprofile_groups (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name            varchar(150)        NOT NULL DEFAULT '',
			description     longtext,
			group_order     int(11)             NOT NULL DEFAULT 0,
			can_delete      tinyint(1)          NOT NULL DEFAULT 1,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// ── xProfile fields ─────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_xprofile_fields (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id        bigint(20) unsigned NOT NULL,
			parent_id       bigint(20) unsigned NOT NULL DEFAULT 0,
			type            varchar(64)         NOT NULL DEFAULT 'textbox',
			name            varchar(150)        NOT NULL DEFAULT '',
			description     longtext,
			is_required     tinyint(1)          NOT NULL DEFAULT 0,
			is_default_option tinyint(1)        NOT NULL DEFAULT 0,
			field_order     int(11)             NOT NULL DEFAULT 0,
			option_order    int(11)             NOT NULL DEFAULT 0,
			can_delete      tinyint(1)          NOT NULL DEFAULT 1,
			visibility      varchar(32)         NOT NULL DEFAULT 'public',
			PRIMARY KEY  (id),
			KEY group_id (group_id)
		) $charset_collate;";

		// ── xProfile field data (user values) ───────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_xprofile_data (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			field_id        bigint(20) unsigned NOT NULL,
			user_id         bigint(20) unsigned NOT NULL,
			value           longtext            NOT NULL,
			last_updated    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY field_id_user_id (field_id, user_id),
			KEY user_id (user_id)
		) $charset_collate;";

		// ── Activity items ───────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_activity (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL DEFAULT 0,
			component       varchar(75)         NOT NULL,
			type            varchar(75)         NOT NULL,
			action          text                NOT NULL,
			content         longtext            NOT NULL,
			primary_link    varchar(2083)       NOT NULL DEFAULT '',
			item_id         bigint(20)          NOT NULL DEFAULT 0,
			secondary_item_id bigint(20)        NOT NULL DEFAULT 0,
			date_recorded   datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			hide_sitewide   tinyint(1)          NOT NULL DEFAULT 0,
			mptt_left       int(11)             NOT NULL DEFAULT 0,
			mptt_right      int(11)             NOT NULL DEFAULT 0,
			is_spam         tinyint(1)          NOT NULL DEFAULT 0,
			privacy         varchar(32)         NOT NULL DEFAULT 'public',
			uid             varchar(23)         NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY component (component),
			KEY type (type),
			KEY date_recorded (date_recorded),
			KEY item_id (item_id),
			KEY secondary_item_id (secondary_item_id),
			KEY hide_sitewide (hide_sitewide),
			KEY uid (uid)
		) $charset_collate;";

		// ── Activity meta ────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_activity_meta (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			activity_id     bigint(20) unsigned NOT NULL,
			meta_key        varchar(255)        DEFAULT NULL,
			meta_value      longtext,
			PRIMARY KEY  (id),
			KEY activity_id (activity_id),
			KEY meta_key (meta_key(191))
		) $charset_collate;";

		// ── Reactions / Likes ────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_activity_reactions (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			activity_id     bigint(20) unsigned NOT NULL,
			user_id         bigint(20) unsigned NOT NULL,
			reaction_type   varchar(32)         NOT NULL DEFAULT 'like',
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY activity_user (activity_id, user_id),
			KEY user_id (user_id)
		) $charset_collate;";

		// ── Groups ───────────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_groups (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			creator_id      bigint(20) unsigned NOT NULL,
			name            varchar(200)        NOT NULL,
			slug            varchar(200)        NOT NULL,
			description     longtext            NOT NULL,
			status          varchar(10)         NOT NULL DEFAULT 'public',
			is_suspended    tinyint(1)          NOT NULL DEFAULT 0,
			suspend_reason  varchar(300)        NOT NULL DEFAULT '',
			parent_id       bigint(20) unsigned NOT NULL DEFAULT 0,
			enable_forum    tinyint(1)          NOT NULL DEFAULT 0,
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY creator_id (creator_id),
			KEY status (status),
			KEY is_suspended (is_suspended)
		) $charset_collate;";

		// ── Group members ────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_groups_members (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id        bigint(20) unsigned NOT NULL,
			user_id         bigint(20) unsigned NOT NULL,
			inviter_id      bigint(20) unsigned NOT NULL DEFAULT 0,
			is_admin        tinyint(1)          NOT NULL DEFAULT 0,
			is_mod          tinyint(1)          NOT NULL DEFAULT 0,
			user_title      varchar(100)        NOT NULL DEFAULT '',
			date_modified   datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			comments        longtext,
			is_confirmed    tinyint(1)          NOT NULL DEFAULT 1,
			is_banned       tinyint(1)          NOT NULL DEFAULT 0,
			invite_sent     tinyint(1)          NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY group_user (group_id, user_id),
			KEY group_id (group_id),
			KEY user_id (user_id),
			KEY is_admin (is_admin),
			KEY is_mod (is_mod)
		) $charset_collate;";

		// ── Group meta ───────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_groups_groupmeta (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id        bigint(20) unsigned NOT NULL,
			meta_key        varchar(255)        DEFAULT NULL,
			meta_value      longtext,
			PRIMARY KEY  (id),
			KEY group_id (group_id),
			KEY meta_key (meta_key(191))
		) $charset_collate;";

		// ── Friends / connections ────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_friends (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			initiator_user_id bigint(20) unsigned NOT NULL,
			friend_user_id  bigint(20) unsigned NOT NULL,
			is_confirmed    tinyint(1)          NOT NULL DEFAULT 0,
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY initiator_friend (initiator_user_id, friend_user_id),
			KEY initiator_user_id (initiator_user_id),
			KEY friend_user_id (friend_user_id)
		) $charset_collate;";

		// ── Follow relationships ─────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_follow (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			follower_id     bigint(20) unsigned NOT NULL,
			followee_id     bigint(20) unsigned NOT NULL,
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY follower_followee (follower_id, followee_id),
			KEY follower_id (follower_id),
			KEY followee_id (followee_id)
		) $charset_collate;";

		// ── Blocks ───────────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_blocks (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blocker_id      bigint(20) unsigned NOT NULL,
			blocked_id      bigint(20) unsigned NOT NULL,
			reason          text,
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY blocker_blocked (blocker_id, blocked_id),
			KEY blocker_id (blocker_id),
			KEY blocked_id (blocked_id)
		) $charset_collate;";

		// ── Private messages (threads) ───────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_messages_threads (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uniqid          varchar(36)         NOT NULL DEFAULT '',
			subject         varchar(200)        NOT NULL DEFAULT '',
			is_group        tinyint(1)          NOT NULL DEFAULT 0,
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY uniqid (uniqid)
		) $charset_collate;";

		// ── Message recipients / inbox view ──────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_messages_recipients (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			thread_id       bigint(20) unsigned NOT NULL,
			user_id         bigint(20) unsigned NOT NULL,
			unread_count    int(11)             NOT NULL DEFAULT 0,
			sender_only     tinyint(1)          NOT NULL DEFAULT 0,
			is_deleted      tinyint(1)          NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY thread_user (thread_id, user_id),
			KEY user_id (user_id),
			KEY thread_id (thread_id)
		) $charset_collate;";

		// ── Messages ─────────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_messages (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			thread_id       bigint(20) unsigned NOT NULL,
			sender_id       bigint(20) unsigned NOT NULL,
			message         longtext            NOT NULL,
			date_sent       datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			is_deleted      tinyint(1)          NOT NULL DEFAULT 0,
			is_edited       tinyint(1)          NOT NULL DEFAULT 0,
			edited_at       datetime                     DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY thread_id (thread_id),
			KEY sender_id (sender_id),
			KEY date_sent (date_sent)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_messages_hidden (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			message_id bigint(20) unsigned NOT NULL,
			user_id    bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY message_user (message_id, user_id)
		) $charset_collate;";

		// ── Message meta ─────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_messages_meta (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			message_id      bigint(20) unsigned NOT NULL,
			meta_key        varchar(255)        DEFAULT NULL,
			meta_value      longtext,
			PRIMARY KEY  (id),
			KEY message_id (message_id),
			KEY meta_key (meta_key(191))
		) $charset_collate;";

		// ── Notifications ────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_notifications (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL,
			item_id         bigint(20) unsigned NOT NULL DEFAULT 0,
			secondary_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			component_name  varchar(75)         NOT NULL,
			component_action varchar(75)        NOT NULL,
			date_notified   datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			is_new          tinyint(1)          NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY is_new (is_new),
			KEY component_name (component_name),
			KEY component_action (component_action)
		) $charset_collate;";

		// ── Site invitations ─────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_invitations (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			inviter_id      bigint(20) unsigned NOT NULL,
			email           varchar(100)        NOT NULL DEFAULT '',
			invite_key      varchar(64)         NOT NULL DEFAULT '',
			status          varchar(20)         NOT NULL DEFAULT 'pending',
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			date_accepted   datetime                     DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY invite_key (invite_key),
			KEY inviter_id (inviter_id),
			KEY email (email),
			KEY status (status)
		) $charset_collate;";

		// ── Activity media attachments ───────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_activity_media (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			activity_id     bigint(20) unsigned NOT NULL,
			media_type      varchar(32)         NOT NULL DEFAULT 'image',
			file_url        varchar(2083)       NOT NULL DEFAULT '',
			file_path       varchar(500)        NOT NULL DEFAULT '',
			file_name       varchar(255)        NOT NULL DEFAULT '',
			file_size       bigint(20)          NOT NULL DEFAULT 0,
			mime_type       varchar(100)        NOT NULL DEFAULT '',
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY activity_id (activity_id),
			KEY media_type (media_type)
		) $charset_collate;";

		// ── Reports / moderation ─────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_reports (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reporter_id     bigint(20) unsigned NOT NULL,
			item_id         bigint(20) unsigned NOT NULL,
			item_type       varchar(50)         NOT NULL,
			reason          varchar(200)        NOT NULL DEFAULT '',
			notes           text,
			attachment_url  varchar(500)        NOT NULL DEFAULT '',
			status          varchar(20)         NOT NULL DEFAULT 'pending',
			date_reported   datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			date_resolved   datetime                     DEFAULT NULL,
			resolved_by     bigint(20) unsigned          DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY reporter_id (reporter_id),
			KEY item_id (item_id),
			KEY item_type (item_type),
			KEY status (status)
		) $charset_collate;";

		// ── Stories ──────────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_stories (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL,
			privacy         varchar(32)         NOT NULL DEFAULT 'public',
			close_friends   tinyint(1)          NOT NULL DEFAULT 0,
			created_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			highlight_id    bigint(20) unsigned          DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY expires_at (expires_at),
			KEY highlight_id (highlight_id)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_story_items (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_id        bigint(20) unsigned NOT NULL,
			media_type      varchar(32)         NOT NULL DEFAULT 'text',
			attachment_id   bigint(20) unsigned          DEFAULT NULL,
			file_url        varchar(2083)       NOT NULL DEFAULT '',
			file_path       varchar(500)        NOT NULL DEFAULT '',
			text_content    text,
			bg_color        varchar(32)         NOT NULL DEFAULT '#2563eb',
			overlays_json   longtext,
			sort_order      int(11)             NOT NULL DEFAULT 0,
			duration        int(11)             NOT NULL DEFAULT 5,
			PRIMARY KEY  (id),
			KEY story_id (story_id),
			KEY sort_order (sort_order)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_story_views (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_item_id   bigint(20) unsigned NOT NULL,
			viewer_id       bigint(20) unsigned NOT NULL,
			viewed_at       datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY item_viewer (story_item_id, viewer_id),
			KEY story_item_id (story_item_id),
			KEY viewer_id (viewer_id)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_story_reactions (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_item_id   bigint(20) unsigned NOT NULL,
			user_id         bigint(20) unsigned NOT NULL,
			reaction        varchar(32)         NOT NULL DEFAULT 'like',
			created_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY item_user (story_item_id, user_id),
			KEY story_item_id (story_item_id),
			KEY user_id (user_id)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_story_highlights (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL,
			title           varchar(150)        NOT NULL DEFAULT '',
			cover_url       varchar(2083)       NOT NULL DEFAULT '',
			created_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_close_friends (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL,
			friend_id       bigint(20) unsigned NOT NULL,
			created_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_friend (user_id, friend_id),
			KEY user_id (user_id)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_muted_stories (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL,
			muted_user_id   bigint(20) unsigned NOT NULL,
			created_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY user_muted (user_id, muted_user_id),
			KEY user_id (user_id)
		) $charset_collate;";

		// ── Verification ─────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_verification_requests (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL,
			type            varchar(64)         NOT NULL DEFAULT '',
			fields_json     longtext,
			document_paths  longtext,
			status          varchar(20)         NOT NULL DEFAULT 'pending',
			reviewer_id     bigint(20) unsigned          DEFAULT NULL,
			reason          text,
			created_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			decided_at      datetime                     DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_verifications (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL,
			type            varchar(64)         NOT NULL DEFAULT 'general',
			badge           varchar(32)         NOT NULL DEFAULT '✓',
			granted_by      bigint(20) unsigned NOT NULL DEFAULT 0,
			granted_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at      datetime                     DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			KEY type (type),
			KEY expires_at (expires_at)
		) $charset_collate;";

		// ── Ads ──────────────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_ads (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title           varchar(255)        NOT NULL DEFAULT '',
			ad_type         varchar(20)         NOT NULL DEFAULT 'image',
			file_url        varchar(2083)       NOT NULL DEFAULT '',
			click_url       varchar(2083)       NOT NULL DEFAULT '',
			js_code         longtext            NOT NULL,
			placement       varchar(20)         NOT NULL DEFAULT 'both',
			every_n_posts   int(10) unsigned    NOT NULL DEFAULT 5,
			impressions     bigint(20) unsigned NOT NULL DEFAULT 0,
			clicks          bigint(20) unsigned NOT NULL DEFAULT 0,
			status          varchar(10)         NOT NULL DEFAULT 'active',
			start_date      date                         DEFAULT NULL,
			end_date        date                         DEFAULT NULL,
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY placement (placement)
		) $charset_collate;";

		// ── Audit log ────────────────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_audit_log (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id         bigint(20) unsigned NOT NULL DEFAULT 0,
			action          varchar(100)        NOT NULL,
			object_type     varchar(50)         NOT NULL DEFAULT '',
			object_id       bigint(20) unsigned NOT NULL DEFAULT 0,
			details         longtext,
			ip_address      varchar(45)         NOT NULL DEFAULT '',
			date_created    datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY date_created (date_created)
		) $charset_collate;";

		// ── Social Embed cache ────────────────────────────────────────────────
		$tables[] = "CREATE TABLE {$wpdb->prefix}arshid6social_embed_cache (
			id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url_hash        char(64)            NOT NULL DEFAULT '',
			provider        varchar(50)         NOT NULL DEFAULT '',
			html            longtext            NOT NULL,
			data_json       longtext            NOT NULL,
			fetched_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at      datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY expires_at (expires_at)
		) $charset_collate;";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		// Seed the mandatory 'Base' xProfile group if not already present.
		self::seed_base_xprofile_group();

		update_option( 'arshid6social_db_version', ARSHID6SOCIAL_DB_VERSION );
	}

	/**
	 * Creates the default 'Base' xProfile field group and its mandatory 'Name' field.
	 */
	private static function seed_base_xprofile_group(): void {
		global $wpdb;

		$group_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}arshid6social_xprofile_groups WHERE id = %d", 1 )
		);

		if ( $group_exists ) {
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_xprofile_groups',
			array(
				'name'        => 'Base',
				'description' => '',
				'group_order' => 1,
				'can_delete'  => 0,
			),
			array( '%s', '%s', '%d', '%d' )
		);
		$group_id = (int) $wpdb->insert_id;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_xprofile_fields',
			array(
				'group_id'    => $group_id,
				'parent_id'   => 0,
				'type'        => 'textbox',
				'name'        => 'Name',
				'description' => '',
				'is_required' => 1,
				'field_order' => 1,
				'can_delete'  => 0,
				'visibility'  => 'public',
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_xprofile_fields',
			array(
				'group_id'    => $group_id,
				'parent_id'   => 0,
				'type'        => 'textarea',
				'name'        => 'bio',
				'description' => '',
				'is_required' => 0,
				'field_order' => 2,
				'can_delete'  => 1,
				'visibility'  => 'public',
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Saves default plugin options (won't override existing values).
	 */
	private static function seed_default_options(): void {
		$defaults = array(
			'arshid6social_enabled_components'             => array( 'members', 'activity', 'groups', 'friends', 'messages', 'notifications', 'moderation' ),
			'arshid6social_members_per_page'               => 20,
			'arshid6social_members_pagination_type'        => 'pagination',
			'arshid6social_members_show_friend_count'      => false,
			'arshid6social_activity_per_page'              => 20,
			'arshid6social_groups_per_page'                => 20,
			'arshid6social_messages_per_page'              => 20,
			'arshid6social_profile_photo_size'             => 150,
			'arshid6social_cover_photo_width'              => 1200,
			'arshid6social_cover_photo_height'             => 350,
			'arshid6social_enable_akismet'                 => true,
			'arshid6social_enable_recaptcha'               => false,
			'arshid6social_recaptcha_site_key'             => '',
			'arshid6social_recaptcha_secret_key'           => '',
			'arshid6social_new_member_moderation'          => false,
			'arshid6social_auto_suspend_threshold'         => 5,
			'arshid6social_banned_words'                   => '',
			'arshid6social_email_notifications'            => true,
			'arshid6social_email_digest'                   => 'daily',
			'arshid6social_rate_limit_posts'               => 10,
			'arshid6social_rate_limit_messages'            => 20,
			'arshid6social_rate_limit_friends'             => 50,
			'arshid6social_max_upload_size_mb'             => 5,
			'arshid6social_allowed_upload_types'           => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
			'arshid6social_invitation_limit'               => 20,
			'arshid6social_activity_allow_comments'        => true,
			'arshid6social_activity_allow_media'           => false,
			'arshid6social_activity_allowed_media_types'   => array( 'image' ),
			'arshid6social_activity_pagination_type'       => 'infinite_scroll',
			'arshid6social_dark_mode'                      => 'off',
			'arshid6social_primary_color'                  => '#2563eb',
			'arshid6social_date_format'                    => 'relative',
			'arshid6social_setup_complete'                 => false,
			// Stories defaults — all off until explicitly enabled.
			'arshid6social_stories_enabled'                => false,
			'arshid6social_stories_bottom_bar'             => false,
			'arshid6social_stories_bottom_bar_marketplace' => false,
			'arshid6social_stories_bottom_bar_messages'    => false,
			'arshid6social_stories_expiry_hours'           => 24,
			'arshid6social_stories_max_video_secs'         => 30,
			'arshid6social_stories_allow_video'            => true,
			'arshid6social_stories_highlights'             => true,
			'arshid6social_stories_rate_limit'             => 20,
			// Block system defaults.
			'arshid6social_blocking_enabled'               => true,
			'arshid6social_blocking_show_reason'           => true,
			// Verification defaults.
			'arshid6social_verification_enabled'           => false,
			'arshid6social_verification_types'             => array(
				array(
					'key'   => 'general',
					'label' => 'Verified',
					'badge' => '✓',
					'color' => '#2563eb',
				),
				array(
					'key'   => 'business',
					'label' => 'Business',
					'badge' => '🏢',
					'color' => '#d97706',
				),
				array(
					'key'   => 'notable',
					'label' => 'Notable',
					'badge' => '⭐',
					'color' => '#7c3aed',
				),
				array(
					'key'   => 'staff',
					'label' => 'Staff',
					'badge' => '🛡️',
					'color' => '#dc2626',
				),
			),
			'arshid6social_verification_require_doc'       => false,
			'arshid6social_verification_expiry_months'     => 0,
			'arshid6social_verification_doc_purge'         => true,
			'arshid6social_verification_rate_limit'        => 3,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Grants social-network-specific capabilities to the administrator role.
	 */
	private static function add_capabilities(): void {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}

		$caps = array(
			'arshid6social_manage_members',
			'arshid6social_manage_groups',
			'arshid6social_manage_activity',
			'arshid6social_manage_messages',
			'arshid6social_manage_reports',
			'arshid6social_manage_settings',
			'arshid6social_view_audit_log',
		);

		foreach ( $caps as $cap ) {
			$admin->add_cap( $cap );
		}

		// Give moderators report management.
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( 'arshid6social_manage_reports' );
		}
	}

	/**
	 * Schedules recurring WP-Cron events.
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'arshid6social_daily_digest' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 08:00:00' ), 'daily', 'arshid6social_daily_digest' );
		}

		if ( ! wp_next_scheduled( 'arshid6social_weekly_digest' ) ) {
			wp_schedule_event( strtotime( 'next monday 08:00:00' ), 'weekly', 'arshid6social_weekly_digest' );
		}

		if ( ! wp_next_scheduled( 'arshid6social_cleanup_transients' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'arshid6social_cleanup_transients' );
		}

		if ( ! wp_next_scheduled( 'arshid6social_expire_stories' ) ) {
			wp_schedule_event( time(), 'hourly', 'arshid6social_expire_stories' );
		}

		if ( ! wp_next_scheduled( 'arshid6social_expire_verifications' ) ) {
			wp_schedule_event( time(), 'daily', 'arshid6social_expire_verifications' );
		}
	}

	/**
	 * One-time migration: deletes WP Media Library attachments in uploads/YYYY/MM/
	 * for every currently-suspended user so their direct URLs become 404.
	 *
	 * Uses raw SQL + unlink() so it is safe to run during plugins_loaded before
	 * WordPress post types are registered (get_posts / wp_delete_attachment both
	 * require the 'attachment' post type to be registered, which only happens on init).
	 */
	public static function purge_suspended_users_standard_attachments(): void {
		global $wpdb;

		$upload_dir  = wp_upload_dir();
		$upload_base = $upload_dir['basedir'];

		// Single query: attachments owned by suspended users that are NOT in social-network/.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT p.ID, pm_file.meta_value AS rel_path
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm_file
			      ON pm_file.post_id = p.ID AND pm_file.meta_key = '_wp_attached_file'
			 JOIN {$wpdb->usermeta} um
			      ON um.user_id = p.post_author
			      AND um.meta_key = 'arshid6social_suspended' AND um.meta_value = '1'
			 WHERE p.post_type = 'attachment'
			   AND pm_file.meta_value NOT LIKE 'social-network/%'"
		);

		if ( ! $rows ) {
			return;
		}

		foreach ( $rows as $row ) {
			self::wipe_attachment_files_and_record( (int) $row->ID, (string) $row->rel_path, $upload_base );
		}
	}

	/**
	 * Deletes a WP Media Library attachment's physical file (+ thumbnails) and
	 * removes its post / postmeta rows without relying on wp_delete_attachment().
	 *
	 * @param int    $att_id      Attachment post ID.
	 * @param string $rel_path    Value of _wp_attached_file (relative to uploads basedir).
	 * @param string $upload_base Absolute path to the uploads base directory.
	 */
	private static function wipe_attachment_files_and_record( int $att_id, string $rel_path, string $upload_base ): void {
		global $wpdb;

		$abs_path = $upload_base . '/' . ltrim( $rel_path, '/' );

		// Delete the main file.
		if ( file_exists( $abs_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $abs_path );
		}

		// Delete generated thumbnail files stored in _wp_attachment_metadata.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$meta_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta}
			 WHERE post_id = %d AND meta_key = '_wp_attachment_metadata' LIMIT 1",
				$att_id
			)
		);

		if ( $meta_raw ) {
			$meta = maybe_unserialize( $meta_raw );
			if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
				$dir = dirname( $abs_path );
				foreach ( $meta['sizes'] as $size_data ) {
					if ( ! empty( $size_data['file'] ) ) {
						$thumb = $dir . '/' . basename( $size_data['file'] );
						if ( file_exists( $thumb ) ) {
							// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
							@unlink( $thumb );
						}
					}
				}
			}
		}

		// Remove the post record and all its meta from the database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->posts, array( 'ID' => $att_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $att_id ), array( '%d' ) );
	}

	/**
	 * Moves legacy group avatar/cover images from the standard WP uploads path
	 * (uploads/YYYY/MM/) into social-network/groups/{id}/{slot}/ so that the
	 * .htaccess suspension rules cover them going forward.
	 */
	public static function migrate_group_images_to_protected_dir(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$group_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}arshid6social_groups ORDER BY id" );
		if ( ! $group_ids ) {
			return;
		}

		$upload_dir  = wp_upload_dir();
		$base_url    = $upload_dir['baseurl'];    // no trailing slash
		$base_dir    = $upload_dir['basedir'];
		$sn_fragment = 'social-network/groups/'; // already-migrated files contain this

		$url_path_prefix = wp_parse_url( $base_url, PHP_URL_PATH ); // e.g. /wp-content/uploads

		foreach ( $group_ids as $raw_id ) {
			$group_id = (int) $raw_id;

			foreach ( array( 'avatar', 'cover' ) as $slot ) {
				$opt_url  = "arshid6social_group_{$slot}_{$group_id}";
				$opt_path = "arshid6social_group_{$slot}_path_{$group_id}";

				$current_url = (string) get_option( $opt_url, '' );

				// Nothing stored or already in the protected directory.
				if ( ! $current_url || false !== strpos( $current_url, $sn_fragment ) ) {
					continue;
				}

				// Build absolute disk path from the stored URL.
				$file_url_path = wp_parse_url( $current_url, PHP_URL_PATH );
				if ( ! $file_url_path ) {
					continue;
				}
				$rel_path = ltrim( str_replace( $url_path_prefix, '', $file_url_path ), '/' );
				$old_path = $base_dir . '/' . $rel_path;

				if ( ! file_exists( $old_path ) ) {
					continue;
				}

				// Prepare destination inside social-network/groups/.
				$dest_dir = $base_dir . "/social-network/groups/{$group_id}/{$slot}";
				if ( ! wp_mkdir_p( $dest_dir ) ) {
					continue;
				}

				$filename  = basename( $old_path );
				$dest_path = $dest_dir . '/' . $filename;
				$dest_url  = $base_url . "/social-network/groups/{$group_id}/{$slot}/{$filename}";

				if ( copy( $old_path, $dest_path ) ) {
					update_option( $opt_url, $dest_url, false );
					update_option( $opt_path, $dest_path, false );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $old_path );
				}
			}
		}
	}

	/**
	 * Creates a .htaccess in the plugin's upload directory AND injects rules into
	 * the root .htaccess so that all social-network file requests are routed through
	 * admin-ajax.php for suspension access control.
	 */
	private static function protect_upload_directory(): void {
		$upload_dir = wp_upload_dir();
		$sn_dir     = trailingslashit( $upload_dir['basedir'] ) . 'social-network/';

		if ( ! is_dir( $sn_dir ) ) {
			wp_mkdir_p( $sn_dir );
		}

		// Subdirectory .htaccess — fallback for servers where root .htaccess isn't writable.
		// Build the admin-ajax path dynamically so it works for sub-directory installs.
		$ajax_url_path = wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH );
		$subdir_rules  = array(
			'Options -Indexes',
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_FILENAME} -f',
			'RewriteRule ^(.+)$ ' . $ajax_url_path . '?action=arshid6social_serve_file&arshid6social_uri=%{REQUEST_URI} [QSA,L]',
			'</IfModule>',
		);
		file_put_contents( $sn_dir . '.htaccess', implode( "\n", $subdir_rules ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// Root .htaccess — injects rules AFTER the WordPress block so the redirect
		// catches existing social-network files before Apache serves them directly.
		self::inject_root_htaccess_rules();
	}

	/**
	 * Uses WordPress's insert_with_markers() to add rewrite rules in the root
	 * .htaccess that route all social-network uploads through admin-ajax.php.
	 * Safe to call on every upgrade — markers keep the block idempotent.
	 */
	private static function inject_root_htaccess_rules(): void {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$htaccess = get_home_path() . '.htaccess';
		if ( ! file_exists( $htaccess ) && ! wp_is_writable( dirname( $htaccess ) ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		// e.g. /wp-content/uploads/social-network (works for sub-directory installs too).
		$sn_path = wp_parse_url( $upload_dir['baseurl'] . '/social-network', PHP_URL_PATH );

		// Build admin-ajax URL path relative to server root dynamically.
		$ajax_path = wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH );

		$rules = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_URI} ^' . $sn_path . '/',
			'RewriteCond %{REQUEST_FILENAME} -f',
			'RewriteRule ^(.*)$ ' . $ajax_path . '?action=arshid6social_serve_file&arshid6social_uri=%{REQUEST_URI} [QSA,L]',
			'</IfModule>',
		);

		insert_with_markers( $htaccess, '6arshid-social-community', $rules );
	}
}
