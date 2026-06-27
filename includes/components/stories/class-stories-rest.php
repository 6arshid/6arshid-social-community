<?php
namespace Arshid6Social\Components\Stories;

/**
 * Stories REST API endpoints.
 *
 * @package Arshid6Social\Components\Stories
 */

defined( 'ABSPATH' ) || exit;

class Stories_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'stories';

	private Stories $stories;

	public function __construct( Stories $stories ) {
		$this->stories = $stories;
	}

	public function register_routes(): void {
		// GET /stories/tray — viewer's tray.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/tray', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tray' ),
				'permission_callback' => '__return_true',
			),
		) );

		// POST /stories — create a story.
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_story' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'privacy'      => array( 'default' => 'public',  'sanitize_callback' => 'sanitize_key' ),
					'media_type'   => array( 'default' => 'text',    'sanitize_callback' => 'sanitize_key' ),
					'text_content' => array( 'default' => '',        'sanitize_callback' => 'sanitize_textarea_field' ),
					'bg_color'     => array( 'default' => '#2563eb', 'sanitize_callback' => 'sanitize_hex_color' ),
					'duration'     => array( 'default' => 5,         'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// DELETE /stories/{id}.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_story' ),
				'permission_callback' => array( $this, 'can_delete_story' ),
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// GET /stories/{id}/items.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/items', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// GET /stories/{id}/viewers — owner only.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/viewers', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_viewers' ),
				'permission_callback' => array( $this, 'can_view_viewers' ),
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// POST /stories/{id}/view.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/view', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mark_viewed' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// POST /stories/{id}/react.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/react', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'react' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'       => array( 'required' => true,  'sanitize_callback' => 'absint' ),
					'reaction' => array( 'default'  => '❤️', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			),
		) );

		// POST /stories/{id}/reply.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/reply', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reply' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'      => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'message' => array( 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			),
		) );

		// POST /stories/{id}/report.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/report', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'report' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'     => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'reason' => array( 'default' => 'spam', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			),
		) );

		// GET/POST /stories/close-friends.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/close-friends', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_close_friends' ),
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_close_friend' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'friend_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'add'       => array( 'default'  => true ),
				),
			),
		) );

		// POST /stories/mute, DELETE /stories/mute.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/mute', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mute' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'user_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unmute' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'user_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// GET/POST/DELETE /stories/highlights.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/highlights', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_highlights' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'user_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ),
				),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_highlight' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'title'     => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'cover_url' => array( 'default'  => '',   'sanitize_callback' => 'esc_url_raw' ),
				),
			),
		) );

		// DELETE /stories/highlights/{id}.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/highlights/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_highlight' ),
				'permission_callback' => array( $this, 'can_delete_highlight' ),
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// POST /stories/highlights/{id}/add.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/highlights/(?P<id>[\d]+)/add', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_to_highlight' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'       => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'story_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );
	}

	// ── Callbacks ─────────────────────────────────────────────────────────────

	public function get_tray( \WP_REST_Request $request ): \WP_REST_Response {
		$viewer = get_current_user_id();
		$tray   = $this->stories->get_tray( $viewer );
		return rest_ensure_response( array( 'stories' => $tray ) );
	}

	public function create_story( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();

		if ( ! arshid6social_check_rate_limit( 'arshid6social_rl_stories', $user_id, (int) get_option( 'arshid6social_stories_rate_limit', 20 ) ) ) {
			return new \WP_Error( 'rate_limited', __( 'Too many stories.', '6arshid social community' ), array( 'status' => 429 ) );
		}

		$item = array(
			'media_type'   => $request->get_param( 'media_type' ),
			'text_content' => $request->get_param( 'text_content' ),
			'bg_color'     => $request->get_param( 'bg_color' ) ?? '#2563eb',
			'duration'     => $request->get_param( 'duration' ),
		);

		$story_id = $this->stories->create( $user_id, $request->get_param( 'privacy' ), array( $item ) );
		if ( ! $story_id ) {
			return new \WP_Error( 'create_failed', __( 'Could not create story.', '6arshid social community' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'story_id' => $story_id ) );
	}

	public function delete_story( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$ok = $this->stories->delete( $request->get_param( 'id' ), get_current_user_id() );
		return $ok
			? rest_ensure_response( array( 'deleted' => true ) )
			: new \WP_Error( 'not_found', __( 'Story not found or permission denied.', '6arshid social community' ), array( 'status' => 404 ) );
	}

	public function get_items( $request ): \WP_REST_Response {
		$items = $this->stories->get_items( $request->get_param( 'id' ) );
		return rest_ensure_response( array( 'items' => $items ) );
	}

	public function get_viewers( \WP_REST_Request $request ): \WP_REST_Response {
		$viewers = $this->stories->get_viewers( $request->get_param( 'id' ), get_current_user_id() );
		return rest_ensure_response( array( 'viewers' => $viewers ) );
	}

	public function mark_viewed( \WP_REST_Request $request ): \WP_REST_Response {
		$this->stories->mark_viewed( $request->get_param( 'id' ), get_current_user_id() );
		return rest_ensure_response( array( 'viewed' => true ) );
	}

	public function react( \WP_REST_Request $request ): \WP_REST_Response {
		$this->stories->react( $request->get_param( 'id' ), get_current_user_id(), $request->get_param( 'reaction' ) );
		return rest_ensure_response( array( 'reacted' => true ) );
	}

	public function reply( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$thread_id = $this->stories->reply(
			$request->get_param( 'id' ),
			get_current_user_id(),
			$request->get_param( 'message' )
		);
		return $thread_id
			? rest_ensure_response( array( 'thread_id' => $thread_id ) )
			: new \WP_Error( 'reply_failed', __( 'Could not send reply.', '6arshid social community' ), array( 'status' => 400 ) );
	}

	public function report( \WP_REST_Request $request ): \WP_REST_Response {
		\Arshid6Social\Components\Moderation\Moderation::add_report(
			get_current_user_id(),
			$request->get_param( 'id' ),
			'story',
			$request->get_param( 'reason' )
		);
		return rest_ensure_response( array( 'reported' => true ) );
	}

	public function get_close_friends( \WP_REST_Request $request ): \WP_REST_Response {
		$ids     = $this->stories->get_close_friends( get_current_user_id() );
		$members = ARSHID6SOCIAL()->component( 'members' );
		$data    = array();
		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user && $members ) {
				$data[] = $members->format_member( $user );
			}
		}
		return rest_ensure_response( array( 'close_friends' => $data ) );
	}

	public function toggle_close_friend( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id   = get_current_user_id();
		$friend_id = $request->get_param( 'friend_id' );
		$add       = (bool) $request->get_param( 'add' );

		if ( $add ) {
			$this->stories->add_close_friend( $user_id, $friend_id );
		} else {
			$this->stories->remove_close_friend( $user_id, $friend_id );
		}

		return rest_ensure_response( array( 'is_close_friend' => $add ) );
	}

	public function mute( \WP_REST_Request $request ): \WP_REST_Response {
		$this->stories->mute( get_current_user_id(), $request->get_param( 'user_id' ) );
		return rest_ensure_response( array( 'muted' => true ) );
	}

	public function unmute( \WP_REST_Request $request ): \WP_REST_Response {
		$this->stories->unmute( get_current_user_id(), $request->get_param( 'user_id' ) );
		return rest_ensure_response( array( 'muted' => false ) );
	}

	public function get_highlights( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = $request->get_param( 'user_id' ) ?: get_current_user_id();
		return rest_ensure_response( array( 'highlights' => $this->stories->get_highlights( $user_id ) ) );
	}

	public function create_highlight( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = $this->stories->create_highlight(
			get_current_user_id(),
			$request->get_param( 'title' ),
			$request->get_param( 'cover_url' )
		);
		return $id
			? rest_ensure_response( array( 'highlight_id' => $id ) )
			: new \WP_Error( 'create_failed', __( 'Could not create highlight.', '6arshid social community' ), array( 'status' => 500 ) );
	}

	public function delete_highlight( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$ok = $this->stories->delete_highlight( $request->get_param( 'id' ), get_current_user_id() );
		return $ok
			? rest_ensure_response( array( 'deleted' => true ) )
			: new \WP_Error( 'not_found', __( 'Highlight not found.', '6arshid social community' ), array( 'status' => 404 ) );
	}

	public function add_to_highlight( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$ok = $this->stories->add_story_to_highlight(
			$request->get_param( 'story_id' ),
			$request->get_param( 'id' ),
			get_current_user_id()
		);
		return $ok
			? rest_ensure_response( array( 'added' => true ) )
			: new \WP_Error( 'failed', __( 'Could not add to highlight.', '6arshid social community' ), array( 'status' => 400 ) );
	}

	// ── Permission callbacks ──────────────────────────────────────────────────

	public function can_delete_story( \WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		global $wpdb;
		$owner_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_stories WHERE id = %d",
			absint( $request['id'] )
		) );
		if ( ! $owner_id ) {
			return true; // Resource not found; let the callback return a 404.
		}
		return $owner_id === get_current_user_id() || current_user_can( 'arshid6social_manage_activity' );
	}

	public function can_view_viewers( \WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		global $wpdb;
		$owner_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_stories WHERE id = %d",
			absint( $request['id'] )
		) );
		if ( ! $owner_id ) {
			return true; // Resource not found; let the callback return an empty result.
		}
		return $owner_id === get_current_user_id();
	}

	public function can_delete_highlight( \WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		global $wpdb;
		$owner_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_story_highlights WHERE id = %d",
			absint( $request['id'] )
		) );
		if ( ! $owner_id ) {
			return true; // Resource not found; let the callback return a 404.
		}
		return $owner_id === get_current_user_id();
	}
}
