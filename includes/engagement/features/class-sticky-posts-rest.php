<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Sticky Posts REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Sticky_Posts_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/activity/(?P<id>\d+)/sticky', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pin' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'scope'      => array( 'default' => 'profile', 'sanitize_callback' => 'sanitize_key' ),
					'scope_id'   => array( 'default' => 0, 'sanitize_callback' => 'absint' ),
					'expires_at' => array( 'default' => null, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unpin' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'scope'    => array( 'default' => 'profile', 'sanitize_callback' => 'sanitize_key' ),
					'scope_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ),
				),
			),
		) );

		register_rest_route( self::NS, '/sticky', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stickies' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'scope'    => array( 'default' => 'site', 'sanitize_callback' => 'sanitize_key' ),
				'scope_id' => array( 'default' => 0, 'sanitize_callback' => 'absint' ),
			),
		) );
	}

	private function feature(): ?Sticky_Posts {
		/** @var Sticky_Posts|null $f */
		return arshid6social_eng()->feature( 'sticky_posts' );
	}

	public function pin( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( null, 503 );
		}

		$activity_id     = absint( $req['id'] );
		$current_user_id = get_current_user_id();
		$scope           = in_array( $req['scope'], array( 'profile', 'group', 'site' ), true ) ? $req['scope'] : 'profile';

		// Only the activity author or admins may pin to profile; site-wide pins require admin.
		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		$activity      = $activity_comp ? $activity_comp->get_by_id( $activity_id ) : null;
		$is_author     = $activity && ( (int) $activity->user_id === $current_user_id );
		$is_admin      = current_user_can( 'arshid6social_manage_activity' );

		if ( ! $is_author && ! $is_admin ) {
			return new \WP_REST_Response( null, 403 );
		}

		$id = $f->pin( $activity_id, $scope, (int) $req['scope_id'], $current_user_id, $req['expires_at'] ?: null );

		return $id
			? new \WP_REST_Response( array( 'sticky_id' => $id ), 201 )
			: new \WP_REST_Response( null, 403 );
	}

	public function unpin( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( null, 503 );
		}

		$activity_id     = absint( $req['id'] );
		$current_user_id = get_current_user_id();
		$scope           = in_array( $req['scope'], array( 'profile', 'group', 'site' ), true ) ? $req['scope'] : 'profile';

		// Only the activity author or admins may unpin.
		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		$activity      = $activity_comp ? $activity_comp->get_by_id( $activity_id ) : null;
		$is_author     = $activity && ( (int) $activity->user_id === $current_user_id );
		$is_admin      = current_user_can( 'arshid6social_manage_activity' );

		if ( ! $is_author && ! $is_admin ) {
			return new \WP_REST_Response( null, 403 );
		}

		$f->unpin( $activity_id, $scope, (int) $req['scope_id'] );
		return new \WP_REST_Response( null, 204 );
	}

	public function get_stickies( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array(), 503 );
		}

		$scope    = in_array( $req['scope'], array( 'profile', 'group', 'site' ), true ) ? $req['scope'] : 'site';
		$ids      = $f->get_sticky_ids( $scope, (int) $req['scope_id'] );
		$activity = ARSHID6SOCIAL()->component( 'activity' );
		$items    = array();

		if ( $activity ) {
			foreach ( $ids as $id ) {
				// Only include activities the current user is allowed to see.
				if ( ! arshid6social_current_user_can_view_activity( (int) $id ) ) {
					continue;
				}
				$row = $activity->get_by_id( $id );
				if ( $row ) {
					$formatted           = $activity->format_activity( $row );
					$formatted['sticky'] = true;
					$items[]             = $formatted;
				}
			}
		}

		return new \WP_REST_Response( $items );
	}
}
