<?php
namespace Arshid6Social\Components\Notifications;

/**
 * REST API for the Notifications component.
 *
 * @package Arshid6Social\Components\Notifications
 */

defined( 'ABSPATH' ) || exit;

class Notifications_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'notifications';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array(
				'unread_only' => array( 'type' => 'boolean', 'default' => false ),
				'limit'       => array( 'type' => 'integer', 'default' => 25, 'sanitize_callback' => 'absint' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/read', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'mark_read' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array( 'ids' => array( 'type' => 'array', 'default' => array() ) ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/unread-count', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'unread_count' ),
			'permission_callback' => 'is_user_logged_in',
		) );
	}

	public function get_items( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'notifications' );
		$items     = $component->get_for_user(
			get_current_user_id(),
			(bool) $request->get_param( 'unread_only' ),
			(int) $request->get_param( 'limit' )
		);
		return rest_ensure_response( $items );
	}

	public function mark_read( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'notifications' );
		$ids       = array_map( 'absint', (array) $request->get_param( 'ids' ) );
		$component->mark_read( get_current_user_id(), $ids );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function unread_count( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'notifications' );
		return rest_ensure_response( array( 'count' => $component->get_unread_count( get_current_user_id() ) ) );
	}
}
