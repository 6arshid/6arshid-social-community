<?php
namespace Arshid6Social\Components\Messages;

/**
 * REST API for the Messages component.
 *
 * @package Arshid6Social\Components\Messages
 */

defined( 'ABSPATH' ) || exit;

class Messages_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'messages';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/threads', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_threads' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array( 'page' => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ) ),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_thread' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'recipients' => array( 'required' => true, 'type' => 'array' ),
					'subject'    => array( 'type' => 'string', 'default' => 'New Message', 'sanitize_callback' => 'sanitize_text_field' ),
					'content'    => array( 'required' => true, 'type' => 'string' ),
				),
			),
		) );

		register_rest_route( $this->namespace, '/threads/(?P<id>[\d]+)/messages', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_messages' ),
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array( 'content' => array( 'required' => true, 'type' => 'string' ) ),
			),
		) );

		register_rest_route( $this->namespace, '/threads/(?P<id>[\d]+)/read', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'mark_read' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		register_rest_route( $this->namespace, '/unread-count', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'unread_count' ),
			'permission_callback' => 'is_user_logged_in',
		) );
	}

	public function get_threads( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'messages' );
		return rest_ensure_response( $component->get_threads( get_current_user_id(), $request['page'] ) );
	}

	public function create_thread( $request ): \WP_REST_Response|\WP_Error {
		$component    = ARSHID6SOCIAL()->component( 'messages' );
		$recipients   = array_map( 'absint', (array) $request->get_param( 'recipients' ) );
		$thread_id    = $component->start_thread(
			$recipients,
			$request->get_param( 'subject' ),
			wp_kses_post( $request->get_param( 'content' ) )
		);

		if ( ! $thread_id ) {
			return new \WP_Error( 'arshid6social_message_error', __( 'Could not create thread.', '6arshid-social-community-main' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'thread_id' => $thread_id ) );
	}

	public function get_messages( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'messages' );
		return rest_ensure_response( $component->get_thread_messages( (int) $request['id'], get_current_user_id() ) );
	}

	public function send_message( $request ): \WP_REST_Response|\WP_Error {
		$component  = ARSHID6SOCIAL()->component( 'messages' );
		$message_id = $component->add_message_to_thread(
			(int) $request['id'],
			get_current_user_id(),
			wp_kses_post( $request->get_param( 'content' ) )
		);

		if ( ! $message_id ) {
			return new \WP_Error( 'arshid6social_message_error', __( 'Could not send message.', '6arshid-social-community-main' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'message_id' => $message_id ) );
	}

	public function mark_read( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'messages' );
		$component->mark_thread_read( (int) $request['id'], get_current_user_id() );
		return rest_ensure_response( array( 'read' => true ) );
	}

	public function unread_count( $request ): \WP_REST_Response {
		$component = ARSHID6SOCIAL()->component( 'messages' );
		return rest_ensure_response( array( 'count' => $component->get_unread_count( get_current_user_id() ) ) );
	}
}
