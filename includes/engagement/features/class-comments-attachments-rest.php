<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Comment Attachments REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Comments_Attachments_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/comments/(?P<id>\d+)/attachments', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attachments' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( self::NS, '/attachments/comment/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_attachment' ),
			'permission_callback' => 'is_user_logged_in',
		) );
	}

	private function feature(): ?Comments_Attachments {
		/** @var Comments_Attachments|null $f */
		return arshid6social_eng()->feature( 'comments_attachments' );
	}

	public function get_attachments( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array(), 503 );
		}

		$comment_id = absint( $req['id'] );

		// Privacy check: find the parent activity ID for the comment, then use the shared visibility helper.
		global $wpdb;
		$parent_activity_id = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT item_id FROM {$wpdb->prefix}sn_activity WHERE id = %d LIMIT 1",
			$comment_id
		) );

		if ( $parent_activity_id && ! arshid6social_current_user_can_view_activity( $parent_activity_id ) ) {
			return new \WP_REST_Response( null, 403 );
		}

		return new \WP_REST_Response( $f->get_for_comment( $comment_id ) );
	}

	public function delete_attachment( \WP_REST_Request $req ): \WP_REST_Response {
		global $wpdb;
		$att_id  = absint( $req['id'] );
		$user_id = get_current_user_id();

		$att = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_attachments WHERE id = %d AND parent_type = 'comment'",
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
