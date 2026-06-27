<?php
namespace Arshid6Social\Engagement;

/**
 * Engagement Pack – database migrations.
 *
 * @package Arshid6Social\Engagement
 */

defined( 'ABSPATH' ) || exit;

class Engagement_DB {

	const DB_VERSION_OPTION = 'arshid6social_engagement_db_version';
	const DB_VERSION        = '1.0.4';

	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION, '0.0.0' ) !== self::DB_VERSION ) {
			self::create_tables();
			self::create_pages();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	public static function create_pages(): void {
		$page_id = (int) get_option( 'arshid6social_page_saved_posts', 0 );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return;
		}

		$existing = get_page_by_path( 'saved-posts' );
		if ( $existing && 'publish' === $existing->post_status ) {
			update_option( 'arshid6social_page_saved_posts', $existing->ID );
			return;
		}

		$new_id = wp_insert_post( array(
			'post_title'     => __( 'Saved Posts', '6arshid social community' ),
			'post_name'      => 'saved-posts',
			'post_content'   => '[arshid6social_bookmarks]',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );

		if ( $new_id && ! is_wp_error( $new_id ) ) {
			update_option( 'arshid6social_page_saved_posts', $new_id );
		}
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_polls (
			id                   BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			activity_id          BIGINT UNSIGNED  NOT NULL DEFAULT 0,
			user_id              BIGINT UNSIGNED  NOT NULL,
			question             TEXT             NOT NULL,
			poll_type            VARCHAR(20)      NOT NULL DEFAULT 'single',
			anonymous            TINYINT(1)       NOT NULL DEFAULT 0,
			allow_change_vote    TINYINT(1)       NOT NULL DEFAULT 1,
			results_visibility   VARCHAR(20)      NOT NULL DEFAULT 'always',
			allow_voter_suggest  TINYINT(1)       NOT NULL DEFAULT 0,
			end_date             DATETIME                  DEFAULT NULL,
			start_date           DATETIME                  DEFAULT NULL,
			status               VARCHAR(20)      NOT NULL DEFAULT 'open',
			created_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY activity_id (activity_id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_poll_options (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			poll_id      BIGINT UNSIGNED NOT NULL,
			option_text  VARCHAR(500)    NOT NULL,
			option_image VARCHAR(500)             DEFAULT NULL,
			option_image_path VARCHAR(500)        DEFAULT NULL,
			is_correct   TINYINT(1)      NOT NULL DEFAULT 0,
			sort_order   SMALLINT        NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY poll_id (poll_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_poll_votes (
			id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			poll_id   BIGINT UNSIGNED NOT NULL,
			option_id BIGINT UNSIGNED NOT NULL,
			user_id   BIGINT UNSIGNED NOT NULL,
			rank      SMALLINT        NOT NULL DEFAULT 1,
			voted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY actor_poll_option (poll_id, option_id, user_id),
			KEY poll_id (poll_id),
			KEY user_id (user_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_hashtags (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			hashtag    VARCHAR(191)    NOT NULL,
			slug       VARCHAR(191)    NOT NULL,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_hashtag_relations (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			hashtag_id  BIGINT UNSIGNED NOT NULL,
			object_id   BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(50)     NOT NULL DEFAULT 'activity',
			created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY hashtag_id (hashtag_id),
			KEY object_id_type (object_id, object_type)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_hashtag_follows (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			hashtag_id BIGINT UNSIGNED NOT NULL,
			user_id    BIGINT UNSIGNED NOT NULL,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY hashtag_user (hashtag_id, user_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_bookmarks (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id       BIGINT UNSIGNED NOT NULL,
			object_id     BIGINT UNSIGNED NOT NULL,
			object_type   VARCHAR(50)     NOT NULL DEFAULT 'activity',
			collection_id BIGINT UNSIGNED          DEFAULT NULL,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_object (user_id, object_id, object_type),
			KEY user_id (user_id),
			KEY collection_id (collection_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_bookmark_collections (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id    BIGINT UNSIGNED NOT NULL,
			name       VARCHAR(191)    NOT NULL,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_shares (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id     BIGINT UNSIGNED NOT NULL,
			original_id BIGINT UNSIGNED NOT NULL,
			root_id     BIGINT UNSIGNED NOT NULL,
			target_type VARCHAR(30)     NOT NULL DEFAULT 'profile',
			target_id   BIGINT UNSIGNED          DEFAULT NULL,
			comment     TEXT                     DEFAULT NULL,
			created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY original_id (original_id),
			KEY root_id (root_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_post_tags (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id      BIGINT UNSIGNED NOT NULL,
			object_type    VARCHAR(50)     NOT NULL DEFAULT 'activity',
			tagged_user_id BIGINT UNSIGNED NOT NULL,
			tagger_id      BIGINT UNSIGNED NOT NULL,
			status         VARCHAR(20)     NOT NULL DEFAULT 'approved',
			created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY object_id (object_id, object_type),
			KEY tagged_user_id (tagged_user_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_post_tag_coords (
			id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tag_id    BIGINT UNSIGNED NOT NULL,
			x_percent DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
			y_percent DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
			PRIMARY KEY  (id),
			KEY tag_id (tag_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}sn_sticky (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id   BIGINT UNSIGNED NOT NULL,
			object_type VARCHAR(50)     NOT NULL DEFAULT 'activity',
			scope       VARCHAR(20)     NOT NULL DEFAULT 'profile',
			scope_id    BIGINT UNSIGNED          DEFAULT NULL,
			expires_at  DATETIME                 DEFAULT NULL,
			created_by  BIGINT UNSIGNED NOT NULL,
			created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY object_id (object_id),
			KEY scope (scope, scope_id)
		) $charset;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}arshid6social_attachments (
			id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			parent_id         BIGINT UNSIGNED NOT NULL,
			parent_type       VARCHAR(20)     NOT NULL DEFAULT 'comment',
			file_url          VARCHAR(500)    NOT NULL,
			file_path         VARCHAR(500)    NOT NULL DEFAULT '',
			file_name         VARCHAR(255)    NOT NULL,
			file_size         BIGINT UNSIGNED NOT NULL DEFAULT 0,
			mime_type         VARCHAR(127)    NOT NULL,
			media_type        VARCHAR(20)     NOT NULL DEFAULT 'file',
			uploader_id       BIGINT UNSIGNED NOT NULL,
			wp_attachment_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY parent (parent_id, parent_type),
			KEY uploader_id (uploader_id)
		) $charset;" );
	}

	public static function drop_all(): void {
		global $wpdb;
		$tables = array(
			'sn_polls', 'sn_poll_options', 'sn_poll_votes',
			'sn_hashtags', 'sn_hashtag_relations', 'sn_hashtag_follows',
			'sn_bookmarks', 'sn_bookmark_collections',
			'sn_shares',
			'sn_post_tags', 'sn_post_tag_coords',
			'sn_sticky',
			'arshid6social_attachments',
		);
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB
		}
		delete_option( self::DB_VERSION_OPTION );
	}
}
