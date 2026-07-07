<?php
namespace Arshid6Social\Components\Groups;

/**
 * Sitemap provider for public groups.
 *
 * @package Arshid6Social\Components\Groups
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Groups_Sitemap_Provider
 *
 * Registers public, non-suspended groups in the WordPress core sitemap.
 * URL format: /groups/{slug}/
 */
class Groups_Sitemap_Provider extends \WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'arshid6social_groups';
		$this->object_type = 'arshid6social_groups';
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
				"SELECT slug, date_created FROM {$wpdb->prefix}sn_groups
				 WHERE status = 'public' AND is_suspended = 0
				 ORDER BY date_created DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		if ( ! $rows ) {
			return array();
		}

		$urls = array();
		foreach ( $rows as $row ) {
			$urls[] = array(
				'loc'     => home_url( '/groups/' . $row->slug . '/' ),
				'lastmod' => gmdate( 'Y-m-d', strtotime( $row->date_created ) ),
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
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_groups WHERE status = 'public' AND is_suspended = 0"
		);

		return (int) ceil( $total / $this->get_max_num_urls() );
	}
}
