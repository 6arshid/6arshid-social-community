<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Share Posts REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Share_Posts_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/activity/(?P<id>\d+)/share', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'share' ),
			'permission_callback' => 'is_user_logged_in',
			'args'                => array(
				'comment'     => array( 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ),
				'target_type' => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'profile' ),
				'target_id'   => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
			),
		) );

		register_rest_route( self::NS, '/activity/(?P<id>\d+)/share-count', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'share_count' ),
			'permission_callback' => '__return_true',
		) );
	}

	private function feature(): ?Share_Posts {
		/** @var Share_Posts|null $f */
		return arshid6social_eng()->feature( 'share_posts' );
	}

	public function share( \WP_REST_Request $req ): \WP_REST_Response {
		try {
			$f = $this->feature();
			if ( ! $f ) {
				return new \WP_REST_Response( null, 503 );
			}

			$original_id = absint( $req['id'] );

			// Verify the current user can view the original activity before sharing it.
			if ( ! arshid6social_current_user_can_view_activity( $original_id ) ) {
				return new \WP_REST_Response( array( 'message' => __( 'Permission denied.', '6arshid-social-community' ) ), 403 );
			}

			$target_type = in_array( $req['target_type'], array( 'profile', 'group' ), true ) ? $req['target_type'] : 'profile';

			$new_id = $f->share( get_current_user_id(), $original_id, (string) $req['comment'], $target_type, (int) $req['target_id'] );

			if ( ! $new_id ) {
				return new \WP_REST_Response( array( 'message' => __( 'Could not share this post.', '6arshid-social-community' ) ), 400 );
			}

			$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
			$formatted     = $activity_comp ? $activity_comp->format_activity( $activity_comp->get_by_id( $new_id ) ) : array();

			return new \WP_REST_Response( array(
				'activity'    => $formatted,
				'share_count' => $f->get_share_count( $f->get_root_id( $original_id ) ),
			), 201 );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			error_log( '[WPSN Share] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			return new \WP_REST_Response(
				array( 'message' => __( 'Could not share this post.', '6arshid-social-community' ) ),
				500
			);
		}
	}

	public function share_count( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array( 'count' => 0 ) );
		}
		$root = $f->get_root_id( absint( $req['id'] ) );
		return new \WP_REST_Response( array( 'count' => $f->get_share_count( $root ) ) );
	}
}
