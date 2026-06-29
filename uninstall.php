<?php
/**
 * Plugin uninstall handler.
 *
 * Runs when the plugin is deleted via WP Admin → Plugins → Delete.
 * Removes ALL plugin data: tables, options, pages, user meta, cron jobs.
 * Uploads are imported into the WordPress Media Library before the
 * plugin-specific directory is removed.
 *
 * @package Arshid6Social
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── Drop core tables ───────────────────────────────────────────────────────
$core_tables = array(
	'sn_xprofile_groups',
	'sn_xprofile_fields',
	'sn_xprofile_data',
	'sn_activity',
	'sn_activity_meta',
	'sn_activity_reactions',
	'sn_activity_media',
	'sn_groups',
	'sn_groups_members',
	'sn_groups_groupmeta',
	'sn_friends',
	'sn_follow',
	'sn_blocks',
	'sn_messages_threads',
	'sn_messages_recipients',
	'sn_messages',
	'sn_messages_meta',
	'sn_notifications',
	'sn_invitations',
	'sn_reports',
	'sn_stories',
	'sn_story_items',
	'sn_story_views',
	'sn_story_reactions',
	'sn_story_highlights',
	'sn_close_friends',
	'sn_muted_stories',
	'sn_verification_requests',
	'sn_verifications',
	'sn_audit_log',
);

// ── Drop engagement pack tables ────────────────────────────────────────────
$engagement_tables = array(
	'sn_polls',
	'sn_poll_options',
	'sn_poll_votes',
	'sn_hashtags',
	'sn_hashtag_relations',
	'sn_hashtag_follows',
	'sn_bookmarks',
	'sn_bookmark_collections',
	'sn_shares',
	'sn_post_tags',
	'sn_post_tag_coords',
	'sn_sticky',
	'arshid6social_attachments',
);

foreach ( array_merge( $core_tables, $engagement_tables ) as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB
}

// ── Delete auto-created pages ──────────────────────────────────────────────
$page_options = array(
	'arshid6social_page_members',
	'arshid6social_page_activity',
	'arshid6social_page_groups',
	'arshid6social_page_messages',
	'arshid6social_page_notifications',
	'arshid6social_page_register',
	'arshid6social_page_login',
	'arshid6social_page_forgot_password',
	'arshid6social_page_reset_password',
	'arshid6social_page_dashboard',
	'arshid6social_page_saved_posts',
);

foreach ( $page_options as $page_option ) {
	$page_id = (int) get_option( $page_option, 0 );
	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}
}

// ── Delete all plugin options ──────────────────────────────────────────────
$options = array(
	// Core
	'arshid6social_version',
	'arshid6social_db_version',
	'arshid6social_setup_complete',
	'arshid6social_rewrite_version',
	'arshid6social_enabled_components',
	'arshid6social_members_per_page',
	'arshid6social_members_pagination_type',
	'arshid6social_members_show_friend_count',
	'arshid6social_activity_per_page',
	'arshid6social_groups_per_page',
	'arshid6social_messages_per_page',
	'arshid6social_allow_registration',
	'arshid6social_profile_photo_size',
	'arshid6social_cover_photo_width',
	'arshid6social_cover_photo_height',
	'arshid6social_enable_akismet',
	'arshid6social_enable_recaptcha',
	'arshid6social_recaptcha_site_key',
	'arshid6social_recaptcha_secret_key',
	'arshid6social_new_member_moderation',
	'arshid6social_auto_suspend_threshold',
	'arshid6social_banned_words',
	'arshid6social_email_notifications',
	'arshid6social_email_digest',
	'arshid6social_rate_limit_posts',
	'arshid6social_rate_limit_messages',
	'arshid6social_rate_limit_friends',
	'arshid6social_max_upload_size_mb',
	'arshid6social_allowed_upload_types',
	'arshid6social_invitation_limit',
	'arshid6social_activity_allow_comments',
	'arshid6social_activity_allow_media',
	'arshid6social_activity_allowed_media_types',
	'arshid6social_activity_pagination_type',
	'arshid6social_dark_mode',
	'arshid6social_primary_color',
	'arshid6social_date_format',
	// Stories
	'arshid6social_stories_enabled',
	'arshid6social_stories_expiry_hours',
	'arshid6social_stories_max_video_secs',
	'arshid6social_stories_allow_video',
	'arshid6social_stories_highlights',
	'arshid6social_stories_rate_limit',
	// Activity stats bar
	'arshid6social_activity_stats_bar',
	// Blocking
	'arshid6social_blocking_enabled',
	'arshid6social_blocking_show_reason',
	// Verification
	'arshid6social_verification_enabled',
	'arshid6social_verification_types',
	'arshid6social_verification_require_doc',
	'arshid6social_verification_expiry_months',
	'arshid6social_verification_doc_purge',
	'arshid6social_verification_rate_limit',
	// Engagement pack feature toggles
	'arshid6social_eng_hashtags',
	'arshid6social_eng_tag_friends',
	'arshid6social_eng_bookmarks',
	'arshid6social_eng_sticky_posts',
	'arshid6social_eng_share_posts',
	'arshid6social_eng_polls',
	'arshid6social_eng_advanced_polls',
	'arshid6social_eng_comments_gifs',
	'arshid6social_eng_comments_attachments',
	'arshid6social_eng_messages_attachments',
	// Engagement pack settings
	'arshid6social_eng_polls_max_options',
	'arshid6social_eng_polls_allow_voter_suggest',
	'arshid6social_eng_hashtag_banned',
	'arshid6social_eng_tag_photo_tags',
	'arshid6social_eng_tag_privacy',
	'arshid6social_eng_tag_review',
	'arshid6social_eng_bookmark_collections',
	'arshid6social_eng_share_external',
	'arshid6social_eng_sticky_roles',
	'arshid6social_eng_gif_provider',
	'arshid6social_eng_gif_api_key',
	'arshid6social_eng_att_max_size_mb',
	'arshid6social_eng_att_allowed_types',
	'arshid6social_engagement_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// ── Delete all ARSHID6SOCIAL_* options via wildcard (catches any we missed) ─────────
$wpdb->query( // phpcs:ignore WordPress.DB
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ARSHID6SOCIAL\_%'"
);

// ── Delete user meta ───────────────────────────────────────────────────────
$meta_prefixes = array( 'ARSHID6SOCIAL_', 'sn_' );
foreach ( $meta_prefixes as $prefix ) {
	$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( $prefix ) . '%'
	) );
}

// ── Remove custom capabilities ─────────────────────────────────────────────
$caps = array(
	'arshid6social_manage_members',
	'arshid6social_manage_groups',
	'arshid6social_manage_activity',
	'arshid6social_manage_messages',
	'arshid6social_manage_reports',
	'arshid6social_manage_settings',
	'arshid6social_view_audit_log',
);

foreach ( array( get_role( 'administrator' ), get_role( 'editor' ) ) as $role ) {
	if ( $role ) {
		foreach ( $caps as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}

// ── Unschedule cron events ─────────────────────────────────────────────────
$cron_hooks = array(
	'arshid6social_daily_digest',
	'arshid6social_weekly_digest',
	'arshid6social_cleanup_transients',
	'arshid6social_poll_expire_check',
	'arshid6social_story_expire',
);

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// ── Delete transients ──────────────────────────────────────────────────────
$wpdb->query( // phpcs:ignore WordPress.DB
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ARSHID6SOCIAL\_%' OR option_name LIKE '\_transient\_timeout\_ARSHID6SOCIAL\_%'"
);

// ── Import uploads into WordPress Media Library, then remove the folder ───
$upload_dir = wp_upload_dir();
$sn_dir     = trailingslashit( $upload_dir['basedir'] ) . 'social-network/';

if ( is_dir( $sn_dir ) ) {
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$allowed_mime_types = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
		'mp4|m4v'      => 'video/mp4',
		'webm'         => 'video/webm',
		'ogg|ogv'      => 'video/ogg',
		'pdf'          => 'application/pdf',
	);

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $sn_dir, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}

		$file_path = $file->getRealPath();

		// Skip .htaccess and other non-media files
		if ( 0 === strpos( $file->getFilename(), '.' ) ) {
			continue;
		}

		$file_type = wp_check_filetype( $file_path, $allowed_mime_types );
		if ( empty( $file_type['type'] ) ) {
			continue;
		}

		$file_url = str_replace(
			wp_normalize_path( $upload_dir['basedir'] ),
			$upload_dir['baseurl'],
			wp_normalize_path( $file_path )
		);

		$attachment = array(
			'guid'           => $file_url,
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file_path, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $file_path );

		if ( ! is_wp_error( $attach_id ) && $attach_id ) {
			$metadata = wp_generate_attachment_metadata( $attach_id, $file_path );
			wp_update_attachment_metadata( $attach_id, $metadata );
		}
	}

	// Remove the now-empty plugin upload directory tree via WP_Filesystem.
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $sn_dir, true );
	}
}

// ── Flush rewrite rules ────────────────────────────────────────────────────
flush_rewrite_rules();
