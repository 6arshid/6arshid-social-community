<?php
namespace Arshid6Social\Components\Marketplace;

/**
 * Marketplace Listings — CRUD, photo upload, and AJAX handlers.
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Marketplace_Listings
 *
 * Handles creating, updating, and deleting listings, uploading listing photos,
 * and all front-end AJAX actions for the listing wizard.
 */
class Marketplace_Listings {

	public function __construct() {
		// Browse listings grid — both logged-in and guests.
		add_action( 'wp_ajax_arshid6social_mkt_get_listings',        array( $this, 'ajax_get_listings' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_mkt_get_listings', array( $this, 'ajax_get_listings' ) );

		// Photo upload/remove (logged-in only)
		add_action( 'wp_ajax_arshid6social_mkt_upload_photo',   array( $this, 'ajax_upload_photo' ) );
		add_action( 'wp_ajax_arshid6social_mkt_remove_photo',   array( $this, 'ajax_remove_photo' ) );

		// Save / delete listing
		add_action( 'wp_ajax_arshid6social_mkt_save_listing',   array( $this, 'ajax_save_listing' ) );
		add_action( 'wp_ajax_arshid6social_mkt_delete_listing', array( $this, 'ajax_delete_listing' ) );
		add_action( 'wp_ajax_arshid6social_mkt_change_status',  array( $this, 'ajax_change_status' ) );

		// Save (favourite) toggle & report
		add_action( 'wp_ajax_arshid6social_mkt_toggle_save',    array( $this, 'ajax_toggle_save' ) );
		add_action( 'wp_ajax_arshid6social_mkt_report_listing', array( $this, 'ajax_report_listing' ) );

		// Cron: expire listings
		add_action( 'arshid6social_marketplace_expire_listings', array( $this, 'expire_listings' ) );
	}

	// ── Browse listings ───────────────────────────────────────────────────────

	/**
	 * AJAX: Return a page of active listings for the marketplace grid.
	 * Accessible by both logged-in users and guests (nopriv).
	 */
	public function ajax_get_listings(): void {
		global $wpdb;

		$per_page = 12;
		$page     = max( 1, absint( $_GET['page'] ?? $_POST['page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$offset   = ( $page - 1 ) * $per_page;

		// Filters (no nonce needed — read-only public query).
		$q       = sanitize_text_field( wp_unslash( $_GET['q']    ?? $_POST['q']    ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$cat_id  = absint( $_GET['cat']  ?? $_POST['cat']  ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$sort    = sanitize_key( wp_unslash( $_GET['sort'] ?? $_POST['sort'] ?? 'newest' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		// ── Build WHERE ──────────────────────────────────────────────────────
		$user_id  = get_current_user_id();
		$is_admin = current_user_can( 'manage_options' );

		// Status filter — admins see pending too; sellers see their own drafts.
		$conditions = array();
		$params     = array();

		if ( $is_admin ) {
			$conditions[] = "l.status IN ('active','pending')";
		} elseif ( $user_id ) {
			$conditions[] = "(l.status = 'active' OR (l.seller_id = %d AND l.status IN ('pending','draft')))";
			$params[]     = $user_id;
		} else {
			$conditions[] = "l.status = 'active'";
		}

		if ( $cat_id ) {
			$conditions[] = 'l.category_id = %d';
			$params[]     = $cat_id;
		}

		if ( $q ) {
			$like         = '%' . $wpdb->esc_like( $q ) . '%';
			$conditions[] = '(l.title LIKE %s OR l.description LIKE %s)';
			$params[]     = $like;
			$params[]     = $like;
		}

		$where = 'WHERE ' . implode( ' AND ', $conditions );

		// ── ORDER BY ─────────────────────────────────────────────────────────
		$order_map = array(
			'newest'     => 'l.created_at DESC',
			'price_asc'  => 'l.is_free ASC, l.price ASC',
			'price_desc' => 'l.is_free ASC, l.price DESC',
			'most_viewed'=> 'l.views DESC',
		);
		$order = $order_map[ $sort ] ?? 'l.created_at DESC';

		// ── Count (for has_more) ──────────────────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT COUNT(*) FROM {$wpdb->prefix}arshid6social_listings l {$where}",
			...$params
		) );

		// ── Fetch rows ────────────────────────────────────────────────────────
		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT l.id, l.uid, l.title, l.price, l.is_free, l.is_negotiable,
			 l.item_condition, l.location_city, l.status,
			 l.created_at, l.seller_id, l.category_id
			 FROM {$wpdb->prefix}arshid6social_listings l
			 {$where}
			 ORDER BY {$order}
			 LIMIT %d OFFSET %d",
			...$params
		) ) ?: array();

		// ── Fetch primary photo per listing in one query ───────────────────────
		$listing_ids = array_map( static fn( $r ) => (int) $r->id, $rows );
		$thumbs      = array();

		if ( $listing_ids ) {
			$id_placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );
			$media_rows      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					"SELECT listing_id, file_url, attachment_id
					 FROM {$wpdb->prefix}arshid6social_listing_media
					 WHERE listing_id IN ($id_placeholders) AND is_primary = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$listing_ids
				)
			) ?: array();

			foreach ( $media_rows as $m ) {
				// Prefer WP attachment URL (handles CDN/regeneration); fall back to stored file_url.
				$att_url = $m->attachment_id ? wp_get_attachment_image_url( (int) $m->attachment_id, 'medium' ) : false;
				$thumbs[ (int) $m->listing_id ] = $att_url ?: $m->file_url;
			}
		}

		// ── Build response payload ────────────────────────────────────────────
		$base_url = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) )
			?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );

		$listings = array();
		foreach ( $rows as $row ) {
			$listings[] = array(
				'id'              => (int) $row->id,
				'title'           => $row->title,
				'price_formatted' => Marketplace::format_price( $row->price, (bool) $row->is_free ),
				'is_free'         => (bool) $row->is_free,
				'is_negotiable'   => (bool) $row->is_negotiable,
				'status'          => $row->status,
				'location_city'   => $row->location_city,
				'date_relative'   => self::time_ago( $row->created_at ),
				'thumb'           => $thumbs[ (int) $row->id ] ?? '',
				'url'             => add_query_arg( array( 'action' => 'view', 'id' => ( $row->uid ?: $row->id ) ), $base_url ),
			);
		}

		wp_send_json_success( array(
			'listings' => $listings,
			'total'    => $total,
			'page'     => $page,
			'has_more' => ( $offset + count( $rows ) ) < $total,
		) );
	}

