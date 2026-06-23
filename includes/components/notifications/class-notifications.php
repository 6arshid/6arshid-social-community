<?php
namespace Arshid6Social\Components\Notifications;

/**
 * Notifications component.
 *
 * @package Arshid6Social\Components\Notifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Notifications
 *
 * On-site notification centre, email notifications, and digest emails.
 */
class Notifications {

	/** Notification types and their labels/icons for the frontend. */
	const TYPES = array(
		'friend_request'        => array( 'label' => 'Friend Request',      'icon' => '👥', 'color' => '#2563eb' ),
		'friendship_accepted'   => array( 'label' => 'Friend Accepted',     'icon' => '🤝', 'color' => '#16a34a' ),
		'activity_reaction'     => array( 'label' => 'Reaction',            'icon' => '❤️', 'color' => '#dc2626' ),
		'activity_comment'      => array( 'label' => 'Comment',             'icon' => '💬', 'color' => '#0891b2' ),
		'comment_reply'         => array( 'label' => 'Comment Reply',       'icon' => '↩️', 'color' => '#7c3aed' ),
		'activity_mention'      => array( 'label' => 'Mention',             'icon' => '✍️', 'color' => '#7c3aed' ),
		'new_message'           => array( 'label' => 'Message',             'icon' => '✉️', 'color' => '#0284c7' ),
		'group_invitation'      => array( 'label' => 'Group Invite',        'icon' => '🏘️', 'color' => '#d97706' ),
		'new_follower'          => array( 'label' => 'New Follower',        'icon' => '👤', 'color' => '#059669' ),
		'story_reaction'        => array( 'label' => 'Story Reaction',      'icon' => '😍', 'color' => '#f59e0b' ),
		'story_reply'           => array( 'label' => 'Story Reply',         'icon' => '↩️', 'color' => '#0284c7' ),
		'verification_approved' => array( 'label' => 'Verification Approved', 'icon' => '✓', 'color' => '#16a34a' ),
		'verification_rejected' => array( 'label' => 'Verification Rejected', 'icon' => '✗', 'color' => '#dc2626' ),
	);

	public function __construct() {
		$this->hooks();
	}

	private function hooks(): void {
		// Social events that trigger notifications.
		add_action( 'arshid6social_friend_request_sent',     array( $this, 'notify_friend_request' ),  10, 2 );
		add_action( 'arshid6social_friend_request_accepted', array( $this, 'notify_friend_accepted' ),  10, 2 );
		add_action( 'arshid6social_activity_mention',        array( $this, 'notify_mention' ),          10, 1 );
		add_action( 'arshid6social_group_invitation_sent',   array( $this, 'notify_group_invitation' ), 10, 3 );
		add_action( 'arshid6social_message_sent',            array( $this, 'notify_new_message' ),      10, 3 );
		add_action( 'arshid6social_activity_reacted',        array( $this, 'notify_reaction' ),         10, 4 );
		add_action( 'arshid6social_activity_commented',      array( $this, 'notify_comment' ),          10, 4 );
		add_action( 'arshid6social_user_followed',           array( $this, 'notify_follow' ),           10, 2 );

		// AJAX.
		add_action( 'wp_ajax_arshid6social_get_notifications',      array( $this, 'ajax_get_notifications' ) );
		add_action( 'wp_ajax_arshid6social_mark_notifications_read', array( $this, 'ajax_mark_read' ) );
		add_action( 'wp_ajax_arshid6social_unread_notification_count', array( $this, 'ajax_unread_count' ) );
		add_action( 'wp_ajax_arshid6social_delete_notification',    array( $this, 'ajax_delete_notification' ) );
		add_action( 'wp_ajax_arshid6social_save_notification_prefs', array( $this, 'ajax_save_prefs' ) );

		// Email digest cron.
		add_action( 'arshid6social_daily_digest',  array( $this, 'send_daily_digest' ) );
		add_action( 'arshid6social_weekly_digest', array( $this, 'send_weekly_digest' ) );
	}

	// ── Core CRUD ─────────────────────────────────────────────────────────────

