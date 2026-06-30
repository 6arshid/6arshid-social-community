<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Tag Friends REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Tag_Friends_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/activity/(?P<id>\d+)/tags', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tags' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_tag' ),
				'permission_callback' => array( $this, 'can_tag' ),
			),
		) );

		register_rest_route( self::NS, '/tags/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'remove_tag' ),
			'permission_callback' => 'is_user_logged_in',
		) );
	}

	public function can_tag(): bool {
		return is_user_logged_in();
	}

	public function get_tags( \WP_REST_Request $req ): \WP_REST_Response {
		$activity_id = absint( $req['id'] );

		// Check activity visibility before exposing tagged user details.
		if ( ! arshid6social_current_user_can_view_activity( $activity_id ) ) {
			return new \WP_REST_Response( array(), 403 );
		}

		$feature = arshid6social_eng()->feature( 'tag_friends' );
		if ( ! $feature ) {
			return new \WP_REST_Response( array(), 503 );
		}
		$tags = $feature->get_tags_for_object( $activity_id, 'activity' );

		// Append user info.
		$tags = array_map( function( array $t ): array {
			$user = get_userdata( (int) $t['tagged_user_id'] );
			$t['displayName'] = $user ? esc_html( $user->display_name ) : '';
			$t['profileUrl']  = $user ? esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ) : '';
			$t['avatar']      = $user ? esc_url( get_avatar_url( $user->ID, array( 'size' => 32 ) ) ) : '';
			return $t;
		}, $tags );

		return new \WP_REST_Response( $tags );
	}

	public function add_tag( \WP_REST_Request $req ): \WP_REST_Response {
		$activity_id    = absint( $req['id'] );
		$tagged_user_id = absint( $req->get_param( 'user_id' ) );
		$x              = (float) ( $req->get_param( 'x' ) ?? 0 );
		$y              = (float) ( $req->get_param( 'y' ) ?? 0 );

		if ( ! $tagged_user_id ) {
			return new \WP_REST_Response( array( 'message' => __( 'User ID required.', '6arshid-social-community' ) ), 400 );
		}

		global $wpdb;
		$activity = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) );

		if ( ! $activity || (int) $activity->user_id !== get_current_user_id() ) {
			return new \WP_REST_Response( array( 'message' => __( 'Permission denied.', '6arshid-social-community' ) ), 403 );
		}

		$feature = arshid6social_eng()->feature( 'tag_friends' );
		if ( ! $feature ) {
			return new \WP_REST_Response( array(), 503 );
		}

		$review = (bool) get_option( 'arshid6social_eng_tag_review', false );
		$status = $review ? 'pending' : 'approved';

		// Using reflection to call the private method isn't ideal; we expose it via tag_photo.
		// Instead, directly do the insert here.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_post_tags',
			array(
				'object_id'      => $activity_id,
				'object_type'    => 'activity',
				'tagged_user_id' => $tagged_user_id,
				'tagger_id'      => get_current_user_id(),
				'status'         => $status,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		$tag_id = (int) $wpdb->insert_id;

		if ( $tag_id && ( $x || $y ) ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_post_tag_coords',
				array( 'tag_id' => $tag_id, 'x_percent' => max( 0.0, min( 100.0, $x ) ), 'y_percent' => max( 0.0, min( 100.0, $y ) ) ),
				array( '%d', '%f', '%f' )
			);
		}

		return new \WP_REST_Response( array( 'tag_id' => $tag_id, 'status' => $status ), 201 );
	}

	public function remove_tag( \WP_REST_Request $req ): \WP_REST_Response {
		global $wpdb;
		$tag_id  = absint( $req['id'] );
		$user_id = get_current_user_id();

		$tag = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_post_tags WHERE id = %d",
			$tag_id
		) );

		if ( ! $tag ) {
			return new \WP_REST_Response( null, 404 );
		}

		if ( (int) $tag->tagged_user_id !== $user_id && (int) $tag->tagger_id !== $user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) {
			return new \WP_REST_Response( null, 403 );
		}

		$wpdb->delete( $wpdb->prefix . 'sn_post_tag_coords', array( 'tag_id' => $tag_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_post_tags', array( 'id' => $tag_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return new \WP_REST_Response( null, 204 );
	}
}
