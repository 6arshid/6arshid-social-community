<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Social Embeds – DB cache layer.
 *
 * Table: {prefix}arshid6social_embed_cache
 *   url_hash   CHAR(64)  – SHA-256 of the canonical URL (unique index)
 *   provider   VARCHAR   – provider id
 *   html       LONGTEXT  – rendered embed HTML
 *   data_json  LONGTEXT  – raw oEmbed/OG data JSON
 *   fetched_at DATETIME
 *   expires_at DATETIME  – pruned by daily cron
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Social_Embeds_Cache {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'arshid6social_embed_cache';
	}

	/**
	 * Retrieve a cached embed.
	 *
	 * @param string $url Canonical URL.
	 * @return array{html:string,data:array<string,mixed>}|null  Null on cache miss or expired.
	 */
	public static function get( string $url ): ?array {
		global $wpdb;
		$hash = self::hash( $url );
		$tbl  = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT html, data_json FROM $tbl WHERE url_hash = %s AND expires_at > %s LIMIT 1",
				$hash,
				current_time( 'mysql' )
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $row ) {
			return null;
		}

		return array(
			'html' => (string) $row['html'],
			'data' => (array) json_decode( (string) $row['data_json'], true ),
		);
	}

	/**
	 * Store an embed in the cache.
	 *
	 * @param string               $url
	 * @param string               $provider_id
	 * @param string               $html
	 * @param array<string, mixed> $data
	 * @param int                  $hours
	 */
	public static function set( string $url, string $provider_id, string $html, array $data, int $hours = 24 ): void {
		global $wpdb;
		$tbl = self::table();
		$now = current_time( 'mysql' );
		$exp = gmdate( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$tbl,
			array(
				'url_hash'   => self::hash( $url ),
				'provider'   => sanitize_key( $provider_id ),
				'html'       => $html,
				'data_json'  => (string) wp_json_encode( $data ),
				'fetched_at' => $now,
				'expires_at' => $exp,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		// phpcs:enable
	}

	/** Remove expired rows (called by daily cron). */
	public static function prune(): void {
		global $wpdb;
		$tbl = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM $tbl WHERE expires_at < %s", current_time( 'mysql' ) )
		);
		// phpcs:enable
	}

	/** Wipe the entire cache (e.g., on uninstall). */
	public static function purge_all(): void {
		global $wpdb;
		$tbl = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "TRUNCATE TABLE $tbl" );
		// phpcs:enable
	}

	private static function hash( string $url ): string {
		return hash( 'sha256', $url );
	}
}
