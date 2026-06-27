<?php
namespace Arshid6Social\Components\Verification;

/**
 * Verification REST API endpoints.
 *
 * @package Arshid6Social\Components\Verification
 */

defined( 'ABSPATH' ) || exit;

class Verification_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'verification';

	private Verification $verification;

	public function __construct( Verification $verification ) {
		$this->verification = $verification;
	}

	public function register_routes(): void {
		// GET /verification/status → current user's verification status.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/status', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'user_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// POST /verification/request → submit verification request.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/request', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_request' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'type'      => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
					'full_name' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'category'  => array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
					'links'     => array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			),
		) );

		// Admin: GET /verification/requests → list pending requests.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/requests', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_requests' ),
				'permission_callback' => array( $this, 'is_admin' ),
				'args'                => array(
					'status' => array( 'default' => 'pending', 'sanitize_callback' => 'sanitize_key' ),
					'page'   => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// Admin: POST /verification/requests/{id}/approve
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/requests/(?P<id>[\d]+)/approve', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_request' ),
				'permission_callback' => array( $this, 'is_admin' ),
				'args'                => array(
					'id'   => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'type' => array( 'default' => 'general', 'sanitize_callback' => 'sanitize_key' ),
				),
			),
		) );

		// Admin: POST /verification/requests/{id}/reject
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/requests/(?P<id>[\d]+)/reject', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject_request' ),
				'permission_callback' => array( $this, 'is_admin' ),
				'args'                => array(
					'id'     => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'reason' => array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			),
		) );

		// Admin: POST /verification/grant/{user_id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/grant/(?P<user_id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'grant' ),
				'permission_callback' => array( $this, 'is_admin' ),
				'args'                => array(
					'user_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'type'    => array( 'default' => 'general', 'sanitize_callback' => 'sanitize_key' ),
				),
			),
			// DELETE → revoke.
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'revoke' ),
				'permission_callback' => array( $this, 'is_admin' ),
				'args'                => array(
					'user_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );
	}

	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		$current_user_id = get_current_user_id();
		$requested_user  = absint( $request->get_param( 'user_id' ) );
		$user_id         = $requested_user ?: $current_user_id;

		if ( ! $user_id ) {
			return rest_ensure_response( array( 'verified' => false ) );
		}

		$is_self  = ( $user_id === $current_user_id );
		$is_admin = current_user_can( 'arshid6social_manage_members' );

		$response = array(
			'verified'   => $this->verification->is_verified( $user_id ),
			'badge_html' => $this->verification->get_badge_html( $user_id ),
		);

		// Only expose pending state to the requesting user themselves or admins.
		if ( $is_self || $is_admin ) {
			$response['pending'] = null !== $this->verification->get_pending_request( $user_id );
		}

		return rest_ensure_response( $response );
	}

	public function submit_request( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id = get_current_user_id();

		if ( ! arshid6social_check_rate_limit( 'arshid6social_rl_verify', $user_id, (int) get_option( 'arshid6social_verification_rate_limit', 3 ) ) ) {
			return new \WP_Error( 'rate_limited', __( 'Too many requests.', '6arshid-social-community' ), array( 'status' => 429 ) );
		}

		$id = $this->verification->submit_request(
			$user_id,
			$request->get_param( 'type' ),
			array(
				'full_name' => $request->get_param( 'full_name' ),
				'category'  => $request->get_param( 'category' ),
				'links'     => $request->get_param( 'links' ),
			)
		);

		if ( ! $id ) {
			return new \WP_Error( 'already_pending', __( 'A pending request already exists.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'id' => $id ) );
	}

	public function get_requests( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT r.*, u.user_login, u.display_name, u.user_email
			 FROM {$wpdb->prefix}sn_verification_requests r
			 JOIN {$wpdb->users} u ON u.ID = r.user_id
			 WHERE r.status = %s
			 ORDER BY r.created_at DESC
			 LIMIT %d OFFSET %d",
			$status, $per_page, $offset
		) ) ?: array();

		return rest_ensure_response( array(
			'requests' => $rows,
			'has_more' => count( $rows ) >= $per_page,
		) );
	}

	public function approve_request( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$ok = $this->verification->approve_request(
			$request->get_param( 'id' ),
			$request->get_param( 'type' )
		);
		return $ok
			? rest_ensure_response( array( 'approved' => true ) )
			: new \WP_Error( 'not_found', __( 'Request not found.', '6arshid-social-community' ), array( 'status' => 404 ) );
	}

	public function reject_request( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$ok = $this->verification->reject_request(
			$request->get_param( 'id' ),
			$request->get_param( 'reason' )
		);
		return $ok
			? rest_ensure_response( array( 'rejected' => true ) )
			: new \WP_Error( 'not_found', __( 'Request not found.', '6arshid-social-community' ), array( 'status' => 404 ) );
	}

	public function grant( \WP_REST_Request $request ): \WP_REST_Response {
		$ok = $this->verification->grant(
			$request->get_param( 'user_id' ),
			$request->get_param( 'type' )
		);
		return rest_ensure_response( array( 'granted' => $ok ) );
	}

	public function revoke( \WP_REST_Request $request ): \WP_REST_Response {
		$ok = $this->verification->revoke( $request->get_param( 'user_id' ) );
		return rest_ensure_response( array( 'revoked' => $ok ) );
	}

	public function is_admin(): bool {
		return current_user_can( 'arshid6social_manage_members' );
	}
}
