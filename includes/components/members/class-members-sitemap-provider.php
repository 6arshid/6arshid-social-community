<?php
namespace Arshid6Social\Components\Members;

/**
 * Sitemap provider for member profile pages.
 *
 * @package Arshid6Social\Components\Members
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Members_Sitemap_Provider
 *
 * Registers non-suspended member profiles in the WordPress core sitemap.
 * URL format: /members/{user_nicename}/
 */
class Members_Sitemap_Provider extends \WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'arshid6social_members';
		$this->object_type = 'arshid6social_members';
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
				"SELECT u.user_nicename, u.user_registered
				 FROM {$wpdb->users} u
				 LEFT JOIN {$wpdb->usermeta} um
				   ON um.user_id = u.ID AND um.meta_key = 'arshid6social_suspended'
				 WHERE um.meta_value IS NULL OR um.meta_value != '1'
				 ORDER BY u.user_registered DESC
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
				'loc'     => home_url( '/members/' . $row->user_nicename . '/' ),
				'lastmod' => gmdate( 'Y-m-d', strtotime( $row->user_registered ) ),
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
			"SELECT COUNT(u.ID)
			 FROM {$wpdb->users} u
			 LEFT JOIN {$wpdb->usermeta} um
			   ON um.user_id = u.ID AND um.meta_key = 'arshid6social_suspended'
			 WHERE um.meta_value IS NULL OR um.meta_value != '1'"
		);

		return (int) ceil( $total / $this->get_max_num_urls() );
	}
}
