<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Thin HTTP client for the Stripe REST API.
 *
 * Uses wp_remote_request() — no Stripe PHP SDK dependency.
 * Secret key is always retrieved from Monetization_Crypto (never stored plain).
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Stripe_API {

	const API_BASE    = 'https://api.stripe.com/v1/';
	const API_VERSION = '2024-06-20';

	// -------------------------------------------------------------------------
	// Core HTTP layer
	// -------------------------------------------------------------------------

	/**
	 * Flattens a nested array to bracket-notation keys suitable for
	 * application/x-www-form-urlencoded Stripe requests.
	 *
	 * e.g. ['metadata' => ['a' => 'b']] → ['metadata[a]' => 'b']
	 */
	private static function flatten( array $params, string $prefix = '' ): array {
		$out = array();
		foreach ( $params as $k => $v ) {
			$key = $prefix ? "{$prefix}[{$k}]" : (string) $k;
			if ( is_array( $v ) ) {
				$out = array_merge( $out, self::flatten( $v, $key ) );
			} else {
				$out[ $key ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Makes an authenticated request to the Stripe API.
	 *
	 * @param  string $method   GET | POST
	 * @param  string $endpoint Path after /v1/ (e.g. 'payment_intents').
	 * @param  array  $params   Body (POST) or query (GET) parameters.
	 * @return array  Parsed JSON response; contains 'error' key on failure.
	 */
	private static function request( string $method, string $endpoint, array $params = array() ): array {
		$secret = Monetization_Crypto::get_stripe_secret();
		if ( '' === $secret ) {
			return array( 'error' => array( 'type' => 'configuration', 'message' => 'Stripe secret key is not configured.' ) );
		}

		$url  = self::API_BASE . ltrim( $endpoint, '/' );
		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Authorization'  => 'Bearer ' . $secret,
				'Stripe-Version' => self::API_VERSION,
			),
			'timeout' => 15,
		);

		if ( ! empty( $params ) ) {
			if ( 'GET' === $args['method'] ) {
				$url = add_query_arg( $params, $url );
			} else {
				$args['body']                       = self::flatten( $params );
				$args['headers']['Content-Type']    = 'application/x-www-form-urlencoded';
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'error' => array( 'type' => 'network', 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array( 'error' => array( 'type' => 'parse', 'message' => 'Invalid response from Stripe.' ) );
		}

		return $body;
	}

	// -------------------------------------------------------------------------
	// PaymentIntents
	// -------------------------------------------------------------------------

	/**
	 * Creates a PaymentIntent for a pay-per-view purchase.
	 *
	 * @param  int    $amount_cents Amount in the smallest currency unit (e.g. cents).
	 * @param  string $currency     3-letter ISO currency code (e.g. 'USD').
	 * @param  array  $metadata     Key-value metadata attached to the intent.
	 * @return array  Stripe PaymentIntent object, or ['error' => ...] on failure.
	 */
	public static function create_payment_intent( int $amount_cents, string $currency, array $metadata = array() ): array {
		return self::request( 'POST', 'payment_intents', array(
			'amount'                     => $amount_cents,
			'currency'                   => strtolower( $currency ),
			'automatic_payment_methods'  => array( 'enabled' => 'true' ),
			'metadata'                   => $metadata,
		) );
	}

	/**
	 * Retrieves a PaymentIntent by ID.
	 *
	 * @param  string $pi_id Stripe PaymentIntent ID (pi_…).
	 * @return array
	 */
	public static function retrieve_payment_intent( string $pi_id ): array {
		return self::request( 'GET', 'payment_intents/' . $pi_id );
	}
}
