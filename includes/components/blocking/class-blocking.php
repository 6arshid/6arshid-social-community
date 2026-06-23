<?php
namespace Arshid6Social\Components\Blocking;

/**
 * Block / Unblock system component.
 *
 * @package Arshid6Social\Components\Blocking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Blocking
 *
 * Expands the core Friends block system:
 * - Block-list management page + shortcode [sn_block_list]
 * - Admin site-wide block (separate from user-level blocks)
 * - Block reason tracking
 * - Hooks arshid6social_is_blocked() enforcement into activity, search, REST
 * - Silent: blocked user never notified
 */
class Blocking {

	public function __construct() {
		if ( ! get_option( 'arshid6social_blocking_enabled', true ) ) {
			return;
		}
		$this->hooks();
	}

	private function hooks(): void {
		// Shortcode.
		add_shortcode( 'sn_block_list', array( $this, 'shortcode_block_list' ) );

		// Wire block enforcement into activity query args.
		add_filter( 'arshid6social_get_activity_args', array( $this, 'filter_activity_args' ) );

		// Block UI trigger on profile/activity/message/story (AJAX).
		add_action( 'wp_ajax_arshid6social_block_with_reason',   array( $this, 'ajax_block_with_reason' ) );
		add_action( 'wp_ajax_arshid6social_unblock_user',        array( $this, 'ajax_unblock_user' ) );
		add_action( 'wp_ajax_arshid6social_get_block_list',      array( $this, 'ajax_get_block_list' ) );
		add_action( 'wp_ajax_arshid6social_admin_site_block',    array( $this, 'ajax_admin_site_block' ) );
		add_action( 'wp_ajax_arshid6social_admin_site_unblock',  array( $this, 'ajax_admin_site_unblock' ) );

		// Filter members directory to exclude blocked users.
		add_filter( 'arshid6social_members_query_args', array( $this, 'filter_members_args' ) );

		// Remove blocked users from friend suggestions.
		add_filter( 'arshid6social_friend_suggestions_exclude', array( $this, 'exclude_blocked_from_suggestions' ) );

		// Prevent messaging blocked users (fired before thread creation).
		add_filter( 'arshid6social_can_message_user', array( $this, 'filter_can_message' ), 10, 3 );

		// Prevent commenting on blocked users' content.
		add_filter( 'arshid6social_can_comment_activity', array( $this, 'filter_can_comment' ), 10, 3 );

		// Admin column on user list.
		if ( is_admin() ) {
			add_filter( 'user_row_actions', array( $this, 'add_site_block_action' ), 10, 2 );
			add_action( 'admin_action_arshid6social_site_block',   array( $this, 'handle_admin_site_block' ) );
			add_action( 'admin_action_arshid6social_site_unblock', array( $this, 'handle_admin_site_unblock' ) );
		}
	}

	// ── Shortcode ────────────────────────────────────────────────────────────

	public function shortcode_block_list( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in to view your block list.', 'social-network-6' ) . '</p>';
		}

		$user_id  = get_current_user_id();
		$friends  = ARSHID6SOCIAL()->component( 'friends' );
		if ( ! $friends ) {
			return '';
		}

		$page     = max( 1, (int) ( $_GET['block_page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$blocks   = $friends->get_block_list( $user_id, $page );
		$loader   = \Arshid6Social\Template_Loader::instance();

		return $loader->get_template(
			'blocking/block-list.php',
			array(
				'blocks'   => $blocks,
				'page'     => $page,
				'has_more' => count( $blocks ) >= 20,
				'user_id'  => $user_id,
			),
			true
		);
	}

	// ── Activity filter ───────────────────────────────────────────────────────

	/**
	 * Excludes blocked users' content from the current user's activity feed.
	 * Called via filter arshid6social_get_activity_args.
	 */
	public function filter_activity_args( array $args ): array {
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return $args;
		}

		global $wpdb;
		$blocked_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT blocked_id FROM {$wpdb->prefix}sn_blocks WHERE blocker_id = %d
			 UNION
			 SELECT blocker_id FROM {$wpdb->prefix}sn_blocks WHERE blocked_id = %d",
			$current_user_id,
			$current_user_id
		) );

