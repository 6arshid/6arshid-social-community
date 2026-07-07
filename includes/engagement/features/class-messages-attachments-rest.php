<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Message Attachments REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Messages_Attachments_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/messages/(?P<id>\d+)/attachments', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_attachments' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		register_rest_route( self::NS, '/attachments/message/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_attachment' ),
			'permission_callback' => 'is_user_logged_in',
		) );
	}

	private function feature(): ?Messages_Attachments {
		/** @var Messages_Attachments|null $f */
		return arshid6social_eng()->feature( 'messages_attachments' );
	}

	public function get_attachments( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array(), 503 );
		}
		$atts = $f->get_for_message( absint( $req['id'] ), get_current_user_id() );
		return new \WP_REST_Response( $atts );
	}

	public function delete_attachment( \WP_REST_Request $req ): \WP_REST_Response {
		global $wpdb;
		$att_id  = absint( $req['id'] );
		$user_id = get_current_user_id();

		$att = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_attachments WHERE id = %d AND parent_type = 'message'",
			$att_id
		) );

		if ( ! $att ) {
			return new \WP_REST_Response( null, 404 );
		}

		if ( (int) $att->uploader_id !== $user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) {
			return new \WP_REST_Response( null, 403 );
		}

		if ( ! empty( $att->file_path ) && file_exists( $att->file_path ) ) {
			wp_delete_file( $att->file_path );
		}

		$wpdb->delete( $wpdb->prefix . 'arshid6social_attachments', array( 'id' => $att_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return new \WP_REST_Response( null, 204 );
	}
}
