<?php
namespace Arshid6Social\Components\Stories;

/**
 * Stories component — ephemeral 24h photo/video/text stories.
 *
 * @package Arshid6Social\Components\Stories
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Stories
 *
 * Features:
 * - Create story with multiple items (photo / short video / text card)
 * - Tray (unseen first, then by recency) on activity page + profiles
 * - Auto-expire via hourly cron; expired media cleaned up
 * - Seen/unseen ring; viewers list visible to owner only
 * - Reply to a story → private message thread referencing the story
 * - Reactions (emoji) → notification to owner
 * - Privacy: public / friends / followers / close-friends / hidden-from
 * - Close friends list management
 * - Mute a user's stories
 * - Report a story → core moderation queue
 * - Highlights: save expired stories permanently
 */
class Stories {

	public function __construct() {
		if ( ! get_option( 'arshid6social_stories_enabled', false ) ) {
			return;
		}
		$this->hooks();
	}

	private function hooks(): void {
		// Shortcode for manual placement outside the activity block.
		add_shortcode( 'sn_stories_tray', array( $this, 'shortcode_tray' ) );

		// Cron: expire stories.
		add_action( 'arshid6social_expire_stories', array( $this, 'expire_stories' ) );

		// AJAX — public story operations.
		add_action( 'wp_ajax_arshid6social_create_story',         array( $this, 'ajax_create_story' ) );
		add_action( 'wp_ajax_arshid6social_delete_story',         array( $this, 'ajax_delete_story' ) );
		add_action( 'wp_ajax_arshid6social_get_story_tray',       array( $this, 'ajax_get_tray' ) );
		add_action( 'wp_ajax_arshid6social_nopriv_get_story_tray', array( $this, 'ajax_get_tray' ) );
		add_action( 'wp_ajax_arshid6social_get_story_items',      array( $this, 'ajax_get_items' ) );
		add_action( 'wp_ajax_arshid6social_mark_story_viewed',    array( $this, 'ajax_mark_viewed' ) );
		add_action( 'wp_ajax_arshid6social_react_story',          array( $this, 'ajax_react' ) );
		add_action( 'wp_ajax_arshid6social_reply_story',          array( $this, 'ajax_reply' ) );
		add_action( 'wp_ajax_arshid6social_report_story',         array( $this, 'ajax_report' ) );
		add_action( 'wp_ajax_arshid6social_get_story_viewers',    array( $this, 'ajax_get_viewers' ) );

		// Close friends management.
		add_action( 'wp_ajax_arshid6social_toggle_close_friend',  array( $this, 'ajax_toggle_close_friend' ) );
		add_action( 'wp_ajax_arshid6social_get_close_friends',    array( $this, 'ajax_get_close_friends' ) );

		// Mute management.
		add_action( 'wp_ajax_arshid6social_mute_stories',   array( $this, 'ajax_mute_stories' ) );
		add_action( 'wp_ajax_arshid6social_unmute_stories', array( $this, 'ajax_unmute_stories' ) );

		// Fixed bottom stories bar on all plugin pages.
		add_action( 'wp_footer', array( $this, 'render_bottom_tray' ) );

		// Highlights.
		if ( get_option( 'arshid6social_stories_highlights', true ) ) {
			add_action( 'wp_ajax_arshid6social_create_highlight',      array( $this, 'ajax_create_highlight' ) );
			add_action( 'wp_ajax_arshid6social_delete_highlight',      array( $this, 'ajax_delete_highlight' ) );
			add_action( 'wp_ajax_arshid6social_add_to_highlight',      array( $this, 'ajax_add_to_highlight' ) );
			add_action( 'wp_ajax_arshid6social_get_highlights',        array( $this, 'ajax_get_highlights' ) );
			add_action( 'wp_ajax_arshid6social_nopriv_get_highlights', array( $this, 'ajax_get_highlights' ) );
		}
	}

	// ── Core Queries ─────────────────────────────────────────────────────────

