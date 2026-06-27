<?php
namespace Arshid6Social\Components\Verification;

/**
 * Verification badge system.
 *
 * @package Arshid6Social\Components\Verification
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Verification
 *
 * Features:
 * - Admin grant/revoke any user a badge with type
 * - User-submitted request flow (form + document upload)
 * - Admin queue: approve (assign type), reject (reason), request more info
 * - Badge rendering filter on profiles, activity, comments, stories
 * - Re-verification expiry cron
 * - [sn_verification_request] shortcode
 */
class Verification {

	public function __construct() {
		if ( ! get_option( 'arshid6social_verification_enabled', false ) ) {
			return;
		}
		$this->hooks();
	}

	private function hooks(): void {
		// Shortcode.
		add_shortcode( 'sn_verification_request', array( $this, 'shortcode_request_form' ) );

		// Inject badge into display names throughout the site.
		add_filter( 'arshid6social_format_member',    array( $this, 'inject_badge_into_member' ), 10, 2 );
		add_filter( 'arshid6social_activity_content', array( $this, 'maybe_linkify_badge' ), 20, 1 );

		// Cron: expire badges.
		add_action( 'arshid6social_expire_verifications', array( $this, 'expire_badges' ) );

		// AJAX (user side).
		add_action( 'wp_ajax_arshid6social_submit_verification_request',   array( $this, 'ajax_submit_request' ) );
		add_action( 'wp_ajax_arshid6social_resubmit_verification_request', array( $this, 'ajax_resubmit_request' ) );
		add_action( 'wp_ajax_arshid6social_get_verification_status',       array( $this, 'ajax_get_status' ) );

		// AJAX (admin side).
		add_action( 'wp_ajax_arshid6social_admin_verify_user',    array( $this, 'ajax_admin_grant' ) );
		add_action( 'wp_ajax_arshid6social_admin_unverify_user',  array( $this, 'ajax_admin_revoke' ) );
		add_action( 'wp_ajax_arshid6social_admin_approve_request', array( $this, 'ajax_admin_approve' ) );
		add_action( 'wp_ajax_arshid6social_admin_reject_request',  array( $this, 'ajax_admin_reject' ) );
		add_action( 'wp_ajax_arshid6social_admin_more_info',       array( $this, 'ajax_admin_more_info' ) );

		// Serve protected document.
		add_action( 'wp_ajax_arshid6social_serve_verification_doc', array( $this, 'serve_doc' ) );
	}

	// ── Public queries ────────────────────────────────────────────────────────

