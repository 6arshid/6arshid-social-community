<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Bookmarks feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Bookmarks {

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_bookmark_toggle',         array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_arshid6social_bookmark_status',         array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_arshid6social_bookmark_collections',    array( $this, 'ajax_get_collections' ) );
		add_action( 'wp_ajax_arshid6social_bookmark_add_collection', array( $this, 'ajax_add_collection' ) );
		add_action( 'wp_ajax_arshid6social_bookmark_del_collection', array( $this, 'ajax_del_collection' ) );
		add_action( 'wp_ajax_arshid6social_bookmarks_feed',          array( $this, 'ajax_feed' ) );

		// When an activity is deleted, remove bookmarks pointing to it.
		add_action( 'arshid6social_activity_deleted', array( $this, 'on_activity_deleted' ) );

		// Register Saved Posts page in the admin Pages & Shortcodes screen.
		add_filter( 'arshid6social_page_definitions', array( $this, 'add_page_definition' ) );

		// Shortcode (primary name + legacy alias).
		add_shortcode( 'arshid6social_bookmarks', array( $this, 'shortcode' ) );
		add_shortcode( 'sn_bookmarks',      array( $this, 'shortcode' ) );

		// One-time migration: update existing Saved Posts page content + author via direct DB.
		add_action( 'admin_init', array( $this, 'migrate_saved_posts_page' ) );
	}

	/**
	 * One-time migration: update [sn_bookmarks] → [arshid6social_bookmarks] and fix missing
	 * post_author on the Saved Posts page using direct DB queries (no hooks fired).
	 * Runs on admin_init and self-disables via an option flag once done.
	 */
	public function migrate_saved_posts_page(): void {
		if ( get_option( 'arshid6social_bookmarks_page_migrated_v1' ) ) {
			return;
		}

		global $wpdb;

		$page_id = (int) get_option( 'arshid6social_page_saved_posts', 0 );
		if ( ! $page_id ) {
			update_option( 'arshid6social_bookmarks_page_migrated_v1', 1 );
			return;
		}

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_author, post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
			$page_id
		) );

		if ( ! $row ) {
			update_option( 'arshid6social_bookmarks_page_migrated_v1', 1 );
			return;
		}

		$data   = array();
		$format = array();

		if ( empty( $row->post_author ) || 0 === (int) $row->post_author ) {
			$admin_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT u.ID FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID
				 WHERE m.meta_key = '{$wpdb->prefix}capabilities'
				   AND m.meta_value LIKE '%administrator%'
				 ORDER BY u.ID ASC LIMIT 1"
			) ?: 1;
			$data['post_author'] = $admin_id;
			$format[]            = '%d';
		}

		if ( false !== strpos( $row->post_content, '[sn_bookmarks]' ) ) {
			$data['post_content'] = str_replace( '[sn_bookmarks]', '[arshid6social_bookmarks]', $row->post_content );
			$format[]             = '%s';
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $wpdb->posts, $data, array( 'ID' => $page_id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $page_id );
		}

		update_option( 'arshid6social_bookmarks_page_migrated_v1', 1 );
	}

	/** @param array<string, array> $pages */
	public function add_page_definition( array $pages ): array {
		// Auto-detect the page if the option is not yet set.
		if ( ! get_option( 'arshid6social_page_saved_posts', 0 ) ) {
			$existing = get_page_by_path( 'saved-posts' );
			if ( $existing && 'publish' === $existing->post_status ) {
				update_option( 'arshid6social_page_saved_posts', $existing->ID );
			}
		}

		$pages['saved_posts'] = array(
			'title'       => __( 'Saved Posts', '6arshid social community' ),
			'slug'        => 'saved-posts',
			'shortcode'   => '[arshid6social_bookmarks]',
			'option'      => 'arshid6social_page_saved_posts',
			'description' => __( 'Bookmarked posts and saved marketplace listings', '6arshid social community' ),
		);
		return $pages;
	}

	// ── Core CRUD ─────────────────────────────────────────────────────────────

	public function add( int $user_id, int $object_id, string $object_type = 'activity', ?int $collection_id = null ): bool {
		global $wpdb;

		$data = array(
			'user_id'     => $user_id,
			'object_id'   => $object_id,
			'object_type' => $object_type,
			'created_at'  => current_time( 'mysql' ),
		);
		$fmt = array( '%d', '%d', '%s', '%s' );

		if ( null !== $collection_id ) {
			if ( ! $this->user_owns_collection( $user_id, $collection_id ) ) {
				return false;
			}
			$data['collection_id'] = $collection_id;
			$fmt[]                 = '%d';
		}

		$wpdb->replace( $wpdb->prefix . 'sn_bookmarks', $data, $fmt ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->rows_affected;
	}

	public function remove( int $user_id, int $object_id, string $object_type = 'activity' ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_bookmarks',
			array( 'user_id' => $user_id, 'object_id' => $object_id, 'object_type' => $object_type ),
			array( '%d', '%d', '%s' )
		);
		return (bool) $deleted;
	}

	public function is_bookmarked( int $user_id, int $object_id, string $object_type = 'activity' ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_bookmarks WHERE user_id = %d AND object_id = %d AND object_type = %s",
			$user_id, $object_id, $object_type
		) );
	}

	/**
	 * Returns bookmarked activity posts for a user, paginated.
	 */
	public function get_for_user( int $user_id, array $args = array() ): array {
		global $wpdb;

		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = $args['search'] ?? '';
		$coll_id  = isset( $args['collection_id'] ) ? absint( $args['collection_id'] ) : null;

		$where  = 'b.user_id = %d AND b.object_type = %s';
		$values = array( $user_id, 'activity' );

		if ( null !== $coll_id ) {
			$where   .= ' AND b.collection_id = %d';
			$values[] = $coll_id;
		}

		if ( $search ) {
			$where   .= ' AND a.content LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_bookmarks b
			LEFT JOIN {$wpdb->prefix}sn_activity a ON a.id = b.object_id
			WHERE $where", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$values
		) );

		$values[] = $per_page;
		$values[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT b.*, a.content, a.date_recorded, a.user_id AS author_id
			FROM {$wpdb->prefix}sn_bookmarks b
			LEFT JOIN {$wpdb->prefix}sn_activity a ON a.id = b.object_id
			WHERE $where
			ORDER BY b.created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$values
		), ARRAY_A ) ?: array();

		return array(
			'items'       => $rows,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
		);
	}

	// ── Collections ───────────────────────────────────────────────────────────

	public function get_collections( int $user_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT c.*, COUNT(b.id) AS bookmark_count
			FROM {$wpdb->prefix}sn_bookmark_collections c
			LEFT JOIN {$wpdb->prefix}sn_bookmarks b ON b.collection_id = c.id
			WHERE c.user_id = %d
			GROUP BY c.id ORDER BY c.created_at DESC",
			$user_id
		), ARRAY_A ) ?: array();
	}

	public function add_collection( int $user_id, string $name ): int|false {
		if ( ! get_option( 'arshid6social_eng_bookmark_collections', true ) ) {
			return false;
		}

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_bookmark_collections',
			array( 'user_id' => $user_id, 'name' => sanitize_text_field( $name ), 'created_at' => current_time( 'mysql' ) ),
			array( '%d', '%s', '%s' )
		);
		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	public function delete_collection( int $user_id, int $collection_id ): bool {
		if ( ! $this->user_owns_collection( $user_id, $collection_id ) ) {
			return false;
		}

		global $wpdb;
		// Unset collection_id on bookmarks within it (don't delete the bookmarks).
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_bookmarks',
			array( 'collection_id' => null ),
			array( 'user_id' => $user_id, 'collection_id' => $collection_id ),
			array( '%s' ),
			array( '%d', '%d' )
		);

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_bookmark_collections',
			array( 'id' => $collection_id, 'user_id' => $user_id ),
			array( '%d', '%d' )
		);

		return (bool) $deleted;
	}

	private function user_owns_collection( int $user_id, int $collection_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_bookmark_collections WHERE id = %d AND user_id = %d",
			$collection_id, $user_id
		) );
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_toggle(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$object_id   = absint( $_POST['object_id'] ?? 0 );
		$object_type = sanitize_key( $_POST['object_type'] ?? 'activity' );
		$coll_id     = absint( $_POST['collection_id'] ?? 0 ) ?: null;
		// phpcs:enable

		if ( ! $object_id ) {
			wp_send_json_error( null, 400 );
		}

		$user_id = get_current_user_id();

		if ( $this->is_bookmarked( $user_id, $object_id, $object_type ) ) {
			$this->remove( $user_id, $object_id, $object_type );
			wp_send_json_success( array( 'bookmarked' => false ) );
		} else {
			$ok = $this->add( $user_id, $object_id, $object_type, $coll_id );
			if ( ! $ok ) {
				global $wpdb;
				wp_send_json_error( array( 'message' => 'Save failed. ' . ( $wpdb->last_error ?: 'DB error' ) ), 500 );
			}
			wp_send_json_success( array( 'bookmarked' => true ) );
		}
	}

	public function ajax_status(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$object_id   = absint( $_GET['object_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$object_type = sanitize_key( $_GET['object_type'] ?? 'activity' ); // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json_success( array( 'bookmarked' => $this->is_bookmarked( get_current_user_id(), $object_id, $object_type ) ) );
	}

	public function ajax_get_collections(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		wp_send_json_success( $this->get_collections( get_current_user_id() ) );
	}

	public function ajax_add_collection(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $name ) {
			wp_send_json_error( null, 400 );
		}
		$id = $this->add_collection( get_current_user_id(), $name );
		$id ? wp_send_json_success( array( 'id' => $id, 'name' => $name ) ) : wp_send_json_error( null, 500 );
	}

	public function ajax_del_collection(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$id = absint( $_POST['collection_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->delete_collection( get_current_user_id(), $id )
			? wp_send_json_success()
			: wp_send_json_error( null, 403 );
	}

	// ── Cleanup ───────────────────────────────────────────────────────────────

	public function on_activity_deleted( int $activity_id ): void {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_bookmarks',
			array( 'object_id' => $activity_id, 'object_type' => 'activity' ),
			array( '%d', '%s' )
		);
	}

	// ── Feed AJAX (for Saved Posts page infinite scroll) ─────────────────────

	public function ajax_feed(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		$user_id  = get_current_user_id();
		$page     = max( 1, absint( $_GET['page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page = min( 20, max( 1, absint( $_GET['per_page'] ?? 10 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$search   = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$coll_id  = absint( $_GET['collection_id'] ?? 0 ) ?: null; // phpcs:ignore WordPress.Security.NonceVerification

		$result   = $this->get_for_user( $user_id, array(
			'page'          => $page,
			'per_page'      => $per_page,
			'search'        => $search,
			'collection_id' => $coll_id,
		) );

		$activity_comp = ARSHID6SOCIAL()->component( 'activity' );
		$activities    = array();

		foreach ( $result['items'] as $item ) {
			if ( 'activity' !== $item['object_type'] ) {
				continue;
			}
			$row = $activity_comp ? $activity_comp->get_by_id( (int) $item['object_id'] ) : null;
			if ( $row ) {
				$activities[] = $activity_comp->format_activity( $row );
			}
		}

		// Also include saved marketplace listings (stored in user meta).
		$listings = array();
		if ( $page === 1 ) {
			$_raw      = get_user_meta( $user_id, 'arshid6social_mkt_saved_listings', true );
			$saved_ids = is_array( $_raw ) ? array_values( array_filter( array_map( 'intval', $_raw ) ) ) : array();

			if ( ! empty( $saved_ids ) ) {
				global $wpdb;

				$placeholders  = implode( ',', array_fill( 0, count( $saved_ids ), '%d' ) );
				$listing_rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						"SELECT * FROM {$wpdb->prefix}arshid6social_listings WHERE id IN ($placeholders)",
						...$saved_ids
					)
				) ?: array();

				$base_url = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) )
					?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );

				$currency_symbol   = (string) get_option( 'arshid6social_marketplace_currency_symbol',   '$' );
				$currency_position = (string) get_option( 'arshid6social_marketplace_currency_position', 'before' );
				$currency_decimals = (int)    get_option( 'arshid6social_marketplace_currency_decimals',  2 );
				$currency_thousands = (string) get_option( 'arshid6social_marketplace_currency_thousands', ',' );

				foreach ( $listing_rows as $listing ) {
					$lid   = (int) $listing->id;
					$media = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						"SELECT file_url, attachment_id FROM {$wpdb->prefix}arshid6social_listing_media WHERE listing_id = %d ORDER BY sort_order ASC LIMIT 1",
						$lid
					) );
					$thumb = '';
					if ( $media ) {
						$thumb = $media->attachment_id
							? wp_get_attachment_image_url( (int) $media->attachment_id, 'medium' )
							: $media->file_url;
					}

					$uid = ! empty( $listing->uid ) ? $listing->uid : $listing->id;

					if ( ! empty( $listing->is_free ) ) {
						$price_formatted = __( 'Free', '6arshid social community' );
					} else {
						$num = number_format( (float) $listing->price, $currency_decimals, '.', $currency_thousands );
						$price_formatted = 'before' === $currency_position
							? $currency_symbol . $num
							: $num . $currency_symbol;
					}

					$listings[] = array(
						'id'              => $lid,
						'title'           => (string) ( $listing->title ?? '' ),
						'price_formatted' => $price_formatted,
						'is_free'         => ! empty( $listing->is_free ),
						'thumb'           => $thumb ?: '',
						'url'             => add_query_arg( array( 'action' => 'view', 'id' => $uid ), $base_url ),
						'date_relative'   => ! empty( $listing->created_at )
							? human_time_diff( strtotime( $listing->created_at ), time() ) . ' ago'
							: '',
					);
				}
			}
		}

		wp_send_json_success( array(
			'activities'  => $activities,
			'listings'    => $listings,
			'total_pages' => $result['total_pages'],
			'page'        => $page,
		) );
	}

	// ── Shortcode ─────────────────────────────────────────────────────────────

	public function shortcode( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your bookmarks.', '6arshid social community' ) . '</p>';
		}

		$atts     = shortcode_atts( array( 'per_page' => 20 ), $atts );
		$user_id  = get_current_user_id();
		$page     = max( 1, absint( get_query_var( 'paged', 1 ) ) );
		$search   = sanitize_text_field( wp_unslash( $_GET['bs'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$coll_id  = absint( $_GET['collection'] ?? 0 ) ?: null; // phpcs:ignore WordPress.Security.NonceVerification

		$result  = $this->get_for_user( $user_id, array( 'per_page' => (int) $atts['per_page'], 'page' => $page, 'search' => $search, 'collection_id' => $coll_id ) );
		$colls   = get_option( 'arshid6social_eng_bookmark_collections', true ) ? $this->get_collections( $user_id ) : array();

		return \Arshid6Social\Template_Loader::instance()->get_template(
			'engagement/bookmarks.php',
			array( 'result' => $result, 'collections' => $colls, 'search' => $search, 'active_collection' => $coll_id ),
			true
		);
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Bookmarks_REST() )->register_routes();
	}
}
