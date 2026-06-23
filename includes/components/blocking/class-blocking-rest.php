<?php
namespace Arshid6Social\Components\Blocking;

/**
 * Block system REST API endpoints.
 *
 * @package Arshid6Social\Components\Blocking
 */

defined( 'ABSPATH' ) || exit;

class Blocking_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'blocks';

	public function register_routes(): void {
		// GET  /blocks          → current user's block list
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_block_list' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
				'args'                => array(
					'page' => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// POST /blocks/{id}     → block user
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'block_user' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
				'args'                => array(
					'id'     => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'reason' => array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			),
			// DELETE /blocks/{id} → unblock user
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unblock_user' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		// GET /blocks/{id}/status → check if blocked
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/status', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			),
		) );
	}

	public function get_block_list( \WP_REST_Request $request ): \WP_REST_Response {
		$current = get_current_user_id();
		$friends = ARSHID6SOCIAL()->component( 'friends' );
		$page    = $request->get_param( 'page' );

		if ( ! $friends ) {
			return rest_ensure_response( array( 'blocks' => array(), 'has_more' => false ) );
		}

		$blocks  = $friends->get_block_list( $current, $page );
		$members = ARSHID6SOCIAL()->component( 'members' );
		$data    = array();

		foreach ( $blocks as $block ) {
			$user = get_userdata( (int) $block->blocked_id );
			if ( ! $user ) {
				continue;
			}
			$item = $members ? $members->format_member( $user ) : array( 'id' => (int) $block->blocked_id );
			$item['block_date']   = $block->date_created;
			$item['block_reason'] = $block->reason ?? '';
			$data[] = $item;
		}

		return rest_ensure_response( array( 'blocks' => $data, 'has_more' => count( $blocks ) >= 20 ) );
	}

	public function block_user( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$current   = get_current_user_id();
		$target_id = $request->get_param( 'id' );
		$reason    = $request->get_param( 'reason' );

		if ( $target_id === $current ) {
			return new \WP_Error( 'self_block', __( 'You cannot block yourself.', '6arshid social community' ), array( 'status' => 400 ) );
		}

		if ( ! arshid6social_check_rate_limit( 'arshid6social_rl_block', $current, 20 ) ) {
			return new \WP_Error( 'rate_limited', __( 'Too many block actions.', '6arshid social community' ), array( 'status' => 429 ) );
		}

		$friends = ARSHID6SOCIAL()->component( 'friends' );
		if ( $friends ) {
			$friends->block( $current, $target_id, $reason );
		}

		return rest_ensure_response( array( 'blocked' => true ) );
	}

	public function unblock_user( \WP_REST_Request $request ): \WP_REST_Response {
		$current   = get_current_user_id();
		$target_id = $request->get_param( 'id' );
		$friends   = ARSHID6SOCIAL()->component( 'friends' );

		if ( $friends ) {
			$friends->unblock( $current, $target_id );
		}

		return rest_ensure_response( array( 'blocked' => false ) );
	}

	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		$current   = get_current_user_id();
		$target_id = $request->get_param( 'id' );

		return rest_ensure_response( array(
			'blocked'      => arshid6social_is_blocked( $current, $target_id ),
			'site_blocked' => arshid6social_is_site_blocked( $target_id ),
		) );
	}

	public function is_logged_in(): bool {
		return is_user_logged_in();
	}
}
