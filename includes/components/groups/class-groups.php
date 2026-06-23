<?php
namespace Arshid6Social\Components\Groups;

/**
 * Groups component.
 *
 * @package Arshid6Social\Components\Groups
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Groups
 *
 * Manages group creation, membership, invitations, and group-scoped activity.
 */
class Groups {

	public function __construct() {
		$this->hooks();
	}

	private function hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_group_page' ) );

		add_action( 'wp_ajax_arshid6social_create_group', array( $this, 'ajax_create_group' ) );
		add_action( 'wp_ajax_arshid6social_get_groups', array( $this, 'ajax_get_groups' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_get_groups', array( $this, 'ajax_get_groups' ) );
		add_action( 'wp_ajax_arshid6social_join_group', array( $this, 'ajax_join_group' ) );
		add_action( 'wp_ajax_arshid6social_leave_group', array( $this, 'ajax_leave_group' ) );
		add_action( 'wp_ajax_arshid6social_invite_to_group', array( $this, 'ajax_invite_to_group' ) );
		add_action( 'wp_ajax_arshid6social_delete_group',         array( $this, 'ajax_delete_group' ) );
		add_action( 'wp_ajax_arshid6social_update_group',         array( $this, 'ajax_update_group' ) );
		add_action( 'wp_ajax_arshid6social_upload_group_avatar',  array( $this, 'ajax_upload_group_avatar' ) );
		add_action( 'wp_ajax_arshid6social_upload_group_cover',   array( $this, 'ajax_upload_group_cover' ) );

		// Sitemap.
		add_action( 'init', array( $this, 'register_sitemap_provider' ) );
	}

	public function register_sitemap_provider(): void {
		if ( function_exists( 'wp_sitemaps_add_provider' ) ) {
			wp_sitemaps_add_provider( 'arshid6social_groups', new Groups_Sitemap_Provider() );
		}
	}

	public function add_rewrite_rules(): void {
		// Directory (/groups/) is a WordPress page with [arshid6social_groups] shortcode — no rule needed.
		add_rewrite_rule( '^groups/create/?$', 'index.php?arshid6social_groups=create', 'top' );
		add_rewrite_rule( '^groups/([^/]+)/?$', 'index.php?arshid6social_group=$matches[1]', 'top' );
		add_rewrite_rule( '^groups/([^/]+)/([^/]+)/?$', 'index.php?arshid6social_group=$matches[1]&arshid6social_group_tab=$matches[2]', 'top' );
	}

	public function register_query_vars( array $vars ): array {
		$vars[] = 'arshid6social_groups';
		$vars[] = 'arshid6social_group';
		$vars[] = 'arshid6social_group_tab';
		return $vars;
	}

	public function handle_group_page(): void {
		$groups_page = get_query_var( 'arshid6social_groups' );
		$group_slug  = get_query_var( 'arshid6social_group' );

		if ( ! $groups_page && ! $group_slug ) {
			return;
		}

		global $arshid6social_is_page, $post, $wp_query;
		$arshid6social_is_page = true;

		$loader = \Arshid6Social\Template_Loader::instance();

		// Prime $post / $wp_query with the Groups page so the theme renders its
		// full template (header, nav, footer) just as it would for a normal page.
		$groups_page_id = (int) get_option( 'arshid6social_page_groups', 0 );
		if ( $groups_page_id ) {
			$groups_post = get_post( $groups_page_id );
			if ( $groups_post instanceof \WP_Post ) {
				$post = $groups_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				$wp_query->queried_object    = $post;
				$wp_query->queried_object_id = $post->ID;
				$wp_query->is_page           = true;
				$wp_query->is_singular       = true;
				$wp_query->is_archive        = false;
				$wp_query->is_home           = false;
				$wp_query->is_404            = false;
				$wp_query->post              = $post;
				$wp_query->posts             = array( $post );
				$wp_query->found_posts       = 1;
				$wp_query->post_count        = 1;
				$wp_query->max_num_pages     = 1;
				setup_postdata( $post );
			}
		}

		// Remove the [arshid6social_groups] shortcode so it won't render alongside our HTML.
		remove_shortcode( 'arshid6social_groups' );

		if ( 'create' === $groups_page ) {

			add_filter(
				'the_content',
				static function () use ( $loader ): string {
					return $loader->get_template( 'groups/create.php', array(), true );
				},
				99
			);

		} elseif ( $group_slug ) {

			$group = $this->get_by_slug( sanitize_title( $group_slug ) );

			if ( ! $group ) {
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
				return;
			}

			// Private/hidden visibility check.
			if ( 'public' !== $group->status && ! $this->is_member( get_current_user_id(), $group->id ) && ! current_user_can( 'arshid6social_manage_groups' ) ) {
				$formatted = $this->format_group( $group );
				add_filter(
					'the_content',
					static function () use ( $loader, $formatted ): string {
						return $loader->get_template( 'groups/restricted.php', array( 'group' => $formatted ), true );
					},
					99
				);
				return;
			}

			$formatted   = $this->format_group( $group );
			$current_tab = sanitize_key( get_query_var( 'arshid6social_group_tab', 'activity' ) );
			$component   = $this;

			add_filter(
				'the_content',
				static function () use ( $loader, $formatted, $component, $current_tab ): string {
					return $loader->get_template(
						'groups/single.php',
						array(
							'group'       => $formatted,
							'component'   => $component,
							'current_tab' => $current_tab,
						),
						true
					);
				},
				99
			);
		}

		// Let WordPress load the theme's normal page template.
		// Do NOT call get_header() / get_footer() manually here.
	}

	/**
	 * Creates a new group.
	 *
	 * @param array<string, mixed> $args Group data.
	 * @return int|false Group ID or false on failure.
	 */
	public function create( array $args ): int|false {
		global $wpdb;

		$defaults = array(
			'creator_id'  => get_current_user_id(),
			'name'        => '',
			'description' => '',
			'status'      => 'public',
			'parent_id'   => 0,
			'enable_forum' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['name'] ) ) {
			return false;
		}

		$slug        = wp_unique_id( sanitize_title( $args['name'] ) . '-' );
		$unique_slug = $this->generate_unique_slug( sanitize_title( $args['name'] ) );

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_groups',
			array(
				'creator_id'   => absint( $args['creator_id'] ),
				'name'         => sanitize_text_field( $args['name'] ),
				'slug'         => $unique_slug,
				'description'  => wp_kses_post( $args['description'] ),
				'status'       => in_array( $args['status'], array( 'public', 'private', 'hidden' ), true ) ? $args['status'] : 'public',
				'parent_id'    => absint( $args['parent_id'] ),
				'enable_forum' => absint( $args['enable_forum'] ),
				'date_created' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$group_id = (int) $wpdb->insert_id;

		// Add creator as group admin.
		$this->add_member( $group_id, $args['creator_id'], array( 'is_admin' => 1, 'is_confirmed' => 1 ) );

		do_action( 'arshid6social_group_created', $group_id, $args );

		return $group_id;
	}

	/**
	 * Returns a single group by ID.
	 *
	 * @param int $group_id Group ID.
	 * @return object|null
	 */
	public function get_by_id( int $group_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_groups WHERE id = %d", $group_id )
		);
	}

