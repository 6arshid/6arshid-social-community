<?php
namespace Arshid6Social;

/**
 * Authenticated encryption for strictly private media files.
 *
 * Architecture:
 *   - Per-site master encryption key stored encrypted in wp_options
 *   - Dual key wrapping: salt-derived KEK (primary) + recovery secret KEK (fallback)
 *   - Recovery secret: per-site, salt-independent, stored once, never overwritten
 *   - Legacy A6S1 key preserved durably in dual-wrapped form for salt rotation safety
 *   - Two crypto backends: libsodium (primary) and OpenSSL AES-256-GCM (fallback)
 *   - Versioned envelope format with key versioning for rotation support
 *   - Legacy A6S1 format remains decryptable via preserved key
 *   - Blog-ID-aware static caches for multisite switch_to_blog() safety
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

class Private_Encryption {

	// ── Envelope magic markers ──────────────────────────────────────────────
	private const MAGIC_V1   = 'A6S1'; // Legacy: salt-derived key, libsodium only
	private const MAGIC_V2   = 'A6S2'; // Current: master key, multi-backend

	// ── Backend identifiers ─────────────────────────────────────────────────
	const BACKEND_SODIUM  = 0x01;
	const BACKEND_OPENSSL = 0x02;

	// ── Key management option names ─────────────────────────────────────────
	private const KEY_OPTION          = 'arshid6social_enc_master_key';
	private const KEYRING_OPTION      = 'arshid6social_enc_keyring';
	private const RECOVERY_OPTION     = 'arshid6social_enc_recovery_secret';
	private const LEGACY_KEY_OPTION   = 'arshid6social_enc_legacy_a6s1_key';

	// ── Version constants ───────────────────────────────────────────────────
	private const CURRENT_VERSION         = 2;
	private const SODIUM_NONCE_LEN        = 24; // SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
	private const SODIUM_MAC_LEN          = 16; // SODIUM_CRYPTO_SECRETBOX_MACBYTES
	private const OPENSSL_IV_LEN          = 12; // GCM standard IV length
	private const OPENSSL_TAG_LEN         = 16; // GCM authentication tag length
	private const OPENSSL_KEY_LEN         = 32; // AES-256
	private const RECOVERY_SECRET_LEN     = 32; // Recovery secret byte length

	// ── Special keyring entry type for the preserved legacy A6S1 key ────────
	private const LEGACY_A6S1_KEY_TYPE    = 'legacy_a6s1';
	private const LEGACY_A6S1_KEY_VERSION = 0; // Version 0 = legacy, never used for A6S2 data.

	// ── Per-request caches keyed by blog ID ─────────────────────────────────
	/** @var array<int, string> Master key raw bytes, keyed by blog ID. */
	private static $key_cache = array();

	/** @var array<int, array> Keyring data, keyed by blog ID. */
	private static $keyring_cache = array();

	/** @var array<int, string> Recovery secrets, keyed by blog ID. */
	private static $recovery_cache = array();

	/** @var array<int, string|null> Preserved legacy A6S1 keys, keyed by blog ID. Null = not yet checked. */
	private static $legacy_key_cache = array();

	// ═══════════════════════════════════════════════════════════════════════
	//  PUBLIC API
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Returns true if any authenticated-encryption backend is available.
	 */
	public static function is_available(): bool {
		return self::sodium_available() || self::openssl_available();
	}

	/**
	 * Encrypts plaintext content into a versioned binary envelope.
	 *
	 * @param string $plaintext Raw file contents.
	 * @return string|\WP_Error Binary envelope or error.
	 */
	public static function encrypt( string $plaintext ) {
		$key_result = self::get_active_key();
		if ( is_wp_error( $key_result ) ) {
			return $key_result;
		}

		$key_version = $key_result['version'];
		$key         = $key_result['key'];
		$backend     = self::select_backend();

		if ( self::BACKEND_SODIUM === $backend ) {
			$envelope = self::encrypt_sodium( $plaintext, $key );
		} else {
			$envelope = self::encrypt_openssl( $plaintext, $key );
		}

		sodium_memzero( $key );

		if ( is_wp_error( $envelope ) ) {
			return $envelope;
		}

		// Build V2 envelope: MAGIC + version(1) + backend(1) + key_version(1) + payload.
		return self::MAGIC_V2 . chr( self::CURRENT_VERSION ) . chr( $backend ) . chr( $key_version ) . $envelope;
	}

	/**
	 * Decrypts a binary envelope back to plaintext.
	 *
	 * Supports both legacy A6S1 (preserved key or salt-derived) and current A6S2 (master key).
	 *
	 * @param string $envelope Binary envelope.
	 * @return string|false|\WP_Error Decrypted plaintext, false on failure, or WP_Error.
	 */
	public static function decrypt( string $envelope ) {
		if ( strlen( $envelope ) < 9 ) {
			return false;
		}

		$magic = substr( $envelope, 0, 4 );

		// Legacy A6S1 format.
		if ( $magic === self::MAGIC_V1 ) {
			return self::decrypt_legacy_v1( $envelope );
		}

		// Current A6S2 format.
		if ( $magic === self::MAGIC_V2 ) {
			return self::decrypt_v2( $envelope );
		}

		return false;
	}

	/**
	 * Checks whether a file starts with an encrypted envelope magic marker.
	 *
	 * @param string $path Absolute file path.
	 * @return bool
	 */
	public static function is_encrypted( string $path ): bool {
		if ( ! is_file( $path ) || filesize( $path ) < 9 ) {
			return false;
		}
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return false;
		}
		$magic = fread( $handle, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $magic === self::MAGIC_V1 || $magic === self::MAGIC_V2;
	}

	/**
	 * Encrypts a file in-place, replacing its contents with an encrypted envelope.
	 *
	 * @param string $path Absolute file path.
	 * @return bool True on success.
	 */
	public static function encrypt_file( string $path ): bool {
		$plaintext = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $plaintext ) {
			return false;
		}

		$envelope = self::encrypt( $plaintext );
		sodium_memzero( $plaintext );

		if ( is_wp_error( $envelope ) || false === $envelope ) {
			return false;
		}

		$bytes = file_put_contents( $path, $envelope ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		sodium_memzero( $envelope );

		return false !== $bytes;
	}

	/**
	 * Decrypts a file and writes the plaintext to PHP output.
	 *
	 * @param string $path Absolute file path.
	 * @return bool True on success.
	 */
	public static function decrypt_file_to_output( string $path ): bool {
		$ciphertext = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $ciphertext ) {
			return false;
		}

		$plaintext = self::decrypt( $ciphertext );
		sodium_memzero( $ciphertext );

		if ( false === $plaintext || is_wp_error( $plaintext ) ) {
			return false;
		}

		echo $plaintext; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		sodium_memzero( $plaintext );

		return true;
	}

	/**
	 * Returns the active backend identifier.
	 */
	public static function select_backend(): int {
		if ( self::sodium_available() ) {
			return self::BACKEND_SODIUM;
		}
		if ( self::openssl_available() ) {
			return self::BACKEND_OPENSSL;
		}
		return 0;
	}

	/**
	 * Public wrapper for activation-time recovery secret initialization.
	 *
	 * @return string|WP_Error The recovery secret or error.
	 */
	public static function initialize_recovery_secret_for_activation() {
		return self::initialize_recovery_secret();
	}

	/**
	 * Preserves the current A6S1 legacy key in durable dual-wrapped form.
	 *
	 * Must be called while WordPress salts are still valid (before any rotation).
	 * The key is derived from the same salts used by the original A6S1 encryption,
	 * then wrapped with both salt-KEK and recovery-KEK for durability.
	 *
	 * Idempotent: safe to call multiple times. Will not overwrite an existing
	 * preserved key.
	 *
	 * @return bool True if the key was preserved or already existed, false on error.
	 */
	public static function preserve_legacy_a6s1_key(): bool {
		$blog_id = self::current_blog_id();

		// Check if already preserved.
		$existing = get_option( self::LEGACY_KEY_OPTION, '' );
		if ( is_string( $existing ) && '' !== $existing ) {
			return true; // Already preserved.
		}

		// Derive the current A6S1 key from WordPress salts.
		$legacy_key = self::derive_a6s1_key_from_salts();
		if ( is_wp_error( $legacy_key ) ) {
			return false;
		}

		// Wrap with both salt-KEK and recovery-KEK (authenticated dual wrap).
		$wrapped = self::wrap_key_dual( $legacy_key, self::LEGACY_A6S1_KEY_VERSION );
		sodium_memzero( $legacy_key );

		if ( is_wp_error( $wrapped ) ) {
			return false;
		}

		// Mark this entry as the legacy A6S1 key.
		$wrapped['type'] = self::LEGACY_A6S1_KEY_TYPE;

		// Atomically persist — add_option returns false if already exists.
		$encoded = wp_json_encode( $wrapped );
		$added   = add_option( self::LEGACY_KEY_OPTION, $encoded, '', false );

		if ( ! $added ) {
			// Another request created it first — that's fine.
			return true;
		}

		// Update the in-memory cache.
		self::$legacy_key_cache[ $blog_id ] = $wrapped;

		return true;
	}

	/**
	 * Returns the preserved legacy A6S1 keyring entry.
	 *
	 * @return array|false The wrapped legacy key entry, or false if not preserved.
	 */
	public static function get_preserved_legacy_a6s1_entry() {
		$blog_id = self::current_blog_id();

		if ( isset( self::$legacy_key_cache[ $blog_id ] ) ) {
			$cached = self::$legacy_key_cache[ $blog_id ];
			return ( false === $cached ) ? false : $cached;
		}

		$stored = get_option( self::LEGACY_KEY_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			self::$legacy_key_cache[ $blog_id ] = false;
			return false;
		}

		$decoded = json_decode( $stored, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['kek_nonce'], $decoded['kek_cipher'] ) ) {
			self::$legacy_key_cache[ $blog_id ] = false;
			return false;
		}

		self::$legacy_key_cache[ $blog_id ] = $decoded;
		return $decoded;
	}

	/**
	 * Unwraps the preserved legacy A6S1 key to recover the raw 32-byte key.
	 *
	 * Uses the same dual-recovery mechanism as A6S2 master keys: salt-KEK first,
	 * then recovery-KEK.
	 *
	 * @return string|WP_Error Raw 32-byte key or error.
	 */
	public static function unwrap_preserved_legacy_a6s1_key() {
		$entry = self::get_preserved_legacy_a6s1_entry();
		if ( false === $entry ) {
			return new \WP_Error(
				'arshid6social_no_legacy_key',
				__( 'No preserved legacy A6S1 key available.', '6arshid-social-community' )
			);
		}

		return self::unwrap_key_with_recovery( $entry );
	}

	// ═══════════════════════════════════════════════════════════════════════
	//  KEY MANAGEMENT
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Returns the active encryption key with metadata.
	 *
	 * @return array{version:int, key:string}|WP_Error
	 */
	private static function get_active_key() {
		$blog_id = self::current_blog_id();

		// Check in-memory cache first.
		if ( isset( self::$key_cache[ $blog_id ] ) ) {
			$keyring = self::get_keyring();
			$active  = self::find_active_entry( $keyring );
			if ( null === $active ) {
				return new \WP_Error( 'arshid6social_key_missing', __( 'No encryption key available.', '6arshid-social-community' ) );
			}
			return array(
				'version' => $active['version'],
				'key'     => self::$key_cache[ $blog_id ],
			);
		}

		$keyring = self::get_keyring();
		$active  = self::find_active_entry( $keyring );

		if ( null === $active ) {
			// No key exists yet — initialize one.
			return self::initialize_master_key();
		}

		$raw_key = self::unwrap_key_with_recovery( $active );
		if ( is_wp_error( $raw_key ) ) {
			return $raw_key;
		}

		// Cache for reuse within this request.
		self::$key_cache[ $blog_id ] = $raw_key;

		return array(
			'version' => $active['version'],
			'key'     => $raw_key,
		);
	}

	/**
	 * Finds the active keyring entry (highest version) among data keys (version > 0).
	 *
	 * @param array $keyring Keyring array.
	 * @return array|null Active entry or null.
	 */
	private static function find_active_entry( array $keyring ) {
		$active = null;
		foreach ( $keyring as $entry ) {
			// Skip legacy A6S1 key entries (version 0 or type=legacy_a6s1).
			if ( isset( $entry['type'] ) && self::LEGACY_A6S1_KEY_TYPE === $entry['type'] ) {
				continue;
			}
			$ver = isset( $entry['version'] ) ? (int) $entry['version'] : 0;
			if ( $ver < 1 ) {
				continue;
			}
			if ( null === $active || $ver > (int) $active['version'] ) {
				$active = $entry;
			}
		}
		return $active;
	}

	/**
	 * Returns the current blog ID, safely handling non-multisite.
	 *
	 * @return int
	 */
	private static function current_blog_id(): int {
		if ( is_multisite() && function_exists( 'get_current_blog_id' ) ) {
			return get_current_blog_id();
		}
		return 1;
	}

	/**
	 * Returns the full keyring array from wp_options.
	 *
	 * @return array<int, array>
	 */
	private static function get_keyring(): array {
		$blog_id = self::current_blog_id();

		if ( isset( self::$keyring_cache[ $blog_id ] ) ) {
			return self::$keyring_cache[ $blog_id ];
		}

		$serialized = get_option( self::KEYRING_OPTION, '' );
		$decoded    = is_string( $serialized ) ? json_decode( $serialized, true ) : array();
		$keyring    = is_array( $decoded ) ? $decoded : array();

		self::$keyring_cache[ $blog_id ] = $keyring;

		return $keyring;
	}

	/**
	 * Saves the keyring to wp_options and updates the in-memory cache.
	 */
	private static function save_keyring( array $keyring ): void {
		$blog_id = self::current_blog_id();
		update_option( self::KEYRING_OPTION, wp_json_encode( $keyring ), false );
		self::$keyring_cache[ $blog_id ] = $keyring;
	}

	/**
	 * Returns the per-site recovery secret, generating one if it does not exist.
	 *
	 * @return string|WP_Error 32-byte recovery secret or error.
	 */
	private static function get_recovery_secret() {
		$blog_id = self::current_blog_id();

		if ( isset( self::$recovery_cache[ $blog_id ] ) ) {
			return self::$recovery_cache[ $blog_id ];
		}

		$stored = get_option( self::RECOVERY_OPTION, '' );

		if ( is_string( $stored ) && strlen( $stored ) === self::RECOVERY_SECRET_LEN ) {
			self::$recovery_cache[ $blog_id ] = $stored;
			return $stored;
		}

		// If stored is empty/missing, generate a new recovery secret.
		if ( '' === $stored ) {
			return self::initialize_recovery_secret();
		}

		// Stored value is malformed — do NOT overwrite. Return error.
		return new \WP_Error(
			'arshid6social_recovery_secret_corrupt',
			__( 'The recovery secret is corrupted. Cannot recover encryption keys.', '6arshid-social-community' )
		);
	}

	/**
	 * Atomically initializes the per-site recovery secret using add_option().
	 *
	 * @return string|WP_Error 32-byte recovery secret or error.
	 */
	private static function initialize_recovery_secret() {
		if ( self::sodium_available() ) {
			$secret = random_bytes( self::RECOVERY_SECRET_LEN );
		} elseif ( self::openssl_available() ) {
			$secret = openssl_random_pseudo_bytes( self::RECOVERY_SECRET_LEN );
			if ( false === $secret ) {
				return new \WP_Error( 'arshid6social_recovery_gen_failed', __( 'Failed to generate recovery secret.', '6arshid-social-community' ) );
			}
		} else {
			return new \WP_Error( 'arshid6social_no_crypto', __( 'No cryptographic backend is available.', '6arshid-social-community' ) );
		}

		$added = add_option( self::RECOVERY_OPTION, $secret, '', false );

		if ( ! $added ) {
			$persisted = get_option( self::RECOVERY_OPTION, '' );
			if ( is_string( $persisted ) && strlen( $persisted ) === self::RECOVERY_SECRET_LEN ) {
				sodium_memzero( $secret );
				self::$recovery_cache[ self::current_blog_id() ] = $persisted;
				return $persisted;
			}
			sodium_memzero( $secret );
			return new \WP_Error(
				'arshid6social_recovery_corrupt',
				__( 'Recovery secret initialization failed due to a race condition.', '6arshid-social-community' )
			);
		}

		self::$recovery_cache[ self::current_blog_id() ] = $secret;
		return $secret;
	}

	/**
	 * Derives the legacy A6S1 encryption key from current WordPress salts.
	 *
	 * This is the EXACT key derivation used by the original A6S1 encryption.
	 * It must be called while the original salts are still in wp-config.php.
	 *
	 * @return string|WP_Error 32-byte key or error.
	 */
	private static function derive_a6s1_key_from_salts() {
		$salt = '';
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $const ) {
			if ( defined( $const ) ) {
				$salt .= constant( $const );
			}
		}

		if ( '' === $salt ) {
			return new \WP_Error( 'arshid6social_no_salts', __( 'WordPress cryptographic salts are not configured.', '6arshid-social-community' ) );
		}

		return hash_hkdf( 'sha256', $salt, 32, 'arshid6social-private-v1' );
	}

	/**
	 * Derives a key-encryption key (KEK) from current WordPress salts.
	 *
	 * @return string|WP_Error 32-byte KEK or error.
	 */
	private static function derive_kek_from_salts() {
		$salts = '';
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $const ) {
			if ( defined( $const ) ) {
				$salts .= constant( $const );
			}
		}

		if ( '' === $salts ) {
			return new \WP_Error( 'arshid6social_no_salts', __( 'WordPress cryptographic salts are not configured.', '6arshid-social-community' ) );
		}

		if ( self::sodium_available() ) {
			return hash_hkdf( 'sha256', $salts, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'arshid6social-kek-v1' );
		}

		return hash_hkdf( 'sha256', $salts, 32, 'arshid6social-kek-v1' );
	}

	/**
	 * Derives a recovery key-encryption key (KEK-B) from the per-site recovery secret.
	 *
	 * @return string|WP_Error 32-byte KEK or error.
	 */
	private static function derive_kek_from_recovery() {
		$recovery = self::get_recovery_secret();
		if ( is_wp_error( $recovery ) ) {
			return $recovery;
		}

		if ( self::sodium_available() ) {
			return hash_hkdf( 'sha256', $recovery, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'arshid6social-recovery-kek-v1' );
		}

		return hash_hkdf( 'sha256', $recovery, 32, 'arshid6social-recovery-kek-v1' );
	}

	/**
	 * Generates a new master key and wraps it with both salt-KEK and recovery-KEK.
	 *
	 * @return array{version:int, key:string}|WP_Error
	 */
	private static function initialize_master_key() {
		$keyring = self::get_keyring();

		// Another request may have initialized the key — re-read and return active.
		if ( ! empty( $keyring ) ) {
			$active = self::find_active_entry( $keyring );
			if ( $active ) {
				$raw = self::unwrap_key_with_recovery( $active );
				if ( is_wp_error( $raw ) ) {
					return $raw;
				}
				$blog_id = self::current_blog_id();
				self::$key_cache[ $blog_id ] = $raw;
				return array(
					'version' => $active['version'],
					'key'     => $raw,
				);
			}
		}

		// Generate a cryptographically random 32-byte master key.
		if ( self::sodium_available() ) {
			$raw_key = random_bytes( 32 );
		} elseif ( self::openssl_available() ) {
			$raw_key = openssl_random_pseudo_bytes( 32 );
			if ( false === $raw_key ) {
				return new \WP_Error( 'arshid6social_key_gen_failed', __( 'Failed to generate encryption key.', '6arshid-social-community' ) );
			}
		} else {
			return new \WP_Error( 'arshid6social_no_crypto', __( 'No authenticated encryption backend is available.', '6arshid-social-community' ) );
		}

		$wrapped = self::wrap_key_dual( $raw_key, 1 );
		if ( is_wp_error( $wrapped ) ) {
			return $wrapped;
		}

		$keyring[] = $wrapped;
		self::save_keyring( $keyring );

		$blog_id = self::current_blog_id();
		self::$key_cache[ $blog_id ] = $raw_key;

		return array(
			'version' => 1,
			'key'     => $raw_key,
		);
	}

	/**
	 * Wraps a raw key using both salt-KEK and recovery-KEK (dual wrap).
	 *
	 * All wrapping uses authenticated encryption (sodium_crypto_secretbox).
	 *
	 * @param string $raw_key 32-byte key.
	 * @param int    $version Key version.
	 * @return array|WP_Error Dual-wrapped keyring entry or error.
	 */
	private static function wrap_key_dual( string $raw_key, int $version ) {
		// Wrap with salt-derived KEK (primary).
		$salt_kek = self::derive_kek_from_salts();
		if ( is_wp_error( $salt_kek ) ) {
			return $salt_kek;
		}

		$salt_nonce  = random_bytes( self::SODIUM_NONCE_LEN );
		$salt_cipher = sodium_crypto_secretbox( $raw_key, $salt_nonce, $salt_kek );
		sodium_memzero( $salt_kek );

		// Wrap with recovery-derived KEK (fallback).
		$recovery_kek = self::derive_kek_from_recovery();
		if ( is_wp_error( $recovery_kek ) ) {
			return $recovery_kek;
		}

		$recovery_nonce  = random_bytes( self::SODIUM_NONCE_LEN );
		$recovery_cipher = sodium_crypto_secretbox( $raw_key, $recovery_nonce, $recovery_kek );
		sodium_memzero( $recovery_kek );

		return array(
			'version'         => $version,
			'backend'         => self::BACKEND_SODIUM,
			'kek_algo'        => 'sodium_secretbox',
			'kek_nonce'       => base64_encode( $salt_nonce ),
			'kek_cipher'      => base64_encode( $salt_cipher ),
			'recovery_nonce'  => base64_encode( $recovery_nonce ),
			'recovery_cipher' => base64_encode( $recovery_cipher ),
			'wrap_version'    => 2,
		);
	}

	/**
	 * Unwraps a keyring entry with dual recovery: salt-KEK first, then recovery-KEK.
	 *
	 * @param array $entry Keyring entry.
	 * @return string|WP_Error Raw 32-byte key or error.
	 */
	private static function unwrap_key_with_recovery( array $entry ) {
		if ( ! isset( $entry['kek_nonce'], $entry['kek_cipher'] ) ) {
			return new \WP_Error( 'arshid6social_key_corrupt', __( 'Encryption key data is corrupted.', '6arshid-social-community' ) );
		}

		// Attempt 1: Primary salt-derived unwrap.
		$raw_key = self::unwrap_with_salt_kek( $entry );
		if ( ! is_wp_error( $raw_key ) ) {
			return $raw_key;
		}

		// Attempt 2: Recovery-derived unwrap.
		if ( isset( $entry['recovery_nonce'], $entry['recovery_cipher'] ) ) {
			$raw_key = self::unwrap_with_recovery_kek( $entry );
			if ( ! is_wp_error( $raw_key ) ) {
				// Salt rotation detected — rewrap with current salts.
				self::rewrap_keyring_entry_after_salt_rotation( $entry, $raw_key );
				return $raw_key;
			}
		}

		return new \WP_Error(
			'arshid6social_key_unwrap_failed',
			__( 'Encryption key unwrap failed. The master key cannot be recovered.', '6arshid-social-community' )
		);
	}

	/**
	 * Attempts to unwrap a keyring entry using the salt-derived KEK.
	 *
	 * @param array $entry Keyring entry.
	 * @return string|WP_Error Raw key or error.
	 */
	private static function unwrap_with_salt_kek( array $entry ) {
		$salt_kek = self::derive_kek_from_salts();
		if ( is_wp_error( $salt_kek ) ) {
			return $salt_kek;
		}

		$nonce  = base64_decode( $entry['kek_nonce'], true );
		$cipher = base64_decode( $entry['kek_cipher'], true );

		if ( false === $nonce || false === $cipher ) {
			sodium_memzero( $salt_kek );
			return new \WP_Error( 'arshid6social_key_corrupt', __( 'Encryption key data is corrupted.', '6arshid-social-community' ) );
		}

		$raw_key = sodium_crypto_secretbox_open( $cipher, $nonce, $salt_kek );
		sodium_memzero( $salt_kek );

		if ( false === $raw_key || strlen( $raw_key ) !== 32 ) {
			return new \WP_Error( 'arshid6social_key_unwrap_salt_failed', __( 'Salt-based key unwrap failed.', '6arshid-social-community' ) );
		}

		return $raw_key;
	}

	/**
	 * Attempts to unwrap a keyring entry using the recovery-derived KEK.
	 *
	 * @param array $entry Keyring entry.
	 * @return string|WP_Error Raw key or error.
	 */
	private static function unwrap_with_recovery_kek( array $entry ) {
		$recovery_kek = self::derive_kek_from_recovery();
		if ( is_wp_error( $recovery_kek ) ) {
			return $recovery_kek;
		}

		$nonce  = base64_decode( $entry['recovery_nonce'], true );
		$cipher = base64_decode( $entry['recovery_cipher'], true );

		if ( false === $nonce || false === $cipher ) {
			sodium_memzero( $recovery_kek );
			return new \WP_Error( 'arshid6social_key_corrupt', __( 'Recovery key data is corrupted.', '6arshid-social-community' ) );
		}

		$raw_key = sodium_crypto_secretbox_open( $cipher, $nonce, $recovery_kek );
		sodium_memzero( $recovery_kek );

		if ( false === $raw_key || strlen( $raw_key ) !== 32 ) {
			return new \WP_Error( 'arshid6social_key_unwrap_recovery_failed', __( 'Recovery key unwrap failed.', '6arshid-social-community' ) );
		}

		return $raw_key;
	}

	/**
	 * Rewraps a single keyring entry with current salts after salt rotation.
	 *
	 * @param array  $entry   The entry that was successfully recovery-unwrapped.
	 * @param string $raw_key The recovered 32-byte key.
	 */
	private static function rewrap_keyring_entry_after_salt_rotation( array $entry, string $raw_key ): void {
		$keyring = self::get_keyring();

		foreach ( $keyring as $idx => $ke ) {
			if ( (int) $ke['version'] === (int) $entry['version'] && ! isset( $ke['type'] ) ) {
				$rewrapped = self::wrap_key_dual( $raw_key, (int) $entry['version'] );
				if ( ! is_wp_error( $rewrapped ) ) {
					$keyring[ $idx ] = $rewrapped;
					self::save_keyring( $keyring );
				}
				break;
			}
		}

		// Also rewrap the preserved legacy A6S1 key if it exists.
		self::rewrap_preserved_legacy_key_after_salt_rotation();
	}

	/**
	 * Rewraps the preserved legacy A6S1 key after salt rotation.
	 */
	private static function rewrap_preserved_legacy_key_after_salt_rotation(): void {
		$stored = get_option( self::LEGACY_KEY_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return;
		}

		$decoded = json_decode( $stored, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}

		$raw_key = self::unwrap_key_with_recovery( $decoded );
		if ( is_wp_error( $raw_key ) ) {
			return;
		}

		// Re-wrap with current salts (dual wrap).
		$rewrapped = self::wrap_key_dual( $raw_key, self::LEGACY_A6S1_KEY_VERSION );
		sodium_memzero( $raw_key );

		if ( ! is_wp_error( $rewrapped ) ) {
			$rewrapped['type'] = self::LEGACY_A6S1_KEY_TYPE;
			update_option( self::LEGACY_KEY_OPTION, wp_json_encode( $rewrapped ), false );
			self::$legacy_key_cache[ self::current_blog_id() ] = $rewrapped;
		}
	}

	// ═══════════════════════════════════════════════════════════════════════
	//  ENCRYPTION BACKENDS
	// ═══════════════════════════════════════════════════════════════════════

	/**
	 * Encrypts using libsodium secretbox.
	 *
	 * @param string $plaintext
	 * @param string $key 32-byte key.
	 * @return string|\WP_Error Payload (nonce + ciphertext) or error.
	 */
	private static function encrypt_sodium( string $plaintext, string $key ) {
		if ( ! self::sodium_available() ) {
			return new \WP_Error( 'arshid6social_sodium_unavailable', __( 'libsodium is not available.', '6arshid-social-community' ) );
		}

		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		return $nonce . $cipher;
	}

	/**
	 * Encrypts using OpenSSL AES-256-GCM.
	 *
	 * @param string $plaintext
	 * @param string $key 32-byte key.
	 * @return string|\WP_Error Payload (iv + tag + ciphertext) or error.
	 */
	private static function encrypt_openssl( string $plaintext, string $key ) {
		if ( ! self::openssl_available() ) {
			return new \WP_Error( 'arshid6social_openssl_unavailable', __( 'OpenSSL is not available.', '6arshid-social-community' ) );
		}

		$iv   = random_bytes( self::OPENSSL_IV_LEN );
		$tag  = '';
		$cipher = openssl_encrypt(
			$plaintext,
			'aes-256-gcm',
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::OPENSSL_TAG_LEN
		);

		if ( false === $cipher ) {
			return new \WP_Error( 'arshid6social_openssl_encrypt_failed', __( 'OpenSSL encryption failed.', '6arshid-social-community' ) );
		}

		return $iv . $tag . $cipher;
	}

	/**
	 * Decrypts a legacy A6S1 envelope.
	 *
	 * Uses the preserved legacy key if available (salt-rotation safe),
	 * otherwise falls back to current-salt derivation.
	 *
	 * @param string $envelope Full file contents.
	 * @return string|false|\WP_Error Plaintext, false on failure, or WP_Error.
	 */
	private static function decrypt_legacy_v1( string $envelope ) {
		$min_len = 4 + 1 + self::SODIUM_NONCE_LEN;
		if ( strlen( $envelope ) < $min_len ) {
			return false;
		}

		$version = ord( $envelope[4] );
		if ( $version > 1 ) {
			return false;
		}

		if ( ! self::sodium_available() ) {
			return false;
		}

		$nonce  = substr( $envelope, 5, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $envelope, 5 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		// ── Attempt 1: Use preserved legacy key (salt-rotation safe). ──────
		$preserved = self::unwrap_preserved_legacy_a6s1_key();
		if ( ! is_wp_error( $preserved ) ) {
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, $preserved );
			sodium_memzero( $preserved );
			if ( false !== $plain ) {
				return $plain;
			}
		}

		// ── Attempt 2: Derive from current salts (may fail after rotation). ─
		$salt = '';
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $const ) {
			if ( defined( $const ) ) {
				$salt .= constant( $const );
			}
		}
		$key = hash_hkdf( 'sha256', $salt, 32, 'arshid6social-private-v1' );

		$plain = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

		sodium_memzero( $key );
		sodium_memzero( $nonce );

		if ( false === $plain ) {
			return new \WP_Error(
				'arshid6social_a6s1_decrypt_failed',
				__( 'Legacy A6S1 file decryption failed. The file may have been encrypted with rotated salts and no preserved key is available.', '6arshid-social-community' )
			);
		}

		return $plain;
	}

	/**
	 * Decrypts a V2 envelope using the appropriate master key and backend.
	 *
	 * @param string $envelope Full file contents.
	 * @return string|false|\WP_Error Decrypted plaintext, false on failure, or WP_Error.
	 */
	private static function decrypt_v2( string $envelope ) {
		if ( strlen( $envelope ) < 7 ) {
			return false;
		}

		$format_ver = ord( $envelope[4] );
		$backend    = ord( $envelope[5] );
		$key_ver    = ord( $envelope[6] );
		$payload    = substr( $envelope, 7 );

		if ( $format_ver > self::CURRENT_VERSION ) {
			return false;
		}

		// Find the key version in the keyring.
		$keyring  = self::get_keyring();
		$raw_key  = null;
		foreach ( $keyring as $entry ) {
			if ( isset( $entry['version'] ) && (int) $entry['version'] === $key_ver && ! isset( $entry['type'] ) ) {
				$raw_key = self::unwrap_key_with_recovery( $entry );
				break;
			}
		}

		if ( null === $raw_key || is_wp_error( $raw_key ) ) {
			return $raw_key ? $raw_key : false;
		}

		// Decrypt with the appropriate backend.
		switch ( $backend ) {
			case self::BACKEND_SODIUM:
				$plain = self::decrypt_sodium_payload( $payload, $raw_key );
				break;
			case self::BACKEND_OPENSSL:
				$plain = self::decrypt_openssl_payload( $payload, $raw_key );
				break;
			default:
				$plain = false;
				break;
		}

		sodium_memzero( $raw_key );

		return $plain;
	}

	/**
	 * Decrypts a libsodium secretbox payload.
	 */
	private static function decrypt_sodium_payload( string $payload, string $key ) {
		if ( ! self::sodium_available() || strlen( $payload ) < self::SODIUM_NONCE_LEN ) {
			return false;
		}

		$nonce  = substr( $payload, 0, self::SODIUM_NONCE_LEN );
		$cipher = substr( $payload, self::SODIUM_NONCE_LEN );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
		sodium_memzero( $nonce );

		return false === $plain ? false : $plain;
	}

	/**
	 * Decrypts an OpenSSL AES-256-GCM payload.
	 */
	private static function decrypt_openssl_payload( string $payload, string $key ) {
		if ( ! self::openssl_available() || strlen( $payload ) < self::OPENSSL_IV_LEN + self::OPENSSL_TAG_LEN ) {
			return false;
		}

		$iv   = substr( $payload, 0, self::OPENSSL_IV_LEN );
		$tag  = substr( $payload, self::OPENSSL_IV_LEN, self::OPENSSL_TAG_LEN );
		$cipher = substr( $payload, self::OPENSSL_IV_LEN + self::OPENSSL_TAG_LEN );

		$plain = openssl_decrypt(
			$cipher,
			'aes-256-gcm',
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		sodium_memzero( $iv );

		return false === $plain ? false : $plain;
	}

	// ═══════════════════════════════════════════════════════════════════════
	//  BACKEND AVAILABILITY
	// ═══════════════════════════════════════════════════════════════════════

	private static function sodium_available(): bool {
		return function_exists( 'sodium_crypto_secretbox' )
			&& function_exists( 'sodium_crypto_secretbox_open' )
			&& function_exists( 'random_bytes' );
	}

	private static function openssl_available(): bool {
		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& function_exists( 'openssl_random_pseudo_bytes' )
			&& defined( 'OPENSSL_RAW_DATA' );
	}
}
