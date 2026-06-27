<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Tag Friends / @Mentions feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Tag_Friends {

	/** Unicode-aware @mention regex. */
	const REGEX = '/@([\p{L}\p{N}_\-\.]+)/u';

	public function __construct() {
		// Process mentions after activity posted/commented.
		add_action( 'arshid6social_activity_added',    array( $this, 'process_activity_tags' ), 10, 2 );
		add_action( 'arshid6social_activity_commented', array( $this, 'process_comment_tags' ), 10, 3 );

		// Render clickable @mentions in content.
		add_filter( 'arshid6social_activity_content', array( $this, 'linkify' ), 20 );

		// AJAX.
		add_action( 'wp_ajax_arshid6social_mention_autocomplete',        array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_mention_autocomplete', array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_ajax_arshid6social_tag_photo',    array( $this, 'ajax_tag_photo' ) );
		add_action( 'wp_ajax_arshid6social_remove_tag',   array( $this, 'ajax_remove_tag' ) );
		add_action( 'wp_ajax_arshid6social_approve_tag',  array( $this, 'ajax_approve_tag' ) );
		add_action( 'wp_ajax_arshid6social_reject_tag',   array( $this, 'ajax_reject_tag' ) );
		add_action( 'wp_ajax_arshid6social_save_tag_privacy', array( $this, 'ajax_save_tag_privacy' ) );
	}

	// ── Process mentions ──────────────────────────────────────────────────────

	public function process_activity_tags( int $activity_id, array $args ): void {
		$content   = wp_strip_all_tags( $args['content'] ?? '' );
		$poster_id = (int) ( $args['user_id'] ?? get_current_user_id() );
		$this->process_mentions( $activity_id, 'activity', $content, $poster_id );
	}

	public function process_comment_tags( int $comment_id, int $activity_id, int $commenter_id ): void {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT content FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$comment_id
		) );
		if ( $row ) {
			$this->process_mentions( $comment_id, 'activity', wp_strip_all_tags( $row->content ), $commenter_id );
		}
	}

	/**
	 * Extracts @mentions from content, creates tag records, and sends notifications.
	 */
	private function process_mentions( int $object_id, string $object_type, string $content, int $actor_id ): void {
		preg_match_all( self::REGEX, $content, $matches );
		$usernames = array_unique( $matches[1] ?? array() );

		if ( empty( $usernames ) ) {
			return;
		}

		$notif_comp = ARSHID6SOCIAL()->component( 'notifications' );
		$review     = (bool) get_option( 'arshid6social_eng_tag_review', false );

		foreach ( $usernames as $username ) {
			$username = sanitize_user( $username );
			$user     = get_user_by( 'login', $username );
			if ( ! $user || (int) $user->ID === $actor_id ) {
				continue;
			}

			// Check if the tagged user allows being tagged.
			$tag_pref = get_user_meta( $user->ID, 'arshid6social_tag_privacy', true )
				?: get_option( 'arshid6social_eng_tag_privacy', 'everyone' );

			if ( 'nobody' === $tag_pref ) {
				continue;
			}
			if ( 'friends' === $tag_pref ) {
				$friends_comp = ARSHID6SOCIAL()->component( 'friends' );
				if ( $friends_comp && ! $friends_comp->are_friends( $actor_id, $user->ID ) ) {
					continue;
				}
			}

			$status = $review ? 'pending' : 'approved';
			$this->create_tag_record( $object_id, $object_type, $user->ID, $actor_id, $status );

			if ( $notif_comp ) {
				$notif_comp->add( array(
					'user_id'           => $user->ID,
					'item_id'           => $actor_id,
					'secondary_item_id' => $object_id,
					'component_name'    => 'tag_friends',
					'component_action'  => 'activity_mention',
					'sender_id'         => $actor_id,
				) );
			}
		}
	}

	private function create_tag_record( int $object_id, string $object_type, int $tagged_user_id, int $tagger_id, string $status = 'approved' ): int|false {
		global $wpdb;

		$exists = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_post_tags
			WHERE object_id = %d AND object_type = %s AND tagged_user_id = %d",
			$object_id, $object_type, $tagged_user_id
		) );

		if ( $exists ) {
			return $exists;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_post_tags',
			array(
				'object_id'      => $object_id,
				'object_type'    => $object_type,
				'tagged_user_id' => $tagged_user_id,
				'tagger_id'      => $tagger_id,
				'status'         => sanitize_key( $status ),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	// ── Linkify ───────────────────────────────────────────────────────────────

	public function linkify( string $content ): string {
		return preg_replace_callback(
			self::REGEX,
			function ( array $m ): string {
				$username = sanitize_user( $m[1] );
				$user     = get_user_by( 'login', $username );
				if ( ! $user ) {
					return esc_html( $m[0] );
				}
				$url = esc_url( home_url( '/members/' . $user->user_nicename . '/' ) );
				return '<a href="' . $url . '" class="arshid6social-mention-link">@' . esc_html( $user->display_name ) . '</a>';
			},
			$content
		) ?? $content;
	}

	// ── Photo tagging ─────────────────────────────────────────────────────────

	public function ajax_tag_photo(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		if ( ! get_option( 'arshid6social_eng_tag_photo_tags', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Photo tagging is disabled.', '6arshid social community' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$activity_id    = absint( $_POST['activity_id'] ?? 0 );
		$tagged_user_id = absint( $_POST['user_id'] ?? 0 );
		$x              = (float) ( $_POST['x'] ?? 0 );
		$y              = (float) ( $_POST['y'] ?? 0 );
		// phpcs:enable

		if ( ! $activity_id || ! $tagged_user_id ) {
			wp_send_json_error( null, 400 );
		}

		global $wpdb;

		// Verify the activity belongs to the current user.
		$activity = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) );

		if ( ! $activity || (int) $activity->user_id !== get_current_user_id() ) {
			wp_send_json_error( null, 403 );
		}

		$tagged_user = get_userdata( $tagged_user_id );
		if ( ! $tagged_user ) {
			wp_send_json_error( null, 404 );
		}

		$review  = (bool) get_option( 'arshid6social_eng_tag_review', false );
		$status  = $review ? 'pending' : 'approved';
		$tag_id  = $this->create_tag_record( $activity_id, 'activity', $tagged_user_id, get_current_user_id(), $status );

		if ( $tag_id ) {
			$x = max( 0.0, min( 100.0, $x ) );
			$y = max( 0.0, min( 100.0, $y ) );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_post_tag_coords',
				array( 'tag_id' => $tag_id, 'x_percent' => $x, 'y_percent' => $y ),
				array( '%d', '%f', '%f' )
			);

			$notif_comp = ARSHID6SOCIAL()->component( 'notifications' );
			if ( $notif_comp ) {
				$notif_comp->add( array(
					'user_id'           => $tagged_user_id,
					'item_id'           => get_current_user_id(),
					'secondary_item_id' => $activity_id,
					'component_name'    => 'tag_friends',
					'component_action'  => 'activity_mention',
					'sender_id'         => get_current_user_id(),
				) );
			}
		}

		wp_send_json_success( array( 'tag_id' => $tag_id, 'status' => $status ) );
	}

	public function ajax_remove_tag(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$tag_id  = absint( $_POST['tag_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id = get_current_user_id();

		global $wpdb;
		$tag = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_post_tags WHERE id = %d",
			$tag_id
		) );

		if ( ! $tag ) {
			wp_send_json_error( null, 404 );
		}

		// Only the tagged user, the tagger, or an admin can remove a tag.
		if ( (int) $tag->tagged_user_id !== $user_id && (int) $tag->tagger_id !== $user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) {
			wp_send_json_error( null, 403 );
		}

		$wpdb->delete( $wpdb->prefix . 'sn_post_tag_coords', array( 'tag_id' => $tag_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_post_tags', array( 'id' => $tag_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_send_json_success();
	}

	public function ajax_approve_tag(): void {
		$this->update_tag_status( 'approved' );
	}

	public function ajax_reject_tag(): void {
		$this->update_tag_status( 'rejected' );
	}

	private function update_tag_status( string $status ): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$tag_id  = absint( $_POST['tag_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id = get_current_user_id();

		global $wpdb;
		$tag = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_post_tags WHERE id = %d",
			$tag_id
		) );

		if ( ! $tag || (int) $tag->tagged_user_id !== $user_id ) {
			wp_send_json_error( null, 403 );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_post_tags',
			array( 'status' => $status ),
			array( 'id' => $tag_id ),
			array( '%s' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'status' => $status ) );
	}

	public function ajax_save_tag_privacy(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$pref = sanitize_key( $_POST['privacy'] ?? 'everyone' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $pref, array( 'everyone', 'friends', 'nobody' ), true ) ) {
			$pref = 'everyone';
		}

		update_user_meta( get_current_user_id(), 'arshid6social_tag_privacy', $pref );
		wp_send_json_success();
	}

	// ── Autocomplete ──────────────────────────────────────────────────────────

	public function ajax_autocomplete(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}

		$q       = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id = get_current_user_id();

		if ( strlen( $q ) < 1 ) {
			wp_send_json_success( array() );
			return;
		}

		global $wpdb;

		$friends_first = array();
		if ( $user_id ) {
			$friends_comp = ARSHID6SOCIAL()->component( 'friends' );
			if ( $friends_comp ) {
				$friend_ids = $friends_comp->get_friend_ids( $user_id );
				if ( $friend_ids ) {
					$in = implode( ',', array_map( 'absint', $friend_ids ) );
					$friends_first = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						"SELECT ID, user_login, display_name FROM {$wpdb->users}
						WHERE ID IN ($in) AND (user_login LIKE %s OR display_name LIKE %s) LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						'%' . $wpdb->esc_like( $q ) . '%',
						'%' . $wpdb->esc_like( $q ) . '%'
					), ARRAY_A );
				}
			}
		}

		$exclude   = array_column( $friends_first, 'ID' );
		$exclude[] = $user_id;
		$exclude   = array_map( 'absint', $exclude );

		$others_params = array(
			'%' . $wpdb->esc_like( $q ) . '%',
			'%' . $wpdb->esc_like( $q ) . '%',
		);

		$others_sql = "SELECT ID, user_login, display_name FROM {$wpdb->users}
			WHERE (user_login LIKE %s OR display_name LIKE %s)";

		if ( $exclude ) {
			$placeholders = implode( ', ', array_fill( 0, count( $exclude ), '%d' ) );
			$others_sql  .= " AND ID NOT IN ($placeholders)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$others_params = array_merge( $others_params, $exclude );
		}

		$others_sql .= ' LIMIT 5';

		$others = $wpdb->get_results( $wpdb->prepare( $others_sql, ...$others_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$all = array_merge( $friends_first, $others );

		$result = array_map( function( array $u ): array {
			return array(
				'id'          => (int) $u['ID'],
				'login'       => esc_attr( $u['user_login'] ),
				'displayName' => esc_html( $u['display_name'] ),
				'avatar'      => esc_url( get_avatar_url( (int) $u['ID'], array( 'size' => 32 ) ) ),
			);
		}, $all );

		wp_send_json_success( $result );
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Tag_Friends_REST() )->register_routes();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	public function get_tags_for_object( int $object_id, string $object_type ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.*, c.x_percent, c.y_percent
			FROM {$wpdb->prefix}sn_post_tags t
			LEFT JOIN {$wpdb->prefix}sn_post_tag_coords c ON c.tag_id = t.id
			WHERE t.object_id = %d AND t.object_type = %s AND t.status = 'approved'",
			$object_id, $object_type
		), ARRAY_A ) ?: array();
	}
}