	/**
	 * Returns the verification record for a user or null.
	 */
	public function get( int $user_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT v.* FROM {$wpdb->prefix}sn_verifications v
			 WHERE v.user_id = %d
			   AND (v.expires_at IS NULL OR v.expires_at > NOW())",
			$user_id
		) );
	}

	/**
	 * Returns true if the user currently holds a valid verified badge.
	 */
	public function is_verified( int $user_id ): bool {
		return null !== $this->get( $user_id );
	}

	/**
	 * Returns the badge HTML span for a user (empty string if not verified).
	 */
	public function get_badge_html( int $user_id ): string {
		$record = $this->get( $user_id );
		if ( ! $record ) {
			return '';
		}

		$types = $this->get_types();
		$type  = $record->type ?? 'general';
		$cfg   = $types[ $type ] ?? $types['general'] ?? array( 'badge' => '✓', 'label' => 'Verified', 'color' => '#2563eb' );

		$label     = esc_attr( $cfg['label'] ?? 'Verified' );
		$color     = esc_attr( $cfg['color'] ?? '#2563eb' );
		$img_id    = (int) get_option( 'arshid6social_verification_badge_image', 0 );
		$img_url   = $img_id ? wp_get_attachment_image_url( $img_id, array( 32, 32 ) ) : '';

		if ( $img_url ) {
			return sprintf(
				'<img src="%s" class="arshid6social-verified-badge arshid6social-verified-badge--img" title="%s" aria-label="%s" alt="%s" width="18" height="18" style="vertical-align:middle;margin-left:4px;border-radius:50%%;object-fit:contain;" />',
				esc_url( $img_url ),
				$label,
				$label,
				$label
			);
		}

		return sprintf(
			'<span class="arshid6social-verified-badge" style="background:%s" title="%s" aria-label="%s">%s</span>',
			$color,
			$label,
			$label,
			esc_html( $cfg['badge'] ?? '✓' )
		);
	}

	/**
	 * Returns available verification types as key → config array.
	 *
	 * @return array<string, array{key: string, label: string, badge: string, color: string}>
	 */
	public function get_types(): array {
		$raw = (array) get_option( 'arshid6social_verification_types', array() );
		$out = array();
		foreach ( $raw as $item ) {
			if ( ! empty( $item['key'] ) ) {
				$out[ sanitize_key( $item['key'] ) ] = $item;
			}
		}
		return $out ?: array(
			'general' => array( 'key' => 'general', 'label' => 'Verified', 'badge' => '✓', 'color' => '#2563eb' ),
		);
	}

	// ── Admin grant / revoke ──────────────────────────────────────────────────

	/**
	 * Grants a verified badge to a user (admin action).
	 */
	public function grant( int $user_id, string $type = 'general', int $granted_by = 0 ): bool {
		global $wpdb;

		$types = $this->get_types();
		if ( ! isset( $types[ $type ] ) ) {
			$type = 'general';
		}

		$badge     = $types[ $type ]['badge'] ?? '✓';
		$expiry_mo = (int) get_option( 'arshid6social_verification_expiry_months', 0 );
		$expires   = $expiry_mo > 0
			? gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_mo} months" ) )
			: null;

		// Upsert.
		$existing = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_verifications WHERE user_id = %d",
			$user_id
		) );

		if ( $existing ) {
			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_verifications',
				array(
					'type'       => $type,
					'badge'      => $badge,
					'granted_by' => $granted_by ?: get_current_user_id(),
					'granted_at' => current_time( 'mysql' ),
					'expires_at' => $expires,
				),
				array( 'user_id' => $user_id ),
				array( '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_verifications',
				array(
					'user_id'    => $user_id,
					'type'       => $type,
					'badge'      => $badge,
					'granted_by' => $granted_by ?: get_current_user_id(),
					'granted_at' => current_time( 'mysql' ),
					'expires_at' => $expires,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		if ( $result ) {
			update_user_meta( $user_id, 'arshid6social_verified', '1' );
			\Arshid6Social\Components\Moderation\Moderation::log_action(
				get_current_user_id(), 'verification_granted', 'user', $user_id, array( 'type' => $type )
			);
			do_action( 'arshid6social_verification_granted', $user_id, $type );
		}

		return (bool) $result;
	}

	/**
	 * Revokes a verified badge.
	 */
	public function revoke( int $user_id ): bool {
		global $wpdb;
		$deleted = (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_verifications',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);
		if ( $deleted ) {
			delete_user_meta( $user_id, 'arshid6social_verified' );
			\Arshid6Social\Components\Moderation\Moderation::log_action(
				get_current_user_id(), 'verification_revoked', 'user', $user_id, array()
			);
			do_action( 'arshid6social_verification_revoked', $user_id );
		}
		return $deleted;
	}

	// ── User request flow ─────────────────────────────────────────────────────

	/**
	 * Returns pending request for a user or null.
	 */
	public function get_pending_request( int $user_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_verification_requests
			 WHERE user_id = %d AND status IN ('pending','more_info')
			 ORDER BY created_at DESC LIMIT 1",
			$user_id
		) );
	}

	/**
	 * Submits a new verification request.
	 *
	 * @param int    $user_id
	 * @param string $type        Requested verification type key.
	 * @param array  $fields      Associative array of submitted field values.
	 * @param array  $doc_paths   Absolute paths to uploaded documents (already saved).
	 * @return int|false Inserted request ID or false.
	 */
	public function submit_request( int $user_id, string $type, array $fields, array $doc_paths = array() ): int|false {
		global $wpdb;

		// Only one pending request at a time.
		if ( $this->get_pending_request( $user_id ) ) {
			return false;
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_verification_requests',
			array(
				'user_id'       => $user_id,
				'type'          => sanitize_key( $type ),
				'fields_json'   => wp_json_encode( $fields ),
				'document_paths' => wp_json_encode( $doc_paths ),
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Admin approves a request — grants badge and notifies user.
	 */
	public function approve_request( int $request_id, string $type ): bool {
		global $wpdb;

		$request = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_verification_requests WHERE id = %d",
			$request_id
		) );
		if ( ! $request ) {
			return false;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_verification_requests',
			array(
				'status'      => 'approved',
				'reviewer_id' => get_current_user_id(),
				'decided_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $request_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		$this->grant( (int) $request->user_id, $type );
		$this->notify_user( (int) $request->user_id, 'verification_approved', $request_id );
		$this->maybe_purge_docs( $request );

		return true;
	}

	/**
	 * Admin rejects a request with an optional reason.
	 */
	public function reject_request( int $request_id, string $reason = '' ): bool {
		global $wpdb;

		$request = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_verification_requests WHERE id = %d",
			$request_id
		) );
		if ( ! $request ) {
			return false;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_verification_requests',
			array(
				'status'      => 'rejected',
				'reviewer_id' => get_current_user_id(),
				'reason'      => sanitize_textarea_field( $reason ),
				'decided_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $request_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		$this->notify_user( (int) $request->user_id, 'verification_rejected', $request_id );
		$this->maybe_purge_docs( $request );

		return true;
	}

	/**
	 * Admin requests more info — sets status, notifies user.
	 */
	public function request_more_info( int $request_id, string $message ): bool {
		global $wpdb;

		$request = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_verification_requests WHERE id = %d",
			$request_id
		) );
		if ( ! $request ) {
			return false;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_verification_requests',
			array(
				'status'      => 'more_info',
				'reviewer_id' => get_current_user_id(),
				'reason'      => sanitize_textarea_field( $message ),
			),
			array( 'id' => $request_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		// Notify user via email.
		$user = get_userdata( (int) $request->user_id );
		if ( $user ) {
			wp_mail(
				$user->user_email,
				__( 'More information needed for your verification request', '6arshid-social-community' ),
				sanitize_textarea_field( $message )
			);
		}

		return true;
	}

	// ── Badge filters ─────────────────────────────────────────────────────────

	public function inject_badge_into_member( array $member, \WP_User $user ): array {
		$member['verified']   = $this->is_verified( $user->ID );
		$member['badge_html'] = $this->get_badge_html( $user->ID );
		return $member;
	}

	public function maybe_linkify_badge( string $content ): string {
		return $content;
	}

	// ── Cron: expire badges ───────────────────────────────────────────────────

	public function expire_badges(): void {
		global $wpdb;
		$expired = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_verifications
			 WHERE expires_at IS NOT NULL AND expires_at <= NOW()"
		);
		foreach ( $expired as $uid ) {
			$this->revoke( (int) $uid );
			// Notify user their badge expired — they can re-apply.
			$this->notify_user( (int) $uid, 'verification_expired', 0 );
		}
	}

	// ── Shortcode ─────────────────────────────────────────────────────────────

	public function shortcode_request_form( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in to request verification.', '6arshid-social-community' ) . '</p>';
		}
		$loader = \Arshid6Social\Template_Loader::instance();
		return $loader->get_template(
			'verification/request.php',
			array(
				'verification' => $this,
				'user_id'      => get_current_user_id(),
				'types'        => $this->get_types(),
				'pending'      => $this->get_pending_request( get_current_user_id() ),
				'is_verified'  => $this->is_verified( get_current_user_id() ),
			),
			true
		);
	}

	// ── AJAX ─────────────────────────────────────────────────────────────────

	private function nonce_check( bool $admin = false ): void {
		$ok = check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) && is_user_logged_in();
		if ( $admin ) {
			$ok = $ok && current_user_can( 'arshid6social_manage_members' );
		}
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community' ) ), 403 );
		}
	}

	public function ajax_submit_request(): void {
		$this->nonce_check();

		$user_id = get_current_user_id();

		if ( ! arshid6social_check_rate_limit( 'arshid6social_rl_verify', $user_id, (int) get_option( 'arshid6social_verification_rate_limit', 3 ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many verification requests. Please try later.', '6arshid-social-community' ) ), 429 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$type    = sanitize_key( wp_unslash( $_POST['type'] ?? 'general' ) );
		$name    = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		$cat     = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$links   = sanitize_textarea_field( wp_unslash( $_POST['links'] ?? '' ) );
		// phpcs:enable

		$fields = array(
			'full_name' => $name,
			'category'  => $cat,
			'links'     => $links,
		);

		$doc_paths = array();
		$require   = get_option( 'arshid6social_verification_require_doc', false );

		if ( ! empty( $_FILES['document']['tmp_name'] ) ) {
			$result = \Arshid6Social\Media_Handler::handle( $_FILES['document'], 'verification_doc', $user_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			$doc_paths[] = $result['path'];

			\Arshid6Social\Media_Handler::register_to_media_library(
				$result['path'],
				$result['url'],
				$result['mime'],
				sanitize_file_name( (string) ( $_FILES['document']['name'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$user_id
			);
		} elseif ( $require ) {
			wp_send_json_error( array( 'message' => __( 'A document upload is required for verification.', '6arshid-social-community' ) ) );
		}

		$id = $this->submit_request( $user_id, $type, $fields, $doc_paths );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'You already have a pending verification request.', '6arshid-social-community' ) ) );
		}

		$this->notify_admin_new_request( $user_id );

		wp_send_json_success( array( 'message' => __( 'Your verification request has been submitted. We will review it shortly.', '6arshid-social-community' ) ) );
	}

	public function ajax_get_status(): void {
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		$user_id = get_current_user_id();
		$pending = $this->get_pending_request( $user_id );
		wp_send_json_success( array(
			'verified' => $this->is_verified( $user_id ),
			'badge'    => $this->get_badge_html( $user_id ),
			'pending'  => $pending ? array(
				'id'     => (int) $pending->id,
				'status' => $pending->status,
				'reason' => $pending->reason ?? '',
			) : null,
		) );
	}

	public function ajax_admin_grant(): void {
		$this->nonce_check( true );
		$user_id = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$type    = sanitize_key( wp_unslash( $_POST['type'] ?? 'general' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok = $this->grant( $user_id, $type );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_admin_revoke(): void {
		$this->nonce_check( true );
		$user_id = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok = $this->revoke( $user_id );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_admin_approve(): void {
		$this->nonce_check( true );
		$req_id = absint( $_POST['request_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$type   = sanitize_key( wp_unslash( $_POST['type'] ?? 'general' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok = $this->approve_request( $req_id, $type );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_admin_reject(): void {
		$this->nonce_check( true );
		$req_id = absint( $_POST['request_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok = $this->reject_request( $req_id, $reason );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_admin_more_info(): void {
		$this->nonce_check( true );
		$req_id  = absint( $_POST['request_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$ok = $this->request_more_info( $req_id, $message );
		$ok ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_resubmit_request(): void {
		$this->nonce_check();

		$user_id = get_current_user_id();
		$pending = $this->get_pending_request( $user_id );

		if ( ! $pending || 'more_info' !== $pending->status ) {
			wp_send_json_error( array( 'message' => __( 'No request awaiting additional information.', '6arshid-social-community' ) ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$name  = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		$cat   = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$links = sanitize_textarea_field( wp_unslash( $_POST['links'] ?? '' ) );
		// phpcs:enable

		$fields = array(
			'full_name' => $name,
			'category'  => $cat,
			'links'     => $links,
		);

		$doc_paths = json_decode( $pending->document_paths ?? '[]', true );

		if ( ! empty( $_FILES['document']['tmp_name'] ) ) {
			$result = \Arshid6Social\Media_Handler::handle( $_FILES['document'], 'verification_doc', $user_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			$doc_paths[] = $result['path'];
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_verification_requests',
			array(
				'fields_json'    => wp_json_encode( $fields ),
				'document_paths' => wp_json_encode( $doc_paths ),
				'status'         => 'pending',
				'reason'         => null,
				'created_at'     => current_time( 'mysql' ),
			),
			array( 'id' => (int) $pending->id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$this->notify_admin_new_request( $user_id );

		wp_send_json_success( array( 'message' => __( 'Your additional information has been submitted. We will review it shortly.', '6arshid-social-community' ) ) );
	}

	public function serve_doc(): void {
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			status_header( 403 );
			exit;
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		$req_id = absint( $_GET['request_id'] ?? 0 );
		$idx    = absint( $_GET['idx'] ?? 0 );

		global $wpdb;
		$request = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT document_paths FROM {$wpdb->prefix}sn_verification_requests WHERE id = %d",
			$req_id
		) );

		if ( ! $request ) {
			status_header( 404 );
			exit;
		}

		$paths = json_decode( $request->document_paths ?? '[]', true );
		$path  = $paths[ $idx ] ?? '';
		$mime  = mime_content_type( $path ) ?: 'application/octet-stream';

		\Arshid6Social\Media_Handler::serve_protected_file( $path, $mime );
	}

	// ── Notifications ─────────────────────────────────────────────────────────

	private function notify_user( int $user_id, string $action, int $secondary_id ): void {
		$notifications = ARSHID6SOCIAL()->component( 'notifications' );
		if ( ! $notifications ) {
			return;
		}
		$notifications->add( array(
			'user_id'           => $user_id,
			'item_id'           => get_current_user_id() ?: $user_id,
			'secondary_item_id' => $secondary_id,
			'component_name'    => 'verification',
			'component_action'  => $action,
		) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function notify_admin_new_request( int $user_id ): void {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}
		$user    = get_userdata( $user_id );
		$name    = $user ? $user->display_name : "#{$user_id}";
		$email   = $user ? $user->user_email : '';
		$subject = sprintf(
			/* translators: 1: site name, 2: user display name */
			__( '[%1$s] New verification request from %2$s', '6arshid-social-community' ),
			get_bloginfo( 'name' ),
			$name
		);
		$body = sprintf(
			/* translators: 1: name, 2: email, 3: admin URL */
			__( "User %1\$s (%2\$s) has submitted a verification request.\n\nReview it in the admin panel:\n%3\$s", '6arshid-social-community' ),
			$name,
			$email,
			admin_url( 'admin.php?page=arshid6social-verification' )
		);
		wp_mail( $admin_email, $subject, $body );
	}

	private function maybe_purge_docs( object $request ): void {
		if ( ! get_option( 'arshid6social_verification_doc_purge', true ) ) {
			return;
		}
		$paths = json_decode( $request->document_paths ?? '[]', true );
		foreach ( (array) $paths as $path ) {
			\Arshid6Social\Media_Handler::delete_file( $path );
		}
	}

	// ── REST ─────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Verification_REST( $this ) )->register_routes();
	}
}
