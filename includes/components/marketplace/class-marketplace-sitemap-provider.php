<?php
namespace Arshid6Social\Components\Marketplace;

/**
 * Sitemap provider for active marketplace listings.
 *
 * @package Arshid6Social\Components\Marketplace
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Marketplace_Sitemap_Provider
 *
 * Registers active marketplace listings in the WordPress core sitemap.
 * URL format: {marketplace_page}?action=view&id={uid}
 */
class Marketplace_Sitemap_Provider extends \WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'arshid6social_marketplace';
		$this->object_type = 'arshid6social_marketplace';
	}

	/**
	 * @param string $object_subtype Unused.
	 * @param int    $page           1-based page number.
	 * @return array<int, array{loc: string, lastmod: string}>
	 */
	public function get_url_list( $object_subtype, $page = 1 ): array {
		global $wpdb;

		$per_page = (int) $this->get_max_num_urls();
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT uid, id, updated_at FROM {$wpdb->prefix}arshid6social_listings
				 WHERE status = 'active'
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		if ( ! $rows ) {
			return array();
		}

		$base_url = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) )
			?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );

		$urls = array();
		foreach ( $rows as $row ) {
			$urls[] = array(
				'loc'     => add_query_arg( array( 'action' => 'view', 'id' => ( $row->uid ?: $row->id ) ), $base_url ),
				'lastmod' => gmdate( 'Y-m-d', strtotime( $row->updated_at ) ),
			);
		}

		return $urls;
	}

	/**
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ): int {
		global $wpdb;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}arshid6social_listings WHERE status = 'active'"
		);

		return (int) ceil( $total / $this->get_max_num_urls() );
	}
}
