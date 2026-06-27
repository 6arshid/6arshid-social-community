<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Polls REST controller.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Polls_REST {

	const NS = 'arshid6social/v1';

	public function register_routes(): void {
		register_rest_route( self::NS, '/polls', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create' ),
			'permission_callback' => array( $this, 'can_create' ),
		) );

		register_rest_route( self::NS, '/polls/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/polls/(?P<id>\d+)/vote', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'vote' ),
			'permission_callback' => array( $this, 'can_vote' ),
		) );

		register_rest_route( self::NS, '/polls/(?P<id>\d+)/export', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'export_csv' ),
			'permission_callback' => array( $this, 'can_export' ),
		) );
	}

	private function feature(): ?Polls {
		/** @var Polls|null $f */
		return arshid6social_eng()->feature( 'polls' );
	}

	public function can_create( \WP_REST_Request $req ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$activity_id = absint( $req->get_param( 'activity_id' ) );
		if ( $activity_id ) {
			$activity = arshid6social()->component( 'activity' );
			if ( $activity ) {
				$item = $activity->get_activity( $activity_id );
				if ( ! $item ) {
					return false;
				}
				if ( (int) $item->user_id !== get_current_user_id() && ! current_user_can( 'arshid6social_manage_activity' ) ) {
					return false;
				}
			}
		}
		return true;
	}

	public function can_vote( \WP_REST_Request $req ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$f    = $this->feature();
		$poll = $f ? $f->get_poll( absint( $req['id'] ) ) : null;
		if ( ! $poll ) {
			return true; // Poll not found; let the callback return a proper 404.
		}
		return arshid6social_current_user_can_view_activity( (int) $poll->activity_id );
	}

	public function can_export( \WP_REST_Request $req ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$f    = $this->feature();
		$poll = $f ? $f->get_poll( absint( $req['id'] ) ) : null;
		return $poll && ( (int) $poll->user_id === get_current_user_id() || current_user_can( 'arshid6social_manage_activity' ) );
	}

	public function create( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( null, 503 );
		}

		$poll_id = $f->create( array(
			'activity_id'        => absint( $req->get_param( 'activity_id' ) ),
			'user_id'            => get_current_user_id(),
			'question'           => sanitize_text_field( $req->get_param( 'question' ) ?: '' ),
			'options'            => (array) ( $req->get_param( 'options' ) ?: array() ),
			'poll_type'          => sanitize_key( $req->get_param( 'poll_type' ) ?: 'single' ),
			'anonymous'          => (bool) $req->get_param( 'anonymous' ),
			'allow_change_vote'  => (bool) ( $req->get_param( 'allow_change_vote' ) ?? true ),
			'results_visibility' => sanitize_key( $req->get_param( 'results_visibility' ) ?: 'always' ),
			'end_date'           => sanitize_text_field( $req->get_param( 'end_date' ) ?: '' ),
			'start_date'         => sanitize_text_field( $req->get_param( 'start_date' ) ?: '' ),
		) );

		return $poll_id
			? new \WP_REST_Response( array( 'poll_id' => $poll_id, 'results' => $f->get_results( $poll_id, get_current_user_id() ) ), 201 )
			: new \WP_REST_Response( array( 'message' => __( 'Failed to create poll.', '6arshid-social-community' ) ), 400 );
	}

	public function get( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( null, 503 );
		}

		$poll = $f->get_poll( absint( $req['id'] ) );
		if ( ! $poll ) {
			return new \WP_REST_Response( null, 404 );
		}

		// Check visibility of the parent activity.
		if ( ! arshid6social_current_user_can_view_activity( (int) $poll->activity_id ) ) {
			return new \WP_REST_Response( null, 403 );
		}

		$results = $f->get_results( absint( $req['id'] ), get_current_user_id() );
		return $results ? new \WP_REST_Response( $results ) : new \WP_REST_Response( null, 404 );
	}

	public function vote( \WP_REST_Request $req ): \WP_REST_Response {
		$f = $this->feature();
		if ( ! $f ) {
			return new \WP_REST_Response( null, 503 );
		}

		// Check that the user can view the poll's parent activity before voting.
		$poll = $f->get_poll( absint( $req['id'] ) );
		if ( ! $poll ) {
			return new \WP_REST_Response( null, 404 );
		}

		$option_ids = array_map( 'absint', (array) ( $req->get_param( 'option_ids' ) ?: array() ) );
		$result     = $f->vote( absint( $req['id'] ), $option_ids, get_current_user_id() );

		return $result['success']
			? new \WP_REST_Response( $result )
			: new \WP_REST_Response( $result, 400 );
	}

	public function export_csv( \WP_REST_Request $req ): void {
		$f       = $this->feature();
		$poll_id = absint( $req['id'] );
		$results = $f ? $f->get_results( $poll_id, get_current_user_id() ) : array();

		if ( empty( $results ) ) {
			status_header( 404 );
			exit;
		}

		global $wpdb;

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="poll-' . $poll_id . '-results.csv"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( __( 'Option', '6arshid-social-community' ), __( 'Votes', '6arshid-social-community' ), __( 'Percentage', '6arshid-social-community' ) ) );

		foreach ( $results['options'] as $opt ) {
			fputcsv( $out, array( $opt['text'], $opt['voteCount'] ?? 0, ( $opt['percentage'] ?? 0 ) . '%' ) );
		}

		// If public voting, append voter list.
		if ( ! $results['anonymous'] ) {
			fputcsv( $out, array() );
			fputcsv( $out, array( __( 'Voter', '6arshid-social-community' ), __( 'Option', '6arshid-social-community' ), __( 'Voted At', '6arshid-social-community' ) ) );

			$votes = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT v.user_id, v.voted_at, o.option_text
				FROM {$wpdb->prefix}sn_poll_votes v
				JOIN {$wpdb->prefix}sn_poll_options o ON o.id = v.option_id
				WHERE v.poll_id = %d ORDER BY v.voted_at ASC",
				$poll_id
			), ARRAY_A );

			foreach ( $votes as $v ) {
				$user = get_userdata( (int) $v['user_id'] );
				fputcsv( $out, array( $user ? $user->display_name : '#' . $v['user_id'], $v['option_text'], $v['voted_at'] ) );
			}
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
