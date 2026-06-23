<?php
namespace Arshid6Social\Components\Activity;

/**
 * REST API controller for the Activity component.
 *
 * @package Arshid6Social\Components\Activity
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Activity_REST
 *
 * Provides /arshid6social/v1/activity endpoints: list, create, delete, react.
 */
class Activity_REST extends \WP_REST_Controller {

	protected $namespace = 'arshid6social/v1';
	protected $rest_base = 'activity';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page'    => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint' ),
						'user_id' => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
						'scope'   => array( 'type' => 'string', 'default' => 'all', 'sanitize_callback' => 'sanitize_key' ),
						'search'  => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'content'   => array( 'required' => true, 'type' => 'string' ),
						'privacy'   => array( 'type' => 'string', 'default' => 'public', 'enum' => array( 'public', 'friends', 'private', 'paid' ) ),
						'ppv_price' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/react',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'react' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'reaction_type' => array( 'type' => 'string', 'default' => 'like', 'sanitize_callback' => 'sanitize_key' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/view',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'record_view' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public function get_items( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'activity' );
		if ( ! $component ) {
			return new \WP_Error( 'arshid6social_disabled', __( 'Activity component not active.', 'social-network-6' ), array( 'status' => 503 ) );
		}

		$query_args = array(
			'page'    => $request['page'],
			'user_id' => $request['user_id'],
			'scope'   => $request['scope'],
			'search'  => $request['search'],
		);

		// For unauthenticated requests, restrict to public activities only.
		if ( ! is_user_logged_in() ) {
			$query_args['privacy'] = 'public';
		}

		$data     = $component->get_activity( $query_args );

		$response = rest_ensure_response( $data['activities'] );
		$response->header( 'X-WP-Total', $data['total'] );
		$response->header( 'X-WP-TotalPages', $data['total_pages'] );
		return $response;
	}

	public function create_item( $request ): \WP_REST_Response|\WP_Error {
		$component = ARSHID6SOCIAL()->component( 'activity' );

		$activity_id = $component->add( array(
			'user_id'   => get_current_user_id(),
			'content'   => wp_kses_post( $request->get_param( 'content' ) ),
			'privacy'   => $request->get_param( 'privacy' ),
			'type'      => 'activity_update',
			'component' => 'activity',
			'ppv_price' => (int) $request->get_param( 'ppv_price' ),
		) );

		if ( ! $activity_id ) {
			return new \WP_Error( 'arshid6social_create_failed', __( 'Failed to create activity.', 'social-network-6' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $component->format_activity( $component->get_by_id( $activity_id ) ) );
	}

	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$component   = ARSHID6SOCIAL()->component( 'activity' );
		$activity_id = (int) $request['id'];
		$activity    = $component->get_by_id( $activity_id );

		if ( ! $activity ) {
			return new \WP_Error( 'arshid6social_not_found', __( 'Activity not found.', 'social-network-6' ), array( 'status' => 404 ) );
		}

		// Enforce activity privacy.
		if ( ! arshid6social_current_user_can_view_activity( $activity_id ) ) {
			return new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', 'social-network-6' ), array( 'status' => 403 ) );
		}

		return rest_ensure_response( $component->format_activity( $activity ) );
	}

	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		$component   = ARSHID6SOCIAL()->component( 'activity' );
		$activity_id = (int) $request['id'];
		$activity    = $component->get_by_id( $activity_id );

		if ( ! $activity ) {
			return new \WP_Error( 'arshid6social_not_found', __( 'Activity not found.', 'social-network-6' ), array( 'status' => 404 ) );
		}

		if ( (int) $activity->user_id !== get_current_user_id() && ! current_user_can( 'arshid6social_manage_activity' ) ) {
			return new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', 'social-network-6' ), array( 'status' => 403 ) );
		}

		$component->delete( $activity_id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function react( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$activity_id   = (int) $request['id'];
		$reaction_type = $request->get_param( 'reaction_type' ) ?: 'like';
		$user_id       = get_current_user_id();

		// Verify the current user can view this activity before allowing a reaction.
		if ( ! arshid6social_current_user_can_view_activity( $activity_id ) ) {
			return new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', 'social-network-6' ), array( 'status' => 403 ) );
		}

		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, reaction_type FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id = %d AND user_id = %d",
				$activity_id,
				$user_id
			)
		);

		if ( $existing && $existing->reaction_type === $reaction_type ) {
			$wpdb->delete( $wpdb->prefix . 'sn_activity_reactions', array( 'id' => $existing->id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$reacted = false;
		} else {
			if ( $existing ) {
				$wpdb->update( $wpdb->prefix . 'sn_activity_reactions', array( 'reaction_type' => $reaction_type ), array( 'id' => $existing->id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->insert( $wpdb->prefix . 'sn_activity_reactions', array( 'activity_id' => $activity_id, 'user_id' => $user_id, 'reaction_type' => $reaction_type, 'date_created' => current_time( 'mysql' ) ), array( '%d', '%d', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			$reacted = true;
		}

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id = %d AND reaction_type = %s", $activity_id, $reaction_type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return rest_ensure_response( array( 'reacted' => $reacted, 'count' => $count ) );
	}

	public function record_view( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$activity_id = (int) $request['id'];

		// Deduplicate: one view per user (or IP for guests) per activity per hour.
		$uid = is_user_logged_in()
			? 'u' . get_current_user_id()
			: 'ip' . substr( md5( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 12 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$transient_key = 'arshid6social_view_' . $activity_id . '_' . $uid;

		if ( get_transient( $transient_key ) ) {
			return rest_ensure_response( array( 'counted' => false ) );
		}

		set_transient( $transient_key, 1, HOUR_IN_SECONDS );

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_activity_meta WHERE activity_id = %d AND meta_key = '_view_count' LIMIT 1",
				$activity_id
			)
		);

		if ( $existing ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}sn_activity_meta SET meta_value = meta_value + 1 WHERE activity_id = %d AND meta_key = '_view_count'",
					$activity_id
				)
			);
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_activity_meta',
				array(
					'activity_id' => $activity_id,
					'meta_key'    => '_view_count',
					'meta_value'  => 1,
				),
				array( '%d', '%s', '%d' )
			);
		}

		return rest_ensure_response( array( 'counted' => true ) );
	}
}
