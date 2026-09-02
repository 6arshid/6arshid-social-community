<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Sticky Posts feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Sticky_Posts {

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_sticky_pin', array( $this, 'ajax_pin' ) );
		add_action( 'wp_ajax_arshid6social_sticky_unpin', array( $this, 'ajax_unpin' ) );

		// Remove expired stickies daily.
		add_action( 'arshid6social_sticky_expire_check', array( $this, 'remove_expired' ) );
		if ( ! wp_next_scheduled( 'arshid6social_sticky_expire_check' ) ) {
			wp_schedule_event( time(), 'daily', 'arshid6social_sticky_expire_check' );
		}

		// Inject stickies at the top of activity feeds.
		add_filter( 'arshid6social_get_activity_args', array( $this, 'inject_stickies' ) );
	}

	// ── Core ──────────────────────────────────────────────────────────────────

	/**
	 * Pins an activity post.
	 *
	 * @param int         $object_id  Activity ID.
	 * @param string      $scope      'profile' | 'group' | 'site'
	 * @param int         $scope_id   User ID (profile scope), group ID (group scope), 0 for site scope.
	 * @param int         $created_by User performing the pin.
	 * @param string|null $expires_at  MySQL datetime or null.
	 * @return int|false  Sticky record ID or false.
	 */
	public function pin( int $object_id, string $scope, int $scope_id, int $created_by, ?string $expires_at = null ): int|false {
		global $wpdb;

		// Site scope: enforce single-sticky rule.
		if ( 'site' === $scope && ! get_option( 'arshid6social_eng_sticky_multiple', false ) ) {
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'arshid6social_sticky',
				array(
					'scope'       => 'site',
					'object_type' => 'activity',
				),
				array( '%s', '%s' )
			);
		}

		// Don't duplicate.
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id FROM {$wpdb->prefix}arshid6social_sticky WHERE object_id = %d AND scope = %s AND scope_id <=> %d",
				$object_id,
				$scope,
				$scope_id
			)
		);
		if ( $exists ) {
			return $exists;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_sticky',
			array(
				'object_id'   => $object_id,
				'object_type' => 'activity',
				'scope'       => sanitize_key( $scope ),
				'scope_id'    => $scope_id ?: null,
				'expires_at'  => $expires_at,
				'created_by'  => $created_by,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	public function unpin( int $object_id, string $scope, int $scope_id ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_sticky',
			array(
				'object_id' => $object_id,
				'scope'     => $scope,
				'scope_id'  => $scope_id ?: null,
			),
			array( '%d', '%s', '%d' )
		);
		return (bool) $deleted;
	}

	public function is_sticky( int $object_id, string $scope = 'site', int $scope_id = 0 ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id FROM {$wpdb->prefix}arshid6social_sticky
			WHERE object_id = %d AND scope = %s AND scope_id <=> %s
			AND (expires_at IS NULL OR expires_at > NOW())",
				$object_id,
				$scope,
				$scope_id ?: null
			)
		);
	}

	/**
	 * Returns sticky activity IDs for a given scope.
	 *
	 * @return int[]
	 */
	public function get_sticky_ids( string $scope = 'site', int $scope_id = 0 ): array {
		global $wpdb;
		$rows = $wpdb->get_col(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT object_id FROM {$wpdb->prefix}arshid6social_sticky
			WHERE scope = %s AND scope_id <=> %s AND object_type = 'activity'
			AND (expires_at IS NULL OR expires_at > NOW())
			ORDER BY created_at DESC",
				$scope,
				$scope_id ?: null
			)
		);
		return array_map( 'intval', $rows ?: array() );
	}

	public function remove_expired(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->prefix}arshid6social_sticky WHERE expires_at IS NOT NULL AND expires_at <= NOW()"
		);
	}

	// ── Capability checks ─────────────────────────────────────────────────────

	/**
	 * Returns the canonical scope id for a sticky request after validating the
	 * requested object/scope relationship and the current user's authority.
	 *
	 * @param int    $activity_id Activity ID.
	 * @param string $scope       profile|group|site.
	 * @param int    $scope_id    Requested scope ID.
	 * @return int|\WP_Error Canonical scope ID, or WP_Error when forbidden/invalid.
	 */
	public function authorize_sticky_request( int $activity_id, string $scope, int $scope_id ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'arshid6social_not_logged_in', __( 'You must be logged in.', '6arshid-social-community' ), array( 'status' => 401 ) );
		}

		if ( ! in_array( $scope, array( 'profile', 'group', 'site' ), true ) ) {
			return new \WP_Error( 'arshid6social_invalid_scope', __( 'Invalid sticky scope.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		$activity      = $activity_comp ? $activity_comp->get_by_id( $activity_id ) : null;
		if ( ! $activity ) {
			return new \WP_Error( 'arshid6social_not_found', __( 'Activity not found.', '6arshid-social-community' ), array( 'status' => 404 ) );
		}

		$current_user_id = get_current_user_id();
		$activity_owner  = (int) $activity->user_id;
		$is_moderator    = $this->can_pin_site();

		if ( $activity_owner !== $current_user_id && ! $is_moderator ) {
			return new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', '6arshid-social-community' ), array( 'status' => 403 ) );
		}

		if ( 'site' === $scope ) {
			return $is_moderator
				? 0
				: new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', '6arshid-social-community' ), array( 'status' => 403 ) );
		}

		if ( 'profile' === $scope ) {
			$canonical_scope_id = $scope_id ?: $activity_owner;
			if ( $canonical_scope_id !== $activity_owner ) {
				return new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', '6arshid-social-community' ), array( 'status' => 403 ) );
			}
			if ( ! get_userdata( $canonical_scope_id ) ) {
				return new \WP_Error( 'arshid6social_not_found', __( 'Profile not found.', '6arshid-social-community' ), array( 'status' => 404 ) );
			}
			return $canonical_scope_id;
		}

		if ( ! $scope_id ) {
			return new \WP_Error( 'arshid6social_invalid_scope', __( 'Invalid sticky scope.', '6arshid-social-community' ), array( 'status' => 400 ) );
		}

		$groups = ARSHID6SOCIAL()->component( 'groups' );
		$group  = $groups && method_exists( $groups, 'get_by_id' ) ? $groups->get_by_id( $scope_id ) : null;
		if ( ! $group ) {
			return new \WP_Error( 'arshid6social_not_found', __( 'Group not found.', '6arshid-social-community' ), array( 'status' => 404 ) );
		}

		if ( (int) $activity->item_id !== $scope_id ) {
			return new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', '6arshid-social-community' ), array( 'status' => 403 ) );
		}

		return ( $is_moderator || $this->can_pin_group( $scope_id ) )
			? $scope_id
			: new \WP_Error( 'arshid6social_forbidden', __( 'Permission denied.', '6arshid-social-community' ), array( 'status' => 403 ) );
	}

	private function can_pin_site(): bool {
		return current_user_can( 'arshid6social_manage_activity' ) || current_user_can( 'manage_options' );
	}

	private function can_pin_group( int $group_id ): bool {
		if ( $this->can_pin_site() ) {
			return true;
		}
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id FROM {$wpdb->prefix}arshid6social_groups_members WHERE group_id = %d AND user_id = %d AND is_confirmed = 1 AND is_banned = 0 AND (is_admin = 1 OR is_mod = 1)",
				$group_id,
				get_current_user_id()
			)
		);
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_pin(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$activity_id = absint( $_POST['activity_id'] ?? 0 );
		$scope       = sanitize_key( $_POST['scope'] ?? 'profile' );
		$scope_id    = absint( $_POST['scope_id'] ?? 0 );
		$expires_at  = sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) ) ?: null;
		// phpcs:enable

		if ( ! $activity_id || ! in_array( $scope, array( 'profile', 'group', 'site' ), true ) ) {
			wp_send_json_error( null, 400 );
		}

		$authorized_scope_id = $this->authorize_sticky_request( $activity_id, $scope, $scope_id );
		if ( is_wp_error( $authorized_scope_id ) ) {
			wp_send_json_error( array( 'message' => $authorized_scope_id->get_error_message() ), (int) ( $authorized_scope_id->get_error_data()['status'] ?? 403 ) );
		}

		$id = $this->pin( $activity_id, $scope, (int) $authorized_scope_id, get_current_user_id(), $expires_at );
		$id ? wp_send_json_success( array( 'sticky_id' => $id ) ) : wp_send_json_error( null, 500 );
	}

	public function ajax_unpin(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$activity_id = absint( $_POST['activity_id'] ?? 0 );
		$scope       = sanitize_key( $_POST['scope'] ?? 'profile' );
		$scope_id    = absint( $_POST['scope_id'] ?? 0 );
		// phpcs:enable

		$authorized_scope_id = $this->authorize_sticky_request( $activity_id, $scope, $scope_id );
		if ( is_wp_error( $authorized_scope_id ) ) {
			wp_send_json_error( array( 'message' => $authorized_scope_id->get_error_message() ), (int) ( $authorized_scope_id->get_error_data()['status'] ?? 403 ) );
		}

		$this->unpin( $activity_id, $scope, (int) $authorized_scope_id )
			? wp_send_json_success()
			: wp_send_json_error( null, 500 );
	}

	// ── Feed injection ────────────────────────────────────────────────────────

	/**
	 * Filter hook: prepend sticky IDs to activity query so they appear first.
	 * The Activity class would need to call apply_filters('arshid6social_get_activity_args', $args)
	 * before its query; if not yet implemented, this hook is a no-op but ready.
	 */
	public function inject_stickies( array $args ): array {
		$scope    = $args['scope'] ?? 'all';
		$scope_id = $args['scope_id'] ?? 0;

		$sticky_scope = 'site';
		$sid          = 0;

		if ( isset( $args['group_id'] ) && $args['group_id'] ) {
			$sticky_scope = 'group';
			$sid          = (int) $args['group_id'];
		} elseif ( isset( $args['user_id'] ) && $args['user_id'] ) {
			$sticky_scope = 'profile';
			$sid          = (int) $args['user_id'];
		}

		$args['sticky_ids'] = $this->get_sticky_ids( $sticky_scope, $sid );
		return $args;
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Sticky_Posts_REST() )->register_routes();
	}
}
