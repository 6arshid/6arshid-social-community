<?php
namespace Arshid6Social\Components\Search;

/**
 * Search component — boots the Search REST controller.
 *
 * @package Arshid6Social\Components\Search
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Search
 *
 * Always-active component that powers the unified search page.
 * It does not depend on any other component being enabled — it
 * gracefully skips sections whose component is inactive.
 */
class Search {

	public function __construct() {}

	/**
	 * Called by Plugin::register_rest_routes() on rest_api_init.
	 */
	public function register_rest_routes(): void {
		( new Search_REST() )->register_routes();
	}
}
