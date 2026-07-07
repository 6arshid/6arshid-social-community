<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Hashtags REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Hashtags_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/hashtags/trending', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'trending' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'period' => array( 'default' => '24h', 'sanitize_callback' => 'sanitize_key' ),
				'limit'  => array( 'default' => 10, 'sanitize_callback' => 'absint' ),
			),
		) );

		register_rest_route( self::NS, '/hashtags/(?P<slug>[^/]+)/feed', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'feed' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'slug'     => array( 'sanitize_callback' => 'sanitize_title' ),
				'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
				'per_page' => array( 'default' => 20, 'sanitize_callback' => 'absint' ),
			),
		) );

		register_rest_route( self::NS, '/hashtags/(?P<id>\d+)/follow', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'follow' ),
			'permission_callback' => array( $this, 'logged_in' ),
		) );

		register_rest_route( self::NS, '/hashtags/(?P<id>\d+)/follow', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'unfollow' ),
			'permission_callback' => array( $this, 'logged_in' ),
		) );
	}

	public function logged_in(): bool {
		return is_user_logged_in();
	}

	public function trending( \WP_REST_Request $req ): \WP_REST_Response {
		$feature = arshid6social_eng()->feature( 'hashtags' );
		if ( ! $feature ) {
			return new \WP_REST_Response( array(), 503 );
		}
		$tags = $feature->get_trending(
			in_array( $req['period'], array( '24h', '7d' ), true ) ? $req['period'] : '24h',
			min( 50, absint( $req['limit'] ) ),
			array( 'privacy' => 'public' ) // Only count hashtags from public posts.
		);
		return new \WP_REST_Response( $tags );
	}

	public function feed( \WP_REST_Request $req ): \WP_REST_Response {
		$feature = arshid6social_eng()->feature( 'hashtags' );
		if ( ! $feature ) {
			return new \WP_REST_Response( array(), 503 );
		}

		$result = $feature->get_activity_for_tag(
			$req['slug'],
			max( 1, absint( $req['page'] ) ),
			min( 50, absint( $req['per_page'] ) )
		);

		$activity_comp   = ARSHID6SOCIAL()->component( 'activity' );
		$activities      = array();
		$current_user_id = get_current_user_id();

		if ( $activity_comp ) {
			foreach ( $result['ids'] as $aid ) {
				$row = $activity_comp->get_by_id( $aid );
				if ( ! $row ) {
					continue;
				}

				// Privacy filter: only include public activities, or activities the current user owns.
				$privacy = isset( $row->privacy ) ? $row->privacy : 'public';
				if ( 'public' !== $privacy ) {
					if ( ! $current_user_id ) {
						continue;
					}
					if ( (int) $row->user_id !== $current_user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) {
						continue;
					}
				}

				$activities[] = $activity_comp->format_activity( $row );
			}
		}

		return new \WP_REST_Response( array( 'activities' => $activities, 'total' => $result['total'] ) );
	}

	public function follow( \WP_REST_Request $req ): \WP_REST_Response {
		global $wpdb;
		$hashtag_id = absint( $req['id'] );
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_hashtag_follows',
			array( 'hashtag_id' => $hashtag_id, 'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) ),
			array( '%d', '%d', '%s' )
		);
		return new \WP_REST_Response( array( 'following' => true ) );
	}

	public function unfollow( \WP_REST_Request $req ): \WP_REST_Response {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_hashtag_follows',
			array( 'hashtag_id' => absint( $req['id'] ), 'user_id' => get_current_user_id() ),
			array( '%d', '%d' )
		);
		return new \WP_REST_Response( array( 'following' => false ) );
	}
}