	/**
	 * Returns active (non-expired) stories for the tray, filtered by viewer.
	 *
	 * @param int $viewer_id Currently logged-in user ID (0 = guest).
	 * @return object[]  Each row: story + user data + unseen_count.
	 */
	public function get_tray( int $viewer_id = 0 ): array {
		global $wpdb;
		$now = current_time( 'mysql' );

		// Always exclude suspended users from the public tray.
		$suspend_sql = "AND s.user_id NOT IN (
			     SELECT user_id FROM {$wpdb->usermeta}
			     WHERE meta_key = 'arshid6social_suspended' AND meta_value = '1'
			 )";

		// Basic privacy: guests see only public stories. One bubble per user.
		if ( ! $viewer_id ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT MIN(s.id) AS id, s.user_id, u.display_name, u.user_login,
				        MAX(s.expires_at) AS expires_at, MAX(s.created_at) AS created_at,
				        COUNT(DISTINCT si.id) AS total_items,
				        COUNT(DISTINCT si.id) AS unseen_count
				 FROM {$wpdb->prefix}sn_stories s
				 JOIN {$wpdb->users} u ON u.ID = s.user_id
				 JOIN {$wpdb->prefix}sn_story_items si ON si.story_id = s.id
				 WHERE s.privacy = 'public'
				   AND s.expires_at > %s
				   AND s.highlight_id IS NULL
				   $suspend_sql
				 GROUP BY s.user_id
				 ORDER BY MAX(s.created_at) DESC",
				$now
			) ) ?: array();
			// phpcs:enable
			return $rows;
		}

		// For logged-in viewers: exclude muted, blocked, suspended, apply privacy rules.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT MIN(s.id) AS id, s.user_id, u.display_name, u.user_login,
			        MAX(s.expires_at) AS expires_at, MAX(s.created_at) AS created_at,
			        COUNT(DISTINCT si.id) AS total_items,
			        SUM(CASE WHEN sv.viewer_id IS NULL THEN 1 ELSE 0 END) AS unseen_count
			 FROM {$wpdb->prefix}sn_stories s
			 JOIN {$wpdb->users} u ON u.ID = s.user_id
			 JOIN {$wpdb->prefix}sn_story_items si ON si.story_id = s.id
			 LEFT JOIN {$wpdb->prefix}sn_story_views sv
			       ON sv.story_item_id = si.id AND sv.viewer_id = %d
			 WHERE s.expires_at > %s
			   AND s.highlight_id IS NULL
			   -- exclude blocked users (both directions)
			   AND s.user_id NOT IN (
			       SELECT blocked_id FROM {$wpdb->prefix}sn_blocks WHERE blocker_id = %d
			       UNION
			       SELECT blocker_id FROM {$wpdb->prefix}sn_blocks WHERE blocked_id = %d
			   )
			   -- exclude muted users
			   AND s.user_id NOT IN (
			       SELECT muted_user_id FROM {$wpdb->prefix}sn_muted_stories WHERE user_id = %d
			   )
			   -- exclude suspended users
			   $suspend_sql
			   -- privacy check
			   AND (
			       s.privacy = 'public'
			    OR s.user_id = %d
			    OR (s.privacy = 'close_friends' AND s.close_friends = 1
			        AND s.user_id IN (
			            SELECT user_id FROM {$wpdb->prefix}sn_close_friends WHERE friend_id = %d
			        ))
			    OR (s.privacy = 'friends'
			        AND s.user_id IN (
			            SELECT IF(initiator_user_id = %d, friend_user_id, initiator_user_id)
			            FROM {$wpdb->prefix}sn_friends
			            WHERE (initiator_user_id = %d OR friend_user_id = %d)
			              AND is_confirmed = 1
			        ))
			    OR (s.privacy = 'followers'
			        AND s.user_id IN (
			            SELECT followee_id FROM {$wpdb->prefix}sn_follow WHERE follower_id = %d
			        ))
			   )
			 GROUP BY s.user_id
			 ORDER BY unseen_count DESC, MAX(s.created_at) DESC",
			$viewer_id, $now,
			$viewer_id, $viewer_id,
			$viewer_id,
			$viewer_id,
			$viewer_id,
			$viewer_id, $viewer_id, $viewer_id,
			$viewer_id
		) ) ?: array();
		// phpcs:enable

		// Pin "Your Story" first.
		usort( $rows, static function ( $a, $b ) use ( $viewer_id ) {
			if ( (int) $a->user_id === $viewer_id ) {
				return -1;
			}
			if ( (int) $b->user_id === $viewer_id ) {
				return 1;
			}
			return (int) $b->unseen_count - (int) $a->unseen_count;
		} );

		return $rows;
	}

	/**
	 * Returns story items for a given story (sorted by sort_order).
	 */
	public function get_items( int $story_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_story_items WHERE story_id = %d ORDER BY sort_order ASC",
			$story_id
		) ) ?: array();
	}

	/**
	 * Returns all active story items for the user who owns the given story_id.
	 * This merges items from multiple stories of the same user into one sequential list.
	 */
	public function get_items_for_user( int $story_id ): array {
		global $wpdb;
		$now = current_time( 'mysql' );
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT si.* FROM {$wpdb->prefix}sn_story_items si
			 JOIN {$wpdb->prefix}sn_stories s ON s.id = si.story_id
			 WHERE s.user_id = (
			     SELECT user_id FROM {$wpdb->prefix}sn_stories WHERE id = %d LIMIT 1
			 )
			 AND s.expires_at > %s
			 AND s.highlight_id IS NULL
			 ORDER BY s.created_at ASC, si.sort_order ASC",
			$story_id, $now
		) ) ?: array();
	}

	/**
	 * Returns the list of viewers for a story item (owner-only).
	 */
	public function get_viewers( int $story_item_id, int $owner_id ): array {
		if ( ! $this->user_owns_story_item( $story_item_id, $owner_id ) ) {
			return array();
		}
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT sv.viewer_id, sv.viewed_at, u.display_name, u.user_login
			 FROM {$wpdb->prefix}sn_story_views sv
			 JOIN {$wpdb->users} u ON u.ID = sv.viewer_id
			 WHERE sv.story_item_id = %d
			 ORDER BY sv.viewed_at DESC",
			$story_item_id
		) ) ?: array();
	}

	// ── Create / Delete ───────────────────────────────────────────────────────

	/**
	 * Creates a new story with one or more items.
	 *
	 * @param int    $user_id
	 * @param string $privacy  public|friends|followers|close_friends
	 * @param array  $items    Each item: [media_type, file(optional), text(optional), bg_color, overlays_json, duration]
	 * @return int|false Story ID or false.
	 */
	public function create( int $user_id, string $privacy, array $items ): int|false {
		if ( empty( $items ) ) {
			return false;
		}

		global $wpdb;

		$expiry_hours = (int) get_option( 'arshid6social_stories_expiry_hours', 24 );
		$expires_at   = gmdate( 'Y-m-d H:i:s', time() + $expiry_hours * HOUR_IN_SECONDS );
		$close        = 'close_friends' === $privacy ? 1 : 0;

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_stories',
			array(
				'user_id'      => $user_id,
				'privacy'      => in_array( $privacy, array( 'public', 'friends', 'followers', 'close_friends' ), true ) ? $privacy : 'public',
				'close_friends' => $close,
				'created_at'   => current_time( 'mysql' ),
				'expires_at'   => $expires_at,
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$story_id = (int) $wpdb->insert_id;

		foreach ( $items as $order => $item ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_story_items',
				array(
					'story_id'     => $story_id,
					'media_type'   => sanitize_key( $item['media_type'] ?? 'text' ),
					'attachment_id' => absint( $item['attachment_id'] ?? 0 ) ?: null,
					'file_url'     => esc_url_raw( $item['file_url'] ?? '' ),
					'file_path'    => $item['file_path'] ?? '',
					'text_content' => sanitize_textarea_field( $item['text_content'] ?? '' ),
					'bg_color'     => sanitize_hex_color( $item['bg_color'] ?? '' ) ?? '#2563eb',
					'overlays_json' => wp_json_encode( (array) ( $item['overlays'] ?? array() ) ),
					'sort_order'   => (int) $order,
					'duration'     => max( 1, min( 30, (int) ( $item['duration'] ?? 5 ) ) ),
				),
				array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}

		do_action( 'arshid6social_story_created', $story_id, $user_id );
		return $story_id;
	}

	/**
	 * Deletes a story and all its items and media.
	 */
	public function delete( int $story_id, int $user_id ): bool {
		global $wpdb;

		$story = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_stories WHERE id = %d",
			$story_id
		) );

		if ( ! $story ) {
			return false;
		}
		if ( (int) $story->user_id !== $user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) {
			return false;
		}

		// Delete media files.
		$items = $this->get_items( $story_id );
		foreach ( $items as $item ) {
			if ( ! empty( $item->attachment_id ) ) {
				wp_delete_attachment( (int) $item->attachment_id, true );
			} elseif ( $item->file_path ) {
				\Arshid6Social\Media_Handler::delete_file( $item->file_path );
			}
		}

		$wpdb->delete( $wpdb->prefix . 'sn_story_views',     array( 'story_item_id' => $story_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_story_reactions', array( 'story_item_id' => $story_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_story_items',     array( 'story_id' => $story_id ),      array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_stories',         array( 'id' => $story_id ),            array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		do_action( 'arshid6social_story_deleted', $story_id, $user_id );
		return true;
	}

	// ── View / React / Reply ──────────────────────────────────────────────────

	public function mark_viewed( int $story_item_id, int $viewer_id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"INSERT IGNORE INTO {$wpdb->prefix}sn_story_views (story_item_id, viewer_id, viewed_at)
			 VALUES (%d, %d, %s)",
			$story_item_id, $viewer_id, current_time( 'mysql' )
		) );
	}

	public function react( int $story_item_id, int $user_id, string $reaction ): bool {
		global $wpdb;

		$allowed_reactions = array( '❤️', '😂', '😮', '😢', '😡', '👏', '🔥', '🙏' );
		if ( ! in_array( $reaction, $allowed_reactions, true ) ) {
			$reaction = '❤️';
		}

		$existing = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_story_reactions WHERE story_item_id = %d AND user_id = %d",
			$story_item_id, $user_id
		) );

		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_story_reactions',
				array( 'reaction' => $reaction ),
				array( 'id' => $existing ),
				array( '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_story_reactions',
				array(
					'story_item_id' => $story_item_id,
					'user_id'       => $user_id,
					'reaction'      => $reaction,
					'created_at'    => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}

		// Notify story owner — add() deduplicates so this is safe to call always.
		$owner_id = $this->get_item_owner( $story_item_id );
		if ( $owner_id && $owner_id !== $user_id ) {
			$this->notify( $owner_id, $user_id, 'story_reaction', $story_item_id );
		}

		do_action( 'arshid6social_story_reacted', $story_item_id, $user_id, $reaction );
		return true;
	}

	/**
	 * Reply to a story item — sends a private message referencing it.
	 */
	public function reply( int $story_item_id, int $sender_id, string $message ): int|false {
		$owner_id = $this->get_item_owner( $story_item_id );
		if ( ! $owner_id || $owner_id === $sender_id ) {
			return false;
		}

		if ( arshid6social_is_blocked( $sender_id, $owner_id ) ) {
			return false;
		}

		$messages_comp = ARSHID6SOCIAL()->component( 'messages' );
		if ( ! $messages_comp ) {
			return false;
		}

		// Prepend a story reference to the message.
		$thread_id = $messages_comp->start_thread(
			array( $owner_id ),
			__( 'Story Reply', '6arshid-social-community-main' ),
			'[story:' . $story_item_id . '] ' . $message,
			$sender_id
		);

		if ( $thread_id ) {
			$this->notify( $owner_id, $sender_id, 'story_reply', $story_item_id );
		}

		return $thread_id;
	}

	// ── Close Friends ─────────────────────────────────────────────────────────

	public function is_close_friend( int $user_id, int $friend_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_close_friends WHERE user_id = %d AND friend_id = %d",
			$user_id, $friend_id
		) );
	}

	public function add_close_friend( int $user_id, int $friend_id ): bool {
		global $wpdb;
		if ( $this->is_close_friend( $user_id, $friend_id ) ) {
			return true;
		}
		return (bool) $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_close_friends',
			array( 'user_id' => $user_id, 'friend_id' => $friend_id, 'created_at' => current_time( 'mysql' ) ),
			array( '%d', '%d', '%s' )
		);
	}

	public function remove_close_friend( int $user_id, int $friend_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_close_friends',
			array( 'user_id' => $user_id, 'friend_id' => $friend_id ),
			array( '%d', '%d' )
		);
	}

	public function get_close_friends( int $user_id ): array {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT friend_id FROM {$wpdb->prefix}sn_close_friends WHERE user_id = %d ORDER BY created_at DESC",
			$user_id
		) ) ?: array();
	}

	// ── Mute ─────────────────────────────────────────────────────────────────

	public function mute( int $user_id, int $muted_user_id ): bool {
		global $wpdb;
		return (bool) $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"INSERT IGNORE INTO {$wpdb->prefix}sn_muted_stories (user_id, muted_user_id, created_at)
			 VALUES (%d, %d, %s)",
			$user_id, $muted_user_id, current_time( 'mysql' )
		) );
	}

	public function unmute( int $user_id, int $muted_user_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_muted_stories',
			array( 'user_id' => $user_id, 'muted_user_id' => $muted_user_id ),
			array( '%d', '%d' )
		);
	}

	// ── Highlights ────────────────────────────────────────────────────────────

	public function create_highlight( int $user_id, string $title, string $cover_url ): int|false {
		global $wpdb;
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_story_highlights',
			array(
				'user_id'    => $user_id,
				'title'      => sanitize_text_field( $title ),
				'cover_url'  => esc_url_raw( $cover_url ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
		return $result ? (int) $wpdb->insert_id : false;
	}

	public function add_story_to_highlight( int $story_id, int $highlight_id, int $user_id ): bool {
		global $wpdb;
		// Verify ownership of both.
		$story_owner     = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_stories WHERE id = %d", $story_id
		) );
		$highlight_owner = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_story_highlights WHERE id = %d", $highlight_id
		) );
		if ( (int) $story_owner !== $user_id || (int) $highlight_owner !== $user_id ) {
			return false;
		}
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_stories',
			array( 'highlight_id' => $highlight_id ),
			array( 'id' => $story_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	public function get_highlights( int $user_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT h.*, COUNT(si.id) AS story_count
			 FROM {$wpdb->prefix}sn_story_highlights h
			 LEFT JOIN {$wpdb->prefix}sn_stories s ON s.highlight_id = h.id
			 LEFT JOIN {$wpdb->prefix}sn_story_items si ON si.story_id = s.id
			 WHERE h.user_id = %d
			 GROUP BY h.id
			 ORDER BY h.created_at DESC",
			$user_id
		) ) ?: array();
	}

	public function delete_highlight( int $highlight_id, int $user_id ): bool {
		global $wpdb;
		$owner = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_story_highlights WHERE id = %d", $highlight_id
		) );
		if ( (int) $owner !== $user_id ) {
			return false;
		}
		// Detach stories from this highlight.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_stories',
			array( 'highlight_id' => null ),
			array( 'highlight_id' => $highlight_id ),
			array( '%d' ),
			array( '%d' )
		);
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_story_highlights',
			array( 'id' => $highlight_id ),
			array( '%d' )
		);
	}

	// ── Cron: expire ─────────────────────────────────────────────────────────

	public function expire_stories(): void {
		global $wpdb;

		// Get expired stories not attached to a highlight.
		$expired = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_stories
			 WHERE expires_at <= NOW() AND highlight_id IS NULL"
		);

		foreach ( $expired as $story_id ) {
			// Delete media files.
			$items = $this->get_items( (int) $story_id );
			foreach ( $items as $item ) {
				if ( ! empty( $item->attachment_id ) ) {
					wp_delete_attachment( (int) $item->attachment_id, true );
				} elseif ( $item->file_path ) {
					\Arshid6Social\Media_Handler::delete_file( $item->file_path );
				}
			}

			$wpdb->delete( $wpdb->prefix . 'sn_story_views',     array( 'story_item_id' => $story_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->prefix . 'sn_story_reactions', array( 'story_item_id' => $story_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->prefix . 'sn_story_items',     array( 'story_id' => $story_id ),      array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->prefix . 'sn_stories',         array( 'id' => $story_id ),            array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	// ── Shortcodes / template hooks ───────────────────────────────────────────

	public function shortcode_tray( array $atts ): string {
		$viewer = get_current_user_id();
		$tray   = $this->get_tray( $viewer );
		$loader = \Arshid6Social\Template_Loader::instance();
		return $loader->get_template(
			'stories/tray.php',
			array( 'stories' => $tray, 'viewer_id' => $viewer, 'stories_obj' => $this ),
			true
		);
	}

	public function render_bottom_tray(): void {
		if ( ! get_option( 'arshid6social_stories_bottom_bar', false ) ) {
			return;
		}
		$marketplace_page_id = (int) get_option( 'arshid6social_page_marketplace', 0 );
		if ( $marketplace_page_id && is_page( $marketplace_page_id )
			&& ! get_option( 'arshid6social_stories_bottom_bar_marketplace', false ) ) {
			return;
		}
		$messages_page_id = (int) get_option( 'arshid6social_page_messages', 0 );
		$on_messages_page = ( $messages_page_id && is_page( $messages_page_id ) )
			|| get_query_var( 'arshid6social_messages' );
		if ( $on_messages_page && ! get_option( 'arshid6social_stories_bottom_bar_messages', false ) ) {
			return;
		}
		// Only render when stories CSS/JS is enqueued (i.e., on plugin pages).
		if ( ! wp_style_is( 'arshid6social-stories', 'enqueued' ) ) {
			return;
		}
		// Skip if a page already rendered the tray inline (e.g. member profile).
		if ( defined( 'ARSHID6SOCIAL_STORIES_TRAY_INLINE' ) ) {
			return;
		}
		$viewer = get_current_user_id();
		$tray   = $this->get_tray( $viewer );
		if ( empty( $tray ) && ! is_user_logged_in() ) {
			return;
		}
		$loader    = \Arshid6Social\Template_Loader::instance();
		$tray_args = array(
			'stories'     => $tray,
			'viewer_id'   => $viewer,
			'stories_obj' => $this,
		);
		// Template output is escaped within the template itself.
		echo $loader->get_template( 'stories/bottom-bar.php', $tray_args, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		// Render viewer/creator overlays here if the tray shortcode hasn't already done it.
		if ( ! defined( 'ARSHID6SOCIAL_STORIES_OVERLAYS_RENDERED' ) ) {
			define( 'ARSHID6SOCIAL_STORIES_OVERLAYS_RENDERED', true );
			echo $loader->get_template( 'stories/viewer.php', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $loader->get_template( 'stories/creator.php', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function render_tray_hook(): void {
		echo $this->shortcode_tray( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	/**
	 * Recursively sanitizes an array of values from user input.
	 * Strings are sanitized with sanitize_text_field(); numeric values with absint().
	 *
	 * @param array $data Raw array (e.g. decoded JSON from $_POST).
	 * @return array Sanitized array.
	 */
	private static function sanitize_array_recursive( array $data ): array {
		$sanitized = array();
		foreach ( $data as $key => $value ) {
			$clean_key = sanitize_key( (string) $key );
			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = self::sanitize_array_recursive( $value );
			} elseif ( is_int( $value ) || ( is_numeric( $value ) && floor( (float) $value ) === (float) $value ) ) {
				$sanitized[ $clean_key ] = absint( $value );
			} elseif ( is_float( $value ) ) {
				$sanitized[ $clean_key ] = (float) $value;
			} else {
				$sanitized[ $clean_key ] = sanitize_text_field( (string) $value );
			}
		}
		return $sanitized;
	}

	private function nonce_check(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}
	}

	public function ajax_create_story(): void {
		$this->nonce_check();
		$user_id = get_current_user_id();

		if ( ! arshid6social_check_rate_limit( 'arshid6social_rl_stories', $user_id, (int) get_option( 'arshid6social_stories_rate_limit', 20 ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many stories. Please wait.', '6arshid-social-community-main' ) ), 429 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$privacy    = sanitize_key( wp_unslash( $_POST['privacy'] ?? 'public' ) );
		$media_type = sanitize_key( wp_unslash( $_POST['media_type'] ?? 'text' ) );
		$text       = sanitize_textarea_field( wp_unslash( $_POST['text_content'] ?? '' ) );
		$bg_color   = sanitize_hex_color( wp_unslash( $_POST['bg_color'] ?? '#2563eb' ) ) ?? '#2563eb';
		$overlays   = self::sanitize_array_recursive( json_decode( wp_unslash( $_POST['overlays_json'] ?? '[]' ), true ) ?? array() );
		$duration   = max( 1, min( 30, absint( $_POST['duration'] ?? 5 ) ) );
		// phpcs:enable

		$item = array(
			'media_type'   => $media_type,
			'text_content' => $text,
			'bg_color'     => $bg_color,
			'overlays'     => $overlays,
			'duration'     => $duration,
		);

		// Handle media upload if present.
		if ( 'text' !== $media_type && ! empty( $_FILES['media']['tmp_name'] ) ) {
			$context = 'image' === $media_type ? 'story_image' : 'story_video';

			// Check video enabled.
			if ( 'story_video' === $context && ! get_option( 'arshid6social_stories_allow_video', true ) ) {
				wp_send_json_error( array( 'message' => __( 'Video stories are disabled.', '6arshid-social-community-main' ) ) );
			}

			$upload = \Arshid6Social\Media_Handler::handle( $_FILES['media'], $context, $user_id );
			if ( is_wp_error( $upload ) ) {
				wp_send_json_error( array( 'message' => $upload->get_error_message() ) );
			}
			$item['file_url']  = $upload['url'];
			$item['file_path'] = $upload['path'];

			$attach_id = \Arshid6Social\Media_Handler::register_to_media_library(
				$upload['path'],
				$upload['url'],
				$upload['mime'],
				sanitize_file_name( (string) ( $_FILES['media']['name'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$user_id
			);
			if ( $attach_id ) {
				$item['attachment_id'] = $attach_id;
			}
		}

		$story_id = $this->create( $user_id, $privacy, array( $item ) );
		if ( ! $story_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create story.', '6arshid-social-community-main' ) ) );
		}

		wp_send_json_success( array(
			'story_id' => $story_id,
			'message'  => __( 'Story created.', '6arshid-social-community-main' ),
		) );
	}

	public function ajax_delete_story(): void {
		$this->nonce_check();
		$story_id = absint( $_POST['story_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok       = $this->delete( $story_id, get_current_user_id() );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_get_tray(): void {
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		$viewer = get_current_user_id();
		$tray   = $this->get_tray( $viewer );
		wp_send_json_success( array( 'stories' => $this->format_tray( $tray, $viewer ) ) );
	}

	public function ajax_get_items(): void {
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		$story_id = absint( $_POST['story_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json_success( array( 'items' => $this->get_items_for_user( $story_id ) ) );
	}

	public function ajax_mark_viewed(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( null, 401 );
		}
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		$item_id = absint( $_POST['story_item_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->mark_viewed( $item_id, get_current_user_id() );
		wp_send_json_success();
	}

	public function ajax_react(): void {
		$this->nonce_check();
		$item_id  = absint( $_POST['story_item_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$reaction = sanitize_text_field( wp_unslash( $_POST['reaction'] ?? '❤️' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->react( $item_id, get_current_user_id(), $reaction );
		wp_send_json_success();
	}

	public function ajax_reply(): void {
		$this->nonce_check();

		if ( ! get_option( 'arshid6social_messages_story_enabled', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Story replies are disabled.', '6arshid-social-community-main' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$item_id = absint( $_POST['story_item_id'] ?? 0 );
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		// phpcs:enable
		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Message cannot be empty.', '6arshid-social-community-main' ) ) );
		}
		$thread_id = $this->reply( $item_id, get_current_user_id(), $message );
		$thread_id ? wp_send_json_success( array( 'thread_id' => $thread_id ) ) : wp_send_json_error();
	}

	public function ajax_report(): void {
		$this->nonce_check();
		// phpcs:disable WordPress.Security.NonceVerification
		$story_id = absint( $_POST['story_id'] ?? 0 );
		$reason   = sanitize_text_field( wp_unslash( $_POST['reason'] ?? 'spam' ) );
		// phpcs:enable
		\Arshid6Social\Components\Moderation\Moderation::add_report(
			get_current_user_id(), $story_id, 'story', $reason
		);
		wp_send_json_success();
	}

	public function ajax_get_viewers(): void {
		$this->nonce_check();
		$item_id = absint( $_POST['story_item_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$viewers = $this->get_viewers( $item_id, get_current_user_id() );
		wp_send_json_success( array( 'viewers' => $viewers ) );
	}

	public function ajax_toggle_close_friend(): void {
		$this->nonce_check();
		// phpcs:disable WordPress.Security.NonceVerification
		$friend_id = absint( $_POST['friend_id'] ?? 0 );
		$add       = (bool) ( $_POST['add'] ?? true );
		// phpcs:enable
		$user_id = get_current_user_id();

		if ( $add ) {
			$this->add_close_friend( $user_id, $friend_id );
		} else {
			$this->remove_close_friend( $user_id, $friend_id );
		}
		wp_send_json_success( array( 'is_close_friend' => $add ) );
	}

	public function ajax_get_close_friends(): void {
		$this->nonce_check();
		$ids     = $this->get_close_friends( get_current_user_id() );
		$members = ARSHID6SOCIAL()->component( 'members' );
		$data    = array();
		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user && $members ) {
				$data[] = $members->format_member( $user );
			}
		}
		wp_send_json_success( array( 'close_friends' => $data ) );
	}

	public function ajax_mute_stories(): void {
		$this->nonce_check();
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->mute( get_current_user_id(), $target );
		wp_send_json_success();
	}

	public function ajax_unmute_stories(): void {
		$this->nonce_check();
		$target = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->unmute( get_current_user_id(), $target );
		wp_send_json_success();
	}

	public function ajax_create_highlight(): void {
		$this->nonce_check();
		// phpcs:disable WordPress.Security.NonceVerification
		$title     = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$cover_url = esc_url_raw( wp_unslash( $_POST['cover_url'] ?? '' ) );
		// phpcs:enable
		$id = $this->create_highlight( get_current_user_id(), $title, $cover_url );
		$id ? wp_send_json_success( array( 'highlight_id' => $id ) ) : wp_send_json_error();
	}

	public function ajax_delete_highlight(): void {
		$this->nonce_check();
		$id = absint( $_POST['highlight_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok = $this->delete_highlight( $id, get_current_user_id() );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_add_to_highlight(): void {
		$this->nonce_check();
		// phpcs:disable WordPress.Security.NonceVerification
		$story_id     = absint( $_POST['story_id'] ?? 0 );
		$highlight_id = absint( $_POST['highlight_id'] ?? 0 );
		// phpcs:enable
		$ok = $this->add_story_to_highlight( $story_id, $highlight_id, get_current_user_id() );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_get_highlights(): void {
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		$user_id = absint( $_POST['user_id'] ?? get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json_success( array( 'highlights' => $this->get_highlights( $user_id ) ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function get_item_owner( int $story_item_id ): ?int {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT s.user_id FROM {$wpdb->prefix}sn_story_items si
			 JOIN {$wpdb->prefix}sn_stories s ON s.id = si.story_id
			 WHERE si.id = %d",
			$story_item_id
		) );
		return $result ? (int) $result : null;
	}

	private function user_owns_story_item( int $story_item_id, int $user_id ): bool {
		$owner = $this->get_item_owner( $story_item_id );
		return $owner === $user_id || current_user_can( 'arshid6social_manage_activity' );
	}

	private function notify( int $recipient_id, int $sender_id, string $action, int $secondary_id ): void {
		$notifications = ARSHID6SOCIAL()->component( 'notifications' );
		if ( $notifications ) {
			$notifications->add( array(
				'user_id'           => $recipient_id,
				'item_id'           => $sender_id,
				'secondary_item_id' => $secondary_id,
				'component_name'    => 'stories',
				'component_action'  => $action,
			) );
		}
	}

	private function format_tray( array $tray, int $viewer_id ): array {
		return array_map( static function ( $story ) use ( $viewer_id ) {
			return array(
				'id'           => (int) $story->id,
				'user_id'      => (int) $story->user_id,
				'display_name' => $story->display_name,
				'user_login'   => $story->user_login,
				'avatar'       => get_avatar_url( (int) $story->user_id, array( 'size' => 56 ) ),
				'total_items'  => (int) $story->total_items,
				'unseen_count' => (int) $story->unseen_count,
				'is_own'       => (int) $story->user_id === $viewer_id,
				'expires_at'   => $story->expires_at,
				'profile_url'  => esc_url( home_url( '/members/' . $story->user_login . '/' ) ),
			);
		}, $tray );
	}

	// ── REST ─────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Stories_REST( $this ) )->register_routes();
	}
}
