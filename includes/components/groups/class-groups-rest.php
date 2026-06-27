<?php
namespace Arshid6Social\Components\Groups;

/**
 * REST API controller for the Groups component.
 *
 * @package Arshid6Social\Components\Groups
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Groups_REST
 *
 * Provides /arshid6social/v1/groups endpoints.
 */
class Groups_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'groups';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'   => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
					'search' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
					'type'   => array( 'type' => 'string', 'default' => 'newest', 'sanitize_callback' => 'sanitize_key' ),
				),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'name'        => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'description' => array( 'type' => 'string', 'default' => '' ),
					'status'      => array( 'type' => 'string', 'default' => 'public', 'enum' => array( 'public', 'private', 'hidden' ) ),
				),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'can_delete_item' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/join', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'join' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/leave', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'leave' ),
			'permission_callback' => 'is_user_logged_in',
		) );
	}

	public function can_delete_item( \WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$component = ARSHID6SOCIAL()->component( 'groups' );
		if ( ! $component ) {
			return false;
		}
		$group_id = absint( $request['id'] );
		if ( ! $component->get_by_id( $group_id ) ) {
			return true; // Group not found; let the callback return a proper 404.
		}
		return $component->is_admin( get_current_user_id(), $group_id )
			|| current_user_can( 'arshid6social_manage_groups' );
	}

	public function get_items( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'groups' );
		if ( ! $component ) {
			return new \WP_Error( 'arshid6social_disabled', __( 'Groups component not active.', '6arshid social community' ), array( 'status' => 503 ) );
		}

		$data = $component->get_groups( array(
			'page'   => $request['page'],
			'search' => $request['search'],
			'type'   => $request['type'],
		) );

		$response = rest_ensure_response( $data['groups'] );
		$response->header( 'X-WP-Total', $data['total'] );
		$response->header( 'X-WP-TotalPages', $data['total_pages'] );
		return $response;
	}

	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'groups' );
		$group     = $component->get_by_id( (int) $request['id'] );
		if ( ! $group ) {
			return new \WP_Error( 'arshid6social_not_found', __( 'Group not found.', '6arshid social community' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $component->format_group( $group ) );
	}

	public function create_item( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'groups' );
		$group_id  = $component->create( array(
			'name'        => $request->get_param( 'name' ),
			'description' => wp_kses_post( $request->get_param( 'description' ) ),
			'status'      => $request->get_param( 'status' ),
		) );

		if ( ! $group_id ) {
			return new \WP_Error( 'arshid6social_create_failed', __( 'Failed to create group.', '6arshid social community' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $component->format_group( $component->get_by_id( $group_id ) ) );
	}

	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'groups' );
		$group_id  = (int) $request['id'];
		$group     = $component->get_by_id( $group_id );

		if ( ! $group ) {
			return new \WP_Error( 'arshid6social_not_found', __( 'Group not found.', '6arshid social community' ), array( 'status' => 404 ) );
		}

		$component->delete( $group_id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function join( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'groups' );
		$group_id  = (int) $request['id'];
		$group     = $component->get_by_id( $group_id );

		if ( ! $group ) {
			return new \WP_Error( 'arshid6social_not_found', __( 'Group not found.', '6arshid social community' ), array( 'status' => 404 ) );
		}

		$user_id      = get_current_user_id();
		$is_confirmed = ( 'public' === $group->status ) ? 1 : 0;
		$component->add_member( $group_id, $user_id, array( 'is_confirmed' => $is_confirmed ) );

		return rest_ensure_response( array( 'joined' => (bool) $is_confirmed, 'pending' => ! $is_confirmed ) );
	}

	public function leave( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'groups' );
		$group_id  = (int) $request['id'];
		$component->remove_member( $group_id, get_current_user_id() );
		return rest_ensure_response( array( 'left' => true ) );
	}
}
