<?php
namespace Arshid6Social\Components\Members;

/**
 * REST API controller for the Members component.
 *
 * @package Arshid6Social\Components\Members
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Members_REST
 *
 * Provides /arshid6social/v1/members endpoints: list, single, profile update.
 */
class Members_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'members';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'validate_callback' => fn( $v ) => is_numeric( $v ),
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_current_member' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * GET /arshid6social/v1/members — paginated member list.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'members' );
		if ( ! $component ) {
			return new \WP_Error( 'arshid6social_component_disabled', __( 'Members component is not active.', '6arshid-social-community-main' ), array( 'status' => 503 ) );
		}

		$data = $component->get_members(
			array(
				'page'   => $request->get_param( 'page' ) ?: 1,
				'number' => $request->get_param( 'per_page' ) ?: get_option( 'arshid6social_members_per_page', 20 ),
				'search' => $request->get_param( 'search' ) ?: '',
				'type'   => $request->get_param( 'type' ) ?: 'newest',
			)
		);

		$response = rest_ensure_response( $data['members'] );
		$response->header( 'X-WP-Total', $data['total'] );
		$response->header( 'X-WP-TotalPages', $data['total_pages'] );

		return $response;
	}

	/**
	 * GET /arshid6social/v1/members/{id} — single member.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$user = get_userdata( $request->get_param( 'id' ) );
		if ( ! $user ) {
			return new \WP_Error( 'arshid6social_member_not_found', __( 'Member not found.', '6arshid-social-community-main' ), array( 'status' => 404 ) );
		}

		$component = ARSHID6SOCIAL()->component( 'members' );
		return rest_ensure_response( $component->format_member( $user ) );
	}

	/**
	 * GET /arshid6social/v1/members/me — authenticated user's own profile.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_current_member( $request ): \WP_REST_Response|\WP_Error {
		$user      = wp_get_current_user();
		$component = ARSHID6SOCIAL()->component( 'members' );
		return rest_ensure_response( $component->format_member( $user ) );
	}

	/**
	 * PATCH /arshid6social/v1/members/{id} — update xProfile fields.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		$user_id = $request->get_param( 'id' );
		$fields  = $request->get_param( 'fields' );

		if ( ! is_array( $fields ) ) {
			return new \WP_Error( 'arshid6social_invalid_data', __( 'Fields must be an object.', '6arshid-social-community-main' ), array( 'status' => 400 ) );
		}

		$component = ARSHID6SOCIAL()->component( 'members' );
		$errors    = $component->xprofile->save_profile_data( $user_id, $fields );

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'arshid6social_validation_error', __( 'Validation failed.', '6arshid-social-community-main' ), array( 'status' => 422, 'errors' => $errors ) );
		}

		return rest_ensure_response( $component->format_member( get_userdata( $user_id ) ) );
	}

	/**
	 * Permission check for updating a member profile.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public function update_item_permissions_check( $request ): bool|\WP_Error {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'arshid6social_unauthenticated', __( 'Authentication required.', '6arshid-social-community-main' ), array( 'status' => 401 ) );
		}

		$target_user_id = (int) $request->get_param( 'id' );
		$current_user   = get_current_user_id();

		if ( $current_user !== $target_user_id && ! current_user_can( 'arshid6social_manage_members' ) ) {
			return new \WP_Error( 'arshid6social_forbidden', __( 'You can only edit your own profile.', '6arshid-social-community-main' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Returns REST collection parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return array(
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint' ),
			'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100, 'sanitize_callback' => 'absint' ),
			'search'   => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'type'     => array(
				'type'              => 'string',
				'default'           => 'newest',
				'enum'              => array( 'newest', 'active', 'alphabetical' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}
}
