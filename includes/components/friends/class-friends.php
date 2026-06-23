<?php
namespace Arshid6Social\Components\Friends;

/**
 * Friends & Follow component.
 *
 * @package Arshid6Social\Components\Friends
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Friends
 *
 * Manages mutual friend requests, one-way follow, and blocking.
 */
class Friends {

	public function __construct() {
		$this->hooks();
	}

	private function hooks(): void {
		add_action( 'wp_ajax_arshid6social_send_friend_request', array( $this, 'ajax_send_friend_request' ) );
		add_action( 'wp_ajax_arshid6social_accept_friend_request', array( $this, 'ajax_accept_friend_request' ) );
		add_action( 'wp_ajax_arshid6social_reject_friend_request', array( $this, 'ajax_reject_friend_request' ) );
		add_action( 'wp_ajax_arshid6social_remove_friend', array( $this, 'ajax_remove_friend' ) );
		add_action( 'wp_ajax_arshid6social_follow_user', array( $this, 'ajax_follow_user' ) );
		add_action( 'wp_ajax_arshid6social_unfollow_user', array( $this, 'ajax_unfollow_user' ) );
		add_action( 'wp_ajax_arshid6social_block_user', array( $this, 'ajax_block_user' ) );
		add_action( 'wp_ajax_arshid6social_unblock_user', array( $this, 'ajax_unblock_user' ) );
		add_action( 'wp_ajax_arshid6social_get_friend_suggestions', array( $this, 'ajax_get_friend_suggestions' ) );
		add_action( 'wp_ajax_arshid6social_get_friends',            array( $this, 'ajax_get_friends' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_get_friends',     array( $this, 'ajax_get_friends' ) );
	}

	// ── Friend requests ──────────────────────────────────────────────────────

	/**
	 * Returns the friendship status between two users.
	 *
	 * @param int $user_a User A ID.
	 * @param int $user_b User B ID.
	 * @return string 'not_friends'|'pending_sent'|'pending_received'|'friends'
	 */
	public function get_friendship_status( int $user_a, int $user_b ): string {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sn_friends
				 WHERE (initiator_user_id = %d AND friend_user_id = %d)
				    OR (initiator_user_id = %d AND friend_user_id = %d)",
				$user_a,
				$user_b,
				$user_b,
				$user_a
			)
		);

		if ( ! $row ) {
			return 'not_friends';
		}

		if ( $row->is_confirmed ) {
			return 'friends';
		}

		return ( (int) $row->initiator_user_id === $user_a ) ? 'pending_sent' : 'pending_received';
	}

	/**
	 * Sends a friend request from one user to another.
	 *
	 * @param int $initiator_id Sender.
	 * @param int $friend_id    Recipient.
	 * @return bool|string True on success, error string on failure.
	 */
	public function send_request( int $initiator_id, int $friend_id ): bool|string {
		if ( $initiator_id === $friend_id ) {
			return __( 'You cannot friend yourself.', 'social-network-6' );
		}

		if ( $this->is_blocked( $initiator_id, $friend_id ) ) {
			return __( 'Unable to send friend request.', 'social-network-6' );
		}

		$status = $this->get_friendship_status( $initiator_id, $friend_id );
		if ( 'not_friends' !== $status ) {
			return __( 'A friend request already exists.', 'social-network-6' );
		}

		global $wpdb;
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_friends',
			array(
				'initiator_user_id' => $initiator_id,
				'friend_user_id'    => $friend_id,
				'is_confirmed'      => 0,
				'date_created'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( $result ) {
			\Arshid6Social\Cache::delete( "friend_count_{$initiator_id}" );
			\Arshid6Social\Cache::delete( "friend_count_{$friend_id}" );
			do_action( 'arshid6social_friend_request_sent', $initiator_id, $friend_id );
		}

		return (bool) $result;
	}

	/**
	 * Accepts a pending friend request.
	 *
	 * @param int $accepter_id  User accepting the request.
	 * @param int $requester_id User who sent the request.
	 * @return bool
	 */
	public function accept_request( int $accepter_id, int $requester_id ): bool {
		global $wpdb;

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_friends',
			array( 'is_confirmed' => 1 ),
			array( 'initiator_user_id' => $requester_id, 'friend_user_id' => $accepter_id, 'is_confirmed' => 0 ),
			array( '%d' ),
			array( '%d', '%d', '%d' )
		);

		if ( $result ) {
			\Arshid6Social\Cache::delete( "friend_count_{$accepter_id}" );
			\Arshid6Social\Cache::delete( "friend_count_{$requester_id}" );
			do_action( 'arshid6social_friend_request_accepted', $accepter_id, $requester_id );
		}

		return (bool) $result;
	}

