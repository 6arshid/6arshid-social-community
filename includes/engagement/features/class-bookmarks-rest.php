<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Bookmarks REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Bookmarks_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/bookmarks', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bookmarks' ),
				'permission_callback' => array( $this, 'require_login' ),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'require_login' ),
			),
		) );

		register_rest_route( self::NS, '/bookmarks/(?P<object_type>[a-z]+)/(?P<object_id>\d+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete' ),
			'permission_callback' => array( $this, 'require_login' ),
		) );

		register_rest_route( self::NS, '/bookmark-collections', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_collections' ),
				'permission_callback' => array( $this, 'require_login' ),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_collection' ),
				'permission_callback' => array( $this, 'require_login' ),
			),
		) );

		register_rest_route( self::NS, '/bookmark-collections/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_collection' ),
			'permission_callback' => array( $this, 'can_delete_collection' ),
		) );
	}

	public function require_login( \WP_REST_Request $req ): bool {
		return is_user_logged_in();
	}

	public function can_delete_collection( \WP_REST_Request $req ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$f = $this->feature();
		if ( ! $f ) {
			return true; // Feature unavailable; let callback handle it.
		}
		$collection_id = absint( $req['id'] );
		if ( ! $collection_id ) {
			return true; // Invalid ID; let callback return 404.
		}
		return $f->user_owns_collection( get_current_user_id(), $collection_id );
	}

	private function feature(): ?Bookmarks {
		/** @var Bookmarks|null $f */
		$f = arshid6social_eng()->feature( 'bookmarks' );
		return $f;
	}

	public function get_bookmarks( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array(), 503 );
		}

		$user_id = get_current_user_id();
		$result  = $f->get_for_user( $user_id, array(
			'page'          => max( 1, absint( $req['page'] ?? 1 ) ),
			'per_page'      => min( 50, absint( $req['per_page'] ?? 20 ) ),
			'search'        => sanitize_text_field( $req['search'] ?? '' ),
			'collection_id' => absint( $req['collection_id'] ?? 0 ) ?: null,
		) );

		// Enrich with activity data.
		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		$result['items'] = array_map( function( array $item ) use ( $activity_comp ): array {
			if ( $activity_comp && 'activity' === $item['object_type'] ) {
				$row = $activity_comp->get_by_id( (int) $item['object_id'] );
				$item['activity'] = $row ? $activity_comp->format_activity( $row ) : null;
			}
			return $item;
		}, $result['items'] );

		return new \WP_REST_Response( $result );
	}

	public function create( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array(), 503 );
		}

		$object_id   = absint( $req->get_param( 'object_id' ) );
		$object_type = sanitize_key( $req->get_param( 'object_type' ) ?: 'activity' );
		$coll_id     = absint( $req->get_param( 'collection_id' ) ) ?: null;

		if ( ! $object_id ) {
			return new \WP_REST_Response( array( 'message' => __( 'object_id required.', '6arshid-social-community' ) ), 400 );
		}

		$ok = $f->add( get_current_user_id(), $object_id, $object_type, $coll_id );
		if ( ! $ok ) {
			global $wpdb;
			$db_err = $wpdb->last_error ?: 'unknown DB error';
			return new \WP_REST_Response( array( 'message' => 'Could not save bookmark. ' . $db_err ), 500 );
		}
		return new \WP_REST_Response( array( 'bookmarked' => true ), 201 );
	}

	public function delete( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array(), 503 );
		}

		$f->remove( get_current_user_id(), absint( $req['object_id'] ), sanitize_key( $req['object_type'] ) );
		return new \WP_REST_Response( null, 204 );
	}

	public function get_collections( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( array(), 503 );
		}
		return new \WP_REST_Response( $f->get_collections( get_current_user_id() ) );
	}

	public function create_collection( \WP_REST_Request $req ): \WP_REST_Response {
		$f    = $this->feature();
		$name = sanitize_text_field( $req->get_param( 'name' ) ?: '' );
		if ( ! $f || ! $name ) {
			return new \WP_REST_Response( null, 400 );
		}
		$id = $f->add_collection( get_current_user_id(), $name );
		return $id
			? new \WP_REST_Response( array( 'id' => $id, 'name' => $name ), 201 )
			: new \WP_REST_Response( null, 500 );
	}

	public function delete_collection( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( null, 503 );
		}
		$ok = $f->delete_collection( get_current_user_id(), absint( $req['id'] ) );
		return $ok ? new \WP_REST_Response( null, 204 ) : new \WP_REST_Response( null, 403 );
	}
}
