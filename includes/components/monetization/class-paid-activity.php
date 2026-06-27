<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Pay-per-view enforcement for activities with privacy = 'paid'.
 *
 * Responsibilities:
 *  – Hooks arshid6social_format_activity to inject lock/price metadata.
 *  – Provides sixarshidsc_user_can_view_paid_activity() global helper.
 *  – REST: POST /sixarshidsc/v1/ppv/{id}/checkout  → creates Stripe PaymentIntent.
 *  – REST: GET  /sixarshidsc/v1/ppv/{id}/status    → returns current entitlement state.
 *
 * Entitlements are ONLY granted from the verified webhook handler
 * (class-monetization-webhook.php). The checkout REST endpoint returns a
 * client_secret for Stripe Elements; nothing is unlocked on the server until
 * the webhook fires payment_intent.succeeded.
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Paid_Activity {

	public function __construct() {
		add_filter( 'arshid6social_format_activity', array( $this, 'filter_format_activity' ), 5, 2 );
	}

	// -------------------------------------------------------------------------
	// Entitlement check
	// -------------------------------------------------------------------------

	/**
	 * Returns true if $user_id may view a paid activity.
	 *
	 * Owners and admins always pass. Everyone else needs a row in
	 * sixarshidsc_entitlements with object_type = 'activity'.
	 *
	 * @param int $user_id     WordPress user ID (0 = guest).
	 * @param int $activity_id Activity table ID.
	 * @return bool
	 */
	public static function can_view( int $user_id, int $activity_id ): bool {
		if ( ! $user_id ) {
			return false;
		}

		global $wpdb;

		// Owner always sees their own post.
		$owner = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $owner === $user_id ) {
			return true;
		}

		// Site admins / activity managers bypass paywall.
		if ( user_can( $user_id, 'arshid6social_manage_activity' ) ) {
			return true;
		}

		// Entitlement lookup.
		$has = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sixarshidsc_entitlements
			  WHERE user_id    = %d
			    AND object_type = 'activity'
			    AND object_id   = %d
			    AND ( expires_at IS NULL OR expires_at > %s )
			  LIMIT 1",
			$user_id,
			$activity_id,
			current_time( 'mysql' )
		) );

		return (bool) $has;
	}

	// -------------------------------------------------------------------------
	// format_activity filter
	// -------------------------------------------------------------------------

	/**
	 * Adds paid-content fields to the formatted activity array.
	 *
	 * For non-entitled viewers the full content is replaced with a short
	 * plain-text preview (120 chars). The JS renders a lock overlay when
	 * it sees `locked === true`.
	 *
	 * @param  array  $formatted Formatted activity array.
	 * @param  object $activity  Raw DB row.
	 * @return array
	 */
	public function filter_format_activity( array $formatted, object $activity ): array {
		if ( 'paid' !== ( $activity->privacy ?? '' ) ) {
			return $formatted;
		}

		global $wpdb;

		$price_cents = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT meta_value FROM {$wpdb->prefix}sn_activity_meta
			  WHERE activity_id = %d AND meta_key = '_sixarshidsc_ppv_price' LIMIT 1",
			(int) $activity->id
		) );

		$currency      = strtoupper( (string) get_option( 'sixarshidsc_currency', 'USD' ) );
		$current_uid   = get_current_user_id();
		$is_owner      = (int) $activity->user_id === $current_uid;
		$entitled      = $is_owner || ( $current_uid && self::can_view( $current_uid, (int) $activity->id ) );
		$locked        = ! $entitled;

		$formatted['isPaid']            = true;
		$formatted['ppvPrice']          = $price_cents;
		$formatted['ppvCurrency']       = $currency;
		$formatted['ppvPriceFormatted'] = self::format_price( $price_cents, $currency );
		$formatted['isEntitled']        = ! $locked;
		$formatted['locked']            = $locked;

		if ( $locked ) {
			$plain = wp_strip_all_tags( $formatted['content'] );
			$formatted['lockedPreview'] = mb_strlen( $plain ) > 120
				? mb_substr( $plain, 0, 120 ) . '…'
				: $plain;
			$formatted['content']       = '';
			$formatted['media']         = array(); // hide media for non-entitled
		}

		return $formatted;
	}

	// -------------------------------------------------------------------------
	// Price formatting helper
	// -------------------------------------------------------------------------

	public static function format_price( int $cents, string $currency ): string {
		$amount  = $cents / 100;
		$symbols = array(
			'USD' => '$', 'EUR' => '€', 'GBP' => '£',
			'CAD' => 'CA$', 'AUD' => 'A$', 'JPY' => '¥',
			'CHF' => 'CHF ', 'SEK' => 'kr ', 'NOK' => 'kr ',
			'TRY' => '₺', 'AED' => 'AED ', 'SAR' => 'SAR ',
		);
		$sym = $symbols[ $currency ] ?? ( $currency . ' ' );
		return $sym . number_format( $amount, 2 );
	}

	// -------------------------------------------------------------------------
	// REST: POST /sixarshidsc/v1/ppv/{id}/checkout
	// -------------------------------------------------------------------------

	public function rest_checkout( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id     = get_current_user_id();
		$activity_id = (int) $request['id'];

		global $wpdb;

		$activity = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, user_id, privacy FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) );

		if ( ! $activity ) {
			return new \WP_Error( 'not_found', __( 'Activity not found.', '6arshid social community' ), array( 'status' => 404 ) );
		}
		if ( 'paid' !== $activity->privacy ) {
			return new \WP_Error( 'not_paid', __( 'This post is not a paid post.', '6arshid social community' ), array( 'status' => 400 ) );
		}
		if ( (int) $activity->user_id === $user_id ) {
			return new \WP_Error( 'owner', __( 'You cannot purchase your own post.', '6arshid social community' ), array( 'status' => 400 ) );
		}
		if ( self::can_view( $user_id, $activity_id ) ) {
			return rest_ensure_response( array( 'already_entitled' => true ) );
		}

		$price_cents = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT meta_value FROM {$wpdb->prefix}sn_activity_meta
			  WHERE activity_id = %d AND meta_key = '_sixarshidsc_ppv_price' LIMIT 1",
			$activity_id
		) );

		if ( $price_cents <= 0 ) {
			return new \WP_Error( 'no_price', __( 'This post has no price configured.', '6arshid social community' ), array( 'status' => 400 ) );
		}

		$currency = strtoupper( (string) get_option( 'sixarshidsc_currency', 'USD' ) );

		$pi = Stripe_API::create_payment_intent( $price_cents, $currency, array(
			'sixarshidsc_type' => 'ppv',
			'activity_id'  => (string) $activity_id,
			'buyer_id'     => (string) $user_id,
			'creator_id'   => (string) (int) $activity->user_id,
		) );

		if ( ! empty( $pi['error'] ) ) {
			return new \WP_Error(
				'stripe_error',
				$pi['error']['message'] ?? __( 'Payment provider error.', '6arshid social community' ),
				array( 'status' => 502 )
			);
		}

		$activity_url = add_query_arg( 'sixarshidsc_paid_activity', $activity_id, home_url( '/' ) );

		return rest_ensure_response( array(
			'client_secret'   => $pi['client_secret'],
			'payment_intent'  => $pi['id'],
			'amount'          => $price_cents,
			'currency'        => $currency,
			'price_formatted' => self::format_price( $price_cents, $currency ),
			'pub_key'         => Monetization_Crypto::get_stripe_pub_key(),
			'return_url'      => $activity_url,
			'activity_url'    => $activity_url,
			'activity_id'     => $activity_id,
		) );
	}

	// -------------------------------------------------------------------------
	// REST: GET /sixarshidsc/v1/ppv/{id}/status
	// -------------------------------------------------------------------------

	public function rest_status( \WP_REST_Request $request ): \WP_REST_Response {
		$activity_id = (int) $request['id'];
		$user_id     = get_current_user_id();
		$entitled    = $user_id && self::can_view( $user_id, $activity_id );
		return rest_ensure_response( array( 'entitled' => $entitled ) );
	}

	// -------------------------------------------------------------------------
	// REST: POST /sixarshidsc/v1/ppv/{id}/verify
	// -------------------------------------------------------------------------
	// Called immediately after stripe.confirmPayment() resolves on the client.
	// Retrieves the PaymentIntent directly from Stripe, validates it, then calls
	// grant_ppv_entitlement() so the post unlocks even when webhooks can't reach
	// the server (local / staging / test environments).

	public function rest_verify_payment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$user_id     = get_current_user_id();
		$activity_id = (int) $request['id'];
		$pi_id       = sanitize_text_field( (string) $request->get_param( 'payment_intent' ) );

		if ( ! $pi_id || ! str_starts_with( $pi_id, 'pi_' ) ) {
			return new \WP_Error( 'bad_pi', __( 'Invalid payment_intent ID.', '6arshid social community' ), array( 'status' => 400 ) );
		}

		// Already entitled — nothing to do.
		if ( self::can_view( $user_id, $activity_id ) ) {
			return rest_ensure_response( array( 'entitled' => true ) );
		}

		// Retrieve & validate from Stripe.
		$pi = Stripe_API::retrieve_payment_intent( $pi_id );
		if ( ! empty( $pi['error'] ) ) {
			return new \WP_Error( 'stripe_error', $pi['error']['message'] ?? 'Stripe error.', array( 'status' => 502 ) );
		}

		if ( ( $pi['status'] ?? '' ) !== 'succeeded' ) {
			return rest_ensure_response( array( 'entitled' => false, 'pi_status' => $pi['status'] ?? '' ) );
		}

		$meta = $pi['metadata'] ?? array();
		if (
			( $meta['sixarshidsc_type'] ?? '' ) !== 'ppv' ||
			(int) ( $meta['activity_id'] ?? 0 ) !== $activity_id ||
			(int) ( $meta['buyer_id']    ?? 0 ) !== $user_id
		) {
			return new \WP_Error( 'metadata_mismatch', __( 'Payment metadata does not match.', '6arshid social community' ), array( 'status' => 403 ) );
		}

		self::grant_ppv_entitlement( $pi );
		return rest_ensure_response( array( 'entitled' => true ) );
	}

	// -------------------------------------------------------------------------
	// Shared grant logic — called by verify endpoint and webhook handler.
	// -------------------------------------------------------------------------

	public static function grant_ppv_entitlement( array $pi ): void {
		$meta        = $pi['metadata'] ?? array();
		$buyer_id    = (int) ( $meta['buyer_id']    ?? 0 );
		$activity_id = (int) ( $meta['activity_id'] ?? 0 );
		$creator_id  = (int) ( $meta['creator_id']  ?? 0 );

		if ( ! $buyer_id || ! $activity_id ) {
			return;
		}

		$amount_cents = (int) ( $pi['amount'] ?? 0 );
		$fee_cents    = (int) ( $pi['application_fee_amount'] ?? 0 );
		$currency     = strtoupper( $pi['currency'] ?? 'USD' );
		$gateway_ref  = $pi['id'] ?? '';
		$now          = current_time( 'mysql' );

		global $wpdb;

		// Transaction — only insert if no row with this gateway_ref exists yet
		// (verify endpoint and webhook may both call this; gateway_ref is not UNIQUE).
		$exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sixarshidsc_transactions WHERE gateway_ref = %s LIMIT 1",
			$gateway_ref
		) );
		if ( ! $exists ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sixarshidsc_transactions',
				array(
					'type'         => 'ppv',
					'payer_id'     => $buyer_id,
					'creator_id'   => $creator_id,
					'amount'       => $amount_cents / 100,
					'platform_fee' => $fee_cents / 100,
					'currency'     => $currency,
					'gateway'      => 'stripe_connect',
					'gateway_ref'  => $gateway_ref,
					'status'       => 'completed',
					'created_at'   => $now,
				),
				array( '%s', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		// Purchase row — idempotent.
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"INSERT INTO {$wpdb->prefix}sixarshidsc_purchases
				(buyer_id, activity_id, creator_id, gateway_payment_id, amount, fee, currency, status, created_at)
			 VALUES (%d, %d, %d, %s, %f, %f, %s, 'completed', %s)
			 ON DUPLICATE KEY UPDATE status = 'completed', gateway_payment_id = VALUES(gateway_payment_id)",
			$buyer_id, $activity_id, $creator_id,
			$gateway_ref,
			$amount_cents / 100, $fee_cents / 100, $currency,
			$now
		) );

		// Permanent entitlement — idempotent.
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"INSERT INTO {$wpdb->prefix}sixarshidsc_entitlements
				(user_id, object_type, object_id, source, expires_at, created_at)
			 VALUES (%d, 'activity', %d, 'ppv', NULL, %s)
			 ON DUPLICATE KEY UPDATE source = source",
			$buyer_id, $activity_id, $now
		) );

		\Arshid6Social\Cache::delete( "activity_{$activity_id}" );
	}

	// -------------------------------------------------------------------------
	// REST route registration (called from Monetization::register_rest_routes)
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route(
			'sixarshidsc/v1',
			'/ppv/(?P<id>[\d]+)/checkout',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_checkout' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ),
				),
			)
		);

		register_rest_route(
			'sixarshidsc/v1',
			'/ppv/(?P<id>[\d]+)/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ),
				),
			)
		);

		register_rest_route(
			'sixarshidsc/v1',
			'/ppv/(?P<id>[\d]+)/verify',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_verify_payment' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'id'             => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ),
					'payment_intent' => array( 'type' => 'string',  'required' => true ),
				),
			)
		);
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Global helper — usable outside the namespace.
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'sixarshidsc_user_can_view_paid_activity' ) ) {
	/**
	 * Returns true if $user_id is entitled to view a paid activity.
	 *
	 * @param int $user_id     0 = guest.
	 * @param int $activity_id
	 */
	function sixarshidsc_user_can_view_paid_activity( int $user_id, int $activity_id ): bool {
		return \Arshid6Social\Components\Monetization\Paid_Activity::can_view( $user_id, $activity_id );
	}
}
