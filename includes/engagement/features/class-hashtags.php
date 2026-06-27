<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Hashtags feature.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Hashtags {

	/** Unicode-aware regex: matches Latin, Persian/Arabic, and common Unicode word chars. */
	const REGEX = '/#([\p{L}\p{N}_]+)/u';

	public function __construct() {
		// Process hashtags when activity is created/edited.
		add_action( 'arshid6social_activity_added',   array( $this, 'process_activity' ), 10, 2 );
		add_action( 'arshid6social_activity_deleted', array( $this, 'delete_relations' ), 10, 1 );

		// Rewrite: /tag/{slug}/
		add_action( 'init',              array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars',        array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_archive_page' ) );

		// AJAX.
		add_action( 'wp_ajax_arshid6social_hashtag_autocomplete',        array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_hashtag_autocomplete', array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_ajax_arshid6social_hashtag_follow',   array( $this, 'ajax_follow' ) );
		add_action( 'wp_ajax_arshid6social_hashtag_unfollow', array( $this, 'ajax_unfollow' ) );

		// Cron: rebuild trending cache every hour.
		add_action( 'arshid6social_hashtag_trending_refresh', array( $this, 'refresh_trending_cache' ) );
		if ( ! wp_next_scheduled( 'arshid6social_hashtag_trending_refresh' ) ) {
			wp_schedule_event( time(), 'hourly', 'arshid6social_hashtag_trending_refresh' );
		}

		// Shortcode.
		add_shortcode( 'sn_trending_hashtags', array( $this, 'shortcode_trending' ) );

		// Render hashtag links in activity content output.
		add_filter( 'arshid6social_activity_content', array( $this, 'linkify' ), 10 );
	}

	// ── Rewrite ───────────────────────────────────────────────────────────────

	public function add_rewrite_rules(): void {
		$tag_base = sanitize_title( get_option( 'arshid6social_permalink_tag_base', 'hashtags' ) );
		add_rewrite_rule( '^' . $tag_base . '/([^/]+)/?$', 'index.php?arshid6social_eng_hashtag=$matches[1]', 'top' );
	}

	public function register_query_vars( array $vars ): array {
		$vars[] = 'arshid6social_eng_hashtag';
		return $vars;
	}

	public function handle_archive_page(): void {
		$slug = get_query_var( 'arshid6social_eng_hashtag' );
		if ( ! $slug ) {
			return;
		}

		$slug    = $this->normalize( $slug );
		$hashtag = $this->get_by_slug( $slug );

		if ( ! $hashtag ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		global $arshid6social_is_page, $post, $wp_query;
		$arshid6social_is_page = true;

		// Make the theme (including Elementor/page-builders) render their full
		// layout by pretending we are on the Activity page.
		$activity_page_id = (int) get_option( 'arshid6social_page_activity', 0 );
		if ( $activity_page_id ) {
			$activity_post = get_post( $activity_page_id );
			if ( $activity_post instanceof \WP_Post ) {
				$post = $activity_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				$wp_query->queried_object    = $post;
				$wp_query->queried_object_id = $post->ID;
				$wp_query->is_page           = true;
				$wp_query->is_singular       = true;
				$wp_query->is_archive        = false;
				$wp_query->is_home           = false;
				$wp_query->is_404            = false;
				$wp_query->post              = $post;
				$wp_query->posts             = array( $post );
				$wp_query->found_posts       = 1;
				$wp_query->post_count        = 1;
				$wp_query->max_num_pages     = 1;
				setup_postdata( $post );
			}
		}

		$hashtag_ref = $hashtag;
		$feature_ref = $this;
		$loader      = \Arshid6Social\Template_Loader::instance();

		// Inject hashtag archive HTML via the_content so the theme wraps it
		// in its normal page layout (nav, sidebar, footer, etc.).
		add_filter(
			'the_content',
			static function () use ( $loader, $hashtag_ref, $feature_ref ): string {
				return $loader->get_template(
					'engagement/hashtag-archive.php',
					array(
						'hashtag' => $hashtag_ref,
						'feature' => $feature_ref,
					),
					true
				);
			},
			99
		);

		// Prevent the activity-page shortcode from rendering alongside our content.
		remove_shortcode( 'arshid6social_activity' );
	}

	/**
	 * Loads the first page of activity rows for a hashtag archive page.
	 *
	 * @return object[]
	 */
	private function load_activities_for_archive( object $hashtag, int $per_page = 20 ): array {
		global $wpdb;

		$ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT object_id FROM {$wpdb->prefix}sn_hashtag_relations
			WHERE hashtag_id = %d AND object_type = 'activity'
			ORDER BY created_at DESC LIMIT %d",
			$hashtag->id, $per_page
		) );

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				"SELECT * FROM {$wpdb->prefix}sn_activity WHERE id IN ($placeholders) AND is_spam = 0 ORDER BY date_recorded DESC",
				...$ids
			)
		) ?: array();
	}

	// ── Processing ────────────────────────────────────────────────────────────

	/**
	 * Extracts hashtags from the activity content and stores them.
	 */
	public function process_activity( int $activity_id, array $args ): void {
		$content = wp_strip_all_tags( $args['content'] ?? '' );
		if ( ! $content ) {
			return;
		}
		$this->store_hashtags( $activity_id, 'activity', $content );
	}

	/**
	 * Extracts, normalizes, and persists hashtags for an object.
	 */
	public function store_hashtags( int $object_id, string $object_type, string $content ): void {
		$tags = $this->extract( $content );
		if ( empty( $tags ) ) {
			return;
		}

		global $wpdb;

		// Remove stale relations for this object first.
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_hashtag_relations',
			array( 'object_id' => $object_id, 'object_type' => $object_type ),
			array( '%d', '%s' )
		);

		foreach ( $tags as $tag ) {
			$banned = array_filter( array_map( 'trim', explode( "\n", strtolower( (string) get_option( 'arshid6social_eng_hashtag_banned', '' ) ) ) ) );
			if ( in_array( $tag, $banned, true ) ) {
				continue;
			}

			$hashtag_id = $this->get_or_create( $tag );
			if ( ! $hashtag_id ) {
				continue;
			}

			// Avoid duplicate relations.
			$exists = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM {$wpdb->prefix}sn_hashtag_relations WHERE hashtag_id = %d AND object_id = %d AND object_type = %s",
				$hashtag_id, $object_id, $object_type
			) );

			if ( ! $exists ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'sn_hashtag_relations',
					array(
						'hashtag_id'  => $hashtag_id,
						'object_id'   => $object_id,
						'object_type' => $object_type,
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s', '%s' )
				);

				// Notify followers of this hashtag.
				$this->notify_followers( $hashtag_id, $object_id, $object_type );
			}
		}
	}

	/**
	 * Returns unique normalized hashtag slugs from content.
	 *
	 * @return string[]
	 */
	public function extract( string $content ): array {
		preg_match_all( self::REGEX, $content, $matches );
		if ( empty( $matches[1] ) ) {
			return array();
		}
		return array_unique( array_map( array( $this, 'normalize' ), $matches[1] ) );
	}

	/**
	 * Normalizes a raw hashtag to a slug.
	 */
	public function normalize( string $tag ): string {
		return mb_strtolower( $tag, 'UTF-8' );
	}

	/**
	 * Gets an existing hashtag ID or creates a new record.
	 */
	private function get_or_create( string $slug ): int|false {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}sn_hashtags WHERE slug = %s",
			$slug
		) );

		if ( $row ) {
			return (int) $row->id;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_hashtags',
			array(
				'hashtag'    => '#' . $slug,
				'slug'       => $slug,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	public function get_by_slug( string $slug ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT h.*,
				( SELECT COUNT(*) FROM {$wpdb->prefix}sn_hashtag_relations r
				  WHERE r.hashtag_id = h.id AND r.object_type = 'activity' ) AS post_count
			FROM {$wpdb->prefix}sn_hashtags h
			WHERE h.slug = %s",
			$slug
		) );
	}

	/**
	 * Removes all hashtag relations when an activity is deleted.
	 */
	public function delete_relations( int $activity_id ): void {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_hashtag_relations',
			array( 'object_id' => $activity_id, 'object_type' => 'activity' ),
			array( '%d', '%s' )
		);
	}

	// ── Trending ──────────────────────────────────────────────────────────────

	/**
	 * Returns trending hashtags (cached).
	 *
	 * @param string $period '24h' | '7d'
	 * @param int    $limit
	 * @return array<int, array<string,mixed>>
	 */
	public function get_trending( string $period = '24h', int $limit = 10 ): array {
		$cache_key = 'arshid6social_trending_tags_' . $period . '_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$interval = '24h' === $period ? '1 DAY' : '7 DAY';
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT h.slug, h.hashtag, COUNT(r.id) AS use_count
			FROM {$wpdb->prefix}sn_hashtag_relations r
			JOIN {$wpdb->prefix}sn_hashtags h ON h.id = r.hashtag_id
			WHERE r.created_at >= NOW() - INTERVAL {$interval}
			GROUP BY h.id
			ORDER BY use_count DESC
			LIMIT %d",
			$limit
		), ARRAY_A );

		$rows = $rows ?: array();
		set_transient( $cache_key, $rows, HOUR_IN_SECONDS );
		return $rows;
	}

	public function refresh_trending_cache(): void {
		delete_transient( 'arshid6social_trending_tags_24h_10' );
		delete_transient( 'arshid6social_trending_tags_7d_10' );
		$this->get_trending( '24h' );
		$this->get_trending( '7d' );
	}

	// ── Linkify ───────────────────────────────────────────────────────────────

	/**
	 * Converts #hashtags in content to anchor links.
	 */
	public function linkify( string $content ): string {
		$tag_base = sanitize_title( get_option( 'arshid6social_permalink_tag_base', 'hashtags' ) );
		return preg_replace_callback(
			self::REGEX,
			function ( array $m ) use ( $tag_base ): string {
				$slug = $this->normalize( $m[1] );
				$url  = esc_url( home_url( '/' . $tag_base . '/' . $slug . '/' ) );
				return '<a href="' . $url . '" class="arshid6social-hashtag-link">' . esc_html( $m[0] ) . '</a>';
			},
			$content
		) ?? $content;
	}

	// ── Follow ────────────────────────────────────────────────────────────────

	public function ajax_follow(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$hashtag_id = absint( $_POST['hashtag_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $hashtag_id ) {
			wp_send_json_error( null, 400 );
		}

		global $wpdb;
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_hashtag_follows',
			array( 'hashtag_id' => $hashtag_id, 'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) ),
			array( '%d', '%d', '%s' )
		);

		wp_send_json_success( array( 'following' => true ) );
	}

	public function ajax_unfollow(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$hashtag_id = absint( $_POST['hashtag_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_hashtag_follows',
			array( 'hashtag_id' => $hashtag_id, 'user_id' => get_current_user_id() ),
			array( '%d', '%d' )
		);

		wp_send_json_success( array( 'following' => false ) );
	}

	public function is_followed( int $hashtag_id, int $user_id ): bool {
		if ( ! $hashtag_id || ! $user_id ) {
			return false;
		}
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_hashtag_follows WHERE hashtag_id = %d AND user_id = %d",
			$hashtag_id, $user_id
		) );
	}

	// ── Autocomplete ──────────────────────────────────────────────────────────

	public function ajax_autocomplete(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}

		$q = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( strlen( $q ) < 1 ) {
			wp_send_json_success( array() );
			return;
		}

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT h.slug, h.hashtag, COUNT(r.id) AS use_count
			FROM {$wpdb->prefix}sn_hashtags h
			LEFT JOIN {$wpdb->prefix}sn_hashtag_relations r ON r.hashtag_id = h.id
			WHERE h.slug LIKE %s
			GROUP BY h.id
			ORDER BY use_count DESC
			LIMIT 10",
			$wpdb->esc_like( mb_strtolower( $q, 'UTF-8' ) ) . '%'
		), ARRAY_A );

		wp_send_json_success( $rows ?: array() );
	}

	// ── Notifications ─────────────────────────────────────────────────────────

	private function notify_followers( int $hashtag_id, int $object_id, string $object_type ): void {
		if ( 'activity' !== $object_type ) {
			return;
		}

		global $wpdb;
		$followers = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_hashtag_follows WHERE hashtag_id = %d",
			$hashtag_id
		) );

		$notif_component = ARSHID6SOCIAL()->component( 'notifications' );
		if ( ! $notif_component ) {
			return;
		}

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT user_id FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$object_id
		) );
		$poster_id = $row ? (int) $row->user_id : 0;

		foreach ( $followers as $user_id ) {
			$notif_component->add( array(
				'user_id'           => (int) $user_id,
				'item_id'           => $poster_id,
				'secondary_item_id' => $object_id,
				'component_name'    => 'hashtags',
				'component_action'  => 'hashtag_new_post',
				'sender_id'         => $poster_id,
			) );
		}
	}

	// ── Shortcode ─────────────────────────────────────────────────────────────

	public function shortcode_trending( array $atts ): string {
		$atts   = shortcode_atts( array( 'period' => '24h', 'limit' => 10 ), $atts );
		$tags   = $this->get_trending( $atts['period'], (int) $atts['limit'] );
		if ( empty( $tags ) ) {
			return '<p class="arshid6social-no-trending">' . esc_html__( 'No trending hashtags yet.', '6arshid-social-community' ) . '</p>';
		}

		$tag_base = sanitize_title( get_option( 'arshid6social_permalink_tag_base', 'hashtags' ) );
		$html     = '<ul class="arshid6social-trending-hashtags">';
		foreach ( $tags as $tag ) {
			$url   = esc_url( home_url( '/' . $tag_base . '/' . $tag['slug'] . '/' ) );
			$label = esc_html( $tag['hashtag'] );
			$count = (int) $tag['use_count'];
			$html .= '<li><a href="' . $url . '">' . $label . '</a> <span class="arshid6social-tag-count">(' . $count . ')</span></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		( new Hashtags_REST() )->register_routes();
	}

	/**
	 * Returns paginated activity IDs for a hashtag slug.
	 */
	public function get_activity_for_tag( string $slug, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$hashtag = $this->get_by_slug( $slug );
		if ( ! $hashtag ) {
			return array( 'ids' => array(), 'total' => 0 );
		}

		$offset = ( $page - 1 ) * $per_page;
		$total  = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_hashtag_relations WHERE hashtag_id = %d AND object_type = 'activity'",
			$hashtag->id
		) );

		$ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT object_id FROM {$wpdb->prefix}sn_hashtag_relations
			WHERE hashtag_id = %d AND object_type = 'activity'
			ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$hashtag->id, $per_page, $offset
		) );

		return array( 'ids' => array_map( 'intval', $ids ), 'total' => $total );
	}
}