		if ( ! empty( $blocked_ids ) ) {
			$args['exclude_user_ids'] = array_merge(
				(array) ( $args['exclude_user_ids'] ?? array() ),
				array_map( 'absint', $blocked_ids )
			);
		}

		return $args;
	}

	// ── Members directory filter ──────────────────────────────────────────────

	public function filter_members_args( array $args ): array {
		$current = get_current_user_id();
		if ( ! $current ) {
			return $args;
		}

		global $wpdb;
		$blocked_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT blocked_id FROM {$wpdb->prefix}sn_blocks WHERE blocker_id = %d
			 UNION
			 SELECT blocker_id FROM {$wpdb->prefix}sn_blocks WHERE blocked_id = %d",
			$current,
			$current
		) );

		if ( ! empty( $blocked_ids ) ) {
			$args['exclude'] = array_unique( array_merge(
				(array) ( $args['exclude'] ?? array() ),
				array_map( 'absint', $blocked_ids )
			) );
		}

		return $args;
	}

	// ── Friend suggestions filter ─────────────────────────────────────────────

	public function exclude_blocked_from_suggestions( array $exclude_ids ): array {
		$current = get_current_user_id();
		if ( ! $current ) {
			return $exclude_ids;
		}

		global $wpdb;
		$blocked = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT blocked_id FROM {$wpdb->prefix}sn_blocks WHERE blocker_id = %d
			 UNION
			 SELECT blocker_id FROM {$wpdb->prefix}sn_blocks WHERE blocked_id = %d",
			$current,
			$current
		) );

		return array_unique( array_merge( $exclude_ids, array_map( 'absint', $blocked ) ) );
	}

	// ── Messaging / commenting guards ─────────────────────────────────────────

	public function filter_can_message( bool $can, int $sender_id, int $recipient_id ): bool {
		if ( ! $can ) {
			return false;
		}
		return ! arshid6social_is_blocked( $sender_id, $recipient_id );
	}

	public function filter_can_comment( bool $can, int $commenter_id, int $activity_author_id ): bool {
		if ( ! $can ) {
			return false;
		}
		return ! arshid6social_is_blocked( $commenter_id, $activity_author_id );
	}

	// ── AJAX ─────────────────────────────────────────────────────────────────

	private function nonce_check(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}
	}

	public function ajax_block_with_reason(): void {
		$this->nonce_check();

		// phpcs:disable WordPress.Security.NonceVerification
		$target = absint( $_POST['user_id'] ?? 0 );
		$reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		// phpcs:enable

		$current  = get_current_user_id();
		$friends  = ARSHID6SOCIAL()->component( 'friends' );

		if ( ! $friends || ! $target || $target === $current ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'social-network-6' ) ) );
		}

		if ( ! arshid6social_check_rate_limit( 'arshid6social_rl_block', $current, 20 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many block actions. Please wait.', 'social-network-6' ) ), 429 );
		}

		global $wpdb;
		$wpdb->show_errors();

		$blocked = $friends->block( $current, $target, $reason );

		if ( ! $blocked && ! $friends->is_blocked( $current, $target ) ) {
			wp_send_json_error( array(
				'message' => __( 'Could not block user.', 'social-network-6' ),
				'db_error' => $wpdb->last_error,
				'target'   => $target,
				'current'  => $current,
			) );
			return;
		}

		wp_send_json_success( array(
			'blocked' => true,
			'message' => __( 'User blocked.', 'social-network-6' ),
		) );
	}

	public function ajax_unblock_user(): void {
		$this->nonce_check();
		$target  = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$current = get_current_user_id();
		$friends = ARSHID6SOCIAL()->component( 'friends' );

		if ( $friends ) {
			$friends->unblock( $current, $target );
		}

		wp_send_json_success( array(
			'blocked' => false,
			'message' => __( 'User unblocked.', 'social-network-6' ),
		) );
	}

	public function ajax_get_block_list(): void {
		$this->nonce_check();
		$page    = max( 1, absint( $_POST['page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$current = get_current_user_id();
		$friends = ARSHID6SOCIAL()->component( 'friends' );

		if ( ! $friends ) {
			wp_send_json_success( array( 'blocks' => array(), 'hasMore' => false ) );
		}

		$blocks  = $friends->get_block_list( $current, $page );
		$members = ARSHID6SOCIAL()->component( 'members' );
		$data    = array();

		foreach ( $blocks as $block ) {
			$user = get_userdata( (int) $block->blocked_id );
			if ( ! $user ) {
				continue;
			}
			$item = $members ? $members->format_member( $user ) : array( 'id' => $block->blocked_id );
			$item['block_date']   = $block->date_created;
			$item['block_reason'] = $block->reason ?? '';
			$data[] = $item;
		}

		wp_send_json_success( array( 'blocks' => $data, 'hasMore' => count( $blocks ) >= 20 ) );
	}

	// ── Admin site-wide block ─────────────────────────────────────────────────

	public function ajax_admin_site_block(): void {
		$this->nonce_check();
		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			wp_send_json_error( null, 403 );
		}
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->do_site_block( $target );
		wp_send_json_success( array( 'site_blocked' => true ) );
	}

	public function ajax_admin_site_unblock(): void {
		$this->nonce_check();
		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			wp_send_json_error( null, 403 );
		}
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->do_site_unblock( $target );
		wp_send_json_success( array( 'site_blocked' => false ) );
	}

	public function handle_admin_site_block(): void {
		if ( ! current_user_can( 'arshid6social_manage_members' ) || ! isset( $_GET['user'] ) ) {
			return;
		}
		check_admin_referer( 'arshid6social_site_block_' . absint( $_GET['user'] ) );
		$this->do_site_block( absint( $_GET['user'] ) );
		wp_safe_redirect( admin_url( 'users.php?arshid6social_msg=site_blocked' ) );
		exit;
	}

	public function handle_admin_site_unblock(): void {
		if ( ! current_user_can( 'arshid6social_manage_members' ) || ! isset( $_GET['user'] ) ) {
			return;
		}
		check_admin_referer( 'arshid6social_site_unblock_' . absint( $_GET['user'] ) );
		$this->do_site_unblock( absint( $_GET['user'] ) );
		wp_safe_redirect( admin_url( 'users.php?arshid6social_msg=site_unblocked' ) );
		exit;
	}

	private function do_site_block( int $user_id ): void {
		update_user_meta( $user_id, 'arshid6social_site_blocked', true );
		\Arshid6Social\Components\Moderation\Moderation::log_action(
			get_current_user_id(), 'site_blocked', 'user', $user_id, array()
		);
		do_action( 'arshid6social_user_site_blocked', $user_id );
	}

	private function do_site_unblock( int $user_id ): void {
		delete_user_meta( $user_id, 'arshid6social_site_blocked' );
		\Arshid6Social\Components\Moderation\Moderation::log_action(
			get_current_user_id(), 'site_unblocked', 'user', $user_id, array()
		);
		do_action( 'arshid6social_user_site_unblocked', $user_id );
	}

	// ── Admin user list action ────────────────────────────────────────────────

	public function add_site_block_action( array $actions, \WP_User $user ): array {
		if ( ! current_user_can( 'arshid6social_manage_members' ) || $user->ID === get_current_user_id() ) {
			return $actions;
		}

		$is_blocked = arshid6social_is_site_blocked( $user->ID );

		if ( $is_blocked ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?action=arshid6social_site_unblock&user=' . $user->ID ),
				'arshid6social_site_unblock_' . $user->ID
			);
			$actions['arshid6social_site_unblock'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Site-Unblock', 'social-network-6' ) . '</a>';
		} else {
			$url = wp_nonce_url(
				admin_url( 'admin.php?action=arshid6social_site_block&user=' . $user->ID ),
				'arshid6social_site_block_' . $user->ID
			);
			$actions['arshid6social_site_block'] = '<a href="' . esc_url( $url ) . '" style="color:#dc2626">' . esc_html__( 'Site-Block', 'social-network-6' ) . '</a>';
		}

		return $actions;
	}

	// ── REST ─────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Blocking_REST() )->register_routes();
	}
}
