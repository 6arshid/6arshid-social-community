<?php
namespace Arshid6Social\Components\Monetization;

/**
 * Encryption utilities for sensitive monetization options (Stripe secret + webhook keys).
 *
 * Uses sodium_crypto_secretbox when available, falls back to AES-256-CBC via OpenSSL.
 * The encryption key is derived from WordPress auth salts so it is site-specific and
 * never stored — it only exists in memory at decrypt time.
 *
 * @package Arshid6Social\Components\Monetization
 */

defined( 'ABSPATH' ) || exit;

class Monetization_Crypto {

	/** Prefix written before the base64 blob to identify the algorithm. */
	private const PREFIX_SODIUM  = 's:';
	private const PREFIX_OPENSSL = 'o:';

	/**
	 * Derives a 32-byte key from WordPress auth salts.
	 * The key is computed once per request and memoized.
	 */
	private static function derive_key(): string {
		static $key = null;
		if ( null !== $key ) {
			return $key;
		}
		$material = ( defined( 'AUTH_KEY' )       ? AUTH_KEY       : '' )
		          . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' )
		          . ( defined( 'LOGGED_IN_KEY' )   ? LOGGED_IN_KEY   : '' )
		          . ( defined( 'NONCE_KEY' )        ? NONCE_KEY       : '' );

		// Fallback if salts are all empty (local dev with no wp-config.php salts).
		if ( '' === $material ) {
			$material = 'sixarshidsc-fallback-key-' . wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$key = hash( 'sha256', $material, true ); // 32 raw bytes.
		return $key;
	}

	/**
	 * Encrypts a plaintext string.
	 *
	 * Returns '' for empty input. On success returns a prefixed base64 string.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string Encrypted, base64-encoded blob with algorithm prefix.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		try {
			$key = self::derive_key();

			if ( function_exists( 'sodium_crypto_secretbox' ) && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) && defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' ) ) {
				$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
				return self::PREFIX_SODIUM . base64_encode( $nonce . $ciphertext );
			}

			// Fallback: AES-256-CBC via OpenSSL.
			if ( function_exists( 'openssl_encrypt' ) ) {
				$iv         = random_bytes( 16 );
				$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
				if ( false !== $ciphertext ) {
					return self::PREFIX_OPENSSL . base64_encode( $iv . $ciphertext );
				}
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ARSHID6SOCIAL Monetization] Encryption failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}

		return '';
	}

	/**
	 * Decrypts a value produced by encrypt().
	 *
	 * Returns '' on any failure so callers can treat it as "not set".
	 *
	 * @param string $stored The prefixed base64 blob from the database.
	 * @return string Decrypted plaintext, or '' on failure.
	 */
	public static function decrypt( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}

		$key = self::derive_key();

		if ( 0 === strpos( $stored, self::PREFIX_SODIUM ) ) {
			if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
				return '';
			}
			$data = base64_decode( substr( $stored, strlen( self::PREFIX_SODIUM ) ), true );
			if ( false === $data ) {
				return '';
			}
			$nonce_len  = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( strlen( $data ) <= $nonce_len ) {
				return '';
			}
			$nonce      = substr( $data, 0, $nonce_len );
			$ciphertext = substr( $data, $nonce_len );
			$result     = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
			return ( false === $result ) ? '' : $result;
		}

		if ( 0 === strpos( $stored, self::PREFIX_OPENSSL ) ) {
			$data = base64_decode( substr( $stored, strlen( self::PREFIX_OPENSSL ) ), true );
			if ( false === $data || strlen( $data ) <= 16 ) {
				return '';
			}
			$iv         = substr( $data, 0, 16 );
			$ciphertext = substr( $data, 16 );
			$result     = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			return ( false === $result ) ? '' : $result;
		}

		return '';
	}

	/**
	 * Returns true if an option has a non-empty stored value (set or not).
	 *
	 * @param string $option_name wp_options key.
	 * @return bool
	 */
	public static function is_set( string $option_name ): bool {
		return '' !== (string) get_option( $option_name, '' );
	}

	/**
	 * Returns true if $value is already an encrypted blob produced by encrypt().
	 *
	 * Used by the sanitize_callback to detect double-invocations (WordPress
	 * occasionally calls sanitize callbacks twice — the second time with the
	 * already-encrypted return value of the first call).
	 *
	 * @param string $value
	 * @return bool
	 */
	public static function is_encrypted( string $value ): bool {
		return ( 0 === strpos( $value, self::PREFIX_SODIUM ) )
		    || ( 0 === strpos( $value, self::PREFIX_OPENSSL ) );
	}

	// -------------------------------------------------------------------------
	// Convenience accessors — test/live mode aware.
	// -------------------------------------------------------------------------

	/**
	 * Returns the active Stripe publishable key (plain text, not encrypted).
	 */
	public static function get_stripe_pub_key(): string {
		$test = (bool) get_option( 'sixarshidsc_stripe_test_mode', true );
		return (string) get_option( $test ? 'sixarshidsc_stripe_pub_key_test' : 'sixarshidsc_stripe_pub_key_live', '' );
	}

	/**
	 * Returns the active Stripe secret key, decrypted.
	 * Handles legacy double-encrypted values by decrypting twice.
	 */
	public static function get_stripe_secret(): string {
		$test = (bool) get_option( 'sixarshidsc_stripe_test_mode', true );
		$opt  = $test ? 'sixarshidsc_stripe_secret_test' : 'sixarshidsc_stripe_secret_live';
		return self::safe_decrypt( (string) get_option( $opt, '' ) );
	}

	/**
	 * Returns the active Stripe webhook signing secret, decrypted.
	 * Handles legacy double-encrypted values by decrypting twice.
	 */
	public static function get_stripe_webhook_secret(): string {
		$test = (bool) get_option( 'sixarshidsc_stripe_test_mode', true );
		$opt  = $test ? 'sixarshidsc_stripe_webhook_secret_test' : 'sixarshidsc_stripe_webhook_secret_live';
		return self::safe_decrypt( (string) get_option( $opt, '' ) );
	}

	/**
	 * Decrypts a stored secret, handling double-encrypted values gracefully.
	 *
	 * If the first decrypt returns another encrypted blob (double-encrypted),
	 * it decrypts again. This heals values that were encrypted twice due to
	 * WordPress calling sanitize_callbacks multiple times.
	 *
	 * @param string $stored Raw value from wp_options.
	 * @return string Plaintext secret, or '' if not set / can't decrypt.
	 */
	private static function safe_decrypt( string $stored ): string {
		$result = self::decrypt( $stored );
		// If result is still an encrypted blob, decrypt one more time.
		if ( '' !== $result && self::is_encrypted( $result ) ) {
			$result = self::decrypt( $result );
		}
		return $result;
	}
}
