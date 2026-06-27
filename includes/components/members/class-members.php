<?php
namespace Arshid6Social\Components\Members;

/**
 * Members component bootstrap.
 *
 * @package Arshid6Social\Components\Members
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Members
 *
 * Wires up all members-related sub-systems: xProfile, avatar, cover photo,
 * rewrite rules, directory, and REST routes.
 */
class Members {

	/** @var XProfile Extended profile manager. */
	public XProfile $xprofile;

	/** @var Avatar Avatar and cover photo handler. */
	public Avatar $avatar;

	public function __construct() {
		$this->xprofile = new XProfile();
		$this->avatar   = new Avatar();

		$this->hooks();
	}

	private function hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirect_author_to_profile' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle_profile_page' ) );
		add_action( 'wp_ajax_arshid6social_update_profile', array( $this, 'ajax_update_profile' ) );
		add_action( 'wp_ajax_arshid6social_save_bio', array( $this, 'ajax_save_bio' ) );
		add_action( 'wp_ajax_arshid6social_save_user_setting', array( $this, 'ajax_save_user_setting' ) );
		add_action( 'wp_ajax_arshid6social_change_password', array( $this, 'ajax_change_password' ) );
		add_action( 'wp_ajax_arshid6social_get_members', array( $this, 'ajax_get_members' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_get_members', array( $this, 'ajax_get_members' ) );
		add_action( 'wp_ajax_arshid6social_check_username', array( $this, 'ajax_check_username' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_check_username', array( $this, 'ajax_check_username' ) );
		add_action( 'wp_ajax_arshid6social_check_username_change', array( $this, 'ajax_check_username_change' ) );
		add_action( 'wp_ajax_arshid6social_change_username', array( $this, 'ajax_change_username' ) );
		add_action( 'wp_ajax_arshid6social_save_display_name', array( $this, 'ajax_save_display_name' ) );
		add_action( 'wp_ajax_arshid6social_save_friends_privacy', array( $this, 'ajax_save_friends_privacy' ) );

		// GDPR: register data exporter and eraser.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_eraser' ) );

		// After user registers, create default xProfile data.
		add_action( 'user_register', array( $this, 'after_user_register' ) );

		// Sync WP display name to xProfile Name field.
		add_action( 'profile_update', array( $this->xprofile, 'sync_display_name' ) );

		// Sitemap.
		add_action( 'init', array( $this, 'register_sitemap_provider' ) );
	}

	public function register_sitemap_provider(): void {
		if ( function_exists( 'wp_sitemaps_add_provider' ) ) {
			wp_sitemaps_add_provider( 'arshid6social_members', new Members_Sitemap_Provider() );
		}
	}

	/**
	 * Registers member profile rewrite rules.
	 *
	 * The directory page (/members/) is handled by a WordPress page with the
	 * [arshid6social_members] shortcode created during activation — no rewrite rule needed.
	 * Pagination for the directory is handled by AJAX in the shortcode.
	 * Only individual profile URLs need custom rewrite rules.
	 */
	public function add_rewrite_rules(): void {
		// Member profile: /members/{slug}/, /members/{slug}/{tab}/
		add_rewrite_rule( '^members/([^/]+)/?$', 'index.php?arshid6social_profile=$matches[1]', 'top' );
		add_rewrite_rule( '^members/([^/]+)/([^/]+)/?$', 'index.php?arshid6social_profile=$matches[1]&arshid6social_profile_tab=$matches[2]', 'top' );
	}

	/**
	 * Registers custom query vars.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'arshid6social_members';
		$vars[] = 'arshid6social_profile';
		$vars[] = 'arshid6social_profile_tab';
		return $vars;
	}

	/**
	 * Redirects WordPress author archive URLs to the social network member profile page.
	 * e.g. /author/john/ → /members/john/
	 */
	public function redirect_author_to_profile(): void {
		if ( ! is_author() ) {
			return;
		}
		$user = get_queried_object();
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		wp_safe_redirect( home_url( '/members/' . $user->user_nicename . '/' ), 301 );
		exit;
	}

	/**
	 * Intercepts profile and directory page requests and renders templates.
	 */
	public function handle_profile_page(): void {
		$is_directory = get_query_var( 'arshid6social_members' );
		$profile_slug = get_query_var( 'arshid6social_profile' );

		if ( $is_directory ) {
			global $arshid6social_is_page;
			$arshid6social_is_page = true;

			$loader = \Arshid6Social\Template_Loader::instance();
			$loader->get_template( 'members/directory.php', array( 'component' => $this ) );
			exit;
		}

		if ( $profile_slug ) {
			global $arshid6social_is_page, $post, $wp_query;
			$arshid6social_is_page = true;

			$user = get_user_by( 'slug', sanitize_title( $profile_slug ) );
			if ( ! $user ) {
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
				return;
			}

			$tab    = sanitize_key( get_query_var( 'arshid6social_profile_tab', 'activity' ) );
			$loader = \Arshid6Social\Template_Loader::instance();

			// Make the theme / Elementor / page-builder think we are on the
			// Members page so the full header, footer, and body classes render.
			$members_page_id = (int) get_option( 'arshid6social_page_members', 0 );
			if ( $members_page_id ) {
				$members_post = get_post( $members_page_id );
				if ( $members_post instanceof \WP_Post ) {
					$post = $members_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

					// Fully prime the main query so every theme and plugin
					// (including Elementor) sees a proper singular page.
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

			// Inject profile HTML instead of the members-page shortcode.
			$profile_user_ref = $user;
			$profile_tab_ref  = $tab;
			$component_ref    = $this;
			add_filter(
				'the_content',
				static function () use ( $loader, $component_ref, $profile_user_ref, $profile_tab_ref ): string {
					try {
						return $loader->get_template(
							'members/profile.php',
							array(
								'component'    => $component_ref,
								'profile_user' => $profile_user_ref,
								'active_tab'   => $profile_tab_ref,
							),
							true
						);
					} catch ( \Throwable $e ) {
						$log = sprintf(
							"[WPSN Profile Error] %s in %s on line %d\nTrace: %s\n",
							$e->getMessage(),
							$e->getFile(),
							$e->getLine(),
							$e->getTraceAsString()
						);
						error_log( $log ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
						if ( current_user_can( 'manage_options' ) ) {
							return '<div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:1rem;border-radius:8px;margin:1rem;font-family:monospace;white-space:pre-wrap;">'
								. '<strong>Profile Error (admin only):</strong>' . "\n"
								. esc_html( $e->getMessage() ) . "\n"
								. 'in ' . esc_html( $e->getFile() ) . ' line ' . esc_html( (string) $e->getLine() )
								. '</div>';
						}
						return '';
					}
				},
				99
			);

			// Remove shortcode output so the members page shortcode does not
			// render alongside our profile HTML.
			remove_shortcode( 'arshid6social_members' );

			// Let WordPress load the normal page template (theme's single.php /
			// page.php / Elementor template) instead of exiting early.
			// We do NOT call get_header() / get_footer() manually.
			return;
		}
	}

	/**
	 * AJAX: Saves xProfile data for the current user.
	 */
	public function ajax_update_profile(): void {
		if ( ! check_ajax_referer( 'arshid6social_update_profile', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$fields  = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? $_POST['fields'] : array(); // phpcs:ignore WordPress.Security.NonceVerification

		$errors = $this->xprofile->save_profile_data( $user_id, $fields );

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ), 422 );
		}

		do_action( 'arshid6social_profile_updated', $user_id );
		wp_send_json_success( array( 'message' => __( 'Profile updated.', '6arshid social community' ) ) );
	}

	/**
	 * AJAX: Saves the current user's bio field by name.
	 */
	public function ajax_save_bio(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		global $wpdb;
		$user_id  = get_current_user_id();
		$bio      = wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$field_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sn_xprofile_fields WHERE name = %s LIMIT 1",
				'bio'
			)
		);

		if ( ! $field_id ) {
			$group_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id FROM {$wpdb->prefix}sn_xprofile_groups ORDER BY group_order ASC LIMIT 1"
			);
			if ( ! $group_id ) {
				wp_send_json_error( array( 'message' => __( 'Profile field group not found.', '6arshid social community' ) ), 500 );
			}
			$max_order = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT MAX(field_order) FROM {$wpdb->prefix}sn_xprofile_fields WHERE group_id = %d",
					$group_id
				)
			);
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_xprofile_fields',
				array(
					'group_id'    => $group_id,
					'parent_id'   => 0,
					'type'        => 'textarea',
					'name'        => 'bio',
					'description' => '',
					'is_required' => 0,
					'field_order' => $max_order + 1,
					'can_delete'  => 1,
					'visibility'  => 'public',
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
			);
			$field_id = (int) $wpdb->insert_id;
		}

		if ( ! $field_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not create bio field.', '6arshid social community' ) ), 500 );
		}

		$this->xprofile->save_field_value( $user_id, $field_id, $bio );
		wp_send_json_success( array( 'bio' => wp_kses_post( $bio ) ) );
	}

	/**
	 * AJAX: Saves a single user preference (user meta) for the current user.
	 */
	public function ajax_save_user_setting(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		$allowed = array(
			'arshid6social_reaction_style'    => array( 'heart', 'emoji' ),
			'arshid6social_activity_feed_tab' => array( 'all', 'follow' ),
			'arshid6social_theme_mode'        => array( 'light', 'dark', 'dim', 'system' ),
		);

		$key   = sanitize_key( wp_unslash( $_POST['setting_key'] ?? '' ) );   // phpcs:ignore WordPress.Security.NonceVerification
		$value = sanitize_key( wp_unslash( $_POST['setting_value'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! array_key_exists( $key, $allowed ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid setting.', '6arshid social community' ) ) );
		}

		if ( ! in_array( $value, $allowed[ $key ], true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid value.', '6arshid social community' ) ) );
		}

		update_user_meta( get_current_user_id(), $key, $value );
		wp_send_json_success( array( 'message' => __( 'Saved.', '6arshid social community' ) ) );
	}

	/**
	 * AJAX: Change the current user's password.
	 */
	public function ajax_change_password(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$current  = isset( $_POST['current_password'] ) ? wp_unslash( $_POST['current_password'] ) : '';
		$new      = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : '';
		$confirm  = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : '';
		// phpcs:enable

		if ( '' === $current || '' === $new || '' === $confirm ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', '6arshid social community' ) ) );
		}

		if ( $new !== $confirm ) {
			wp_send_json_error( array( 'message' => __( 'New passwords do not match.', '6arshid social community' ) ) );
		}

		if ( strlen( $new ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', '6arshid social community' ) ) );
		}

		$user = wp_get_current_user();
		if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'Current password is incorrect.', '6arshid social community' ) ) );
		}

		wp_set_password( $new, $user->ID );

		// Re-authenticate so the session stays valid after password change.
		wp_set_auth_cookie( $user->ID, true );

		wp_send_json_success( array( 'message' => __( 'Password changed successfully.', '6arshid social community' ) ) );
	}

	/**
	 * AJAX: Returns paginated members list as JSON.
	 */
	public function ajax_get_members(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$page     = max( 1, absint( $_REQUEST['page'] ?? 1 ) );
		$search   = sanitize_text_field( wp_unslash( $_REQUEST['search'] ?? '' ) );
		$type     = sanitize_key( $_REQUEST['type'] ?? 'newest' );
		$per_page = min( 50, max( 1, absint( $_REQUEST['per_page'] ?? get_option( 'arshid6social_members_per_page', 20 ) ) ) );
		// phpcs:enable

		$members = $this->get_members(
			array(
				'page'    => $page,
				'search'  => $search,
				'type'    => $type,
				'number'  => $per_page,
			)
		);

		wp_send_json_success( $members );
	}

	/**
	 * AJAX: Check whether a username is available during registration.
	 */
	/**
	 * Validate username against admin-configured restrictions (min length, reserved list).
	 *
	 * @return \WP_Error Error object — has_errors() is false when username passes.
	 */
	public static function validate_username_restrictions( string $username ): \WP_Error {
		$errors  = new \WP_Error();
		$min_len = (int) get_option( 'arshid6social_username_min_length', 4 );

		if ( mb_strlen( $username ) < $min_len ) {
			$errors->add(
				'username_too_short',
				/* translators: %d: minimum character count */
				sprintf( __( 'Username must be at least %d characters.', '6arshid social community' ), $min_len )
			);
		}

		$reserved_raw = (string) get_option( 'arshid6social_reserved_usernames', '' );
		if ( '' !== $reserved_raw ) {
			$reserved = array_filter( array_map( 'trim', explode( "\n", mb_strtolower( $reserved_raw ) ) ) );
			if ( in_array( mb_strtolower( $username ), $reserved, true ) ) {
				$errors->add( 'username_reserved', __( 'That username is not available.', '6arshid social community' ) );
			}
		}

		return $errors;
	}

	public function ajax_check_username(): void {
		if ( ! check_ajax_referer( 'arshid6social_check_username', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );

		if ( ! $username ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a username.', '6arshid social community' ) ) );
		}

		$restriction_errors = self::validate_username_restrictions( $username );
		if ( $restriction_errors->has_errors() ) {
			wp_send_json_error( array( 'message' => $restriction_errors->get_error_message() ) );
		}

		if ( username_exists( $username ) ) {
			wp_send_json_error( array( 'message' => __( 'That username is already taken.', '6arshid social community' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Username is available!', '6arshid social community' ) ) );
	}

	/**
	 * AJAX: Check username availability for the change-username form on the settings page.
	 * Unlike ajax_check_username, this allows the current user's own login to pass as "available".
	 */
	public function ajax_check_username_change(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $username ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a username.', '6arshid social community' ) ) );
		}

		$restriction_errors = self::validate_username_restrictions( $username );
		if ( $restriction_errors->has_errors() ) {
			wp_send_json_error( array( 'message' => $restriction_errors->get_error_message() ) );
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_.\-]+$/', $username ) ) {
			wp_send_json_error( array( 'message' => __( 'Username may only contain letters, numbers, underscores, dots and hyphens.', '6arshid social community' ) ) );
		}

		$current_user = wp_get_current_user();

		// Own username is always "available" (no-op change).
		if ( $username === $current_user->user_login ) {
			wp_send_json_success( array( 'message' => __( 'That is your current username.', '6arshid social community' ), 'is_current' => true ) );
		}

		$existing = get_user_by( 'login', $username );
		if ( $existing && (int) $existing->ID !== (int) $current_user->ID ) {
			wp_send_json_error( array( 'message' => __( 'That username is already taken.', '6arshid social community' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Username is available!', '6arshid social community' ), 'is_current' => false ) );
	}

	/**
	 * AJAX: Change the current user's username (user_login + user_nicename).
	 */
	public function ajax_change_username(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		$new_username = sanitize_user( wp_unslash( $_POST['new_username'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $new_username ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a username.', '6arshid social community' ) ) );
		}

		if ( mb_strlen( $new_username ) < 3 ) {
			wp_send_json_error( array( 'message' => __( 'Username must be at least 3 characters.', '6arshid social community' ) ) );
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_.\-]+$/', $new_username ) ) {
			wp_send_json_error( array( 'message' => __( 'Username may only contain letters, numbers, underscores, dots and hyphens.', '6arshid social community' ) ) );
		}

		$current_user = wp_get_current_user();

		if ( $new_username === $current_user->user_login ) {
			wp_send_json_error( array( 'message' => __( 'That is already your username.', '6arshid social community' ) ) );
		}

		$existing = get_user_by( 'login', $new_username );
		if ( $existing && (int) $existing->ID !== (int) $current_user->ID ) {
			wp_send_json_error( array( 'message' => __( 'That username is already taken.', '6arshid social community' ) ) );
		}

		global $wpdb;

		$nicename = sanitize_title( $new_username );

		// Ensure nicename is unique.
		$suffix = 2;
		$base   = $nicename;
		while (
			$wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s AND ID != %d", $nicename, $current_user->ID ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		) {
			$nicename = $base . '-' . $suffix;
			$suffix++;
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->users,
			array(
				'user_login'    => $new_username,
				'user_nicename' => $nicename,
			),
			array( 'ID' => $current_user->ID ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not update username. Please try again.', '6arshid social community' ) ) );
		}

		// Flush user caches so the change is seen immediately.
		clean_user_cache( $current_user->ID );
		wp_cache_delete( $current_user->ID, 'users' );
		wp_cache_delete( $current_user->user_login, 'userlogins' );

		$new_profile_url = home_url( '/members/' . $nicename . '/settings/' );

		wp_send_json_success(
			array(
				'message'     => __( 'Username changed successfully!', '6arshid social community' ),
				'new_url'     => $new_profile_url,
				'new_username' => $new_username,
			)
		);
	}

	/**
	 * AJAX: Save the current user's display name.
	 */
	public function ajax_save_display_name(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		$display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( '' === $display_name ) {
			wp_send_json_error( array( 'message' => __( 'Display name cannot be empty.', '6arshid social community' ) ) );
		}

		if ( mb_strlen( $display_name ) > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Display name is too long.', '6arshid social community' ) ) );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		// Write directly to wp_users to avoid hook interference.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->users,
			array( 'display_name' => $display_name ),
			array( 'ID' => $user_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_user_cache( $user_id );

		// Sync to every xProfile "Name" field so the profile-edit form stays in sync.
		$name_field_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_xprofile_fields WHERE LOWER(name) = 'name' LIMIT 1"
		);
		if ( $name_field_id ) {
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}sn_xprofile_data WHERE field_id = %d AND user_id = %d",
					$name_field_id,
					$user_id
				)
			);
			if ( $existing ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'sn_xprofile_data',
					array( 'value' => $display_name, 'last_updated' => current_time( 'mysql' ) ),
					array( 'field_id' => $name_field_id, 'user_id' => $user_id ),
					array( '%s', '%s' ),
					array( '%d', '%d' )
				);
			} else {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'sn_xprofile_data',
					array( 'field_id' => $name_field_id, 'user_id' => $user_id, 'value' => $display_name, 'last_updated' => current_time( 'mysql' ) ),
					array( '%d', '%d', '%s', '%s' )
				);
			}
			\Arshid6Social\Cache::delete( "xprofile_user_{$user_id}" );
		}

		wp_send_json_success( array( 'display_name' => $display_name ) );
	}

	/**
	 * AJAX: Save the current user's friends list privacy setting.
	 */
	public function ajax_save_friends_privacy(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid social community' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid social community' ) ), 401 );
		}

		$privacy = sanitize_key( wp_unslash( $_POST['friends_privacy'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! in_array( $privacy, array( 'public', 'private' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid privacy value.', '6arshid social community' ) ) );
		}

		update_user_meta( get_current_user_id(), 'arshid6social_friends_list_privacy', $privacy );
		wp_send_json_success( array( 'message' => __( 'Saved.', '6arshid social community' ) ) );
	}

	/**
	 * Returns a formatted list of members with pagination data.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	public function get_members( array $args = array() ): array {
		$defaults = array(
			'page'     => 1,
			'number'   => (int) get_option( 'arshid6social_members_per_page', 20 ),
			'search'   => '',
			'type'     => 'newest',
			'exclude'  => array(),
		);
		$args = wp_parse_args( $args, $defaults );

		// Allow 'per_page' as an alias for 'number'.
		if ( isset( $args['per_page'] ) ) {
			$args['number'] = (int) $args['per_page'];
		}

		$query_args = array(
			'number'      => $args['number'],
			'offset'      => ( $args['page'] - 1 ) * $args['number'],
			'fields'      => 'all',
			'count_total' => true,
		);

		if ( ! empty( $args['exclude'] ) ) {
			$query_args['exclude'] = array_map( 'intval', (array) $args['exclude'] );
		}

		if ( $args['search'] ) {
			$query_args['search'] = '*' . $args['search'] . '*';
		}

		switch ( $args['type'] ) {
			case 'active':
				$query_args['orderby'] = 'meta_value';
				$query_args['meta_key'] = 'last_activity'; // phpcs:ignore WordPress.DB.SlowDBQuery
				$query_args['order'] = 'DESC';
				break;
			case 'alphabetical':
				$query_args['orderby'] = 'display_name';
				$query_args['order'] = 'ASC';
				break;
			default:
				$query_args['orderby'] = 'registered';
				$query_args['order'] = 'DESC';
		}

		// Always exclude suspended users from the public members list.
		$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array(
				'key'     => 'arshid6social_suspended',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'arshid6social_suspended',
				'value'   => '1',
				'compare' => '!=',
			),
		);

		$user_query = new \WP_User_Query( $query_args );
		$users      = $user_query->get_results();
		$total      = (int) $user_query->get_total();

		$members = array();
		foreach ( $users as $user ) {
			$members[] = $this->format_member( $user );
		}

		return array(
			'members'      => $members,
			'total'        => $total,
			'total_pages'  => (int) ceil( $total / $args['number'] ),
			'current_page' => $args['page'],
		);
	}

	/**
	 * Formats a WP_User object into a frontend-ready array.
	 *
	 * @param \WP_User $user WordPress user object.
	 * @return array<string, mixed>
	 */
	public function format_member( \WP_User $user ): array {
		return array(
			'id'            => $user->ID,
			'name'          => $user->display_name,
			'username'      => $user->user_login,
			'profileUrl'    => esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ),
			'avatarUrl'     => esc_url( $this->avatar->get_avatar_url( $user->ID ) ),
			'coverUrl'      => esc_url( $this->avatar->get_cover_url( $user->ID ) ),
			'bio'           => wp_kses_post( $this->xprofile->get_field_value( $user->ID, 'bio' ) ),
			'isVerified'    => arshid6social_verification() ? arshid6social_verification()->is_verified( $user->ID ) : (bool) get_user_meta( $user->ID, 'arshid6social_verified', true ),
			'isSuspended'   => (bool) get_user_meta( $user->ID, 'arshid6social_suspended', true ),
			'isOnline'      => $this->is_user_online( $user->ID ),
			'friendCount'   => $this->get_friend_count( $user->ID ),
			'lastActivity'  => get_user_meta( $user->ID, 'arshid6social_last_activity', true ) ?: '',
			'registered'    => $user->user_registered,
		);
	}

	/**
	 * Checks if a user has been active within the last 15 minutes.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_user_online( int $user_id ): bool {
		$last = (int) get_user_meta( $user_id, 'arshid6social_last_activity_timestamp', true );
		return $last && ( time() - $last < 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Returns the friend count for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_friend_count( int $user_id ): int {
		return (int) \Arshid6Social\Cache::remember(
			'friend_count_' . $user_id,
			function () use ( $user_id ) {
				global $wpdb;
				return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}sn_friends
						 WHERE (initiator_user_id = %d OR friend_user_id = %d) AND is_confirmed = 1",
						$user_id,
						$user_id
					)
				);
			},
			300
		);
	}

	/**
	 * Seeds default profile data for a newly registered user.
	 *
	 * @param int $user_id Newly created user ID.
	 */
	public function after_user_register( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->xprofile->save_field_value( $user_id, 1, $user->display_name );

		update_user_meta( $user_id, 'arshid6social_last_activity', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'arshid6social_last_activity_timestamp', time() );

		do_action( 'arshid6social_member_registered', $user_id );
	}

	/**
	 * Registers the GDPR data exporter.
	 *
	 * @param array<int, array<string, mixed>> $exporters Registered exporters.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_data_exporter( array $exporters ): array {
		$exporters[] = array(
			'exporter_friendly_name' => __( '6Arshid Social Community Profile Data', '6arshid social community' ),
			'callback'               => array( $this->xprofile, 'export_data' ),
		);
		return $exporters;
	}

	/**
	 * Registers the GDPR data eraser.
	 *
	 * @param array<int, array<string, mixed>> $erasers Registered erasers.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_data_eraser( array $erasers ): array {
		$erasers[] = array(
			'eraser_friendly_name' => __( '6Arshid Social Community Profile Data', '6arshid social community' ),
			'callback'             => array( $this->xprofile, 'erase_data' ),
		);
		return $erasers;
	}

	/**
	 * Registers REST API routes for the Members component.
	 */
	public function register_rest_routes(): void {
		$controller = new Members_REST();
		$controller->register_routes();
	}
}
