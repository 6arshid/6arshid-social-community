<?php
namespace Arshid6Social\Components\Search;

/**
 * Search REST endpoint registration and handler.
 *
 * Endpoint: GET /arshid6social/v1/search
 *   q        — search query
 *   section  — all | activity | members | groups | marketplace
 *   page     — page number
 *
 * @package Arshid6Social\Components\Search
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Search_REST
 */
class Search_REST {

	private const EMPTY = array( 'items' => array(), 'total' => 0, 'total_pages' => 0 );

	public function register_routes() {
		register_rest_route(
			'arshid6social/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'       => array( 'type' => 'string',  'default' => '',    'sanitize_callback' => 'sanitize_text_field' ),
					'section' => array( 'type' => 'string',  'default' => 'all', 'sanitize_callback' => 'sanitize_key' ),
					'page'    => array( 'type' => 'integer', 'default' => 1,     'minimum' => 1, 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	public function handle( $request ) {
		$q    = trim( (string) $request->get_param( 'q' ) );
		$sec  = (string) $request->get_param( 'section' );
		$page = max( 1, (int) $request->get_param( 'page' ) );

		$valid = array( 'all', 'activity', 'members', 'groups', 'marketplace' );
		if ( ! in_array( $sec, $valid, true ) ) {
			$sec = 'all';
		}

		if ( '' === $q ) {
			return rest_ensure_response( array( 'query' => '', 'section' => $sec, 'page' => 1, 'results' => array() ) );
		}

		$n_sec  = max( 1, (int) get_option( 'arshid6social_search_results_per_section', 5 ) );
		$n_page = max( 1, (int) get_option( 'arshid6social_search_per_page', 10 ) );

		$results = array();

		if ( 'all' === $sec ) {
			$results['activity']    = $this->run( 'activity',    $q, 1, $n_sec );
			$results['members']     = $this->run( 'members',     $q, 1, $n_sec );
			$results['groups']      = $this->run( 'groups',      $q, 1, $n_sec );
			$results['marketplace'] = $this->run( 'marketplace', $q, 1, $n_sec );
		} else {
			$results[ $sec ] = $this->run( $sec, $q, $page, $n_page );
		}

		return rest_ensure_response( array(
			'query'   => $q,
			'section' => $sec,
			'page'    => $page,
			'results' => $results,
		) );
	}

	/** Run one section, return EMPTY on any failure. */
	private function run( $section, $q, $page, $per_page ) {
		try {
			switch ( $section ) {
				case 'activity':    return $this->activity( $q, $page, $per_page );
				case 'members':     return $this->members( $q, $page, $per_page );
				case 'groups':      return $this->groups( $q, $page, $per_page );
				case 'marketplace': return $this->marketplace( $q, $page, $per_page );
				default:            return self::EMPTY;
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[arshid6social-search] ' . $section . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			return self::EMPTY;
		}
	}

	/** Search activity posts. */
	private function activity( $q, $page, $per_page ) {
		global $wpdb;

		$like            = '%' . $wpdb->esc_like( $q ) . '%';
		$offset          = ( $page - 1 ) * $per_page;
		$current_user_id = get_current_user_id();

		// Build privacy clause: guests see only public; logged-in users also see their own posts.
		if ( $current_user_id ) {
			$privacy_clause = $wpdb->prepare(
				"AND (privacy = 'public' OR user_id = %d)",
				$current_user_id
			);
		} else {
			$privacy_clause = "AND privacy = 'public'";
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity
			 WHERE is_spam = 0 AND item_id = 0 AND type != 'activity_comment' AND content LIKE %s
			 $privacy_clause",
			$like
		) );

		if ( ! $total ) {
			return self::EMPTY;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, user_id, content, date_recorded, primary_link
			 FROM {$wpdb->prefix}sn_activity
			 WHERE is_spam = 0 AND item_id = 0 AND type != 'activity_comment' AND content LIKE %s
			 $privacy_clause
			 ORDER BY date_recorded DESC
			 LIMIT %d OFFSET %d",
			$like,
			(int) $per_page,
			(int) $offset
		) );
		// phpcs:enable

		if ( ! $rows ) {
			return self::EMPTY;
		}

		$items = array();
		foreach ( $rows as $row ) {
			$user  = get_userdata( (int) $row->user_id );
			$name  = $user ? $user->display_name  : '';
			$slug  = $user ? $user->user_nicename : '';
			$link  = ! empty( $row->primary_link ) ? $row->primary_link : home_url( '/activity/' . $row->id . '/' );

			$items[] = array(
				'id'             => (int) $row->id,
				'content'        => (string) apply_filters( 'arshid6social_activity_content', wp_kses_post( $row->content ) ),
				'userName'       => $name,
				'userProfileUrl' => $slug ? home_url( '/members/' . $slug . '/' ) : '',
				'userAvatarUrl'  => get_avatar_url( (int) $row->user_id ) ?: '',
				'permalink'      => $link,
				'dateRecorded'   => $row->date_recorded,
			);
		}

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/** Search members. */
	private function members( $q, $page, $per_page ) {
		$c = ARSHID6SOCIAL()->component( 'members' );
		if ( ! $c ) {
			return self::EMPTY;
		}
		$d = $c->get_members( array( 'page' => $page, 'number' => $per_page, 'search' => $q ) );
		return array(
			'items'       => $d['members'] ?? array(),
			'total'       => (int) ( $d['total'] ?? 0 ),
			'total_pages' => (int) ( $d['total_pages'] ?? 0 ),
		);
	}

	/** Search groups. */
	private function groups( $q, $page, $per_page ) {
		$c = ARSHID6SOCIAL()->component( 'groups' );
		if ( ! $c ) {
			return self::EMPTY;
		}
		$d = $c->get_groups( array( 'page' => $page, 'number' => $per_page, 'search' => $q ) );
		return array(
			'items'       => $d['groups'] ?? array(),
			'total'       => (int) ( $d['total'] ?? 0 ),
			'total_pages' => (int) ( $d['total_pages'] ?? 0 ),
		);
	}

	/** Search marketplace listings. */
	private function marketplace( $q, $page, $per_page ) {
		if ( ! get_option( 'arshid6social_marketplace_enabled', false ) ) {
			return self::EMPTY;
		}
		global $wpdb;

		$like   = '%' . $wpdb->esc_like( $q ) . '%';
		$offset = ( $page - 1 ) * $per_page;
		$table  = $wpdb->prefix . 'arshid6social_listings';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `$table` WHERE status = 'active' AND (title LIKE %s OR description LIKE %s)",
			$like, $like
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, uid, title, price, is_free, location_city FROM `$table`
			 WHERE status = 'active' AND (title LIKE %s OR description LIKE %s)
			 ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$like, $like, (int) $per_page, (int) $offset
		) );
		// phpcs:enable

		$base = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) )
			?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = array(
				'id'              => (int) $row->id,
				'title'           => $row->title,
				'price_formatted' => $row->is_free ? __( 'Free', '6arshid-social-community-main' ) : number_format( (float) $row->price, 2 ),
				'is_free'         => (bool) $row->is_free,
				'location_city'   => $row->location_city ?? '',
				'url'             => add_query_arg( array( 'action' => 'view', 'id' => ( $row->uid ?: $row->id ) ), $base ),
			);
		}

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}
}
