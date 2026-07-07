<?php
namespace Arshid6Social;

/**
 * Caching layer wrapping object cache and transients.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Cache
 *
 * Provides a unified get/set/delete interface that prefers the object cache
 * (persistent when available) and falls back to transients for long-lived data.
 */
final class Cache {

	private static ?Cache $instance = null;

	/** @var string Object cache group name. */
	private const GROUP = 'arshid6social';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Retrieves a cached value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $found Set to true/false to indicate cache hit.
	 * @return mixed Cached value or false on miss.
	 */
	public static function get( string $key, mixed &$found = null ): mixed {
		return wp_cache_get( $key, self::GROUP, false, $found );
	}

	/**
	 * Stores a value in the cache.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to store.
	 * @param int    $expiration Seconds until expiry (0 = no expiry in object cache).
	 */
	public static function set( string $key, mixed $value, int $expiration = 0 ): bool {
		return wp_cache_set( $key, $value, self::GROUP, $expiration );
	}

	/**
	 * Deletes a cached value.
	 *
	 * @param string $key Cache key.
	 */
	public static function delete( string $key ): bool {
		return wp_cache_delete( $key, self::GROUP );
	}

	/**
	 * Deletes all cache entries matching a group prefix.
	 *
	 * @param string $prefix Key prefix to flush.
	 */
	public static function flush_prefix( string $prefix ): void {
		// WP does not support group flushing natively without a persistent cache.
		// Store a version counter and bust it; individual keys include the version.
		$version_key = "ver_{$prefix}";
		$version     = (int) wp_cache_get( $version_key, self::GROUP );
		wp_cache_set( $version_key, $version + 1, self::GROUP );
	}

	/**
	 * Builds a versioned cache key for a given prefix.
	 *
	 * @param string $prefix Base prefix.
	 * @param string $key    Specific key.
	 * @return string Versioned key.
	 */
	public static function versioned_key( string $prefix, string $key ): string {
		$version = (int) wp_cache_get( "ver_{$prefix}", self::GROUP );
		return "{$prefix}_v{$version}_{$key}";
	}

	/**
	 * Remembers a value: returns cached or runs the callback and caches the result.
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Returns the value when cache misses.
	 * @param int      $expiration Seconds until expiry.
	 * @return mixed
	 */
	public static function remember( string $key, callable $callback, int $expiration = 300 ): mixed {
		$found = false;
		$value = self::get( $key, $found );

		if ( $found ) {
			return $value;
		}

		$value = $callback();
		self::set( $key, $value, $expiration );
		return $value;
	}
}