	/**
	 * Rejects or withdraws a friend request.
	 *
	 * @param int $user_a One party.
	 * @param int $user_b Other party.
	 * @return bool
	 */
	public function reject_or_withdraw( int $user_a, int $user_b ): bool {
		global $wpdb;

		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}sn_friends
				 WHERE ((initiator_user_id = %d AND friend_user_id = %d)
				     OR (initiator_user_id = %d AND friend_user_id = %d))
				   AND is_confirmed = 0",
				$user_a,
				$user_b,
				$user_b,
				$user_a
			)
		);

		if ( $deleted ) {
			do_action( 'arshid6social_friend_request_rejected', $user_a, $user_b );
		}

		return (bool) $deleted;
	}

	/**
	 * Removes an existing friendship.
	 *
	 * @param int $user_a One party.
	 * @param int $user_b Other party.
	 * @return bool
	 */
	public function remove_friend( int $user_a, int $user_b ): bool {
		global $wpdb;

		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}sn_friends
				 WHERE (initiator_user_id = %d AND friend_user_id = %d)
				    OR (initiator_user_id = %d AND friend_user_id = %d)",
				$user_a,
				$user_b,
				$user_b,
				$user_a
			)
		);

		if ( $deleted ) {
			\Arshid6Social\Cache::delete( "friend_count_{$user_a}" );
			\Arshid6Social\Cache::delete( "friend_count_{$user_b}" );
			do_action( 'arshid6social_friendship_removed', $user_a, $user_b );
		}

		return (bool) $deleted;
	}

	// ── Follow ───────────────────────────────────────────────────────────────

	/**
	 * Follows a user (one-way).
	 *
	 * @param int $follower_id User who follows.
	 * @param int $followee_id User being followed.
	 * @return bool
	 */
	public function follow( int $follower_id, int $followee_id ): bool {
		if ( $follower_id === $followee_id || $this->is_following( $follower_id, $followee_id ) ) {
			return false;
		}

		global $wpdb;
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_follow',
			array(
				'follower_id'  => $follower_id,
				'followee_id'  => $followee_id,
				'date_created' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		if ( $result ) {
			do_action( 'arshid6social_user_followed', $follower_id, $followee_id );
		}

		return (bool) $result;
	}

	/**
	 * Unfollows a user.
	 *
	 * @param int $follower_id Follower user ID.
	 * @param int $followee_id Followee user ID.
	 * @return bool
	 */
	public function unfollow( int $follower_id, int $followee_id ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_follow',
			array( 'follower_id' => $follower_id, 'followee_id' => $followee_id ),
			array( '%d', '%d' )
		);

		if ( $deleted ) {
			do_action( 'arshid6social_user_unfollowed', $follower_id, $followee_id );
		}

		return (bool) $deleted;
	}

	/**
	 * Checks if a user is following another.
	 *
	 * @param int $follower_id Follower.
	 * @param int $followee_id Followee.
	 * @return bool
	 */
	public function is_following( int $follower_id, int $followee_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_follow WHERE follower_id = %d AND followee_id = %d",
				$follower_id,
				$followee_id
			)
		);
	}

	// ── Blocking ─────────────────────────────────────────────────────────────

	/**
	 * Blocks a user.
	 *
	 * @param int $blocker_id  Blocker.
	 * @param int $blocked_id  Blocked user.
	 * @return bool
	 */
	/**
	 * Blocks a user, optionally with a private reason.
	 *
	 * @param int    $blocker_id Blocker user ID.
	 * @param int    $blocked_id Blocked user ID.
	 * @param string $reason     Optional private reason (not shown to blocked user).
	 * @return bool
	 */
	public function block( int $blocker_id, int $blocked_id, string $reason = '' ): bool {
		global $wpdb;

		// Remove any existing friendship / follow both ways.
		$this->remove_friend( $blocker_id, $blocked_id );
		$this->unfollow( $blocker_id, $blocked_id );
		$this->unfollow( $blocked_id, $blocker_id );

		if ( $this->is_blocked( $blocker_id, $blocked_id ) ) {
			return false;
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_blocks',
			array(
				'blocker_id'   => $blocker_id,
				'blocked_id'   => $blocked_id,
				'reason'       => $reason ? sanitize_textarea_field( $reason ) : null,
				'date_created' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( $result ) {
			do_action( 'arshid6social_user_blocked', $blocker_id, $blocked_id );
		}

		return (bool) $result;
	}

	/**
	 * Unblocks a user. Does NOT restore previous friendship/follow.
	 *
	 * @param int $blocker_id Blocker.
	 * @param int $blocked_id Previously blocked user.
	 * @return bool
	 */
	public function unblock( int $blocker_id, int $blocked_id ): bool {
		global $wpdb;
		$deleted = (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_blocks',
			array( 'blocker_id' => $blocker_id, 'blocked_id' => $blocked_id ),
			array( '%d', '%d' )
		);
		if ( $deleted ) {
			do_action( 'arshid6social_user_unblocked', $blocker_id, $blocked_id );
		}
		return $deleted;
	}

	/**
	 * Checks whether user A has blocked user B or vice versa (bidirectional).
	 *
	 * @param int $user_a User A.
	 * @param int $user_b User B.
	 * @return bool
	 */
	public function is_blocked( int $user_a, int $user_b ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_blocks
				 WHERE (blocker_id = %d AND blocked_id = %d)
				    OR (blocker_id = %d AND blocked_id = %d)",
				$user_a,
				$user_b,
				$user_b,
				$user_a
			)
		);
	}

	/**
	 * Returns all blocks created by a given user (their block list).
	 *
	 * @param int $user_id Blocker user ID.
	 * @param int $page    Page number (1-based).
	 * @param int $per_page Results per page.
	 * @return object[]
	 */
	public function get_block_list( int $user_id, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$offset = ( $page - 1 ) * $per_page;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sn_blocks
				 WHERE blocker_id = %d
				 ORDER BY date_created DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		) ?: array();
	}

	/**
	 * Returns friend suggestions based on mutual friends.
	 *
	 * @param int $user_id    User ID to generate suggestions for.
	 * @param int $limit      Max number of suggestions.
	 * @return int[]  User IDs.
	 */
	public function get_suggestions( int $user_id, int $limit = 5 ): array {
		global $wpdb;

		// Get current friends.
		$friends = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT IF(initiator_user_id = %d, friend_user_id, initiator_user_id) as friend_id
				 FROM {$wpdb->prefix}sn_friends
				 WHERE (initiator_user_id = %d OR friend_user_id = %d) AND is_confirmed = 1",
				$user_id,
				$user_id,
				$user_id
			)
		);

		if ( empty( $friends ) ) {
			// Fall back to newest members.
			return $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->users} WHERE ID != %d ORDER BY user_registered DESC LIMIT %d",
					$user_id,
					$limit
				)
			);
		}

		$friend_ids_placeholder = implode( ', ', array_fill( 0, count( $friends ), '%d' ) );
		$exclude                = array_merge( $friends, array( $user_id ) );
		$exclude_placeholder    = implode( ', ', array_fill( 0, count( $exclude ), '%d' ) );

		// Friends-of-friends who aren't already friends.
		$suggestions = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT IF(f.initiator_user_id IN ($friend_ids_placeholder), f.friend_user_id, f.initiator_user_id) as suggested_id,
				        COUNT(*) as mutual_count
				 FROM {$wpdb->prefix}sn_friends f
				 WHERE (f.initiator_user_id IN ($friend_ids_placeholder) OR f.friend_user_id IN ($friend_ids_placeholder))
				   AND f.is_confirmed = 1
				   AND f.initiator_user_id NOT IN ($exclude_placeholder)
				   AND f.friend_user_id NOT IN ($exclude_placeholder)
				 GROUP BY suggested_id
				 ORDER BY mutual_count DESC
				 LIMIT %d",
				array_merge( $friends, $friends, $friends, $exclude, $exclude, array( $limit ) )
			)
		);

		return array_map( 'absint', $suggestions );
	}

	// ── AJAX handlers ────────────────────────────────────────────────────────

	private function nonce_check_and_auth(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}
	}

	public function ajax_send_friend_request(): void {
		$this->nonce_check_and_auth();
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error( array( 'message' => __( 'Too many friend requests. Please wait.', 'social-network-6' ) ), 429 );
		}

		$result = $this->send_request( get_current_user_id(), $target );
		if ( true === $result ) {
			wp_send_json_success( array( 'status' => 'pending_sent', 'message' => __( 'Friend request sent.', 'social-network-6' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $result ?: __( 'Could not send request.', 'social-network-6' ) ) );
		}
	}

	public function ajax_accept_friend_request(): void {
		$this->nonce_check_and_auth();
		$requester = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->accept_request( get_current_user_id(), $requester );
		wp_send_json_success( array( 'status' => 'friends', 'message' => __( 'Friend request accepted.', 'social-network-6' ) ) );
	}

	public function ajax_reject_friend_request(): void {
		$this->nonce_check_and_auth();
		$requester = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->reject_or_withdraw( get_current_user_id(), $requester );
		wp_send_json_success( array( 'status' => 'not_friends', 'message' => __( 'Request rejected.', 'social-network-6' ) ) );
	}

	public function ajax_remove_friend(): void {
		$this->nonce_check_and_auth();
		$friend = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->remove_friend( get_current_user_id(), $friend );
		wp_send_json_success( array( 'status' => 'not_friends', 'message' => __( 'Friend removed.', 'social-network-6' ) ) );
	}

	public function ajax_follow_user(): void {
		$this->nonce_check_and_auth();
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->follow( get_current_user_id(), $target );
		wp_send_json_success( array( 'following' => true, 'message' => __( 'You are now following this member.', 'social-network-6' ) ) );
	}

	public function ajax_unfollow_user(): void {
		$this->nonce_check_and_auth();
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->unfollow( get_current_user_id(), $target );
		wp_send_json_success( array( 'following' => false, 'message' => __( 'You have unfollowed this member.', 'social-network-6' ) ) );
	}

	public function ajax_block_user(): void {
		$this->nonce_check_and_auth();
		// phpcs:disable WordPress.Security.NonceVerification
		$target = absint( $_POST['user_id'] ?? 0 );
		$reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		// phpcs:enable
		$this->block( get_current_user_id(), $target, $reason );
		wp_send_json_success( array( 'blocked' => true, 'message' => __( 'User blocked.', 'social-network-6' ) ) );
	}

	public function ajax_unblock_user(): void {
		$this->nonce_check_and_auth();
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->unblock( get_current_user_id(), $target );
		wp_send_json_success( array( 'blocked' => false, 'message' => __( 'User unblocked.', 'social-network-6' ) ) );
	}

	public function ajax_get_friends(): void {
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		$user_id  = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = 20;

		if ( ! $user_id ) {
			wp_send_json_error();
		}

		$current_user_id = get_current_user_id();
		if ( $current_user_id !== $user_id ) {
			$privacy = get_user_meta( $user_id, 'arshid6social_friends_list_privacy', true ) ?: 'private';
			if ( 'private' === $privacy ) {
				wp_send_json_success( array( 'friends' => array(), 'hasMore' => false, 'private' => true ) );
				return;
			}
		}

		global $wpdb;
		$offset  = ( $page - 1 ) * $per_page;
		$ids     = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT IF(initiator_user_id = %d, friend_user_id, initiator_user_id) as friend_id
				 FROM {$wpdb->prefix}sn_friends
				 WHERE (initiator_user_id = %d OR friend_user_id = %d) AND is_confirmed = 1
				 ORDER BY date_created DESC LIMIT %d OFFSET %d",
				$user_id, $user_id, $user_id, $per_page + 1, $offset
			)
		);

		$has_more = count( $ids ) > $per_page;
		if ( $has_more ) {
			array_pop( $ids );
		}

		$members_comp = ARSHID6SOCIAL()->component( 'members' );
		$current      = get_current_user_id();
		$data         = array();
		foreach ( $ids as $fid ) {
			$user = get_userdata( (int) $fid );
			if ( ! $user || ! $members_comp ) continue;
			$item = $members_comp->format_member( $user );
			if ( $current ) {
				$item['friendshipStatus'] = $this->get_friendship_status( $current, (int) $fid );
			}
			$data[] = $item;
		}

		wp_send_json_success( array( 'friends' => $data, 'hasMore' => $has_more ) );
	}

	public function ajax_get_friend_suggestions(): void {
		$this->nonce_check_and_auth();
		$suggestions = $this->get_suggestions( get_current_user_id() );
		$members_comp = ARSHID6SOCIAL()->component( 'members' );

		$data = array();
		foreach ( $suggestions as $uid ) {
			$user = get_userdata( $uid );
			if ( $user && $members_comp ) {
				$data[] = $members_comp->format_member( $user );
			}
		}

		wp_send_json_success( $data );
	}

	/**
	 * Rate-limits friend requests for the current user.
	 *
	 * @return bool True if under the limit.
	 */
	private function check_rate_limit(): bool {
		$user_id = get_current_user_id();
		$max     = (int) get_option( 'arshid6social_rate_limit_friends', 50 );
		$key     = "arshid6social_rl_friends_{$user_id}";
		$count   = (int) get_transient( $key );

		if ( $count >= $max ) {
			return false;
		}

		if ( ! $count ) {
			set_transient( $key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Registers REST API routes.
	 */
	public function register_rest_routes(): void {
		( new Friends_REST() )->register_routes();
	}
}
