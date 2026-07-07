<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Stripe webhook handler for the Monetization component.
 *
 * Security model:
 *  – Signature verified via HMAC-SHA256 before any processing.
 *  – Every event is written to sixarshidsc_webhook_events FIRST (idempotency guard).
 *  – Entitlements are ONLY granted here — never from client-side redirects.
 *
 * Endpoint: POST /wp-json/sixarshidsc/v1/webhook
 * Register this URL in Stripe Dashboard → Webhooks.
 * Events required: payment_intent.succeeded
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Monetization_Webhook {

	// -------------------------------------------------------------------------
	// REST route
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route(
			'sixarshidsc/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// -------------------------------------------------------------------------
	// Incoming webhook
	// -------------------------------------------------------------------------

	public function handle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$raw_body   = $request->get_body();
		$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';

		$webhook_secret = Monetization_Crypto::get_stripe_webhook_secret();
		if ( '' === $webhook_secret ) {
			return new \WP_Error( 'no_secret', 'Webhook secret not configured.', array( 'status' => 500 ) );
		}

		if ( ! $this->verify_signature( $raw_body, $sig_header, $webhook_secret ) ) {
			return new \WP_Error( 'invalid_signature', 'Webhook signature verification failed.', array( 'status' => 400 ) );
		}

		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new \WP_Error( 'invalid_payload', 'Malformed event payload.', array( 'status' => 400 ) );
		}

		global $wpdb;

		// Idempotency — record event before processing to prevent double-grants.
		$already = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sixarshidsc_webhook_events WHERE gateway = 'stripe_connect' AND event_id = %s",
			$event['id']
		) );

		if ( $already ) {
			return rest_ensure_response( array( 'ok' => true, 'duplicate' => true ) );
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sixarshidsc_webhook_events',
			array(
				'gateway'      => 'stripe_connect',
				'event_id'     => $event['id'],
				'type'         => $event['type'],
				'payload_hash' => hash( 'sha256', $raw_body ),
				'processed_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		switch ( $event['type'] ) {
			case 'payment_intent.succeeded':
				$this->handle_ppv_payment( $event['data']['object'] ?? array() );
				break;
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	// -------------------------------------------------------------------------
	// Stripe signature verification
	// -------------------------------------------------------------------------

	/**
	 * Verifies the Stripe-Signature header using HMAC-SHA256.
	 *
	 * Format: "t=<timestamp>,v1=<hex-digest>"
	 * Signed string: "<timestamp>.<raw_payload>"
	 * Tolerance: ±300 seconds (5 minutes).
	 */
	private function verify_signature( string $payload, string $sig_header, string $secret ): bool {
		if ( '' === $sig_header || '' === $payload ) {
			return false;
		}

		$parts = array();
		foreach ( explode( ',', $sig_header ) as $part ) {
			$kv = explode( '=', $part, 2 );
			if ( 2 === count( $kv ) ) {
				$parts[ $kv[0] ] = $kv[1];
			}
		}

		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		// Reject stale events.
		if ( abs( time() - (int) $parts['t'] ) > 300 ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $parts['t'] . '.' . $payload, $secret );
		return hash_equals( $expected, $parts['v1'] );
	}

	// -------------------------------------------------------------------------
	// Event handler: payment_intent.succeeded (pay-per-view)
	// -------------------------------------------------------------------------

	private function handle_ppv_payment( array $pi ): void {
		$meta = $pi['metadata'] ?? array();

		if ( empty( $meta['sixarshidsc_type'] ) || 'ppv' !== $meta['sixarshidsc_type'] ) {
			return;
		}

		Paid_Activity::grant_ppv_entitlement( $pi );
	}
}