	/**
	 * Returns a single group by slug.
	 *
	 * @param string $slug Group slug.
	 * @return object|null
	 */
	public function get_by_slug( string $slug ): ?object {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_groups WHERE slug = %s", $slug )
		);
	}

	/**
	 * Returns paginated groups with optional filters.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	public function get_groups( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'page'    => 1,
			'number'  => (int) get_option( 'arshid6social_groups_per_page', 20 ),
			'search'  => '',
			'status'  => is_user_logged_in() ? array( 'public', 'private' ) : array( 'public' ),
			'type'    => 'newest',
		);
		$args   = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['number'];

		$where  = array( '1=1' );
		$values = array();

		// Always hide suspended groups and groups whose creator is suspended.
		$where[] = 'is_suspended = 0';
		$where[] = "creator_id NOT IN (
			SELECT user_id FROM {$wpdb->usermeta}
			WHERE meta_key = 'arshid6social_suspended' AND meta_value = '1'
		)";

		$statuses = array_intersect( (array) $args['status'], array( 'public', 'private', 'hidden' ) );
		if ( $statuses ) {
			$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
			$where[]      = "status IN ($placeholders)";
			$values       = array_merge( $values, $statuses );
		}

		if ( $args['search'] ) {
			$where[]  = '(name LIKE %s OR description LIKE %s)';
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$values[] = $like;
			$values[] = $like;
		}

		$order_by = 'date_created DESC';
		if ( 'alphabetical' === $args['type'] ) {
			$order_by = 'name ASC';
		} elseif ( 'active' === $args['type'] ) {
			$order_by = 'date_created DESC'; // Could join last activity when that exists.
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->prefix}sn_groups WHERE $where_sql ORDER BY $order_by LIMIT %d OFFSET %d";
		$values[]  = $args['number'];
		$values[]  = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$groups = array_map( array( $this, 'format_group' ), $rows );

		return array(
			'groups'       => $groups,
			'total'        => $total,
			'total_pages'  => (int) ceil( $total / $args['number'] ),
			'current_page' => $args['page'],
		);
	}

	/**
	 * Formats a raw group row for the frontend.
	 *
	 * @param object $group Raw DB row.
	 * @return array<string, mixed>
	 */
	public function format_group( object $group ): array {
		$avatar = get_option( "arshid6social_group_avatar_{$group->id}", '' );
		$cover  = get_option( "arshid6social_group_cover_{$group->id}", '' );

		$creator_id       = (int) ( $group->creator_id ?? 0 );
		$creator_suspended = $creator_id > 0 && (bool) get_user_meta( $creator_id, 'arshid6social_suspended', true );

		return array(
			'id'            => (int) $group->id,
			'name'          => esc_html( $group->name ),
			'slug'          => esc_attr( $group->slug ),
			'description'   => wp_kses_post( $group->description ),
			'status'        => esc_attr( $group->status ),
			'creatorId'     => $creator_id,
			'isSuspended'   => (bool) ( $group->is_suspended ?? false ) || $creator_suspended,
			'suspendReason' => isset( $group->suspend_reason ) ? esc_html( $group->suspend_reason ) : '',
			'url'           => esc_url( home_url( '/groups/' . $group->slug . '/' ) ),
			'avatarUrl'     => $avatar ? esc_url( $avatar ) : '',
			'coverUrl'      => $cover ? esc_url( $cover ) : '',
			'memberCount'   => $this->get_member_count( (int) $group->id ),
			'isMember'      => is_user_logged_in() ? $this->is_member( get_current_user_id(), (int) $group->id ) : false,
			'isAdmin'       => is_user_logged_in() ? $this->is_admin( get_current_user_id(), (int) $group->id ) : false,
			'dateCreated'   => esc_attr( $group->date_created ),
		);
	}

	/**
	 * Adds a member to a group.
	 *
	 * @param int                  $group_id Group ID.
	 * @param int                  $user_id  User ID.
	 * @param array<string, mixed> $args     Additional columns.
	 * @return bool
	 */
	public function add_member( int $group_id, int $user_id, array $args = array() ): bool {
		global $wpdb;

		if ( $this->is_member( $user_id, $group_id ) ) {
			return false;
		}

		$data = wp_parse_args(
			$args,
			array(
				'group_id'     => $group_id,
				'user_id'      => $user_id,
				'inviter_id'   => 0,
				'is_admin'     => 0,
				'is_mod'       => 0,
				'is_confirmed' => 1,
				'is_banned'    => 0,
				'invite_sent'  => 0,
				'date_modified' => current_time( 'mysql' ),
			)
		);

		$result = $wpdb->insert( $wpdb->prefix . 'sn_groups_members', $data, array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $result ) {
			\Arshid6Social\Cache::delete( "group_member_count_{$group_id}" );
			do_action( 'arshid6social_group_member_added', $group_id, $user_id );
		}

		return (bool) $result;
	}

	/**
	 * Removes a member from a group.
	 *
	 * @param int $group_id Group ID.
	 * @param int $user_id  User ID.
	 */
	public function remove_member( int $group_id, int $user_id ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'sn_groups_members', array( 'group_id' => $group_id, 'user_id' => $user_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		\Arshid6Social\Cache::delete( "group_member_count_{$group_id}" );
		do_action( 'arshid6social_group_member_removed', $group_id, $user_id );
	}

	/**
	 * Checks if a user is a member of a group.
	 *
	 * @param int $user_id  User ID.
	 * @param int $group_id Group ID.
	 * @return bool
	 */
	public function is_member( int $user_id, int $group_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_groups_members WHERE group_id = %d AND user_id = %d AND is_confirmed = 1 AND is_banned = 0",
				$group_id,
				$user_id
			)
		);
	}

	/**
	 * Checks if a user is a group admin.
	 *
	 * @param int $user_id  User ID.
	 * @param int $group_id Group ID.
	 * @return bool
	 */
	public function is_admin( int $user_id, int $group_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_groups_members WHERE group_id = %d AND user_id = %d AND is_admin = 1",
				$group_id,
				$user_id
			)
		);
	}

	/**
	 * Returns the member count for a group.
	 *
	 * @param int $group_id Group ID.
	 * @return int
	 */
	public function get_member_count( int $group_id ): int {
		return (int) \Arshid6Social\Cache::remember(
			"group_member_count_{$group_id}",
			function () use ( $group_id ) {
				global $wpdb;
				return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}sn_groups_members WHERE group_id = %d AND is_confirmed = 1 AND is_banned = 0",
						$group_id
					)
				);
			},
			300
		);
	}

	/**
	 * Generates a unique slug for a group, appending a number if needed.
	 *
	 * @param string $base Base slug.
	 * @return string Unique slug.
	 */
	private function generate_unique_slug( string $base ): string {
		global $wpdb;

		$slug    = $base;
		$counter = 1;

		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sn_groups WHERE slug = %s", $slug ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$slug = $base . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * AJAX: Creates a new group.
	 */
	public function ajax_create_group(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( get_user_meta( get_current_user_id(), 'arshid6social_suspended', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account has been suspended.', '6arshid social community' ) ), 403 );
		}

		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$status      = sanitize_key( $_POST['status'] ?? 'public' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Group name is required.', '6arshid social community' ) ), 400 );
		}

		$group_id = $this->create( array( 'name' => $name, 'description' => $description, 'status' => $status ) );

		if ( ! $group_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create group.', '6arshid social community' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'group'   => $this->format_group( $this->get_by_id( $group_id ) ),
				'message' => __( 'Group created!', '6arshid social community' ),
			)
		);
	}

	/**
	 * AJAX: Returns groups list.
	 */
	public function ajax_get_groups(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$data = $this->get_groups( array(
			'page'   => max( 1, absint( $_GET['page'] ?? 1 ) ),
			'search' => sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) ),
			'type'   => sanitize_key( $_GET['type'] ?? 'newest' ),
		) );
		// phpcs:enable

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Joins or requests to join a group.
	 */
	public function ajax_join_group(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$group_id = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id  = get_current_user_id();
		$group    = $this->get_by_id( $group_id );

		if ( ! $group ) {
			wp_send_json_error( array( 'message' => __( 'Group not found.', '6arshid social community' ) ), 404 );
		}

		if ( 'hidden' === $group->status ) {
			wp_send_json_error( array( 'message' => __( 'This group cannot be joined directly.', '6arshid social community' ) ), 403 );
		}

		$is_confirmed = ( 'public' === $group->status ) ? 1 : 0;
		$this->add_member( $group_id, $user_id, array( 'is_confirmed' => $is_confirmed ) );

		wp_send_json_success(
			array(
				'joined'  => $is_confirmed,
				'pending' => ! $is_confirmed,
				'message' => $is_confirmed
					? __( 'You have joined the group.', '6arshid social community' )
					: __( 'Membership request sent.', '6arshid social community' ),
			)
		);
	}

	/**
	 * AJAX: Leaves a group.
	 */
	public function ajax_leave_group(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$group_id = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id  = get_current_user_id();

		// Prevent sole admin from leaving.
		if ( $this->is_admin( $user_id, $group_id ) ) {
			global $wpdb;
			$admin_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_groups_members WHERE group_id = %d AND is_admin = 1", $group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $admin_count <= 1 ) {
				wp_send_json_error( array( 'message' => __( 'You are the only admin. Assign another admin before leaving.', '6arshid social community' ) ), 422 );
			}
		}

		$this->remove_member( $group_id, $user_id );
		wp_send_json_success( array( 'message' => __( 'You have left the group.', '6arshid social community' ) ) );
	}

	/**
	 * AJAX: Invites a user to a group (group admin only).
	 */
	public function ajax_invite_to_group(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$group_id  = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$invitee_id = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$current    = get_current_user_id();

		if ( ! $this->is_member( $current, $group_id ) && ! current_user_can( 'arshid6social_manage_groups' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only group members can invite others.', '6arshid social community' ) ), 403 );
		}

		if ( ! get_userdata( $invitee_id ) ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', '6arshid social community' ) ), 404 );
		}

		$this->add_member( $group_id, $invitee_id, array( 'inviter_id' => $current, 'is_confirmed' => 0, 'invite_sent' => 1 ) );

		do_action( 'arshid6social_group_invitation_sent', $group_id, $invitee_id, $current );

		wp_send_json_success( array( 'message' => __( 'Invitation sent.', '6arshid social community' ) ) );
	}

	/**
	 * AJAX: Deletes a group (group admin or site admin).
	 */
	public function ajax_delete_group(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$group_id = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id  = get_current_user_id();

		if ( ! $this->is_admin( $user_id, $group_id ) && ! current_user_can( 'arshid6social_manage_groups' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid social community' ) ), 403 );
		}

		$this->delete( $group_id );
		wp_send_json_success( array( 'message' => __( 'Group deleted.', '6arshid social community' ) ) );
	}

	/**
	 * AJAX: Updates a group's name, description, and status (group admin only).
	 */
	public function ajax_update_group(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$group_id = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id  = get_current_user_id();

		if ( ! $this->is_admin( $user_id, $group_id ) && ! current_user_can( 'arshid6social_manage_groups' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid social community' ) ), 403 );
		}

		$group = $this->get_by_id( $group_id );
		if ( ! $group ) {
			wp_send_json_error( array( 'message' => __( 'Group not found.', '6arshid social community' ) ), 404 );
		}

		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$status      = sanitize_key( $_POST['status'] ?? 'public' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Group name is required.', '6arshid social community' ) ), 400 );
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_groups',
			array(
				'name'        => $name,
				'description' => $description,
				'status'      => in_array( $status, array( 'public', 'private', 'hidden' ), true ) ? $status : 'public',
			),
			array( 'id' => $group_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'arshid6social_group_updated', $group_id );

		wp_send_json_success(
			array(
				'group'   => $this->format_group( $this->get_by_id( $group_id ) ),
				'message' => __( 'Group updated.', '6arshid social community' ),
			)
		);
	}

	/**
	 * Deletes a group and all associated data.
	 *
	 * @param int $group_id Group ID.
	 */
	public function delete( int $group_id ): void {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'sn_groups_members', array( 'group_id' => $group_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_groups_groupmeta', array( 'group_id' => $group_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_groups', array( 'id' => $group_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		do_action( 'arshid6social_group_deleted', $group_id );
	}

	/**
	 * AJAX: Uploads a group avatar image (group admin only).
	 */
	public function ajax_upload_group_avatar(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$group_id = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $group_id || ! $this->is_admin( get_current_user_id(), $group_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid social community' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', '6arshid social community' ) ), 400 );
		}

		$result = $this->save_group_image( $_FILES['file'], $group_id, 'avatar' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		// Delete old avatar file if it exists.
		$old_path = get_option( "arshid6social_group_avatar_path_{$group_id}", '' );
		if ( $old_path ) {
			\Arshid6Social\Media_Handler::delete_file( $old_path );
		}

		update_option( "arshid6social_group_avatar_{$group_id}", $result['url'], false );
		update_option( "arshid6social_group_avatar_path_{$group_id}", $result['path'], false );

		wp_send_json_success( array( 'url' => esc_url( $result['url'] ) ) );
	}

	/**
	 * AJAX: Uploads a group cover image (group admin only).
	 */
	public function ajax_upload_group_cover(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$group_id = absint( $_POST['group_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $group_id || ! $this->is_admin( get_current_user_id(), $group_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid social community' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', '6arshid social community' ) ), 400 );
		}

		$result = $this->save_group_image( $_FILES['file'], $group_id, 'cover' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		// Delete old cover file if it exists.
		$old_path = get_option( "arshid6social_group_cover_path_{$group_id}", '' );
		if ( $old_path ) {
			\Arshid6Social\Media_Handler::delete_file( $old_path );
		}

		update_option( "arshid6social_group_cover_{$group_id}", $result['url'], false );
		update_option( "arshid6social_group_cover_path_{$group_id}", $result['path'], false );

		wp_send_json_success( array( 'url' => esc_url( $result['url'] ) ) );
	}

	/**
	 * Saves a group image (avatar or cover) inside the social-network/groups/ directory
	 * so that file-level suspension blocking applies.
	 *
	 * @param array  $file     Entry from $_FILES.
	 * @param int    $group_id Group ID.
	 * @param string $slot     'avatar' or 'cover'.
	 * @return array{url: string, path: string}|\WP_Error
	 */
	private function save_group_image( array $file, int $group_id, string $slot ): array|\WP_Error {
		$allowed_mime = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$finfo        = new \finfo( FILEINFO_MIME_TYPE );
		$real_mime    = $finfo->file( $file['tmp_name'] );

		if ( ! in_array( $real_mime, $allowed_mime, true ) ) {
			return new \WP_Error( 'invalid_mime', __( 'Only image files are allowed.', '6arshid social community' ) );
		}

		$max_bytes = (int) get_option( 'arshid6social_max_upload_size_mb', 5 ) * MB_IN_BYTES;
		if ( $file['size'] > $max_bytes ) {
			return new \WP_Error( 'file_too_large', __( 'File is too large.', '6arshid social community' ) );
		}

		$upload_dir = wp_upload_dir();
		$dest_dir   = trailingslashit( $upload_dir['basedir'] ) . "social-network/groups/{$group_id}/{$slot}";

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', '6arshid social community' ) );
		}

		$ext      = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp' )[ $real_mime ] ?? 'jpg';
		$filename = wp_generate_uuid4() . '.' . $ext;
		$dest     = $dest_dir . '/' . $filename;

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'move_failed', __( 'Failed to save file.', '6arshid social community' ) );
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( ! $wp_filesystem || ! $wp_filesystem->move( $file['tmp_name'], $dest, true ) ) {
			return new \WP_Error( 'move_failed', __( 'Failed to save file.', '6arshid social community' ) );
		}

		$url = trailingslashit( $upload_dir['baseurl'] ) . "social-network/groups/{$group_id}/{$slot}/{$filename}";

		return array( 'url' => $url, 'path' => $dest );
	}

	/**
	 * Registers REST API routes.
	 */
	public function register_rest_routes(): void {
		( new Groups_REST() )->register_routes();
	}
}