	/**
	 * Inserts a notification record, respecting per-user preferences.
	 *
	 * @param array<string, mixed> $args Notification data.
	 * @return int|false Notification ID or false.
	 */
	public function add( array $args ): int|false {
		global $wpdb;

		$defaults = array(
			'user_id'           => 0,
			'item_id'           => 0,
			'secondary_item_id' => 0,
			'component_name'    => '',
			'component_action'  => '',
			'date_notified'     => current_time( 'mysql', true ),
			'is_new'            => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! $args['user_id'] || ! $args['component_name'] || ! $args['component_action'] ) {
			return false;
		}

		// Don't notify users about their own actions.
		if ( isset( $args['sender_id'] ) && (int) $args['sender_id'] === (int) $args['user_id'] ) {
			return false;
		}

		// Respect per-user notification preference for this action type.
		$pref = get_user_meta( (int) $args['user_id'], 'arshid6social_notify_' . $args['component_action'], true );
		if ( $pref === '0' ) {
			return false;
		}

		$user_id           = absint( $args['user_id'] );
		$item_id           = absint( $args['item_id'] );
		$secondary_item_id = absint( $args['secondary_item_id'] );
		$component_name    = sanitize_key( $args['component_name'] );
		$component_action  = sanitize_key( $args['component_action'] );

		// Dedup: one notification per (recipient, sender, object, action).
		$existing_id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_notifications
			 WHERE user_id = %d AND item_id = %d AND secondary_item_id = %d
			   AND component_name = %s AND component_action = %s
			 LIMIT 1",
			$user_id, $item_id, $secondary_item_id, $component_name, $component_action
		) );

		if ( $existing_id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_notifications',
				array( 'date_notified' => $args['date_notified'], 'is_new' => 1 ),
				array( 'id' => (int) $existing_id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
			return (int) $existing_id;
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_notifications',
			array(
				'user_id'           => $user_id,
				'item_id'           => $item_id,
				'secondary_item_id' => $secondary_item_id,
				'component_name'    => $component_name,
				'component_action'  => $component_action,
				'date_notified'     => $args['date_notified'],
				'is_new'            => 1,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d' )
		);

		if ( ! $result ) {
			return false;
		}

		$notification_id = (int) $wpdb->insert_id;

		$this->invalidate_unread_cache( $user_id );
		do_action( 'arshid6social_notification_added', $notification_id, $args );

		if ( get_option( 'arshid6social_email_notifications' ) ) {
			$this->maybe_send_email( $notification_id, $args );
		}

		return $notification_id;
	}

	/**
	 * Returns notifications for a user with optional pagination.
	 *
	 * @param int  $user_id     User ID.
	 * @param bool $unread_only Return only unread.
	 * @param int  $limit       Max results.
	 * @param int  $page        Page number (1-based).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_user( int $user_id, bool $unread_only = false, int $limit = 25, int $page = 1 ): array {
		global $wpdb;

		$offset = ( max( 1, $page ) - 1 ) * $limit;
		$where  = $wpdb->prepare( 'user_id = %d', $user_id );
		if ( $unread_only ) {
			$where .= ' AND is_new = 1';
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sn_notifications WHERE $where ORDER BY date_notified DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			)
		);

		return array_map( array( $this, 'format_notification' ), $rows );
	}

	/**
	 * Returns total notification count for a user.
	 */
	public function get_total_count( int $user_id, bool $unread_only = false ): int {
		global $wpdb;
		$where = $wpdb->prepare( 'user_id = %d', $user_id );
		if ( $unread_only ) {
			$where .= ' AND is_new = 1';
		}
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_notifications WHERE $where" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Returns the unread notification count for a user, cached per request.
	 *
	 * Short TTL (60 s) so near-real-time polling stays accurate while cutting
	 * repeated DB hits when the JS polls multiple times in quick succession.
	 */
	public function get_unread_count( int $user_id ): int {
		return (int) \Arshid6Social\Cache::remember(
			"notif_unread_{$user_id}",
			fn() => $this->get_total_count( $user_id, true ),
			60
		);
	}

	/**
	 * Invalidates the cached unread count for a user (call after add/mark_read/delete).
	 */
	private function invalidate_unread_cache( int $user_id ): void {
		\Arshid6Social\Cache::delete( "notif_unread_{$user_id}" );
	}

	/**
	 * Marks all or specific notifications as read.
	 *
	 * @param int   $user_id         User ID.
	 * @param int[] $notification_ids Specific IDs; empty = mark all.
	 */
	public function mark_read( int $user_id, array $notification_ids = array() ): void {
		global $wpdb;

		if ( $notification_ids ) {
			$ids_placeholder = implode( ', ', array_fill( 0, count( $notification_ids ), '%d' ) );
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}sn_notifications SET is_new = 0 WHERE user_id = %d AND id IN ($ids_placeholder)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array_merge( array( $user_id ), array_map( 'absint', $notification_ids ) )
				)
			);
		} else {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_notifications',
				array( 'is_new' => 0 ),
				array( 'user_id' => $user_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Deletes a notification (only the owner can delete).
	 */
	public function delete_notification( int $notification_id, int $user_id ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_notifications',
			array( 'id' => $notification_id, 'user_id' => $user_id ),
			array( '%d', '%d' )
		);
		return (bool) $deleted;
	}

	/**
	 * Returns per-user notification preferences for all types.
	 */
	/**
	 * Sends an immediate notification email for a single event.
	 * Skipped when the user prefers a digest (digest cron handles those).
	 */
	private function maybe_send_email( int $notification_id, array $args ): void {
		$user = get_userdata( (int) ( $args['user_id'] ?? 0 ) );
		if ( ! $user ) {
			return;
		}

		// Respect per-user opt-out.
		if ( get_user_meta( $user->ID, 'arshid6social_email_opt_out', true ) ) {
			return;
		}

		// Skip immediate email if the user wants digest instead.
		$digest = get_user_meta( $user->ID, 'arshid6social_email_digest', true )
			?: get_option( 'arshid6social_email_digest', 'daily' );
		if ( 'none' !== $digest ) {
			return;
		}

		// Build a minimal email from the notification row.
		global $wpdb;
		$notif = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_notifications WHERE id = %d",
			$notification_id
		) );
		if ( ! $notif ) {
			return;
		}

		$formatted  = $this->format_notification( $notif );
		$site_name  = esc_html( get_bloginfo( 'name' ) );
		$notif_url  = esc_url( home_url( '/notifications/' ) );
		$type_info  = self::TYPES[ $args['component_action'] ?? '' ] ?? array( 'label' => __( 'Notification', 'social-network-6' ) );
		$subject    = sprintf( '[%s] %s', $site_name, $type_info['label'] );
		$plain_desc = wp_strip_all_tags( $formatted['description'] ?? '' );

		$body = "<!DOCTYPE html><html><body style='font-family:sans-serif;color:#111;max-width:600px;margin:auto;padding:24px;'>
			<p style='font-size:1rem;'>{$plain_desc}</p>
			<p><a href='{$notif_url}' style='color:#2563eb;'>"
			. esc_html__( 'View all notifications', 'social-network-6' )
			. "</a></p>
		</body></html>";

		wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public function get_user_prefs( int $user_id ): array {
		$prefs = array();
		foreach ( array_keys( self::TYPES ) as $action ) {
			$val            = get_user_meta( $user_id, 'arshid6social_notify_' . $action, true );
			$prefs[ $action ] = $val === '0' ? false : true; // default = enabled
		}
		$prefs['email_notifications'] = ! (bool) get_user_meta( $user_id, 'arshid6social_email_opt_out', true );
		$prefs['email_digest']        = get_user_meta( $user_id, 'arshid6social_email_digest', true ) ?: 'daily';
		return $prefs;
	}

	// ── Formatting ────────────────────────────────────────────────────────────

	/**
	 * Formats a raw notification row for the frontend, including sender info.
	 */
	/** Maps legacy/wrong component_action values stored in DB to canonical ones. */
	private const ACTION_ALIASES = array(
		'activity_like'          => 'activity_reaction',
		'new_activity_comment'   => 'activity_comment',
		'friendship_request'     => 'friend_request',
		'new_membership_request' => 'group_invitation',
	);

	public function format_notification( object $notification ): array {
		$action = self::ACTION_ALIASES[ $notification->component_action ] ?? $notification->component_action;

		// Work on a clone so the original DB row is untouched.
		$notification                   = clone $notification;
		$notification->component_action = $action;

		$sender       = get_userdata( (int) $notification->item_id );
		$sender_name  = $sender ? esc_html( $sender->display_name ) : __( 'Someone', 'social-network-6' );
		$sender_url   = $sender ? esc_url( home_url( '/members/' . $sender->user_nicename . '/' ) ) : '#';
		$sender_avatar = $sender
			? esc_url( get_avatar_url( $sender->ID, array( 'size' => 48, 'default' => 'mp' ) ) )
			: 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=48';

		$type_info = self::TYPES[ $notification->component_action ] ?? array(
			'label' => ucfirst( str_replace( '_', ' ', $notification->component_action ) ),
			'icon'  => '🔔',
			'color' => '#6b7280',
		);

		return array(
			'id'              => (int) $notification->id,
			'userId'          => (int) $notification->user_id,
			'itemId'          => (int) $notification->item_id,
			'secondaryItemId' => (int) $notification->secondary_item_id,
			'componentName'   => esc_attr( $notification->component_name ),
			'componentAction' => esc_attr( $notification->component_action ),
			'dateNotified'    => esc_attr( rtrim( $notification->date_notified, 'Z' ) . 'Z' ),
			'isNew'           => (bool) $notification->is_new,
			'description'     => wp_kses_post( $this->get_description( $notification ) ),
			'link'            => esc_url( $this->get_notification_link( $notification ) ),
			'senderName'      => $sender_name,
			'senderUrl'       => $sender_url,
			'senderAvatar'    => $sender_avatar,
			'typeLabel'       => esc_html( $type_info['label'] ),
			'typeIcon'        => esc_html( $type_info['icon'] ),
			'typeColor'       => esc_attr( $type_info['color'] ),
		);
	}

	/**
	 * Generates the destination URL for a notification.
	 *
	 * Convention:
	 *   item_id           = sender user ID
	 *   secondary_item_id = activity_id | thread_id | group_id (depends on action)
	 */
	private function get_notification_link( object $notification ): string {
		global $wpdb;

		$secondary = (int) $notification->secondary_item_id;
		$sender    = get_userdata( (int) $notification->item_id );

		$activity_page = (int) get_option( 'arshid6social_page_activity', 0 );
		$activity_base = $activity_page ? trailingslashit( get_permalink( $activity_page ) ) : home_url( '/activity/' );

		$messages_page = (int) get_option( 'arshid6social_page_messages', 0 );
		$messages_base = $messages_page ? trailingslashit( get_permalink( $messages_page ) ) : home_url( '/messages/' );

		$groups_page = (int) get_option( 'arshid6social_page_groups', 0 );
		$groups_base = $groups_page ? trailingslashit( get_permalink( $groups_page ) ) : home_url( '/groups/' );

		switch ( $notification->component_action ) {

			case 'activity_reaction':
			case 'activity_mention':
				// secondary_item_id = the activity post ID; link directly to its permalink.
				return $secondary
					? \Arshid6Social\Components\Activity\Activity::get_permalink( $secondary )
					: $activity_base;

			case 'activity_comment':
			case 'comment_reply':
				// secondary_item_id = comment activity ID; resolve parent post and anchor to the comment.
				if ( $secondary ) {
					$parent_activity_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						"SELECT item_id FROM {$wpdb->prefix}sn_activity WHERE id = %d AND type = 'activity_comment'",
						$secondary
					) );
					$base_url = $parent_activity_id
						? \Arshid6Social\Components\Activity\Activity::get_permalink( $parent_activity_id )
						: \Arshid6Social\Components\Activity\Activity::get_permalink( $secondary );
					return $base_url . '#arshid6social-activity-' . $secondary;
				}
				return $activity_base;

			case 'new_message':
				return $secondary
					? add_query_arg( 'thread', \Arshid6Social\Components\Messages\Messages::get_thread_uid( (int) $secondary ), $messages_base )
					: $messages_base;

			case 'group_invitation':
				if ( $secondary ) {
					$slug = (string) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						"SELECT slug FROM {$wpdb->prefix}sn_groups WHERE id = %d",
						$secondary
					) );
					if ( $slug ) {
						return $groups_base . $slug . '/';
					}
				}
				return $groups_base;

			case 'friend_request':
			case 'friendship_accepted':
			case 'new_follower':
				return $sender
					? home_url( '/members/' . $sender->user_nicename . '/' )
					: '#';

			case 'story_reaction':
				// Link to the reactor's profile — the story may be expired by the time the notification is read.
				return $sender
					? home_url( '/members/' . $sender->user_nicename . '/' )
					: '#';

			case 'story_reply':
				// Story reply creates a private message thread; go to the messages page.
				return $messages_base;

			case 'verification_approved':
			case 'verification_rejected': {
				$sender_data = get_userdata( (int) $notification->user_id );
				return $sender_data
					? home_url( '/members/' . $sender_data->user_nicename . '/' )
					: home_url( '/' );
			}

			default:
				return '#';
		}
	}

	/**
	 * Generates a human-readable notification description.
	 */
	private function get_description( object $notification ): string {
		$user     = get_userdata( (int) $notification->item_id );
		$username = $user ? esc_html( $user->display_name ) : __( 'Someone', 'social-network-6' );
		$url      = $user ? esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ) : '#';
		$link     = "<a href=\"$url\"><strong>$username</strong></a>";

		return match ( $notification->component_action ) {
			/* translators: %s: linked username */
			'friend_request'        => sprintf( __( '%s sent you a friend request.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'friendship_accepted'   => sprintf( __( '%s accepted your friend request.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'activity_mention'      => sprintf( __( '%s mentioned you in a post.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'group_invitation'      => sprintf( __( '%s invited you to join a group.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'new_message'           => sprintf( __( '%s sent you a message.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'activity_reaction'     => sprintf( __( '%s reacted to your post.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'activity_comment'      => sprintf( __( '%s commented on your post.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'comment_reply'         => sprintf( __( '%s replied to your comment.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'new_follower'          => sprintf( __( '%s started following you.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'story_reaction'        => sprintf( __( '%s reacted to your story.', 'social-network-6' ), $link ),
			/* translators: %s: linked username */
			'story_reply'           => sprintf( __( '%s replied to your story.', 'social-network-6' ), $link ),
			'verification_approved' => __( 'Your verification request has been approved.', 'social-network-6' ),
			'verification_rejected' => __( 'Your verification request has been rejected.', 'social-network-6' ),
			default                 => apply_filters( 'arshid6social_notification_description', '', $notification ),
		};
	}

	// ── Event listeners ───────────────────────────────────────────────────────

	public function notify_friend_request( int $initiator_id, int $friend_id ): void {
		$this->add( array(
			'user_id'          => $friend_id,
			'item_id'          => $initiator_id,
			'component_name'   => 'friends',
			'component_action' => 'friend_request',
			'sender_id'        => $initiator_id,
		) );
	}

	public function notify_friend_accepted( int $accepter_id, int $requester_id ): void {
		$this->add( array(
			'user_id'          => $requester_id,
			'item_id'          => $accepter_id,
			'component_name'   => 'friends',
			'component_action' => 'friendship_accepted',
			'sender_id'        => $accepter_id,
		) );
	}

	public function notify_mention( array $data ): void {
		$this->add( array(
			'user_id'           => $data['user_id'],
			'item_id'           => $data['poster_id'],
			'secondary_item_id' => $data['activity_id'],
			'component_name'    => 'activity',
			'component_action'  => 'activity_mention',
			'sender_id'         => $data['poster_id'],
		) );
	}

	public function notify_group_invitation( int $group_id, int $invitee_id, int $inviter_id ): void {
		$this->add( array(
			'user_id'           => $invitee_id,
			'item_id'           => $inviter_id,
			'secondary_item_id' => $group_id,
			'component_name'    => 'groups',
			'component_action'  => 'group_invitation',
			'sender_id'         => $inviter_id,
		) );
	}

	public function notify_new_message( int $message_id, int $thread_id, int $sender_id ): void {
		global $wpdb;

		$recipients = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}sn_messages_recipients WHERE thread_id = %d AND user_id != %d AND is_deleted = 0",
				$thread_id,
				$sender_id
			)
		);

		foreach ( $recipients as $recipient_id ) {
			$this->add( array(
				'user_id'           => (int) $recipient_id,
				'item_id'           => $sender_id,
				'secondary_item_id' => $thread_id,
				'component_name'    => 'messages',
				'component_action'  => 'new_message',
				'sender_id'         => $sender_id,
			) );
		}
	}

	public function notify_reaction( int $activity_id, int $reactor_id, string $reaction_type, bool $reacted ): void {
		if ( ! $reacted ) {
			return;
		}

		global $wpdb;
		$owner = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) );

		if ( $owner ) {
			$this->add( array(
				'user_id'           => $owner,
				'item_id'           => $reactor_id,
				'secondary_item_id' => $activity_id,
				'component_name'    => 'activity',
				'component_action'  => 'activity_reaction',
				'sender_id'         => $reactor_id,
			) );
		}
	}

	public function notify_comment( int $comment_id, int $activity_id, int $commenter_id, int $parent_comment_id = 0 ): void {
		global $wpdb;

		// Notify the post owner.
		$owner = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) );

		if ( $owner ) {
			$this->add( array(
				'user_id'           => $owner,
				'item_id'           => $commenter_id,
				'secondary_item_id' => $comment_id,
				'component_name'    => 'activity',
				'component_action'  => 'activity_comment',
				'sender_id'         => $commenter_id,
			) );
		}

		// If this is a reply to a comment, also notify the comment author (unless they own the post — already notified above).
		if ( $parent_comment_id ) {
			$comment_author = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d AND type = 'activity_comment'",
				$parent_comment_id
			) );

			if ( $comment_author && $comment_author !== $owner ) {
				$this->add( array(
					'user_id'           => $comment_author,
					'item_id'           => $commenter_id,
					'secondary_item_id' => $comment_id,
					'component_name'    => 'activity',
					'component_action'  => 'comment_reply',
					'sender_id'         => $commenter_id,
				) );
			}
		}
	}

	public function notify_follow( int $follower_id, int $followee_id ): void {
		$this->add( array(
			'user_id'          => $followee_id,
			'item_id'          => $follower_id,
			'component_name'   => 'friends',
			'component_action' => 'new_follower',
			'sender_id'        => $follower_id,
		) );
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_get_notifications(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		$page        = max( 1, absint( $_POST['page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$unread_only = ! empty( $_POST['unread_only'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$limit       = 20;
		$user_id     = get_current_user_id();

		$notifications = $this->get_for_user( $user_id, $unread_only, $limit, $page );
		$total         = $this->get_total_count( $user_id, $unread_only );

		wp_send_json_success( array(
			'notifications' => $notifications,
			'total'         => $total,
			'page'          => $page,
			'perPage'       => $limit,
			'hasMore'       => ( $page * $limit ) < $total,
		) );
	}

	public function ajax_mark_read(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$ids     = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) // phpcs:ignore WordPress.Security.NonceVerification
			? array_map( 'absint', $_POST['ids'] ) // phpcs:ignore WordPress.Security.NonceVerification
			: array();

		$this->mark_read( $user_id, $ids );
		$this->invalidate_unread_cache( $user_id );
		wp_send_json_success( array( 'unreadCount' => $this->get_unread_count( $user_id ) ) );
	}

	public function ajax_unread_count(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_success( array( 'count' => 0 ) );
			return;
		}
		wp_send_json_success( array( 'count' => $this->get_unread_count( get_current_user_id() ) ) );
	}

	public function ajax_delete_notification(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid notification.', 'social-network-6' ) ), 400 );
		}

		$user_id = get_current_user_id();
		$deleted = $this->delete_notification( $id, $user_id );
		if ( $deleted ) {
			$this->invalidate_unread_cache( $user_id );
			wp_send_json_success( array( 'unreadCount' => $this->get_unread_count( $user_id ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not delete notification.', 'social-network-6' ) ), 403 );
		}
	}

	public function ajax_save_prefs(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$allowed = array_keys( self::TYPES );

		foreach ( $allowed as $action ) {
			$key = 'arshid6social_notify_' . $action;
			// phpcs:ignore WordPress.Security.NonceVerification
			$val = isset( $_POST[ $key ] ) ? '1' : '0';
			update_user_meta( $user_id, $key, $val );
		}

		// Email preferences.
		// phpcs:ignore WordPress.Security.NonceVerification
		$email_on = ! empty( $_POST['arshid6social_email_notifications'] );
		update_user_meta( $user_id, 'arshid6social_email_opt_out', $email_on ? '0' : '1' );

		// phpcs:ignore WordPress.Security.NonceVerification
		$digest = sanitize_key( $_POST['arshid6social_email_digest'] ?? 'daily' );
		if ( in_array( $digest, array( 'none', 'daily', 'weekly' ), true ) ) {
			update_user_meta( $user_id, 'arshid6social_email_digest', $digest );
		}

		wp_send_json_success( array( 'message' => __( 'Preferences saved.', 'social-network-6' ) ) );
	}

	// ── Digest emails ─────────────────────────────────────────────────────────

	public function send_daily_digest(): void {
		if ( 'daily' !== get_option( 'arshid6social_email_digest' ) ) {
			return;
		}
		$this->send_digest( 'daily' );
	}

	public function send_weekly_digest(): void {
		if ( 'weekly' !== get_option( 'arshid6social_email_digest' ) ) {
			return;
		}
		$this->send_digest( 'weekly' );
	}

	private function send_digest( string $period ): void {
		global $wpdb;

		$user_ids = array_map( 'intval', (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT DISTINCT user_id FROM {$wpdb->prefix}sn_notifications WHERE is_new = 1"
		) );

		if ( empty( $user_ids ) ) {
			return;
		}

		// Pre-warm WP user + usermeta caches for all recipients in two queries.
		update_meta_cache( 'user', $user_ids );

		$site_digest = get_option( 'arshid6social_email_digest', 'daily' );
		/* translators: %s: site name */
		$subject_tpl = sprintf( __( 'Your %s notifications digest', 'social-network-6' ), get_bloginfo( 'name' ) );

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id ); // hits WP internal cache, no extra query
			if ( ! $user ) {
				continue;
			}

			// get_user_meta() uses the pre-warmed cache — no per-user query.
			if ( get_user_meta( $user_id, 'arshid6social_email_opt_out', true ) ) {
				continue;
			}

			$digest = get_user_meta( $user_id, 'arshid6social_email_digest', true ) ?: $site_digest;
			if ( $digest !== $period ) {
				continue;
			}

			$notifications = $this->get_for_user( $user_id, true, 50 );
			if ( empty( $notifications ) ) {
				continue;
			}

			$body = $this->build_digest_email( $user, $notifications );
			wp_mail( $user->user_email, $subject_tpl, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
	}

	private function build_digest_email( \WP_User $user, array $notifications ): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url  = esc_url( home_url( '/' ) );
		$notif_url = esc_url( home_url( '/members/' . $user->user_nicename . '/notifications/' ) );

		$items = '';
		foreach ( $notifications as $n ) {
			$icon    = self::TYPES[ $n['componentAction'] ]['icon'] ?? '🔔';
			$items  .= '<tr><td style="padding:12px 0;border-bottom:1px solid #e5e7eb;">'
				. '<span style="font-size:1.2em;margin-right:8px;">' . esc_html( $icon ) . '</span>'
				. $n['description']
				. '<br><small style="color:#6b7280;">' . esc_html( $n['dateNotified'] ) . '</small>'
				. '</td></tr>';
		}

		return "<!DOCTYPE html><html><body style='font-family:sans-serif;max-width:600px;margin:auto;color:#111;'>
			<div style='background:#2563eb;padding:24px;border-radius:8px 8px 0 0;'>
				<h1 style='color:#fff;margin:0;font-size:1.25rem;'><a href='{$site_url}' style='color:#fff;text-decoration:none;'>{$site_name}</a></h1>
			</div>
			<div style='padding:24px;background:#fff;border:1px solid #e5e7eb;border-top:none;'>
				<p>" .
				/* translators: %s: user display name */
				sprintf( esc_html__( 'Hello %s,', 'social-network-6' ), esc_html( $user->display_name ) ) . "</p>
				<p>" . esc_html__( 'Here are your recent notifications:', 'social-network-6' ) . "</p>
				<table width='100%' cellpadding='0' cellspacing='0'>{$items}</table>
				<p style='margin-top:24px;'>
					<a href='{$notif_url}' style='background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;'>
					" . esc_html__( 'View All Notifications', 'social-network-6' ) . "
					</a>
				</p>
			</div>
		</body></html>";
	}

	public function register_rest_routes(): void {
		( new Notifications_REST() )->register_routes();
	}
}
