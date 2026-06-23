<?php
namespace Arshid6Social\Components\Moderation;

/**
 * Moderation component.
 *
 * @package Arshid6Social\Components\Moderation
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Moderation
 *
 * Content reporting, auto-suspend, audit logging, and banned word enforcement.
 */
class Moderation {

	public function __construct() {
		$this->hooks();
	}

	private function hooks(): void {
		// Auto-suspend on report threshold.
		add_action( 'arshid6social_report_added', array( $this, 'check_auto_suspend' ), 10, 1 );

		// Block access for suspended users.
		add_action( 'template_redirect', array( $this, 'block_suspended_users' ) );
		add_action( 'template_redirect', array( $this, 'handle_blocked_file_redirect' ), 1 );
		add_filter( 'arshid6social_can_post_activity', array( $this, 'filter_suspended_users' ), 10, 2 );

		// Hide suspended users' and groups' posts from the activity feed.
		add_filter( 'arshid6social_get_activity_args', array( $this, 'exclude_suspended_from_feed' ), 20, 1 );

		// Protect media files via admin-ajax.php routing.
		add_action( 'wp_ajax_arshid6social_serve_file', array( $this, 'serve_media_file' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_serve_file', array( $this, 'serve_media_file' ) );

		// Group suspension AJAX (admin only).
		add_action( 'wp_ajax_arshid6social_admin_suspend_group', array( $this, 'ajax_suspend_group' ) );

		// Frontend report submission (profile & group).
		add_action( 'wp_ajax_arshid6social_submit_report', array( $this, 'ajax_submit_report' ) );

		// Purge all social-network data when a WP user is deleted (via wp-admin or wp_delete_user()).
		add_action( 'delete_user', array( $this, 'on_delete_user' ) );

		// On every admin page load: delete WP Media Library files for suspended users
		// so their uploads/YYYY/MM/ files cannot be reached via direct URL.
		add_action( 'admin_init', array( $this, 'sweep_suspended_users_attachments' ) );
	}

	/**
	 * Fires just before WordPress deletes a user. Purges all social-network data.
	 *
	 * @param int $user_id User being deleted.
	 */
	public function on_delete_user( int $user_id ): void {
		self::purge_user_data( $user_id );
	}

	/**
	 * Runs on every admin page load.
	 * Finds all WP Media Library attachments owned by suspended users that live in
	 * the standard uploads/YYYY/MM/ path and permanently deletes them so their
	 * direct URLs return 404 regardless of server type.
	 *
	 * Uses a short-lived transient so it only queries once per 5 minutes,
	 * but the transient is cleared immediately when a user is suspended
	 * so the next admin page load always triggers cleanup.
	 */
	public function sweep_suspended_users_attachments(): void {
		if ( get_transient( 'arshid6social_sweep_done' ) ) {
			return;
		}

		// Lock for 5 minutes so repeated admin navigation doesn't re-run constantly.
		set_transient( 'arshid6social_sweep_done', 1, 5 * MINUTE_IN_SECONDS );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm
			      ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
			 JOIN {$wpdb->usermeta} um
			      ON um.user_id = p.post_author
			      AND um.meta_key = 'arshid6social_suspended' AND um.meta_value = '1'
			 WHERE p.post_type = 'attachment'
			   AND pm.meta_value NOT LIKE 'social-network/%'"
		);

		if ( ! $rows ) {
			return;
		}

		foreach ( $rows as $row ) {
			wp_delete_attachment( (int) $row->ID, true );
		}
	}

	/**
	 * Permanently deletes all social-network files and database records for a user.
	 * Called both from the delete_user WP hook and the admin GDPR-erasure button.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function purge_user_data( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		global $wpdb;

		// ── 1. Collect IDs needed for cascade file + DB deletion ─────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$activity_ids = array_map( 'intval', $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sn_activity WHERE user_id = %d", $user_id )
		) ?: array() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$story_ids = array_map( 'intval', $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sn_stories WHERE user_id = %d", $user_id )
		) ?: array() );

		// ── 2. Delete activity media files from disk ──────────────────────────
		if ( $activity_ids ) {
			$ph = implode( ',', array_fill( 0, count( $activity_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$media_paths = $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare( "SELECT file_path FROM {$wpdb->prefix}sn_activity_media WHERE activity_id IN ($ph)", ...$activity_ids )
			) ?: array();
			foreach ( $media_paths as $path ) {
				\Arshid6Social\Media_Handler::delete_file( $path );
			}
		}

		// ── 3. Delete story item files from disk ──────────────────────────────
		if ( $story_ids ) {
			$ph = implode( ',', array_fill( 0, count( $story_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$story_paths = $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare( "SELECT file_path FROM {$wpdb->prefix}sn_story_items WHERE story_id IN ($ph) AND file_path != ''", ...$story_ids )
			) ?: array();
			foreach ( $story_paths as $path ) {
				\Arshid6Social\Media_Handler::delete_file( $path );
			}
		}

		// ── 4. Delete user directories (avatar, cover, stories, verification docs) ─
		$upload_base = trailingslashit( wp_upload_dir()['basedir'] );
		self::rmdir_recursive( $upload_base . "social-network/users/{$user_id}" );
		self::rmdir_recursive( $upload_base . "social-network/stories/{$user_id}" );
		self::rmdir_recursive( $upload_base . "social-network/verification-docs/{$user_id}" );

		// ── 4b. Delete group avatar/cover files for groups this user created ──
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$created_group_ids = array_map( 'intval', $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sn_groups WHERE creator_id = %d", $user_id )
		) ?: array() );
		foreach ( $created_group_ids as $gid ) {
			self::rmdir_recursive( $upload_base . "social-network/groups/{$gid}" );
			delete_option( "arshid6social_group_avatar_{$gid}" );
			delete_option( "arshid6social_group_avatar_path_{$gid}" );
			delete_option( "arshid6social_group_cover_{$gid}" );
			delete_option( "arshid6social_group_cover_path_{$gid}" );
		}

		// ── 4c. Delete WordPress media library attachments uploaded by this user ─
		$attachment_ids = get_posts( array(
			'post_type'      => 'attachment',
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		) );
		foreach ( $attachment_ids as $attach_id ) {
			wp_delete_attachment( (int) $attach_id, true );
		}

		// ── 5. Database cleanup — cascade through all social-network tables ───

		// Activity-related (delete child rows before parent).
		if ( $activity_ids ) {
			$ph = implode( ',', array_fill( 0, count( $activity_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_activity_media WHERE activity_id IN ($ph)", ...$activity_ids ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id IN ($ph)", ...$activity_ids ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_activity', array( 'user_id' => $user_id ), array( '%d' ) );
		// Reactions this user made on other posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_activity_reactions', array( 'user_id' => $user_id ), array( '%d' ) );

		// Story-related.
		if ( $story_ids ) {
			$ph = implode( ',', array_fill( 0, count( $story_ids ), '%d' ) );
			// Get story item IDs to delete views + reactions.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$item_ids = array_map( 'intval', $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sn_story_items WHERE story_id IN ($ph)", ...$story_ids )
			) ?: array() );
			if ( $item_ids ) {
				$iph = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_story_views WHERE story_item_id IN ($iph)", ...$item_ids ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_story_reactions WHERE story_item_id IN ($iph)", ...$item_ids ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sn_story_items WHERE story_id IN ($ph)", ...$story_ids ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_stories', array( 'user_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'sn_story_highlights', array( 'user_id' => $user_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// Views + reactions this user left on other users' stories.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_story_views', array( 'viewer_id' => $user_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_story_reactions', array( 'user_id' => $user_id ), array( '%d' ) );

		// Bidirectional relationship tables.
		$bidirectional = array(
			$wpdb->prefix . 'sn_friends'       => array( 'initiator_user_id', 'friend_user_id' ),
			$wpdb->prefix . 'sn_follow'        => array( 'follower_id', 'followee_id' ),
			$wpdb->prefix . 'sn_blocks'        => array( 'blocker_id', 'blocked_id' ),
			$wpdb->prefix . 'sn_close_friends' => array( 'user_id', 'friend_id' ),
			$wpdb->prefix . 'sn_muted_stories' => array( 'user_id', 'muted_user_id' ),
		);
		foreach ( $bidirectional as $table => $columns ) {
			foreach ( $columns as $col ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->delete( $table, array( $col => $user_id ), array( '%d' ) );
			}
		}

		// Other single-column tables.
		$simple = array(
			$wpdb->prefix . 'sn_xprofile_data'          => 'user_id',
			$wpdb->prefix . 'sn_groups_members'          => 'user_id',
			$wpdb->prefix . 'sn_messages_recipients'     => 'user_id',
			$wpdb->prefix . 'sn_messages'                => 'sender_id',
			$wpdb->prefix . 'sn_notifications'           => 'user_id',
			$wpdb->prefix . 'sn_reports'                 => 'reporter_id',
			$wpdb->prefix . 'sn_invitations'             => 'inviter_id',
			$wpdb->prefix . 'sn_verification_requests'   => 'user_id',
			$wpdb->prefix . 'sn_verifications'           => 'user_id',
		);
		foreach ( $simple as $table => $col ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( $col => $user_id ), array( '%d' ) );
		}

		// Nullify creator_id on groups this user created (orphan rather than cascade-delete).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'sn_groups',
			array( 'creator_id' => 0 ),
			array( 'creator_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Plugin-specific user meta.
		$meta_keys = array(
			'arshid6social_suspended', 'arshid6social_suspended_reason',
			'arshid6social_avatar_url', 'arshid6social_avatar_path',
			'arshid6social_cover_url', 'arshid6social_cover_path',
			'arshid6social_reaction_style', 'arshid6social_story_privacy', 'arshid6social_last_active',
		);
		foreach ( $meta_keys as $key ) {
			delete_user_meta( $user_id, $key );
		}

		do_action( 'arshid6social_purged_user_data', $user_id );
	}

	/**
	 * Recursively deletes a directory and its contents from within uploads/social-network/.
	 *
	 * @param string $dir Absolute path to directory.
	 */
	private static function rmdir_recursive( string $dir ): void {
		if ( ! is_dir( $dir ) || ! str_contains( $dir, 'social-network' ) ) {
			return;
		}
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iter as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getRealPath() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			} else {
				wp_delete_file( $item->getRealPath() );
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Records a new content report.
	 *
	 * @param int    $reporter_id    Reporter user ID.
	 * @param int    $item_id        Reported item ID.
	 * @param string $item_type      Item type (activity, message, profile, group).
	 * @param string $reason         Report reason.
	 * @param string $notes          Optional notes.
	 * @param string $attachment_url Optional attachment URL.
	 * @return int|false Report ID or false.
	 */
	public static function add_report( int $reporter_id, int $item_id, string $item_type, string $reason, string $notes = '', string $attachment_url = '' ): int|false {
		global $wpdb;

		$allowed_types = array( 'activity', 'message', 'profile', 'group', 'comment', 'story', 'poll_suggestion' );
		if ( ! in_array( $item_type, $allowed_types, true ) ) {
			return false;
		}

		// Prevent duplicate reports from the same user on the same item.
		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_reports WHERE reporter_id = %d AND item_id = %d AND item_type = %s AND status = 'pending'",
				$reporter_id,
				$item_id,
				$item_type
			)
		);

		if ( $existing ) {
			return (int) $existing;
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_reports',
			array(
				'reporter_id'    => $reporter_id,
				'item_id'        => $item_id,
				'item_type'      => $item_type,
				'reason'         => sanitize_text_field( $reason ),
				'notes'          => sanitize_textarea_field( $notes ),
				'attachment_url' => esc_url_raw( $attachment_url ),
				'status'         => 'pending',
				'date_reported'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$report_id = (int) $wpdb->insert_id;

		do_action( 'arshid6social_report_added', $report_id );

		return $report_id;
	}

	/**
	 * AJAX: Submits a report from the frontend (profile or group).
	 */
	public function ajax_submit_report(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to report.', 'social-network-6' ) ), 401 );
		}

		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$item_id   = absint( $_POST['item_id'] ?? 0 );
		$item_type = sanitize_key( $_POST['item_type'] ?? '' );
		$reason    = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$notes     = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		// phpcs:enable

		if ( ! $item_id || ! in_array( $item_type, array( 'profile', 'group' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report target.', 'social-network-6' ) ), 400 );
		}

		if ( ! $reason ) {
			wp_send_json_error( array( 'message' => __( 'Please select a reason.', 'social-network-6' ) ), 400 );
		}

		// Cannot report yourself.
		if ( 'profile' === $item_type && (int) $item_id === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot report yourself.', 'social-network-6' ) ), 400 );
		}

		$attachment_url = '';

		// Handle optional file attachment.
		if ( (bool) get_option( 'arshid6social_report_allow_attachments', false ) && ! empty( $_FILES['attachment']['name'] ) ) {
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$allowed_mime = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
			$file_mime    = isset( $_FILES['attachment']['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES['attachment']['type'] ) ) : '';

			if ( ! in_array( $file_mime, $allowed_mime, true ) ) {
				wp_send_json_error( array( 'message' => __( 'Only image files are allowed as attachments.', 'social-network-6' ) ), 400 );
			}

			$max_size = (int) get_option( 'arshid6social_max_upload_size_mb', 5 ) * 1024 * 1024;
			if ( (int) ( $_FILES['attachment']['size'] ?? 0 ) > $max_size ) {
				wp_send_json_error( array( 'message' => __( 'Attachment file is too large.', 'social-network-6' ) ), 400 );
			}

			$uploaded = wp_handle_upload(
				$_FILES['attachment'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				array( 'test_form' => false, 'mimes' => array_fill_keys( array( 'jpg|jpeg', 'png', 'gif', 'webp' ), true ) )
			);

			if ( isset( $uploaded['url'] ) ) {
				$attachment_url = $uploaded['url'];
			}
		}

		$report_id = self::add_report(
			get_current_user_id(),
			$item_id,
			$item_type,
			$reason,
			$notes,
			$attachment_url
		);

		if ( ! $report_id ) {
			wp_send_json_error( array( 'message' => __( 'You have already reported this. It is pending review.', 'social-network-6' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Thank you. Your report has been submitted for review.', 'social-network-6' ) ) );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Suspension enforcement — feed, files, groups
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Returns all suspended user IDs (cached per request via static variable).
	 *
	 * @return int[]
	 */
	public static function get_suspended_user_ids(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows  = $wpdb->get_col(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'arshid6social_suspended' AND meta_value = '1'"
		);
		$cache = array_map( 'intval', $rows ?: array() );
		return $cache;
	}

	/**
	 * Returns all suspended group IDs (cached per request).
	 *
	 * @return int[]
	 */
	public static function get_suspended_group_ids(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows  = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}sn_groups WHERE is_suspended = 1" );
		$cache = array_map( 'intval', $rows ?: array() );
		return $cache;
	}

	/**
	 * Injects suspended user IDs into the activity query's exclude list so
	 * their posts never appear in any feed (global, profile, or group).
	 * Also excludes activity belonging to suspended groups.
	 *
	 * @param array<string,mixed> $args Activity query args.
	 * @return array<string,mixed>
	 */
	public function exclude_suspended_from_feed( array $args ): array {
		$suspended_users = self::get_suspended_user_ids();
		if ( $suspended_users ) {
			$existing = array_map( 'intval', (array) ( $args['exclude_user_ids'] ?? array() ) );
			$args['exclude_user_ids'] = array_unique( array_merge( $existing, $suspended_users ) );
		}

		$suspended_groups = self::get_suspended_group_ids();
		if ( $suspended_groups ) {
			$existing = array_map( 'intval', (array) ( $args['exclude_group_ids'] ?? array() ) );
			$args['exclude_group_ids'] = array_unique( array_merge( $existing, $suspended_groups ) );
		}

		return $args;
	}

	/**
	 * Serves a social-network media file, blocking access if the owner is
	 * suspended. Called via admin-ajax.php after the .htaccess rewrites
	 * all direct file requests through WordPress.
	 */
	public function serve_media_file(): void {
		$raw_uri = sanitize_text_field( wp_unslash( $_GET['arshid6social_uri'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $raw_uri ) {
			status_header( 400 );
			exit;
		}

		// Extract activity ID from URI pattern: /social-network/activity/{id}/filename
		if ( ! preg_match( '#/social-network/activity/(\d+)/([^?]+)$#', $raw_uri, $m ) ) {
			// Not an activity file — serve normally (profile/group covers etc.).
			$this->passthrough_file( $raw_uri );
			return;
		}

		$activity_id = (int) $m[1];
		$filename    = sanitize_file_name( $m[2] );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.user_id, med.file_path, med.mime_type
				 FROM {$wpdb->prefix}sn_activity a
				 JOIN {$wpdb->prefix}sn_activity_media med ON med.activity_id = a.id
				 WHERE a.id = %d
				   AND med.file_path LIKE %s
				 LIMIT 1",
				$activity_id,
				'%' . $wpdb->esc_like( $filename )
			)
		);

		if ( ! $row || ! file_exists( $row->file_path ) ) {
			status_header( 404 );
			exit;
		}

		// Block access if the posting user is suspended.
		if ( get_user_meta( (int) $row->user_id, 'arshid6social_suspended', true ) ) {
			$this->exit_suspended();
		}

		$this->output_file( $row->file_path, $row->mime_type );
	}

	/**
	 * Serves a non-activity file (cover, avatar, story, verification doc)
	 * after checking suspension state for user-owned and group-owned files.
	 *
	 * @param string $uri Request URI.
	 */
	private function passthrough_file( string $uri ): void {
		$upload_dir  = wp_upload_dir();
		$upload_base = wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH );
		$rel_path    = str_replace( $upload_base, '', strtok( $uri, '?' ) );
		$file_path   = $upload_dir['basedir'] . $rel_path;

		if ( ! file_exists( $file_path ) ) {
			status_header( 404 );
			exit;
		}

		// Block files belonging to a suspended user (/social-network/users/{id}/).
		if (
			preg_match( '#/social-network/users/(\d+)/#', $file_path, $m ) &&
			get_user_meta( (int) $m[1], 'arshid6social_suspended', true )
		) {
			$this->exit_suspended();
		}

		// Block files belonging to a suspended group or a group whose creator is suspended.
		if ( preg_match( '#/social-network/groups/(\d+)/#', $file_path, $m ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$group_row = $wpdb->get_row(
				$wpdb->prepare( "SELECT is_suspended, creator_id FROM {$wpdb->prefix}sn_groups WHERE id = %d LIMIT 1", (int) $m[1] )
			);
			if (
				$group_row && (
					(bool) $group_row->is_suspended ||
					( $group_row->creator_id && get_user_meta( (int) $group_row->creator_id, 'arshid6social_suspended', true ) )
				)
			) {
				$this->exit_suspended();
			}
		}

		$mime = wp_check_filetype( $file_path )['type'] ?: 'application/octet-stream';
		$this->output_file( $file_path, $mime );
	}

	/**
	 * Terminates a file request for a suspended account.
	 * - Direct browser visits (Accept: text/html) are redirected to a suspension notice page.
	 * - Embedded requests (image src, XHR) receive a 403 so the asset shows as broken/blocked.
	 */
	private function exit_suspended(): never {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : ''; // phpcs:ignore WordPress.Security
		if ( false !== strpos( $accept, 'text/html' ) ) {
			wp_redirect( add_query_arg( 'arshid6social_blocked', '1', home_url( '/' ) ) );
		} else {
			status_header( 403 );
		}
		exit;
	}

	/**
	 * Intercepts ?arshid6social_blocked=1 and renders an inline suspension notice page.
	 * Hooked on template_redirect at priority 1 (before theme templates load).
	 */
	public function handle_blocked_file_redirect(): void {
		if ( ! isset( $_GET['arshid6social_blocked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		status_header( 403 );
		header( 'X-Robots-Tag: noindex' );

		get_header();
		echo '<div class="arshid6social-wrap"><div class="arshid6social-container" style="padding-block:4rem;">';
		echo '<div class="arshid6social-suspended-notice">';
		echo '<div class="arshid6social-suspended-notice__icon">&#128683;</div>';
		echo '<h2 class="arshid6social-suspended-notice__title">' . esc_html__( 'Content Not Available', 'social-network-6' ) . '</h2>';
		echo '<p class="arshid6social-suspended-notice__text">' . esc_html__( 'This content belongs to a suspended account and is not available.', 'social-network-6' ) . '</p>';
		echo '<div class="arshid6social-suspended-notice__actions">';
		echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="arshid6social-btn arshid6social-btn-primary">' . esc_html__( 'Back to Home', 'social-network-6' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div></div>';
		get_footer();
		exit;
	}

	/**
	 * Outputs a file to the browser with appropriate headers.
	 *
	 * @param string $file_path Absolute path to file.
	 * @param string $mime_type MIME type.
	 */
	private function output_file( string $file_path, string $mime_type ): void {
		$size = (int) filesize( $file_path );

		status_header( 200 );
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . $size );
		header( 'Cache-Control: private, max-age=86400' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );
		exit;
	}

	/**
	 * AJAX: Suspends or unsuspends a group.
	 */
	public function ajax_suspend_group(): void {
		$group_id = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! check_ajax_referer( 'arshid6social_suspend_group_' . $group_id, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		if ( ! current_user_can( 'arshid6social_manage_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'social-network-6' ) ), 403 );
		}

		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$group = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, is_suspended FROM {$wpdb->prefix}sn_groups WHERE id = %d", $group_id ) );

		if ( ! $group ) {
			wp_send_json_error( array( 'message' => __( 'Group not found.', 'social-network-6' ) ), 404 );
		}

		$new_state = ! (bool) $group->is_suspended;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'sn_groups',
			array(
				'is_suspended'   => (int) $new_state,
				'suspend_reason' => $new_state ? $reason : '',
			),
			array( 'id' => $group_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		self::log_action(
			get_current_user_id(),
			$new_state ? 'group_suspended' : 'group_unsuspended',
			'group',
			$group_id,
			array( 'reason' => $reason )
		);

		wp_send_json_success(
			array(
				'suspended' => $new_state,
				'label'     => $new_state
					? __( 'Unsuspend Group', 'social-network-6' )
					: __( 'Suspend Group', 'social-network-6' ),
			)
		);
	}

	/**
	 * Checks if the report threshold has been exceeded and auto-suspends if so.
	 *
	 * @param int $report_id Newly created report ID.
	 */
	public function check_auto_suspend( int $report_id ): void {
		$threshold = (int) get_option( 'arshid6social_auto_suspend_threshold', 5 );
		if ( ! $threshold ) {
			return;
		}

		global $wpdb;

		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_reports WHERE id = %d", $report_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $report || 'profile' !== $report->item_type ) {
			// For now, auto-suspend is based on profile reports (reports of the user themselves).
			return;
		}

		$report_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sn_reports WHERE item_id = %d AND item_type = 'profile' AND status = 'pending'",
				$report->item_id
			)
		);

		if ( $report_count >= $threshold ) {
			$already_suspended = (bool) get_user_meta( $report->item_id, 'arshid6social_suspended', true );
			if ( ! $already_suspended ) {
				update_user_meta( $report->item_id, 'arshid6social_suspended', true );
				update_user_meta( $report->item_id, 'arshid6social_suspended_reason', 'auto_threshold' );

				self::log_action( 0, 'auto_suspended', 'user', $report->item_id, array( 'report_count' => $report_count ) );

				do_action( 'arshid6social_user_auto_suspended', (int) $report->item_id, $report_count );
			}
		}
	}

	/**
	 * Prevents suspended users from accessing their profile or posting pages.
	 */
	public function block_suspended_users(): void {
		if ( ! is_user_logged_in() || ! get_user_meta( get_current_user_id(), 'arshid6social_suspended', true ) ) {
			return;
		}

		// Allow access to logout, admin, and account pages.
		if ( is_admin() || get_query_var( 'arshid6social_messages' ) ) {
			return;
		}

		// Show suspended notice but don't completely lock out.
		add_action( 'wp_footer', function () {
			echo '<div class="arshid6social-suspension-notice" style="position:fixed;bottom:0;left:0;right:0;background:#dc2626;color:#fff;padding:12px;text-align:center;z-index:9999;">'
				. esc_html__( 'Your account has been suspended. Please contact an administrator.', 'social-network-6' )
				. '</div>';
		} );
	}

	/**
	 * Filters posting capability for suspended users.
	 *
	 * @param bool $can_post Current capability.
	 * @param int  $user_id  User ID.
	 * @return bool
	 */
	public function filter_suspended_users( bool $can_post, int $user_id ): bool {
		if ( get_user_meta( $user_id, 'arshid6social_suspended', true ) ) {
			return false;
		}
		return $can_post;
	}

	/**
	 * Logs an admin or moderation action to the audit log.
	 *
	 * @param int                  $user_id     Actor user ID (0 = system).
	 * @param string               $action      Action slug.
	 * @param string               $object_type Object type.
	 * @param int                  $object_id   Object ID.
	 * @param array<string, mixed> $details     Extra details.
	 */
	public static function log_action( int $user_id, string $action, string $object_type, int $object_id, array $details = array() ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_audit_log',
			array(
				'user_id'     => $user_id,
				'action'      => sanitize_key( $action ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => $object_id,
				'details'     => $details ? wp_json_encode( $details ) : '',
				'ip_address'  => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
				'date_created' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Checks whether a piece of content contains a banned word.
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	public static function contains_banned_word( string $content ): bool {
		$words = get_option( 'arshid6social_banned_words', '' );
		if ( ! $words ) {
			return false;
		}

		$list  = array_filter( array_map( 'trim', explode( "\n", strtolower( $words ) ) ) );
		$lower = strtolower( wp_strip_all_tags( $content ) );

		foreach ( $list as $word ) {
			if ( $word && str_contains( $lower, $word ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Registers REST API routes (stub — moderation REST is admin-only via admin-ajax).
	 */
	public function register_rest_routes(): void {
		// Moderation endpoints are intentionally kept in admin-ajax for capability checking.
		// REST endpoints for reporting are registered on the component that owns the content.
	}
}