	/**
	 * Human-friendly relative time (e.g. "3 hours ago").
	 *
	 * @param string $datetime MySQL datetime string (UTC).
	 * @return string
	 */
	private static function time_ago( string $datetime ): string {
		$diff = time() - strtotime( $datetime );
		if ( $diff < 60 )       return __( 'just now', '6arshid-social-community' );
		/* translators: %d: number of minutes */
		if ( $diff < 3600 )     return sprintf( _n( '%d minute ago', '%d minutes ago', (int) ( $diff / 60 ),   '6arshid-social-community' ), (int) ( $diff / 60 ) );
		/* translators: %d: number of hours */
		if ( $diff < 86400 )    return sprintf( _n( '%d hour ago',   '%d hours ago',   (int) ( $diff / 3600 ), '6arshid-social-community' ), (int) ( $diff / 3600 ) );
		/* translators: %d: number of days */
		if ( $diff < 604800 )   return sprintf( _n( '%d day ago',    '%d days ago',    (int) ( $diff / 86400 ),'6arshid-social-community' ), (int) ( $diff / 86400 ) );
		return date_i18n( get_option( 'date_format' ), strtotime( $datetime ) );
	}

	// ── Photo upload ─────────────────────────────────────────────────────────

	/**
	 * AJAX: Upload a single photo, attach it to a draft transient keyed by token.
	 */
	public function ajax_upload_photo(): void {
		check_ajax_referer( 'arshid6social_marketplace', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', '6arshid-social-community' ) ), 401 );
		}

		$user_id = get_current_user_id();
		$max     = (int) get_option( 'arshid6social_marketplace_max_photos', 10 );
		$max_mb  = (int) get_option( 'arshid6social_marketplace_max_photo_size_mb', 5 );
		$token   = sanitize_key( wp_unslash( $_POST['token'] ?? '' ) );

		if ( ! $token ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form session.', '6arshid-social-community' ) ), 400 );
		}

		$draft = get_transient( "arshid6social_mkt_draft_{$user_id}_{$token}" ) ?: array();

		if ( count( $draft ) >= $max ) {
			/* translators: %d: maximum number of photos */
			wp_send_json_error( array( 'message' => sprintf( __( 'Maximum %d photos allowed.', '6arshid-social-community' ), $max ) ), 400 );
		}

		if ( empty( $_FILES['photo'] ) || UPLOAD_ERR_OK !== $_FILES['photo']['error'] ) {
			wp_send_json_error( array( 'message' => __( 'No valid file received.', '6arshid-social-community' ) ), 400 );
		}

		$file = $_FILES['photo']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Size
		if ( $file['size'] > $max_mb * MB_IN_BYTES ) {
			/* translators: %d: maximum file size in MB */
			wp_send_json_error( array( 'message' => sprintf( __( 'File exceeds the %d MB limit.', '6arshid-social-community' ), $max_mb ) ), 400 );
		}

		// MIME type (server-side, not just extension)
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );
		$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
		if ( ! in_array( $mime, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Only JPEG, PNG, WebP, and GIF images are accepted.', '6arshid-social-community' ) ), 400 );
		}

		// Upload to WP media library
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'photo', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ), 500 );
		}

		// Tag the attachment so we can clean up orphans later
		update_post_meta( $attachment_id, '_arshid6social_mkt_uploader',    $user_id );
		update_post_meta( $attachment_id, '_arshid6social_mkt_draft_token', $token );

		$thumb = wp_get_attachment_image_url( $attachment_id, 'medium' )
			?: wp_get_attachment_url( $attachment_id );
		$full  = wp_get_attachment_url( $attachment_id );

		$draft[] = array(
			'attachment_id' => $attachment_id,
			'url'           => $full,
			'thumb'         => $thumb,
		);
		set_transient( "arshid6social_mkt_draft_{$user_id}_{$token}", $draft, DAY_IN_SECONDS );

		wp_send_json_success( array(
			'id'    => $attachment_id,
			'url'   => $full,
			'thumb' => $thumb,
		) );
	}

	/**
	 * AJAX: Remove a draft photo by attachment_id + token.
	 */
	public function ajax_remove_photo(): void {
		check_ajax_referer( 'arshid6social_marketplace', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(), 401 );
		}

		$user_id       = get_current_user_id();
		$attachment_id = absint( $_POST['id'] ?? 0 );
		$token         = sanitize_key( wp_unslash( $_POST['token'] ?? '' ) );

		if ( ! $attachment_id || ! $token ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', '6arshid-social-community' ) ), 400 );
		}

		// Ownership check
		$uploader = (int) get_post_meta( $attachment_id, '_arshid6social_mkt_uploader', true );
		if ( $uploader !== $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community' ) ), 403 );
		}

		// Remove from transient
		$draft = get_transient( "arshid6social_mkt_draft_{$user_id}_{$token}" ) ?: array();
		$draft = array_values( array_filter( $draft, static function ( $p ) use ( $attachment_id ) {
			return (int) $p['attachment_id'] !== $attachment_id;
		} ) );
		set_transient( "arshid6social_mkt_draft_{$user_id}_{$token}", $draft, DAY_IN_SECONDS );

		wp_delete_attachment( $attachment_id, true );

		wp_send_json_success();
	}

	// ── Save listing ─────────────────────────────────────────────────────────

	/**
	 * AJAX: Create a new listing (draft or published) with all wizard data.
	 */
	public function ajax_save_listing(): void {
		check_ajax_referer( 'arshid6social_marketplace', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to post a listing.', '6arshid-social-community' ) ), 401 );
		}

		$user_id = get_current_user_id();

		// ── Access checks ────────────────────────────────────────────────────

		// Verified-only mode
		if ( get_option( 'arshid6social_marketplace_require_verified', false ) ) {
			$verification = ARSHID6SOCIAL()->component( 'verification' );
			if ( $verification && ! $verification->is_verified( $user_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Only verified users can post listings.', '6arshid-social-community' ) ), 403 );
			}
		}

		$submit_action = sanitize_key( wp_unslash( $_POST['submit_action'] ?? 'draft' ) );
		$is_publish    = ( 'publish' === $submit_action );

		// Daily rate limit (only applies to new published listings)
		if ( $is_publish ) {
			$daily_max = (int) get_option( 'arshid6social_marketplace_daily_new_listings', 10 );
			if ( $daily_max > 0 && ! arshid6social_check_rate_limit( 'arshid6social_mkt_new', $user_id, $daily_max ) ) {
				wp_send_json_error( array( 'message' => __( 'You have reached your daily listing limit. Try again tomorrow.', '6arshid-social-community' ) ), 429 );
			}

			// Max active listings cap
			$max_active = (int) get_option( 'arshid6social_marketplace_max_active_listings', 50 );
			if ( $max_active > 0 ) {
				global $wpdb;
				$active_count = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					"SELECT COUNT(*) FROM {$wpdb->prefix}arshid6social_listings WHERE seller_id = %d AND status = 'active'",
					$user_id
				) );
				if ( $active_count >= $max_active ) {
					wp_send_json_error( array(
						/* translators: %d: maximum active listings */
						'message' => sprintf( __( 'You can have a maximum of %d active listings at once.', '6arshid-social-community' ), $max_active ),
					), 400 );
				}
			}
		}

		// ── Sanitize inputs ──────────────────────────────────────────────────
		$token       = sanitize_key( wp_unslash( $_POST['token'] ?? '' ) );
		$title       = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$description = wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) );
		$category_id = absint( $_POST['category_id'] ?? 0 );
		$is_free     = ! empty( $_POST['is_free'] ) ? 1 : 0;
		$price       = $is_free ? 0.0 : max( 0.0, (float) ( $_POST['price'] ?? 0 ) );
		$is_neg      = ( ! $is_free && ! empty( $_POST['is_negotiable'] ) ) ? 1 : 0;
		$condition   = sanitize_key( wp_unslash( $_POST['item_condition'] ?? 'used' ) );
		$city        = sanitize_text_field( wp_unslash( $_POST['location_city'] ?? '' ) );
		$country     = sanitize_key( wp_unslash( $_POST['location_country'] ?? '' ) );
		$lat_raw     = wp_unslash( $_POST['lat'] ?? '' );
		$lng_raw     = wp_unslash( $_POST['lng'] ?? '' );
		$lat         = is_numeric( $lat_raw ) ? round( (float) $lat_raw, 7 ) : null;
		$lng         = is_numeric( $lng_raw ) ? round( (float) $lng_raw, 7 ) : null;

		// ── Validate ─────────────────────────────────────────────────────────
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Title is required.', '6arshid-social-community' ) ), 400 );
		}
		if ( mb_strlen( $title ) > 200 ) {
			wp_send_json_error( array( 'message' => __( 'Title must be 200 characters or less.', '6arshid-social-community' ) ), 400 );
		}

		$allowed_conditions = array( 'new', 'like_new', 'good', 'fair', 'poor', 'used' );
		if ( ! in_array( $condition, $allowed_conditions, true ) ) {
			$condition = 'used';
		}

		// Banned words (title + stripped description)
		$banned_raw = (string) get_option( 'arshid6social_marketplace_banned_words', '' );
		if ( $banned_raw ) {
			$banned_words = array_filter( array_map( 'trim', explode( ',', mb_strtolower( $banned_raw ) ) ) );
			$check_text   = mb_strtolower( $title . ' ' . wp_strip_all_tags( $description ) );
			foreach ( $banned_words as $word ) {
				if ( $word && str_contains( $check_text, $word ) ) {
					wp_send_json_error( array( 'message' => __( 'Your listing contains prohibited content and cannot be posted.', '6arshid-social-community' ) ), 400 );
				}
			}
		}

		// ── Status ───────────────────────────────────────────────────────────
		$moderation = (string) get_option( 'arshid6social_marketplace_moderation', 'auto' );
		$status     = ! $is_publish ? 'draft' : ( 'manual' === $moderation ? 'pending' : 'active' );

		// ── Expiry ───────────────────────────────────────────────────────────
		$expiry_days = (int) get_option( 'arshid6social_marketplace_expiry_days', 30 );
		$expires_at  = ( $is_publish && $expiry_days > 0 )
			? gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_days} days" ) )
			: null;

		// ── Insert ───────────────────────────────────────────────────────────
		global $wpdb;
		$now = current_time( 'mysql', true );
		$uid = Marketplace_DB::generate_uid();

		// Build SQL to handle nullable lat/lng safely.
		$insert_sql = $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"INSERT INTO {$wpdb->prefix}arshid6social_listings
				(uid, seller_id, category_id, title, description, price, is_free, is_negotiable,
				 item_condition, location_city, location_country, lat, lng,
				 status, created_at, updated_at, expires_at)
			VALUES (%s, %d, %d, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
			$uid,
			$user_id,
			$category_id,
			$title,
			$description,
			number_format( $price, 4, '.', '' ),
			$is_free,
			$is_neg,
			$condition,
			$city,
			$country,
			null !== $lat ? (string) $lat : null,
			null !== $lng ? (string) $lng : null,
			$status,
			$now,
			$now,
			$expires_at
		);

		$ok = $wpdb->query( $insert_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save your listing. Please try again.', '6arshid-social-community' ) ), 500 );
		}

		$listing_id = (int) $wpdb->insert_id;

		// ── Attach photos from draft transient ───────────────────────────────
		if ( $token ) {
			$draft_photos = get_transient( "arshid6social_mkt_draft_{$user_id}_{$token}" ) ?: array();
			foreach ( $draft_photos as $i => $photo ) {
				$att_id    = (int) $photo['attachment_id'];
				$file_url  = (string) ( $photo['url'] ?? '' );
				$file_path = get_attached_file( $att_id ) ?: '';

				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					"{$wpdb->prefix}arshid6social_listing_media",
					array(
						'listing_id'    => $listing_id,
						'attachment_id' => $att_id,
						'file_url'      => $file_url,
						'file_path'     => $file_path,
						'is_primary'    => ( 0 === $i ) ? 1 : 0,
						'sort_order'    => $i,
					),
					array( '%d', '%d', '%s', '%s', '%d', '%d' )
				);

				update_post_meta( $att_id, '_arshid6social_mkt_listing_id', $listing_id );
				delete_post_meta( $att_id, '_arshid6social_mkt_draft_token' );
			}
			delete_transient( "arshid6social_mkt_draft_{$user_id}_{$token}" );
		}

		// ── Build redirect URL ────────────────────────────────────────────────
		$base_url = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) )
			?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );

		if ( 'pending' === $status ) {
			wp_send_json_success( array(
				'url'     => $base_url,
				'uid'     => $uid,
				'message' => __( 'Your listing is under review and will be published shortly.', '6arshid-social-community' ),
			) );
		}

		$redirect_url = 'draft' === $status
			? add_query_arg( array( 'action' => 'edit', 'id' => $uid ), $base_url )
			: add_query_arg( array( 'action' => 'view', 'id' => $uid ), $base_url );

		wp_send_json_success( array(
			'url'     => $redirect_url,
			'uid'     => $uid,
			'message' => 'draft' === $status
				? __( 'Draft saved.', '6arshid-social-community' )
				: __( 'Your listing is now live!', '6arshid-social-community' ),
		) );
	}

	/**
	 * AJAX: Delete a listing (seller or admin only).
	 */
	public function ajax_delete_listing(): void {
		check_ajax_referer( 'arshid6social_marketplace', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(), 401 );
		}

		$user_id    = get_current_user_id();
		$listing_id = absint( $_POST['listing_id'] ?? 0 );

		if ( ! $listing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid listing.', '6arshid-social-community' ) ), 400 );
		}

		global $wpdb;
		$listing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT seller_id FROM {$wpdb->prefix}arshid6social_listings WHERE id = %d",
			$listing_id
		) );

		if ( ! $listing ) {
			wp_send_json_error( array( 'message' => __( 'Listing not found.', '6arshid-social-community' ) ), 404 );
		}
		if ( (int) $listing->seller_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community' ) ), 403 );
		}

		// Delete media rows + attachments
		$photos = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT attachment_id FROM {$wpdb->prefix}arshid6social_listing_media WHERE listing_id = %d",
			$listing_id
		) );
		foreach ( $photos as $photo ) {
			wp_delete_attachment( (int) $photo->attachment_id, true );
		}
		$wpdb->delete( "{$wpdb->prefix}arshid6social_listing_media", array( 'listing_id' => $listing_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Delete the listing row
		$wpdb->delete( "{$wpdb->prefix}arshid6social_listings", array( 'id' => $listing_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_send_json_success( array( 'message' => __( 'Listing deleted.', '6arshid-social-community' ) ) );
	}

	/**
	 * AJAX: Change listing status (mark as sold, re-activate, etc.)
	 */
	public function ajax_change_status(): void {
		check_ajax_referer( 'arshid6social_marketplace', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(), 401 );
		}

		$user_id    = get_current_user_id();
		$listing_id = absint( $_POST['listing_id'] ?? 0 );
		$new_status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );

		$allowed_statuses = array( 'active', 'sold', 'archived', 'draft' );
		if ( ! $listing_id || ! in_array( $new_status, $allowed_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', '6arshid-social-community' ) ), 400 );
		}

		global $wpdb;
		$listing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT seller_id, status FROM {$wpdb->prefix}arshid6social_listings WHERE id = %d",
			$listing_id
		) );

		if ( ! $listing ) {
			wp_send_json_error( array( 'message' => __( 'Listing not found.', '6arshid-social-community' ) ), 404 );
		}
		if ( (int) $listing->seller_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community' ) ), 403 );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"{$wpdb->prefix}arshid6social_listings",
			array( 'status' => $new_status, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $listing_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'status' => $new_status ) );
	}

	// ── Save (favourite) ─────────────────────────────────────────────────────

	/**
	 * AJAX: Toggle save/unsave a listing for the current user.
	 */
	public function ajax_toggle_save(): void {
		check_ajax_referer( 'arshid6social_marketplace', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid-social-community' ) ), 401 );
		}

		$user_id    = get_current_user_id();
		$listing_id = absint( $_POST['listing_id'] ?? 0 );

		if ( ! $listing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid listing.', '6arshid-social-community' ) ), 400 );
		}

		$saved = get_user_meta( $user_id, 'arshid6social_mkt_saved_listings', true );
		$saved = array_values( array_filter( array_map( 'intval', is_array( $saved ) ? $saved : array() ) ) );

		if ( in_array( $listing_id, $saved, true ) ) {
			$saved    = array_values( array_diff( $saved, array( $listing_id ) ) );
			$is_saved = false;
		} else {
			$saved[]  = $listing_id;
			$is_saved = true;
		}

		update_user_meta( $user_id, 'arshid6social_mkt_saved_listings', $saved );

		wp_send_json_success( array( 'saved' => $is_saved ) );
	}

	// ── Report ────────────────────────────────────────────────────────────────

	/**
	 * AJAX: Report a listing.
	 */
	public function ajax_report_listing(): void {
		check_ajax_referer( 'arshid6social_marketplace', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid-social-community' ) ), 401 );
		}

		$user_id    = get_current_user_id();
		$listing_id = absint( $_POST['listing_id'] ?? 0 );
		$reason     = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

		if ( ! $listing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid listing.', '6arshid-social-community' ) ), 400 );
		}

		// Prevent duplicate reports from the same user.
		$reported = (array) get_user_meta( $user_id, 'arshid6social_mkt_reported_listings', true );
		if ( in_array( $listing_id, array_map( 'intval', $reported ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'You have already reported this listing.', '6arshid-social-community' ) ), 409 );
		}

		// Store report as post meta on the listing for admin review.
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'arshid6social_listing_reports',
			array(
				'listing_id' => $listing_id,
				'user_id'    => $user_id,
				'reason'     => $reason,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		// If insert failed because the table doesn't exist yet, fall back to options.
		if ( $wpdb->last_error ) {
			update_option(
				"arshid6social_mkt_report_{$listing_id}_{$user_id}",
				array( 'reason' => $reason, 'time' => time() ),
				false
			);
		}

		// Mark as reported for this user so they can't spam.
		$reported[] = $listing_id;
		update_user_meta( $user_id, 'arshid6social_mkt_reported_listings', $reported );

		wp_send_json_success( array( 'message' => __( 'Thank you. Your report has been submitted.', '6arshid-social-community' ) ) );
	}

	// ── Cron ─────────────────────────────────────────────────────────────────

	/**
	 * Archive listings whose expires_at has passed.
	 */
	public function expire_listings(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"UPDATE {$wpdb->prefix}arshid6social_listings
			 SET status = 'archived', updated_at = UTC_TIMESTAMP()
			 WHERE status = 'active'
			   AND expires_at IS NOT NULL
			   AND expires_at < UTC_TIMESTAMP()"
		);
	}

	// ── Static helpers ────────────────────────────────────────────────────────

	/**
	 * Returns hierarchical category tree as a flat array with depth info.
	 *
	 * @param int $parent_id Start from this parent (0 = top-level).
	 * @param int $depth     Current recursion depth.
	 * @return array
	 */
	public static function get_category_tree( int $parent_id = 0, int $depth = 0 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_categories WHERE parent_id = %d ORDER BY sort_order ASC, id ASC",
			$parent_id
		) );

		$tree = array();
		foreach ( $rows as $cat ) {
			$cat->depth    = $depth;
			$cat->children = self::get_category_tree( (int) $cat->id, $depth + 1 );
			$tree[]        = $cat;
		}
		return $tree;
	}

	/**
	 * Flattens the category tree for use in <select> options.
	 *
	 * @return array  Each element: { id, name, icon, depth }
	 */
	public static function get_category_select_options(): array {
		$tree = self::get_category_tree();
		return self::flatten_tree( $tree );
	}

	private static function flatten_tree( array $tree ): array {
		$flat = array();
		foreach ( $tree as $cat ) {
			$flat[] = array(
				'id'    => (int) $cat->id,
				'name'  => $cat->name,
				'icon'  => $cat->icon,
				'depth' => $cat->depth,
			);
			if ( ! empty( $cat->children ) ) {
				foreach ( self::flatten_tree( $cat->children ) as $child ) {
					$flat[] = $child;
				}
			}
		}
		return $flat;
	}

	/**
	 * Returns one listing row, or null if not found / access denied.
	 *
	 * @param int  $id      Listing ID.
	 * @param bool $public  When true, only returns active listings.
	 * @return object|null
	 */
	public static function get_listing( int $id, bool $public = true ): ?object {
		global $wpdb;
		$where = $public ? "AND status = 'active'" : '';
		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_listings WHERE id = %d {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$id
		) ) ?: null;
	}

	/**
	 * Returns media rows for a listing, ordered by sort_order.
	 *
	 * @param int $listing_id Listing ID.
	 * @return array
	 */
	public static function get_photos( int $listing_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_listing_media WHERE listing_id = %d ORDER BY sort_order ASC",
			$listing_id
		) ) ?: array();
	}
}
