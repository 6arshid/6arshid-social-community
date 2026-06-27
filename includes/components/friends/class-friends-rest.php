<?php
namespace Arshid6Social\Components\Friends;

/**
 * REST API for the Friends & Follow component.
 *
 * @package Arshid6Social\Components\Friends
 */

defined( 'ABSPATH' ) || exit;

class Friends_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'friends';

	public function register_routes(): void {
		// GET /arshid6social/v1/friends/{user_id}/status?with={other_user_id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/status', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_status' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		// POST /arshid6social/v1/friends/{user_id}/request
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/request', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'send_request' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		// POST /arshid6social/v1/friends/{user_id}/accept
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/accept', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'accept_request' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		// DELETE /arshid6social/v1/friends/{user_id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'remove' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		// POST/DELETE /arshid6social/v1/friends/{user_id}/follow
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/follow', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'follow' ),
				'permission_callback' => array( $this, 'can_follow_user' ),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unfollow' ),
				'permission_callback' => array( $this, 'can_follow_user' ),
			),
		) );

		// POST/DELETE /arshid6social/v1/friends/{user_id}/block
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/block', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'block' ),
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unblock' ),
				'permission_callback' => 'is_user_logged_in',
			),
		) );

		// GET /arshid6social/v1/friends/suggestions
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/suggestions', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'suggestions' ),
			'permission_callback' => 'is_user_logged_in',
		) );
	}

	public function can_follow_user( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$target = absint( $request['id'] );
		if ( $target === get_current_user_id() ) {
			return new \WP_Error( 'arshid6social_self_follow', __( 'You cannot follow yourself.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}
		if ( ! get_userdata( $target ) ) {
			return true; // Target user not found; let the callback return a 404.
		}
		return true;
	}

	public function get_status( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$target    = (int) $request['id'];
		$current   = get_current_user_id();
		$status    = $component->get_friendship_status( $current, $target );
		$following = $component->is_following( $current, $target );
		$blocked   = $component->is_blocked( $current, $target );

		return rest_ensure_response( compact( 'status', 'following', 'blocked' ) );
	}

	public function send_request( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$result    = $component->send_request( get_current_user_id(), (int) $request['id'] );

		if ( true !== $result ) {
			return new \WP_Error( 'arshid6social_friend_error', $result, array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'status' => 'pending_sent' ) );
	}

	public function accept_request( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$component->accept_request( get_current_user_id(), (int) $request['id'] );
		return rest_ensure_response( array( 'status' => 'friends' ) );
	}

	public function remove( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$component->remove_friend( get_current_user_id(), (int) $request['id'] );
		return rest_ensure_response( array( 'status' => 'not_friends' ) );
	}

	public function follow( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$component->follow( get_current_user_id(), (int) $request['id'] );
		return rest_ensure_response( array( 'following' => true ) );
	}

	public function unfollow( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$component->unfollow( get_current_user_id(), (int) $request['id'] );
		return rest_ensure_response( array( 'following' => false ) );
	}

	public function block( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$component->block( get_current_user_id(), (int) $request['id'] );
		return rest_ensure_response( array( 'blocked' => true ) );
	}

	public function unblock( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'friends' );
		$component->unblock( get_current_user_id(), (int) $request['id'] );
		return rest_ensure_response( array( 'blocked' => false ) );
	}

	public function suggestions( $request ): \WP_REST_Response {
		$component    = ARSHID6SOCIAL()->component( 'friends' );
		$members_comp = ARSHID6SOCIAL()->component( 'members' );
		$suggestions  = $component->get_suggestions( get_current_user_id() );

		$data = array();
		foreach ( $suggestions as $uid ) {
			$user = get_userdata( $uid );
			if ( $user && $members_comp ) {
				$data[] = $members_comp->format_member( $user );
			}
		}

		return rest_ensure_response( $data );
	}
}
