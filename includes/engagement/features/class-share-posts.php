<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Share / Repost feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Share_Posts {

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_share_post',         array( $this, 'ajax_share' ) );
		add_action( 'wp_ajax_arshid6social_share_to_message',   array( $this, 'ajax_share_to_message' ) );
		add_action( 'wp_ajax_arshid6social_share_count',        array( $this, 'ajax_share_count' ) );
		add_action( 'arshid6social_activity_deleted',            array( $this, 'on_activity_deleted' ) );
		add_filter( 'arshid6social_format_activity',             array( $this, 'add_share_count_to_activity' ), 10, 2 );
	}

	public function add_share_count_to_activity( array $formatted, object $raw ): array {
		$formatted['shareCount'] = $this->get_share_count( $this->get_root_id( (int) $raw->id ) );
		return $formatted;
	}

	// ── Core ──────────────────────────────────────────────────────────────────

	/**
	 * Creates an internal repost (reshare) of an activity.
	 *
	 * @param int    $user_id     User sharing the post.
	 * @param int    $original_id The activity being shared.
	 * @param string $comment     Optional quote-comment.
	 * @param string $target_type 'profile' | 'group'
	 * @param int    $target_id   Group ID for group share, 0 for profile.
	 * @return int|false  New activity ID or false.
	 */
	public function share( int $user_id, int $original_id, string $comment = '', string $target_type = 'profile', int $target_id = 0 ): int|false {
		global $wpdb;

		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		if ( ! $activity_comp ) {
			return false;
		}

		$original = $activity_comp->get_by_id( $original_id );
		if ( ! $original ) {
			return false;
		}

		// Privacy: never reshare a private post.
		if ( 'private' === $original->privacy ) {
			return false;
		}

		// Find the root original (prevent infinite nesting).
		$root_id = $this->get_root_id( $original_id );

		// Build share activity content.
		$sharer   = get_userdata( $user_id );
		$orig_url = \Arshid6Social\Components\Activity\Activity::get_permalink( $root_id );
		$orig_author = get_userdata( (int) $original->user_id );

		$content = '';
		if ( $comment ) {
			$content .= '<p class="arshid6social-share-quote">' . wp_kses_post( $comment ) . '</p>';
		}
		$content .= '<blockquote class="arshid6social-share-original" data-original-id="' . esc_attr( $root_id ) . '">';
		$content .= '<cite><a href="' . esc_url( home_url( '/members/' . ( $orig_author ? $orig_author->user_nicename : '' ) . '/' ) ) . '">'
			. esc_html( $orig_author ? $orig_author->display_name : __( 'Deleted user', '6arshid social community' ) )
			. '</a></cite>';
		$content .= wp_kses_post( $original->content );
		$content .= '</blockquote>';

		$args = array(
			'user_id'   => $user_id,
			'type'      => 'reshare',
			'component' => 'share',
			'content'   => $content,
			'privacy'   => 'friends' === $original->privacy ? 'friends' : 'public',
			'item_id'   => 0,
		);

		if ( 'group' === $target_type && $target_id ) {
			$args['item_id']   = $target_id;
			$args['component'] = 'groups';
		}

		$new_activity_id = $activity_comp->add( $args );
		if ( ! $new_activity_id ) {
			return false;
		}

		// Record the share.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_shares',
			array(
				'user_id'     => $user_id,
				'original_id' => $original_id,
				'root_id'     => $root_id,
				'target_type' => sanitize_key( $target_type ),
				'target_id'   => $target_id ?: 0,
				'comment'     => $comment ? wp_kses_post( $comment ) : null,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		// Notify original author.
		$notif_comp = ARSHID6SOCIAL()->component( 'notifications' );
		if ( $notif_comp && (int) $original->user_id !== $user_id ) {
			$notif_comp->add( array(
				'user_id'           => (int) $original->user_id,
				'item_id'           => $user_id,
				'secondary_item_id' => $new_activity_id,
				'component_name'    => 'share',
				'component_action'  => 'activity_reaction',
				'sender_id'         => $user_id,
			) );
		}

		return $new_activity_id;
	}

	/**
	 * Returns the root original activity ID (prevents reshare-of-reshare nesting).
	 */
	public function get_root_id( int $activity_id ): int {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT root_id FROM {$wpdb->prefix}sn_shares WHERE original_id = %d LIMIT 1",
			$activity_id
		) );
		return $row ? (int) $row->root_id : $activity_id;
	}

	public function get_share_count( int $root_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_shares WHERE root_id = %d",
			$root_id
		) );
	}

	public function on_activity_deleted( int $activity_id ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'sn_shares', array( 'original_id' => $activity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_share(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$original_id = absint( $_POST['activity_id'] ?? 0 );
		$comment     = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
		$target_type = sanitize_key( $_POST['target_type'] ?? 'profile' );
		$target_id   = absint( $_POST['target_id'] ?? 0 );
		// phpcs:enable

		if ( ! $original_id ) {
			wp_send_json_error( null, 400 );
		}

		// Rate limit.
		$limit = (int) get_option( 'arshid6social_rate_limit_posts', 10 );
		$rl_key = 'arshid6social_rl_share_' . get_current_user_id();
		$count  = (int) get_transient( $rl_key );
		if ( $count >= $limit ) {
			wp_send_json_error( array( 'message' => __( 'Rate limit exceeded. Please wait before sharing again.', '6arshid social community' ) ), 429 );
		}
		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		if ( ! in_array( $target_type, array( 'profile', 'group' ), true ) ) {
			$target_type = 'profile';
		}

		$new_id = $this->share( get_current_user_id(), $original_id, $comment, $target_type, $target_id );
		if ( ! $new_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not share this post.', '6arshid social community' ) ), 400 );
		}

		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		$formatted     = $activity_comp ? $activity_comp->format_activity( $activity_comp->get_by_id( $new_id ) ) : array();

		wp_send_json_success( array(
			'activity'    => $formatted,
			'share_count' => $this->get_share_count( $this->get_root_id( $original_id ) ),
		) );
	}

	public function ajax_share_to_message(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$activity_id  = absint( $_POST['activity_id'] ?? 0 );
		$recipient_id = absint( $_POST['recipient_id'] ?? 0 );
		// phpcs:enable

		if ( ! $activity_id || ! $recipient_id ) {
			wp_send_json_error( null, 400 );
		}

		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		$msg_comp      = ARSHID6SOCIAL()->component( 'messages' );

		if ( ! $activity_comp || ! $msg_comp ) {
			wp_send_json_error( null, 503 );
		}

		$activity = $activity_comp->get_by_id( $activity_id );
		if ( ! $activity || 'private' === $activity->privacy ) {
			wp_send_json_error( array( 'message' => __( 'This post cannot be shared.', '6arshid social community' ) ), 403 );
		}

		$url     = \Arshid6Social\Components\Activity\Activity::get_permalink( (int) $activity->id );
		/* translators: %s: post URL */
		$content = sprintf( __( 'Shared a post: %s', '6arshid social community' ), esc_url( $url ) );

		$thread_id = $msg_comp->start_thread(
			array( $recipient_id ),
			__( 'Shared post', '6arshid social community' ),
			$content,
			get_current_user_id()
		);

		$thread_id
			? wp_send_json_success( array( 'thread_id' => $thread_id ) )
			: wp_send_json_error( null, 500 );
	}

	public function ajax_share_count(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$id = absint( $_GET['activity_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json_success( array( 'count' => $this->get_share_count( $this->get_root_id( $id ) ) ) );
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Share_Posts_REST() )->register_routes();
	}
}
