<?php
namespace Arshid6Social\Components\Activity;

/**
 * Sitemap provider for public activity posts.
 *
 * @package Arshid6Social\Components\Activity
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Activity_Sitemap_Provider
 *
 * Registers public activity posts in the WordPress core sitemap.
 * URL format: /activity/{id}/
 */
class Activity_Sitemap_Provider extends \WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'arshid6social_activity';
		$this->object_type = 'arshid6social_activity';
	}

	/**
	 * Returns a list of sitemap entries for the given page.
	 *
	 * @param string $object_subtype Unused (no subtypes).
	 * @param int    $page           1-based page number.
	 * @return array<int, array{loc: string, lastmod: string}>
	 */
	public function get_url_list( $object_subtype, $page = 1 ): array {
		global $wpdb;

		$per_page = (int) $this->get_max_num_urls();
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, date_recorded FROM {$wpdb->prefix}sn_activity
				 WHERE privacy = 'public' AND is_spam = 0 AND hide_sitewide = 0
				 ORDER BY date_recorded DESC
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
				'loc'     => Activity::get_permalink( (int) $row->id ),
				'lastmod' => gmdate( 'Y-m-d', strtotime( $row->date_recorded ) ),
			);
		}

		return $urls;
	}

	/**
	 * Returns the total number of pages for the sitemap index.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ): int {
		global $wpdb;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity WHERE privacy = 'public' AND is_spam = 0 AND hide_sitewide = 0"
		);

		return (int) ceil( $total / $this->get_max_num_urls() );
	}
}
