<?php
namespace Arshid6Social\Components\Messages;

/**
 * Private Messaging component.
 *
 * @package Arshid6Social\Components\Messages
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Messages
 *
 * Handles one-to-one and group conversations, threads, recipients, and real-time hooks.
 */
class Messages {

	public function __construct() {
		$this->hooks();
	}

	// ── UID helpers ──────────────────────────────────────────────────────────

	public static function get_thread_uid( int $thread_id ): string {
		global $wpdb;
		$uid = (string) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT uniqid FROM {$wpdb->prefix}sn_messages_threads WHERE id = %d",
			$thread_id
		) );
		if ( ! $uid ) {
			$uid = wp_generate_uuid4();
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_messages_threads',
				array( 'uniqid' => $uid ),
				array( 'id'     => $thread_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
		return $uid;
	}

	public static function thread_id_from_uid( string $uid ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_messages_threads WHERE uniqid = %s",
			sanitize_text_field( $uid )
		) );
	}

	public static function get_user_uid( int $user_id ): string {
		$uid = (string) get_user_meta( $user_id, 'arshid6social_uid', true );
		if ( ! $uid ) {
			$uid = wp_generate_uuid4();
			update_user_meta( $user_id, 'arshid6social_uid', $uid );
		}
		return $uid;
	}

	public static function user_id_from_uid( string $uid ): int {
		$users = get_users( array(
			'meta_key'   => 'arshid6social_uid',
			'meta_value' => sanitize_text_field( $uid ),
			'number'     => 1,
			'fields'     => 'ID',
		) );
		return $users ? (int) $users[0] : 0;
	}

	private function hooks(): void {
		add_action( 'wp_ajax_arshid6social_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_arshid6social_get_threads', array( $this, 'ajax_get_threads' ) );
		add_action( 'wp_ajax_arshid6social_get_thread_messages', array( $this, 'ajax_get_thread_messages' ) );
		add_action( 'wp_ajax_arshid6social_get_or_create_thread', array( $this, 'ajax_get_or_create_thread' ) );
		add_action( 'wp_ajax_arshid6social_delete_thread', array( $this, 'ajax_delete_thread' ) );
		add_action( 'wp_ajax_arshid6social_mark_thread_read', array( $this, 'ajax_mark_thread_read' ) );
		add_action( 'wp_ajax_arshid6social_unread_count', array( $this, 'ajax_unread_count' ) );
		add_action( 'wp_ajax_arshid6social_edit_message', array( $this, 'ajax_edit_message' ) );
		add_action( 'wp_ajax_arshid6social_delete_message', array( $this, 'ajax_delete_message' ) );
		add_action( 'wp_ajax_arshid6social_poll_messages', array( $this, 'ajax_poll_messages' ) );

		// Heartbeat API for real-time message polling.
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );

		// Rewrite rules for messages page.
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_messages_page' ) );

		// Add body class on the messages page so CSS can hide the right sidebar.
		add_filter( 'body_class', array( $this, 'add_messages_body_class' ) );
	}

	public function add_messages_body_class( array $classes ): array {
		$messages_page_id = (int) get_option( 'arshid6social_page_messages', 0 );
		$post             = get_post();
		$on_messages      = (
			( $messages_page_id && is_page( $messages_page_id ) ) ||
			get_query_var( 'arshid6social_messages' ) ||
			( $post && is_singular() && has_shortcode( $post->post_content, 'arshid6social_messages' ) )
		);
		if ( $on_messages ) {
			$classes[] = 'arshid6social-on-messages';
		}
		return $classes;
	}

	public function add_rewrite_rules(): void {
		// /messages/ inbox is a WordPress page with [arshid6social_messages] shortcode — no rule needed.
		add_rewrite_rule( '^messages/compose/?$', 'index.php?arshid6social_messages=compose', 'top' );
		add_rewrite_rule( '^messages/thread/([a-zA-Z0-9_-]+)/?$', 'index.php?arshid6social_messages=thread&arshid6social_thread_id=$matches[1]', 'top' );
	}

	public function register_query_vars( array $vars ): array {
		$vars[] = 'arshid6social_messages';
		$vars[] = 'arshid6social_thread_id';
		return $vars;
	}

	public function handle_messages_page(): void {
		if ( ! get_query_var( 'arshid6social_messages' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$messages_page_id = (int) get_option( 'arshid6social_page_messages', 0 );
			$redirect         = $messages_page_id ? get_permalink( $messages_page_id ) : home_url( '/messages/' );
			wp_safe_redirect( wp_login_url( $redirect ) );
			exit;
		}

		global $arshid6social_is_page, $post, $wp_query;
		$arshid6social_is_page = true;

		// Prime $post / $wp_query so the theme renders its full template.
		$messages_page_id = (int) get_option( 'arshid6social_page_messages', 0 );
		if ( $messages_page_id ) {
			$messages_post = get_post( $messages_page_id );
			if ( $messages_post instanceof \WP_Post ) {
				$post = $messages_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				$wp_query->queried_object    = $post;
				$wp_query->queried_object_id = $post->ID;
				$wp_query->is_page           = true;
				$wp_query->is_singular       = true;
				$wp_query->is_archive        = false;
				$wp_query->is_home           = false;
				$wp_query->is_404            = false;
				$wp_query->post              = $post;
				$wp_query->posts             = array( $post );
				$wp_query->found_posts       = 1;
				$wp_query->post_count        = 1;
				$wp_query->max_num_pages     = 1;
				setup_postdata( $post );
			}
		}

		remove_shortcode( 'arshid6social_messages' );

		$loader     = \Arshid6Social\Template_Loader::instance();
		$component  = $this;
		$active_tab = sanitize_key( get_query_var( 'arshid6social_messages' ) );
		$thread_uid = sanitize_text_field( (string) get_query_var( 'arshid6social_thread_id', '' ) );
		$thread_id  = $thread_uid ? self::thread_id_from_uid( $thread_uid ) : 0;

		// ?to=<uid> on compose tab — resolve to user ID for JS auto-open.
		$compose_recipient_id = 0;
		if ( 'compose' === $active_tab ) {
			$user_uid = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( $user_uid ) {
				$compose_recipient_id = self::user_id_from_uid( $user_uid );
			}
		}

		add_filter(
			'the_content',
			static function () use ( $loader, $component, $active_tab, $thread_id, $compose_recipient_id ): string {
				return $loader->get_template(
					'messages/inbox.php',
					array(
						'component'            => $component,
						'active_tab'           => $active_tab,
						'thread_id'            => $thread_id,
						'compose_recipient_id' => $compose_recipient_id,
					),
					true
				);
			},
			99
		);

		// Let WordPress load the theme's normal page template.
	}

	/**
	 * Creates a new message thread and sends the first message.
	 *
	 * @param int[]  $recipient_ids   Recipient user IDs.
	 * @param string $subject         Thread subject.
	 * @param string $content         First message content.
	 * @param int    $sender_id       Sender user ID (defaults to current user).
	 * @return int|false Thread ID or false on failure.
	 */
	public function start_thread( array $recipient_ids, string $subject, string $content, int $sender_id = 0 ): int|false {
		global $wpdb;

		$sender_id = $sender_id ?: get_current_user_id();

		// Validate recipients — filter blocked users.
		$friends_comp = ARSHID6SOCIAL()->component( 'friends' );
		$filtered     = array();
		foreach ( $recipient_ids as $rid ) {
			$rid = absint( $rid );
			if ( $rid && $rid !== $sender_id && get_userdata( $rid ) ) {
				if ( ! $friends_comp || ! $friends_comp->is_blocked( $sender_id, $rid ) ) {
					$filtered[] = $rid;
				}
			}
		}

		if ( empty( $filtered ) ) {
			return false;
		}

		// Check Akismet for the message.
		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );

		// Insert thread.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_messages_threads',
			array(
				'subject'      => sanitize_text_field( $subject ),
				'is_group'     => count( $filtered ) > 1 ? 1 : 0,
				'date_created' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s' )
		);
		$thread_id = (int) $wpdb->insert_id;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_messages_threads',
			array( 'uniqid' => wp_generate_uuid4() ),
			array( 'id'     => $thread_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Add sender as recipient.
		$all_participants = array_merge( array( $sender_id ), $filtered );
		foreach ( $all_participants as $participant_id ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_messages_recipients',
				array(
					'thread_id'   => $thread_id,
					'user_id'     => $participant_id,
					'unread_count' => $participant_id !== $sender_id ? 1 : 0,
					'sender_only' => 0,
					'is_deleted'  => 0,
				),
				array( '%d', '%d', '%d', '%d', '%d' )
			);
		}

		// Send first message.
		$this->add_message_to_thread( $thread_id, $sender_id, $content );

		do_action( 'arshid6social_thread_created', $thread_id, $sender_id, $filtered );

		return $thread_id;
	}

	/**
	 * Adds a message to an existing thread.
	 *
	 * @param int    $thread_id Thread ID.
	 * @param int    $sender_id Sender ID.
	 * @param string $content   Message content.
	 * @return int|false Message ID or false.
	 */
	public function add_message_to_thread( int $thread_id, int $sender_id, string $content ): int|false {
		global $wpdb;

		// Ensure sender is a participant.
		$is_participant = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_messages_recipients WHERE thread_id = %d AND user_id = %d AND is_deleted = 0",
				$thread_id,
				$sender_id
			)
		);

		if ( ! $is_participant ) {
			return false;
		}

		$content = wp_kses_post( $content );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_messages',
			array(
				'thread_id'  => $thread_id,
				'sender_id'  => $sender_id,
				'message'    => $content,
				'date_sent'  => current_time( 'mysql' ),
				'is_deleted' => 0,
			),
			array( '%d', '%d', '%s', '%s', '%d' )
		);
		$message_id = (int) $wpdb->insert_id;

		// Increment unread count for all other participants.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}sn_messages_recipients
				 SET unread_count = unread_count + 1
				 WHERE thread_id = %d AND user_id != %d AND is_deleted = 0",
				$thread_id,
				$sender_id
			)
		);

		do_action( 'arshid6social_message_sent', $message_id, $thread_id, $sender_id );

		return $message_id;
	}

	/**
	 * Returns threads for a user's inbox.
	 *
	 * @param int $user_id  User ID.
	 * @param int $page     Pagination page.
	 * @return array<string, mixed>
	 */
	public function get_threads( int $user_id, int $page = 1, string $search = '' ): array {
		global $wpdb;

		$per_page = 10;
		$offset   = ( $page - 1 ) * $per_page;

		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$threads = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT t.*, r.unread_count
					 FROM {$wpdb->prefix}sn_messages_threads t
					 JOIN {$wpdb->prefix}sn_messages_recipients r ON r.thread_id = t.id
					 WHERE r.user_id = %d AND r.is_deleted = 0
					   AND (
					       t.subject LIKE %s
					       OR EXISTS (
					           SELECT 1 FROM {$wpdb->prefix}sn_messages m2
					           WHERE m2.thread_id = t.id AND m2.message LIKE %s AND m2.is_deleted = 0
					       )
					       OR EXISTS (
					           SELECT 1 FROM {$wpdb->prefix}sn_messages_recipients r2
					           JOIN {$wpdb->users} u ON u.ID = r2.user_id
					           WHERE r2.thread_id = t.id AND r2.user_id != %d AND r2.is_deleted = 0
					             AND u.display_name LIKE %s
					       )
					   )
					 ORDER BY t.date_created DESC
					 LIMIT %d OFFSET %d",
					$user_id, $like, $like, $user_id, $like, $per_page, $offset
				)
			);

			$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT COUNT(*)
					 FROM {$wpdb->prefix}sn_messages_threads t
					 JOIN {$wpdb->prefix}sn_messages_recipients r ON r.thread_id = t.id
					 WHERE r.user_id = %d AND r.is_deleted = 0
					   AND (
					       t.subject LIKE %s
					       OR EXISTS (
					           SELECT 1 FROM {$wpdb->prefix}sn_messages m2
					           WHERE m2.thread_id = t.id AND m2.message LIKE %s AND m2.is_deleted = 0
					       )
					       OR EXISTS (
					           SELECT 1 FROM {$wpdb->prefix}sn_messages_recipients r2
					           JOIN {$wpdb->users} u ON u.ID = r2.user_id
					           WHERE r2.thread_id = t.id AND r2.user_id != %d AND r2.is_deleted = 0
					             AND u.display_name LIKE %s
					       )
					   )",
					$user_id, $like, $like, $user_id, $like
				)
			);
		} else {
			$threads = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT t.*, r.unread_count
					 FROM {$wpdb->prefix}sn_messages_threads t
					 JOIN {$wpdb->prefix}sn_messages_recipients r ON r.thread_id = t.id
					 WHERE r.user_id = %d AND r.is_deleted = 0
					 ORDER BY t.date_created DESC
					 LIMIT %d OFFSET %d",
					$user_id, $per_page, $offset
				)
			);

			$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}sn_messages_recipients WHERE user_id = %d AND is_deleted = 0",
					$user_id
				)
			);
		}

		$total_pages   = (int) ceil( $total / $per_page );
		$total_unread  = $this->get_unread_count( $user_id );

		return array(
			'threads'      => array_map( array( $this, 'format_thread' ), $threads ),
			'total'        => $total,
			'total_pages'  => $total_pages,
			'page'         => $page,
			'has_more'     => $page < $total_pages,
			'total_unread' => $total_unread,
		);
	}

	/**
	 * Returns messages within a thread.
	 *
	 * @param int $thread_id Thread ID.
	 * @param int $user_id   Requesting user (access check).
	 * @param int $page      Pagination page.
	 * @return array<string, mixed>
	 */
	public function get_thread_messages( int $thread_id, int $user_id, int $page = 1, string $order = 'DESC', int $per_page = 0 ): array {
		global $wpdb;

		// Access check.
		$is_participant = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_messages_recipients WHERE thread_id = %d AND user_id = %d AND is_deleted = 0",
				$thread_id,
				$user_id
			)
		);

		if ( ! $is_participant ) {
			return array( 'error' => __( 'Access denied.', 'social-network-6' ) );
		}

		$order    = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$per_page = $per_page > 0 ? $per_page : (int) get_option( 'arshid6social_messages_per_page', 10 );
		$per_page = min( 50, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.* FROM {$wpdb->prefix}sn_messages m
				 WHERE m.thread_id = %d AND m.is_deleted = 0
				   AND NOT EXISTS (
				       SELECT 1 FROM {$wpdb->prefix}sn_messages_hidden h
				       WHERE h.message_id = m.id AND h.user_id = %d
				   )
				 ORDER BY m.date_sent {$order} LIMIT %d OFFSET %d",
				$thread_id,
				$user_id,
				$per_page,
				$offset
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sn_messages m
				 WHERE m.thread_id = %d AND m.is_deleted = 0
				   AND NOT EXISTS (
				       SELECT 1 FROM {$wpdb->prefix}sn_messages_hidden h
				       WHERE h.message_id = m.id AND h.user_id = %d
				   )",
				$thread_id,
				$user_id
			)
		);

		// Mark as read.
		$this->mark_thread_read( $thread_id, $user_id );

		return array(
			'messages'    => array_map( array( $this, 'format_message' ), $messages ),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
		);
	}

	/**
	 * Finds an existing 1-to-1 thread between two users, or creates one.
	 *
	 * @param int $user_id      Current user ID.
	 * @param int $recipient_id Recipient user ID.
	 * @return int Thread ID.
	 */
	public function get_or_create_thread( int $user_id, int $recipient_id ): int {
		global $wpdb;

		// Look for an existing non-group, non-deleted thread shared by both users.
		$thread_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT r1.thread_id
				 FROM {$wpdb->prefix}sn_messages_recipients r1
				 JOIN {$wpdb->prefix}sn_messages_recipients r2 ON r1.thread_id = r2.thread_id
				 JOIN {$wpdb->prefix}sn_messages_threads t ON t.id = r1.thread_id
				 WHERE r1.user_id = %d AND r2.user_id = %d
				   AND r1.is_deleted = 0 AND r2.is_deleted = 0
				   AND t.is_group = 0
				 ORDER BY t.date_created DESC
				 LIMIT 1",
				$user_id,
				$recipient_id
			)
		);

		if ( $thread_id ) {
			return (int) $thread_id;
		}

		// Create a new empty thread (no first message — user types their own).
		$recipient_user = get_userdata( $recipient_id );
		$subject        = $recipient_user ? $recipient_user->display_name : '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_messages_threads',
			array(
				'subject'      => sanitize_text_field( $subject ),
				'is_group'     => 0,
				'date_created' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s' )
		);
		$new_thread_id = (int) $wpdb->insert_id;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_messages_threads',
			array( 'uniqid' => wp_generate_uuid4() ),
			array( 'id'     => $new_thread_id ),
			array( '%s' ),
			array( '%d' )
		);

		foreach ( array( $user_id, $recipient_id ) as $participant_id ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_messages_recipients',
				array(
					'thread_id'    => $new_thread_id,
					'user_id'      => $participant_id,
					'unread_count' => 0,
					'sender_only'  => 0,
					'is_deleted'   => 0,
				),
				array( '%d', '%d', '%d', '%d', '%d' )
			);
		}

		do_action( 'arshid6social_thread_created', $new_thread_id, $user_id, array( $recipient_id ) );

		return $new_thread_id;
	}

	/**
	 * Marks a thread as read for a user.
	 *
	 * @param int $thread_id Thread ID.
	 * @param int $user_id   User ID.
	 */
	public function mark_thread_read( int $thread_id, int $user_id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_messages_recipients',
			array( 'unread_count' => 0 ),
			array( 'thread_id' => $thread_id, 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Returns the total unread message count for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_unread_count( int $user_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT SUM(unread_count) FROM {$wpdb->prefix}sn_messages_recipients WHERE user_id = %d AND is_deleted = 0",
				$user_id
			)
		);
	}

	/**
	 * Formats a thread object for the frontend.
	 *
	 * @param object $thread Raw DB thread row.
	 * @return array<string, mixed>
	 */
	public function format_thread( object $thread ): array {
		global $wpdb;

		$last_message = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sn_messages WHERE thread_id = %d AND is_deleted = 0 ORDER BY date_sent DESC LIMIT 1",
				$thread->id
			)
		);

		$participants = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}sn_messages_recipients WHERE thread_id = %d AND is_deleted = 0",
				$thread->id
			)
		);

		$participant_data = array();
		$members_comp     = ARSHID6SOCIAL()->component( 'members' );
		foreach ( $participants as $pid ) {
			$user = get_userdata( $pid );
			if ( $user && $members_comp ) {
				$participant_data[] = array(
					'id'        => (int) $pid,
					'name'      => esc_html( $user->display_name ),
					'avatarUrl' => esc_url( $members_comp->avatar->get_avatar_url( (int) $pid, 40 ) ),
					'profileUrl' => esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ),
				);
			}
		}

		return array(
			'id'           => (int) $thread->id,
			'subject'      => esc_html( $thread->subject ),
			'isGroup'      => (bool) $thread->is_group,
			'unreadCount'  => (int) $thread->unread_count,
			'dateCreated'  => esc_attr( $thread->date_created ),
			'participants' => $participant_data,
			'lastMessage'  => $last_message ? array(
				'content'  => wp_kses_post( $last_message->message ),
				'dateSent' => esc_attr( $last_message->date_sent ),
				'senderId' => (int) $last_message->sender_id,
			) : null,
		);
	}

	/**
	 * Formats a message row for the frontend.
	 *
	 * @param object $message Raw DB message row.
	 * @return array<string, mixed>
	 */
	public function format_message( object $message ): array {
		$members_comp = ARSHID6SOCIAL()->component( 'members' );
		$user         = get_userdata( $message->sender_id );

		$att_feature = function_exists( 'arshid6social_eng' ) ? arshid6social_eng()->feature( 'messages_attachments' ) : null;
		$attachments = $att_feature ? $att_feature->get_for_message( (int) $message->id, get_current_user_id() ) : array();

		$msg_content = wp_kses_post( $message->message );
		try {
			$msg_content = (string) apply_filters( 'arshid6social_message_content', $msg_content );
		} catch ( \Throwable $e ) {
			// Content filter failed — use sanitised content as-is.
		}

		return array(
			'id'              => (int) $message->id,
			'threadId'        => (int) $message->thread_id,
			'senderId'        => (int) $message->sender_id,
			'senderName'      => $user ? esc_html( $user->display_name ) : '',
			'senderAvatar'    => $members_comp ? esc_url( $members_comp->avatar->get_avatar_url( (int) $message->sender_id, 40 ) ) : '',
			'senderProfileUrl' => $user ? esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ) : '',
			'message'         => $msg_content,
			'dateSent'        => esc_attr( $message->date_sent ),
			'isMine'          => is_user_logged_in() && ( (int) $message->sender_id === get_current_user_id() ),
			'isEdited'        => ! empty( $message->is_edited ),
			'attachments'     => $attachments,
		);
	}

	/**
	 * Heartbeat API handler — returns new message count since last poll.
	 *
	 * @param array<string, mixed> $response Response data.
	 * @param array<string, mixed> $data     Request data.
	 * @return array<string, mixed>
	 */
	public function heartbeat_received( array $response, array $data ): array {
		if ( isset( $data['arshid6social_messages_poll'] ) && is_user_logged_in() ) {
			$thread_id  = absint( $data['arshid6social_messages_poll']['thread_id'] );
			$last_id    = absint( $data['arshid6social_messages_poll']['last_message_id'] );
			$user_id    = get_current_user_id();

			if ( $thread_id && $last_id ) {
				global $wpdb;
				$new_messages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}sn_messages WHERE thread_id = %d AND id > %d AND is_deleted = 0 ORDER BY date_sent ASC",
						$thread_id,
						$last_id
					)
				);

				if ( $new_messages ) {
					$response['arshid6social_new_messages'] = array_map( array( $this, 'format_message' ), $new_messages );
				}
			}

			$response['arshid6social_unread_count'] = $this->get_unread_count( $user_id );
		}

		return $response;
	}

	// ── AJAX handlers ────────────────────────────────────────────────────────

	public function ajax_send_message(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		if ( get_user_meta( get_current_user_id(), 'arshid6social_suspended', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account has been suspended.', 'social-network-6' ) ), 403 );
		}

		// Rate limit.
		$user_id = get_current_user_id();
		$max     = (int) get_option( 'arshid6social_rate_limit_messages', 20 );
		$rl_key  = "arshid6social_rl_messages_{$user_id}";
		$count   = (int) get_transient( $rl_key );
		if ( $count >= $max ) {
			wp_send_json_error( array( 'message' => __( 'You are sending messages too quickly. Please wait.', 'social-network-6' ) ), 429 );
		}
		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );

		// phpcs:disable WordPress.Security.NonceVerification
		$thread_id    = absint( $_POST['thread_id'] ?? 0 );
		$recipient_id = absint( $_POST['recipient_id'] ?? 0 );
		$content      = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
		$subject      = sanitize_text_field( wp_unslash( $_POST['subject'] ?? __( 'New Message', 'social-network-6' ) ) );
		// phpcs:enable

		if ( empty( trim( wp_strip_all_tags( $content ) ) ) ) {
			$content = '';
		}

		if ( $thread_id ) {
			$message_id = $this->add_message_to_thread( $thread_id, $user_id, $content );
			wp_send_json_success( array( 'message_id' => $message_id ) );
		} elseif ( $recipient_id ) {
			$new_thread_id = $this->start_thread( array( $recipient_id ), $subject, $content, $user_id );
			if ( ! $new_thread_id ) {
				wp_send_json_error( array( 'message' => __( 'Could not send message.', 'social-network-6' ) ), 500 );
			}
			wp_send_json_success( array( 'thread_id' => $new_thread_id ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Recipient required.', 'social-network-6' ) ), 400 );
		}
	}

	public function ajax_get_threads(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$page   = max( 1, absint( $_POST['page'] ?? 1 ) );
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		// phpcs:enable
		wp_send_json_success( $this->get_threads( get_current_user_id(), $page, $search ) );
	}

	public function ajax_get_thread_messages(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$thread_id = absint( $_REQUEST['thread_id'] ?? 0 );
		$page      = max( 1, absint( $_REQUEST['page'] ?? 1 ) );
		$order     = sanitize_key( $_REQUEST['order'] ?? 'DESC' );
		$per_page  = min( 50, max( 1, absint( $_REQUEST['per_page'] ?? 10 ) ) );
		// phpcs:enable

		wp_send_json_success( $this->get_thread_messages( $thread_id, get_current_user_id(), $page, $order, $per_page ) );
	}

	public function ajax_get_or_create_thread(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		$recipient_id = absint( $_POST['recipient_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $recipient_id || ! get_userdata( $recipient_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid recipient.', 'social-network-6' ) ), 400 );
		}

		$thread_id  = $this->get_or_create_thread( get_current_user_id(), $recipient_id );

		if ( ! $thread_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create conversation.', 'social-network-6' ) ), 500 );
		}

		wp_send_json_success( array(
			'thread_id'  => $thread_id,
			'thread_uid' => self::get_thread_uid( $thread_id ),
		) );
	}

	public function ajax_delete_thread(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		global $wpdb;
		$thread_id      = absint( $_POST['thread_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$delete_for_both = ! empty( $_POST['delete_for_both'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id        = get_current_user_id();

		// Verify current user is a participant in this thread.
		$is_participant = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'sn_messages_recipients WHERE thread_id = %d AND user_id = %d',
			$thread_id,
			$user_id
		) );

		if ( ! $is_participant ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'social-network-6' ) ), 403 );
		}

		if ( $delete_for_both ) {
			// Soft-delete for all participants.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_messages_recipients',
				array( 'is_deleted' => 1 ),
				array( 'thread_id' => $thread_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			// Soft-delete for this user only.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_messages_recipients',
				array( 'is_deleted' => 1 ),
				array( 'thread_id' => $thread_id, 'user_id' => $user_id ),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Conversation deleted.', 'social-network-6' ) ) );
	}

	public function ajax_mark_thread_read(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		$thread_id = absint( $_POST['thread_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->mark_thread_read( $thread_id, get_current_user_id() );
		wp_send_json_success();
	}

	public function ajax_unread_count(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_success( array( 'count' => 0 ) );
		}

		wp_send_json_success( array( 'count' => $this->get_unread_count( get_current_user_id() ) ) );
	}

	public function ajax_edit_message(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		global $wpdb;
		$message_id = absint( $_POST['message_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$content    = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id    = get_current_user_id();

		if ( ! $message_id || empty( trim( wp_strip_all_tags( $content ) ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'social-network-6' ) ), 400 );
		}

		$message = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_messages WHERE id = %d AND is_deleted = 0",
			$message_id
		) );

		if ( ! $message || (int) $message->sender_id !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'social-network-6' ) ), 403 );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_messages',
			array(
				'message'   => $content,
				'is_edited' => 1,
				'edited_at' => current_time( 'mysql' ),
			),
			array( 'id' => $message_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => $content ) );
	}

	public function ajax_delete_message(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		global $wpdb;
		$message_id  = absint( $_POST['message_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$for_both    = ! empty( $_POST['delete_for_both'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id     = get_current_user_id();

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'social-network-6' ) ), 400 );
		}

		$message = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT m.*, r.thread_id as r_thread_id
			 FROM {$wpdb->prefix}sn_messages m
			 JOIN {$wpdb->prefix}sn_messages_recipients r ON r.thread_id = m.thread_id AND r.user_id = %d AND r.is_deleted = 0
			 WHERE m.id = %d AND m.is_deleted = 0",
			$user_id,
			$message_id
		) );

		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'social-network-6' ) ), 403 );
		}

		$is_sender = (int) $message->sender_id === $user_id;

		if ( $for_both && $is_sender ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_messages',
				array( 'is_deleted' => 1 ),
				array( 'id' => $message_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_messages_hidden',
				array(
					'message_id' => $message_id,
					'user_id'    => $user_id,
				),
				array( '%d', '%d' )
			);
		}

		wp_send_json_success();
	}

	public function ajax_poll_messages(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'social-network-6' ) ), 403 );
		}

		$thread_id = absint( $_POST['thread_id']       ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$last_id   = absint( $_POST['last_message_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id   = get_current_user_id();

		if ( ! $thread_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'social-network-6' ) ), 400 );
		}

		global $wpdb;

		$is_participant = (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_messages_recipients WHERE thread_id = %d AND user_id = %d AND is_deleted = 0",
			$thread_id,
			$user_id
		) );

		if ( ! $is_participant ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'social-network-6' ) ), 403 );
		}

		$new_messages = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_messages WHERE thread_id = %d AND id > %d AND is_deleted = 0 ORDER BY date_sent ASC",
			$thread_id,
			$last_id
		) );

		wp_send_json_success( array(
			'messages'     => array_map( array( $this, 'format_message' ), $new_messages ),
			'unread_count' => $this->get_unread_count( $user_id ),
		) );
	}

	/**
	 * Registers REST API routes.
	 */
	public function register_rest_routes(): void {
		( new Messages_REST() )->register_routes();
	}
}
