<?php
namespace Arshid6Social\Components\Activity;

/**
 * Activity Streams component.
 *
 * @package Arshid6Social\Components\Activity
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Activity
 *
 * Handles creating, querying, deleting, and reacting to activity items.
 * Supports @mentions, #hashtags, privacy levels, and Akismet spam checks.
 */
class Activity {

	public function __construct() {
		$this->hooks();
	}

	private function hooks(): void {
		add_action( 'wp_ajax_arshid6social_post_activity', array( $this, 'ajax_post_activity' ) );
		add_action( 'wp_ajax_arshid6social_get_activity', array( $this, 'ajax_get_activity' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_get_activity', array( $this, 'ajax_get_activity' ) );
		add_action( 'wp_ajax_arshid6social_delete_activity', array( $this, 'ajax_delete_activity' ) );
		add_action( 'wp_ajax_arshid6social_edit_activity',   array( $this, 'ajax_edit_activity' ) );
		add_action( 'wp_ajax_arshid6social_delete_activity_media', array( $this, 'ajax_delete_activity_media' ) );
		add_action( 'wp_ajax_arshid6social_react_activity', array( $this, 'ajax_react_activity' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_react_activity', array( $this, 'ajax_react_activity_nopriv' ) );
		add_action( 'wp_ajax_arshid6social_report_activity', array( $this, 'ajax_report_activity' ) );
		add_action( 'wp_ajax_arshid6social_post_comment', array( $this, 'ajax_post_comment' ) );
		add_action( 'wp_ajax_arshid6social_get_comments', array( $this, 'ajax_get_comments' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_get_comments', array( $this, 'ajax_get_comments' ) );

		// Hashtag archive + single activity rewrite.
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_hashtag_page' ) );
		add_action( 'template_redirect', array( $this, 'handle_single_activity_page' ) );

		// Sitemap.
		add_action( 'init', array( $this, 'register_sitemap_provider' ) );
	}

	public function add_rewrite_rules(): void {
		$activity_base = sanitize_title( get_option( 'arshid6social_permalink_activity_base', 'activity' ) );
		add_rewrite_rule( '^hashtag/([^/]+)/?$', 'index.php?arshid6social_hashtag=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $activity_base . '/([0-9]+)/?$', 'index.php?arshid6social_activity_id=$matches[1]', 'top' );
		add_rewrite_rule( '^' . $activity_base . '/([a-f0-9]{10,23})/?$', 'index.php?arshid6social_activity_uid=$matches[1]', 'top' );
	}

	public function register_query_vars( array $vars ): array {
		$vars[] = 'arshid6social_hashtag';
		$vars[] = 'arshid6social_activity';
		$vars[] = 'arshid6social_activity_id';
		$vars[] = 'arshid6social_activity_uid';
		return $vars;
	}

	/** @var array<int,string> Static uid cache to avoid repeated DB hits per request. */
	private static array $uid_cache = array();

	/**
	 * Returns the canonical permalink for a single activity item.
	 * Uses a unique ID slug when arshid6social_activity_uid_enabled is on.
	 */
	public static function get_permalink( int $activity_id ): string {
		$base = sanitize_title( get_option( 'arshid6social_permalink_activity_base', 'activity' ) );
		if ( get_option( 'arshid6social_activity_uid_enabled' ) ) {
			$uid = self::get_uid_by_id( $activity_id );
			if ( '' !== $uid ) {
				return home_url( '/' . $base . '/' . $uid . '/' );
			}
		}
		return home_url( '/' . $base . '/' . $activity_id . '/' );
	}

	/**
	 * Returns the uid for an activity, generating and persisting one if missing.
	 */
	public static function get_uid_by_id( int $activity_id ): string {
		if ( isset( self::$uid_cache[ $activity_id ] ) ) {
			return self::$uid_cache[ $activity_id ];
		}
		global $wpdb;
		$uid = (string) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT uid FROM {$wpdb->prefix}sn_activity WHERE id = %d",
			$activity_id
		) );
		if ( '' === $uid ) {
			$uid = uniqid();
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_activity',
				array( 'uid' => $uid ),
				array( 'id'  => $activity_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
		self::$uid_cache[ $activity_id ] = $uid;
		return $uid;
	}

	/**
	 * Returns a single activity item by its unique ID slug.
	 */
	public function get_by_uid( string $uid ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_activity WHERE uid = %s",
			$uid
		) );
	}

	public function handle_single_activity_page(): void {
		$uid         = (string) get_query_var( 'arshid6social_activity_uid' );
		$activity_id = (int) get_query_var( 'arshid6social_activity_id' );

		if ( '' !== $uid ) {
			$activity = $this->get_by_uid( $uid );
		} elseif ( $activity_id ) {
			$activity = $this->get_by_id( $activity_id );
		} else {
			return;
		}

		$activity_base = sanitize_title( get_option( 'arshid6social_permalink_activity_base', 'activity' ) );

		if ( ! $activity || $activity->is_spam ) {
			wp_redirect( home_url( '/' . $activity_base . '/' ) );
			exit;
		}

		$activity_id = (int) $activity->id;

		// Privacy: non-public posts only visible to the owner and admins.
		if ( 'public' !== $activity->privacy ) {
			$current = get_current_user_id();
			if ( ! $current || ( $current !== (int) $activity->user_id && ! current_user_can( 'arshid6social_manage_activity' ) ) ) {
				wp_safe_redirect( wp_login_url( self::get_permalink( $activity_id ) ) );
				exit;
			}
		}

		global $arshid6social_is_page, $post, $wp_query;
		$arshid6social_is_page = true;

		// Point the WP query at the activity page so the theme renders the right sidebar, title, etc.
		$page_id = (int) get_option( 'arshid6social_page_activity', 0 );
		if ( $page_id ) {
			$post = get_post( $page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $post ) {
				$wp_query->queried_object    = $post;
				$wp_query->queried_object_id = $page_id;
				$wp_query->is_singular       = true;
				$wp_query->is_page           = true;
				$wp_query->is_home           = false;
				$wp_query->is_archive        = false;
				setup_postdata( $post );
			}
		}

		$formatted = $this->format_activity( $activity );

		// Replace page content with the single activity view.
		add_filter(
			'the_content',
			function() use ( $formatted ) {
				return \Arshid6Social\Template_Loader::instance()->get_template(
					'activity/single.php',
					array( 'activity' => $formatted ),
					true
				);
			},
			1
		);

		// Override the page <title> to reflect the post author.
		add_filter(
			'document_title_parts',
			function( $parts ) use ( $formatted ) {
				$author  = $formatted['userName'] ?? '';
				$excerpt = wp_trim_words( wp_strip_all_tags( $formatted['content'] ?? '' ), 10 );
				$parts['title'] = $author
					? sprintf( '%s — %s', $author, $excerpt ?: __( 'Activity', '6arshid-social-community-main' ) )
					: $excerpt;
				return $parts;
			}
		);

		// Suppress the theme's H1 page title on single activity pages.
		// in_the_loop() limits this to the main template, not nav menus / breadcrumbs.
		add_filter( 'the_title', function( $title ) {
			return in_the_loop() ? '' : $title;
		}, 1 );
		add_filter( 'single_post_title', '__return_empty_string', 1 );

		// Canonical + OG tags.
		add_action(
			'wp_head',
			function() use ( $formatted ) {
				$url    = $formatted['permalink'];
				$desc   = wp_trim_words( wp_strip_all_tags( $formatted['content'] ?? '' ), 30 );
				$author = $formatted['userName'] ?? '';
				$date   = $formatted['dateRecorded'] ?? '';

				$image = '';
				foreach ( $formatted['media'] ?? array() as $m ) {
					if ( ! empty( $m['fileUrl'] ) ) { $image = $m['fileUrl']; break; }
				}
				if ( ! $image && ! empty( $formatted['userAvatarUrl'] ) ) {
					$image = $formatted['userAvatarUrl'];
				}

				echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
				echo '<meta property="og:type" content="article" />' . "\n";
				echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
				echo '<meta property="og:title" content="' . esc_attr( $author ) . '" />' . "\n";
				echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
				if ( $image ) echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
				if ( $date )  echo '<meta property="article:published_time" content="' . esc_attr( gmdate( 'c', strtotime( $date ) ) ) . '" />' . "\n";
			}
		);

		// Do NOT exit — let WordPress finish loading the theme.
	}

	/**
	 * Registers the activity sitemap provider.
	 */
	public function register_sitemap_provider(): void {
		if ( function_exists( 'wp_sitemaps_add_provider' ) ) {
			wp_sitemaps_add_provider( 'arshid6social_activity', new Activity_Sitemap_Provider() );
		}
	}

	public function handle_hashtag_page(): void {
		$hashtag = get_query_var( 'arshid6social_hashtag' );
		if ( ! $hashtag ) {
			return;
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		\Arshid6Social\Template_Loader::instance()->get_template(
			'activity/hashtag.php',
			array( 'hashtag' => sanitize_text_field( $hashtag ) )
		);
		exit;
	}

	/**
	 * AJAX: Posts a new activity item.
	 */
	public function ajax_post_activity(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid-social-community-main' ) ), 401 );
		}

		$user_id = get_current_user_id();

		// Check suspension.
		if ( get_user_meta( $user_id, 'arshid6social_suspended', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account has been suspended.', '6arshid-social-community-main' ) ), 403 );
		}

		$content   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$privacy   = isset( $_POST['privacy'] ) ? sanitize_key( wp_unslash( $_POST['privacy'] ) ) : 'public'; // phpcs:ignore WordPress.Security.NonceVerification
		$type      = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'activity_update'; // phpcs:ignore WordPress.Security.NonceVerification
		$group_id  = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		// PPV price: submitted as dollars (e.g. "10.00"), stored as integer cents.
		$ppv_price_cents = 0;
		if ( 'paid' === $privacy && isset( $_POST['ppv_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$ppv_price_cents = (int) round( (float) sanitize_text_field( wp_unslash( $_POST['ppv_price'] ) ) * 100 ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( $ppv_price_cents < 50 ) { // Minimum 50 cents (Stripe minimum)
				$ppv_price_cents = 50;
			}
		}
		$has_media = get_option( 'arshid6social_activity_allow_media', false ) && ! empty( $_FILES['media_files']['name'] );

		if ( empty( trim( wp_strip_all_tags( $content ) ) ) && ! $has_media ) {
			wp_send_json_error( array( 'message' => __( 'Activity content cannot be empty.', '6arshid-social-community-main' ) ), 400 );
		}

		$allowed_privacy = array( 'public', 'friends', 'private', 'paid' );
		if ( ! in_array( $privacy, $allowed_privacy, true ) ) {
			$privacy = 'public';
		}

		// Banned word filter.
		if ( $this->contains_banned_word( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Your post contains prohibited content.', '6arshid-social-community-main' ) ), 422 );
		}

		// Akismet check.
		if ( get_option( 'arshid6social_enable_akismet' ) && $this->is_spam( $user_id, $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Your post was identified as spam.', '6arshid-social-community-main' ) ), 422 );
		}

		$activity_id = $this->add(
			array(
				'user_id'       => $user_id,
				'type'          => $type,
				'component'     => 'activity',
				'content'       => $content,
				'privacy'       => $privacy,
				'item_id'       => $group_id,
				'ppv_price'     => $ppv_price_cents,
			)
		);

		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to post activity.', '6arshid-social-community-main' ) ), 500 );
		}

		// Process @mentions.
		$this->process_mentions( $activity_id, $content, $user_id );

		// Process #hashtags.
		$this->process_hashtags( $activity_id, $content );

		// Handle media uploads.
		if ( $has_media ) {
			$this->handle_media_upload( $activity_id, $_FILES['media_files'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		wp_send_json_success(
			array(
				'activity' => $this->format_activity( $this->get_by_id( $activity_id ) ),
				'message'  => __( 'Activity posted.', '6arshid-social-community-main' ),
			)
		);
	}

	/**
	 * Inserts a new activity item into the database.
	 *
	 * @param array<string, mixed> $args Activity data.
	 * @return int|false Inserted activity ID or false on failure.
	 */
	public function add( array $args ): int|false {
		global $wpdb;

		$defaults = array(
			'user_id'           => get_current_user_id(),
			'component'         => 'activity',
			'type'              => 'activity_update',
			'action'            => '',
			'content'           => '',
			'primary_link'      => '',
			'item_id'           => 0,
			'secondary_item_id' => 0,
			'date_recorded'     => current_time( 'mysql' ),
			'hide_sitewide'     => 0,
			'is_spam'           => 0,
			'privacy'           => 'public',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build action string if not provided.
		if ( empty( $args['action'] ) ) {
			$user            = get_userdata( $args['user_id'] );
			$args['action']  = sprintf(
				/* translators: %s: member display name with profile link */
				__( '%s posted an update', '6arshid-social-community-main' ),
				'<a href="' . esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ) . '">' . esc_html( $user->display_name ) . '</a>'
			);
		}

		// primary_link is set after insert once we have the ID.

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_activity',
			array(
				'user_id'           => absint( $args['user_id'] ),
				'component'         => sanitize_key( $args['component'] ),
				'type'              => sanitize_key( $args['type'] ),
				'action'            => wp_kses_post( $args['action'] ),
				'content'           => wp_kses_post( $args['content'] ),
				'primary_link'      => esc_url_raw( $args['primary_link'] ),
				'item_id'           => absint( $args['item_id'] ),
				'secondary_item_id' => absint( $args['secondary_item_id'] ),
				'date_recorded'     => $args['date_recorded'],
				'hide_sitewide'     => absint( $args['hide_sitewide'] ),
				'is_spam'           => absint( $args['is_spam'] ),
				'privacy'           => sanitize_key( $args['privacy'] ),
				'uid'               => uniqid(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$activity_id = (int) $wpdb->insert_id;

		// Now that we have the ID, set the canonical permalink as primary_link.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_activity',
			array( 'primary_link' => self::get_permalink( $activity_id ) ),
			array( 'id' => $activity_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Save PPV price meta when privacy = 'paid' and a price was supplied.
		$ppv_price = (int) ( $args['ppv_price'] ?? 0 );
		if ( 'paid' === $args['privacy'] && $ppv_price > 0 ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_activity_meta',
				array(
					'activity_id' => $activity_id,
					'meta_key'    => '_sixarshidsc_ppv_price',
					'meta_value'  => $ppv_price,
				),
				array( '%d', '%s', '%d' )
			);
		}

		do_action( 'arshid6social_activity_added', $activity_id, $args );

		return $activity_id;
	}

	/**
	 * Returns a single activity item by ID.
	 *
	 * Result is stored in the object cache for the duration of the request
	 * (expiry 0 = no timeout in persistent caches, evicted on next cache clear).
	 *
	 * @param int $activity_id Activity ID.
	 * @return object|null
	 */
	public function get_by_id( int $activity_id ): ?object {
		$cache_key = "activity_{$activity_id}";
		$found     = false;
		$cached    = \Arshid6Social\Cache::get( $cache_key, $found );
		if ( $found ) {
			return $cached ?: null;
		}

		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_activity WHERE id = %d", $activity_id )
		);
		// Store null as false so we can distinguish "not cached" from "not found".
		\Arshid6Social\Cache::set( $cache_key, $row ?: false, 300 );
		return $row ?: null;
	}

	/**
	 * Invalidates the object-cache entry for a single activity item.
	 *
	 * Call after any update or delete so stale data is never served.
	 *
	 * @param int $activity_id Activity ID.
	 */
	public function invalidate_cache( int $activity_id ): void {
		\Arshid6Social\Cache::delete( "activity_{$activity_id}" );
		\Arshid6Social\Cache::delete( "comment_count_{$activity_id}" );
	}

	/**
	 * Formats a list of raw activity rows in a single pass using batched DB queries.
	 *
	 * Instead of running 6–8 queries per row (the N+1 pattern in format_activity()),
	 * this collects all IDs up-front and fetches reactions, media, comment counts,
	 * and view counts with one query each — regardless of page size.
	 *
	 * @param object[] $rows Raw DB rows from sn_activity.
	 * @return array<int, array<string, mixed>>
	 */
	private function format_activities_batch( array $rows ): array {
		if ( empty( $rows ) ) {
			return array();
		}

		global $wpdb;

		$ids      = array_map( static fn( $r ) => (int) $r->id, $rows );
		$user_ids = array_unique( array_map( static fn( $r ) => (int) $r->user_id, $rows ) );
		$ids_ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Pre-warm WP user cache for all authors in one shot.
		update_meta_cache( 'user', $user_ids );
		$users = array();
		foreach ( $user_ids as $uid ) {
			$users[ $uid ] = get_userdata( $uid );
		}

		// Batch: reactions grouped by (activity_id, reaction_type).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$raw_reactions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT activity_id, reaction_type, COUNT(*) AS count FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id IN ($ids_ph) GROUP BY activity_id, reaction_type", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$ids
			),
			ARRAY_A
		);
		$reactions_map    = array();
		$reaction_totals  = array();
		foreach ( (array) $raw_reactions as $r ) {
			$aid                      = (int) $r['activity_id'];
			$reactions_map[ $aid ][]  = $r;
			$reaction_totals[ $aid ]  = ( $reaction_totals[ $aid ] ?? 0 ) + (int) $r['count'];
		}

		// Batch: current viewer's own reactions.
		$user_reactions_map = array();
		if ( is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$own_reactions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT activity_id, reaction_type FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id IN ($ids_ph) AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...array_merge( $ids, array( $current_user_id ) )
				),
				ARRAY_A
			);
			foreach ( (array) $own_reactions as $r ) {
				$user_reactions_map[ (int) $r['activity_id'] ] = $r['reaction_type'];
			}
		}

		// Batch: media attachments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$raw_media = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, activity_id, media_type, file_url, file_name, mime_type FROM {$wpdb->prefix}sn_activity_media WHERE activity_id IN ($ids_ph) ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$ids
			),
			ARRAY_A
		);
		$media_map = array();
		foreach ( (array) $raw_media as $m ) {
			$media_map[ (int) $m['activity_id'] ][] = array(
				'id'        => (int) $m['id'],
				'mediaType' => esc_attr( $m['media_type'] ),
				'fileUrl'   => esc_url( $m['file_url'] ),
				'fileName'  => esc_html( $m['file_name'] ),
				'mimeType'  => esc_attr( $m['mime_type'] ),
			);
		}

		// Batch: top-level comment counts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$raw_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT item_id, COUNT(*) AS cnt FROM {$wpdb->prefix}sn_activity WHERE item_id IN ($ids_ph) AND type = 'activity_comment' AND secondary_item_id = 0 AND is_spam = 0 GROUP BY item_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$ids
			),
			ARRAY_A
		);
		$comment_counts = array();
		foreach ( (array) $raw_counts as $r ) {
			$comment_counts[ (int) $r['item_id'] ] = (int) $r['cnt'];
		}

		// Batch: view counts from activity_meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$raw_views = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT activity_id, meta_value FROM {$wpdb->prefix}sn_activity_meta WHERE activity_id IN ($ids_ph) AND meta_key = '_view_count'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$ids
			),
			ARRAY_A
		);
		$view_counts = array();
		foreach ( (array) $raw_views as $r ) {
			$view_counts[ (int) $r['activity_id'] ] = (int) $r['meta_value'];
		}

		$members_comp    = ARSHID6SOCIAL()->component( 'members' );
		$current_user_id = get_current_user_id();

		$results = array();
		foreach ( $rows as $activity ) {
			$id   = (int) $activity->id;
			$user = $users[ (int) $activity->user_id ] ?? null;

			$content = wp_kses_post( $activity->content );
			try {
				$content = (string) apply_filters( 'arshid6social_activity_content', $content, (string) $activity->type );
			} catch ( \Throwable $e ) {
				// Filter failed — use sanitised content as-is.
			}

			$formatted = array(
				'id'                  => $id,
				'userId'              => (int) $activity->user_id,
				'userName'            => $user ? esc_html( $user->display_name ) : '',
				'userProfileUrl'      => $user ? esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ) : '',
				'userAvatarUrl'       => $members_comp
					? esc_url( $members_comp->avatar->get_avatar_url( $activity->user_id ) )
					: get_avatar_url( $activity->user_id ),
				'type'                => esc_attr( $activity->type ),
				'component'           => esc_attr( $activity->component ),
				'action'              => wp_kses_post( $activity->action ),
				'content'             => $content,
				'privacy'             => esc_attr( $activity->privacy ),
				'dateRecorded'        => esc_attr( $activity->date_recorded ),
				'primaryLink'         => esc_url( $activity->primary_link ),
				'permalink'           => 'activity_comment' === $activity->type
					? esc_url( self::get_permalink( (int) $activity->item_id ) )
					: esc_url( self::get_permalink( $id ) ),
				'reactions'           => $reactions_map[ $id ] ?? array(),
				'currentUserReaction' => $user_reactions_map[ $id ] ?? null,
				'reactionCount'       => $reaction_totals[ $id ] ?? 0,
				'viewCount'           => $view_counts[ $id ] ?? 0,
				'commentCount'        => $comment_counts[ $id ] ?? 0,
				'media'               => $media_map[ $id ] ?? array(),
				'attachments'         => array(),
				'parentCommentId'     => 'activity_comment' === $activity->type ? (int) $activity->secondary_item_id : 0,
				'canDelete'           => $current_user_id && (
					(int) $activity->user_id === $current_user_id || current_user_can( 'arshid6social_manage_activity' )
				),
				'canEdit'             => $current_user_id && (int) $activity->user_id === $current_user_id,
			);

			try {
				$formatted = (array) apply_filters( 'arshid6social_format_activity', $formatted, $activity );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[WPSN] arshid6social_format_activity filter error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				}
			}

			$results[] = $formatted;
		}

		return $results;
	}

	/**
	 * Returns a paginated list of activity items.
	 *
	 * @param array<string, mixed> $args Query parameters.
	 * @return array<string, mixed>
	 */
	public function get_activity( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'page'         => 1,
			'per_page'     => (int) get_option( 'arshid6social_activity_per_page', 20 ),
			'user_id'      => 0,
			'component'    => '',
			'type'         => '',
			'privacy'      => array( 'public' ),
			'item_id'      => 0,
			'search'       => '',
			'scope'        => 'all', // all | personal | friends | group
			'hashtag_slug' => '',
		);
		$args = wp_parse_args( $args, $defaults );

		// Let engagement features (Sticky Posts) inject sticky IDs.
		$args       = (array) apply_filters( 'arshid6social_get_activity_args', $args );
		$sticky_ids = array_map( 'intval', $args['sticky_ids'] ?? array() );

		$where  = array( '1=1', 'a.is_spam = 0', "a.type != 'activity_comment'" );
		$values = array();

		if ( $args['user_id'] ) {
			$where[]  = 'a.user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( $args['component'] ) {
			$where[]  = 'a.component = %s';
			$values[] = sanitize_key( $args['component'] );
		}

		if ( $args['type'] ) {
			$where[]  = 'a.type = %s';
			$values[] = sanitize_key( $args['type'] );
		}

		if ( $args['item_id'] ) {
			$where[]  = 'a.item_id = %d';
			$values[] = absint( $args['item_id'] );
		} elseif ( empty( $args['hashtag_slug'] ) ) {
			// Exclude group posts from the global feed (not needed on hashtag feeds).
			$where[] = 'a.item_id = 0';
		}

		if ( ! empty( $args['hashtag_slug'] ) ) {
			$slug        = mb_strtolower( $args['hashtag_slug'], 'UTF-8' );
			$hashtag_row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id FROM {$wpdb->prefix}sn_hashtags WHERE slug = %s",
				$slug
			) );

			if ( $hashtag_row ) {
				$hashtag_activity_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					"SELECT object_id FROM {$wpdb->prefix}sn_hashtag_relations WHERE hashtag_id = %d AND object_type = 'activity'",
					$hashtag_row->id
				) ) );

				if ( empty( $hashtag_activity_ids ) ) {
					$where[] = '1=0';
				} else {
					$ph      = implode( ',', array_fill( 0, count( $hashtag_activity_ids ), '%d' ) );
					$where[] = "a.id IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					foreach ( $hashtag_activity_ids as $haid ) {
						$values[] = $haid;
					}
				}
			} else {
				$where[] = '1=0';
			}
		}

		if ( $args['search'] ) {
			$where[]  = 'a.content LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		// Exclude sticky IDs from the regular feed to avoid duplicates.
		if ( $sticky_ids ) {
			$ph      = implode( ',', array_fill( 0, count( $sticky_ids ), '%d' ) );
			$where[] = "a.id NOT IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $sticky_ids as $sid ) {
				$values[] = $sid;
			}
		}

		// Exclude specific user IDs (e.g. blocked or suspended users).
		$exclude_user_ids = array_filter( array_map( 'absint', (array) ( $args['exclude_user_ids'] ?? array() ) ) );
		if ( $exclude_user_ids ) {
			$ph      = implode( ',', array_fill( 0, count( $exclude_user_ids ), '%d' ) );
			$where[] = "a.user_id NOT IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $exclude_user_ids as $uid ) {
				$values[] = $uid;
			}
		}

		// Exclude activity belonging to suspended groups.
		$exclude_group_ids = array_filter( array_map( 'absint', (array) ( $args['exclude_group_ids'] ?? array() ) ) );
		if ( $exclude_group_ids ) {
			$ph      = implode( ',', array_fill( 0, count( $exclude_group_ids ), '%d' ) );
			$where[] = "NOT (a.component = 'groups' AND a.item_id IN ($ph))"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $exclude_group_ids as $gid ) {
				$values[] = $gid;
			}
		}

		// Follow scope: show only activity from users & hashtags the viewer follows.
		if ( 'follow' === $args['scope'] ) {
			if ( ! is_user_logged_in() ) {
				$where[] = '1=0';
			} else {
				$viewer_id = get_current_user_id();

				$followed_user_ids = array_map( 'intval', (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare( "SELECT followee_id FROM {$wpdb->prefix}sn_follow WHERE follower_id = %d", $viewer_id )
				) );

				$hashtag_activity_ids = array_map( 'intval', (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT DISTINCT hr.object_id
						 FROM {$wpdb->prefix}sn_hashtag_follows hf
						 INNER JOIN {$wpdb->prefix}sn_hashtag_relations hr ON hr.hashtag_id = hf.hashtag_id AND hr.object_type = 'activity'
						 WHERE hf.user_id = %d",
						$viewer_id
					)
				) );

				if ( empty( $followed_user_ids ) && empty( $hashtag_activity_ids ) ) {
					$where[] = '1=0';
				} else {
					$or_conds = array();
					if ( $followed_user_ids ) {
						$ph         = implode( ',', array_fill( 0, count( $followed_user_ids ), '%d' ) );
						$or_conds[] = "a.user_id IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						foreach ( $followed_user_ids as $fuid ) {
							$values[] = $fuid;
						}
					}
					if ( $hashtag_activity_ids ) {
						$ph         = implode( ',', array_fill( 0, count( $hashtag_activity_ids ), '%d' ) );
						$or_conds[] = "a.id IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						foreach ( $hashtag_activity_ids as $haid ) {
							$values[] = $haid;
						}
					}
					$where[] = '(' . implode( ' OR ', $or_conds ) . ')'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
		}

		// Privacy: guests only see public items.
		if ( ! is_user_logged_in() ) {
			$where[] = "a.privacy = 'public'";
		} else {
			$current_user_id = get_current_user_id();
			// User sees their own items regardless of privacy.
			if ( $args['user_id'] && (int) $args['user_id'] !== $current_user_id ) {
				$where[] = "a.privacy IN ('public','friends')";
			}
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $args['page'] - 1 ) * $args['per_page'];

		$sql = "SELECT SQL_CALC_FOUND_ROWS a.* FROM {$wpdb->prefix}sn_activity a WHERE {$where_sql} ORDER BY a.date_recorded DESC LIMIT %d OFFSET %d";

		$values[] = $args['per_page'];
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Batch-format the main feed (replaces per-row N+1 queries).
		$activity = $this->format_activities_batch( $rows );

		// Prepend sticky posts on the first page — also batch-loaded.
		if ( $sticky_ids && 1 === (int) $args['page'] ) {
			$sph          = implode( ',', array_fill( 0, count( $sticky_ids ), '%d' ) );
			$sticky_rows  = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_activity WHERE id IN ($sph) AND is_spam = 0", ...$sticky_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			$sticky_items = $this->format_activities_batch( $sticky_rows );
			foreach ( $sticky_items as &$item ) {
				$item['isSticky'] = true;
			}
			unset( $item );
			$activity = array_merge( $sticky_items, $activity );
		}

		return array(
			'activities'   => $activity,
			'total'        => $total,
			'total_pages'  => (int) ceil( $total / $args['per_page'] ),
			'current_page' => $args['page'],
		);
	}

	/**
	 * Formats a raw activity row into a frontend-ready array.
	 *
	 * @param object|null $activity Raw DB row.
	 * @return array<string, mixed>
	 */
	public function format_activity( ?object $activity ): array {
		if ( ! $activity ) {
			return array();
		}

		global $wpdb;

		$user         = get_userdata( $activity->user_id );
		$members_comp = ARSHID6SOCIAL()->component( 'members' );

		$reactions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT reaction_type, COUNT(*) as count FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id = %d GROUP BY reaction_type",
				$activity->id
			),
			ARRAY_A
		);

		$current_user_reaction = null;
		if ( is_user_logged_in() ) {
			$current_user_reaction = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT reaction_type FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id = %d AND user_id = %d",
					$activity->id,
					get_current_user_id()
				)
			);
		}

		$media_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, media_type, file_url, file_name, mime_type FROM {$wpdb->prefix}sn_activity_media WHERE activity_id = %d ORDER BY id ASC",
				$activity->id
			),
			ARRAY_A
		);

		$media = array_map(
			function ( array $m ): array {
				return array(
					'id'        => (int) $m['id'],
					'mediaType' => esc_attr( $m['media_type'] ),
					'fileUrl'   => esc_url( $m['file_url'] ),
					'fileName'  => esc_html( $m['file_name'] ),
					'mimeType'  => esc_attr( $m['mime_type'] ),
				);
			},
			$media_rows ?: array()
		);

		$content = wp_kses_post( $activity->content );
		try {
			$content = (string) apply_filters( 'arshid6social_activity_content', $content, (string) $activity->type );
		} catch ( \Throwable $e ) {
			// Content filter failed — use sanitised content as-is.
		}

		// For comments: include attachments via direct DB query.
		$comment_attachments = array();
		if ( 'activity_comment' === $activity->type ) {
			global $wpdb;
			$atts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT file_url, file_name, mime_type, media_type FROM {$wpdb->prefix}arshid6social_attachments WHERE parent_id = %d AND parent_type = 'comment' ORDER BY created_at ASC",
					(int) $activity->id
				),
				ARRAY_A
			);
			foreach ( $atts ?: array() as $att ) {
				$comment_attachments[] = array(
					'url'       => esc_url( $att['file_url'] ),
					'fileName'  => esc_html( $att['file_name'] ),
					'mediaType' => esc_attr( $att['media_type'] ),
					'mimeType'  => esc_attr( $att['mime_type'] ),
				);
			}
		}

		// Derive total from the already-loaded per-type breakdown — avoids a redundant COUNT query.
		$reaction_count = (int) array_sum( array_column( $reactions, 'count' ) );

		$view_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}sn_activity_meta WHERE activity_id = %d AND meta_key = '_view_count' LIMIT 1",
				$activity->id
			)
		);

		$formatted = array(
			'id'                  => (int) $activity->id,
			'userId'              => (int) $activity->user_id,
			'userName'            => $user ? esc_html( $user->display_name ) : '',
			'userProfileUrl'      => $user ? esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ) : '',
			'userAvatarUrl'       => $members_comp ? esc_url( $members_comp->avatar->get_avatar_url( $activity->user_id ) ) : get_avatar_url( $activity->user_id ),
			'type'                => esc_attr( $activity->type ),
			'component'           => esc_attr( $activity->component ),
			'action'              => wp_kses_post( $activity->action ),
			'content'             => $content,
			'privacy'             => esc_attr( $activity->privacy ),
			'dateRecorded'        => esc_attr( $activity->date_recorded ),
			'primaryLink'         => esc_url( $activity->primary_link ),
			'permalink'           => 'activity_comment' === $activity->type
				? esc_url( self::get_permalink( (int) $activity->item_id ) )
				: esc_url( self::get_permalink( (int) $activity->id ) ),
			'reactions'           => $reactions,
			'currentUserReaction' => $current_user_reaction,
			'reactionCount'       => $reaction_count,
			'viewCount'           => $view_count,
			'commentCount'        => $this->get_comment_count( (int) $activity->id ),
			'media'               => $media,
			'attachments'         => $comment_attachments,
			'parentCommentId'     => 'activity_comment' === $activity->type ? (int) $activity->secondary_item_id : 0,
			'canDelete'           => is_user_logged_in() && (
				(int) $activity->user_id === get_current_user_id() || current_user_can( 'arshid6social_manage_activity' )
			),
			'canEdit'             => is_user_logged_in() && (int) $activity->user_id === get_current_user_id(),
		);

		try {
			$formatted = (array) apply_filters( 'arshid6social_format_activity', $formatted, $activity );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[WPSN] arshid6social_format_activity filter error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}
		return $formatted;
	}

	/**
	 * Returns the number of comment-type children for an activity item.
	 *
	 * Cached in the object cache; invalidated when comments are added or deleted.
	 *
	 * @param int $activity_id Activity ID.
	 * @return int
	 */
	public function get_comment_count( int $activity_id ): int {
		$cache_key = "comment_count_{$activity_id}";
		return (int) \Arshid6Social\Cache::remember(
			$cache_key,
			static function () use ( $activity_id ): int {
				global $wpdb;
				return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity WHERE item_id = %d AND type = 'activity_comment' AND secondary_item_id = 0 AND is_spam = 0",
						$activity_id
					)
				);
			},
			300
		);
	}

	/**
	 * AJAX: Returns paginated activity as JSON.
	 */
	public function ajax_get_activity(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$data = $this->get_activity(
			array(
				'page'         => max( 1, absint( $_GET['page'] ?? 1 ) ),
				'per_page'     => max( 1, min( 100, absint( $_GET['per_page'] ?? (int) get_option( 'arshid6social_activity_per_page', 20 ) ) ) ),
				'user_id'      => absint( $_GET['user_id'] ?? 0 ),
				'scope'        => sanitize_key( $_GET['scope'] ?? 'all' ),
				'search'       => sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) ),
				'item_id'      => absint( $_GET['group_id'] ?? 0 ),
				'hashtag_slug' => sanitize_text_field( wp_unslash( $_GET['hashtag'] ?? '' ) ),
			)
		);
		// phpcs:enable

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Deletes an activity item (owner or admin only).
	 */
	public function ajax_delete_activity(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', '6arshid-social-community-main' ) ), 401 );
		}

		$activity_id = absint( $_POST['activity_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$activity    = $this->get_by_id( $activity_id );

		if ( ! $activity ) {
			wp_send_json_error( array( 'message' => __( 'Activity not found.', '6arshid-social-community-main' ) ), 404 );
		}

		if ( (int) $activity->user_id !== get_current_user_id() && ! current_user_can( 'arshid6social_manage_activity' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community-main' ) ), 403 );
		}

		$this->delete( $activity_id );

		wp_send_json_success( array( 'message' => __( 'Activity deleted.', '6arshid-social-community-main' ) ) );
	}

	/**
	 * AJAX: Edits an existing activity item (owner only).
	 */
	public function ajax_edit_activity(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', '6arshid-social-community-main' ) ), 401 );
		}

		$activity_id = absint( $_POST['activity_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$activity    = $this->get_by_id( $activity_id );

		if ( ! $activity ) {
			wp_send_json_error( array( 'message' => __( 'Activity not found.', '6arshid-social-community-main' ) ), 404 );
		}

		if ( (int) $activity->user_id !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community-main' ) ), 403 );
		}

		global $wpdb;

		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : $activity->content; // phpcs:ignore WordPress.Security.NonceVerification
		$privacy  = isset( $_POST['privacy'] ) ? sanitize_key( wp_unslash( $_POST['privacy'] ) ) : $activity->privacy; // phpcs:ignore WordPress.Security.NonceVerification

		$allowed_privacy = array( 'public', 'friends', 'private', 'paid' );
		if ( ! in_array( $privacy, $allowed_privacy, true ) ) {
			$privacy = 'public';
		}

		if ( empty( trim( wp_strip_all_tags( $content ) ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Activity content cannot be empty.', '6arshid-social-community-main' ) ), 400 );
		}

		// Delete requested media items.
		$delete_ids = array_filter( array_map( 'absint', (array) ( $_POST['delete_media_ids'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $delete_ids as $media_id ) {
			$media_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sn_activity_media WHERE id = %d AND activity_id = %d", $media_id, $activity_id )
			);
			if ( $media_row ) {
				if ( ! empty( $media_row->file_path ) ) {
					wp_delete_file( $media_row->file_path );
				}
				$wpdb->delete( $wpdb->prefix . 'sn_activity_media', array( 'id' => $media_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}

		// Upload new media files.
		$has_new_media = get_option( 'arshid6social_activity_allow_media', false ) && ! empty( $_FILES['media_files']['name'] );
		if ( $has_new_media ) {
			$this->handle_media_upload( $activity_id, $_FILES['media_files'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_activity',
			array( 'content' => $content, 'privacy' => $privacy ),
			array( 'id' => $activity_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$this->invalidate_cache( $activity_id );

		wp_send_json_success( array(
			'activity' => $this->format_activity( $this->get_by_id( $activity_id ) ),
			'message'  => __( 'Activity updated.', '6arshid-social-community-main' ),
		) );
	}

	/**
	 * AJAX: Deletes a single media attachment (owner only).
	 */
	public function ajax_delete_activity_media(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', '6arshid-social-community-main' ) ), 401 );
		}

		global $wpdb;

		$media_id = absint( $_POST['media_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT m.*, a.user_id FROM {$wpdb->prefix}sn_activity_media m JOIN {$wpdb->prefix}sn_activity a ON a.id = m.activity_id WHERE m.id = %d", $media_id )
		);

		if ( ! $row || (int) $row->user_id !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! empty( $row->file_path ) && file_exists( $row->file_path ) ) {
			wp_delete_file( $row->file_path );
			// Remove the upload directory if now empty.
			$this->remove_dir_if_empty( dirname( $row->file_path ) );
		}
		$wpdb->delete( $wpdb->prefix . 'sn_activity_media', array( 'id' => $media_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_send_json_success( array( 'message' => __( 'Media deleted.', '6arshid-social-community-main' ) ) );
	}

	/**
	 * Removes a directory only when it exists and is empty, using WP_Filesystem.
	 *
	 * @param string $dir Absolute directory path.
	 */
	private function remove_dir_if_empty( string $dir ): void {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( ! $wp_filesystem || ! $wp_filesystem->is_dir( $dir ) ) {
			return;
		}
		$remaining = $wp_filesystem->dirlist( $dir );
		if ( empty( $remaining ) ) {
			$wp_filesystem->rmdir( $dir );
		}
	}

	/**
	 * Deletes an activity item and its children (comments).
	 *
	 * @param int $activity_id Activity ID.
	 */
	public function delete( int $activity_id ): void {
		global $wpdb;

		// Delete comments/children first.
		$children = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}sn_activity WHERE item_id = %d AND type = 'activity_comment'", $activity_id )
		);
		foreach ( $children as $child_id ) {
			$this->delete( (int) $child_id );
		}

		// Delete media files from disk and table.
		$media_items = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT file_path FROM {$wpdb->prefix}sn_activity_media WHERE activity_id = %d", $activity_id )
		);
		$dir_to_remove = null;
		foreach ( $media_items as $media ) {
			if ( ! empty( $media->file_path ) && file_exists( $media->file_path ) ) {
				wp_delete_file( $media->file_path );
				if ( ! $dir_to_remove ) {
					$dir_to_remove = dirname( $media->file_path );
				}
			}
		}
		// Remove the upload directory if it is now empty.
		if ( $dir_to_remove ) {
			$this->remove_dir_if_empty( $dir_to_remove );
		}
		$wpdb->delete( $wpdb->prefix . 'sn_activity_media', array( 'activity_id' => $activity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->delete( $wpdb->prefix . 'sn_activity_reactions', array( 'activity_id' => $activity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_activity_meta', array( 'activity_id' => $activity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'sn_activity', array( 'id' => $activity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->invalidate_cache( $activity_id );

		do_action( 'arshid6social_activity_deleted', $activity_id );
	}

	/**
	 * AJAX: Toggles a reaction (like) on an activity item.
	 */
	public function ajax_react_activity(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to react.', '6arshid-social-community-main' ) ), 401 );
		}

		global $wpdb;

		$activity_id   = absint( $_POST['activity_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$reaction_type = sanitize_key( $_POST['reaction_type'] ?? 'like' ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id       = get_current_user_id();

		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, reaction_type FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id = %d AND user_id = %d",
				$activity_id,
				$user_id
			)
		);

		if ( $existing ) {
			// Toggle off (or switch type).
			if ( $existing->reaction_type === $reaction_type ) {
				$wpdb->delete( $wpdb->prefix . 'sn_activity_reactions', array( 'id' => $existing->id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$reacted = false;
			} else {
				$wpdb->update( $wpdb->prefix . 'sn_activity_reactions', array( 'reaction_type' => $reaction_type ), array( 'id' => $existing->id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$reacted = true;
			}
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_activity_reactions',
				array(
					'activity_id'   => $activity_id,
					'user_id'       => $user_id,
					'reaction_type' => $reaction_type,
					'date_created'  => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
			$reacted = true;
		}

		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity_reactions WHERE activity_id = %d AND reaction_type = %s", $activity_id, $reaction_type )
		);

		do_action( 'arshid6social_activity_reacted', $activity_id, $user_id, $reaction_type, $reacted );

		wp_send_json_success( array( 'reacted' => $reacted, 'count' => $count ) );
	}

	/**
	 * Handles reaction attempt by unauthenticated user.
	 */
	public function ajax_react_activity_nopriv(): void {
		wp_send_json_error( array( 'message' => __( 'You must be logged in to react.', '6arshid-social-community-main' ) ), 401 );
	}

	/**
	 * AJAX: Reports an activity item.
	 */
	public function ajax_report_activity(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		$activity_id = absint( $_POST['activity_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$reason      = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		\Arshid6Social\Components\Moderation\Moderation::add_report(
			get_current_user_id(),
			$activity_id,
			'activity',
			$reason
		);

		wp_send_json_success( array( 'message' => __( 'Report submitted. Thank you.', '6arshid-social-community-main' ) ) );
	}

	/**
	 * AJAX: Posts a comment on an activity item.
	 */
	public function ajax_post_comment(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', '6arshid-social-community-main' ) ), 401 );
		}

		if ( ! get_option( 'arshid6social_activity_allow_comments', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Comments are disabled.', '6arshid-social-community-main' ) ), 403 );
		}

		$user_id = get_current_user_id();

		if ( get_user_meta( $user_id, 'arshid6social_suspended', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account has been suspended.', '6arshid-social-community-main' ) ), 403 );
		}

		$parent_id         = absint( $_POST['activity_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$content           = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$gif_url           = isset( $_POST['gif_url'] ) ? esc_url_raw( wp_unslash( $_POST['gif_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$parent_comment_id = absint( $_POST['parent_comment_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		// Append GIF to content if provided.
		if ( $gif_url ) {
			$content .= '<img src="' . esc_url( $gif_url ) . '" class="arshid6social-gif-embed" alt="GIF" style="max-width:100%;border-radius:4px;display:block;margin-top:6px">';
		}

		if ( ! $parent_id || empty( trim( wp_strip_all_tags( $content ) ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid comment data.', '6arshid-social-community-main' ) ), 400 );
		}

		$parent = $this->get_by_id( $parent_id );
		if ( ! $parent || 'activity_comment' === $parent->type ) {
			wp_send_json_error( array( 'message' => __( 'Activity not found.', '6arshid-social-community-main' ) ), 404 );
		}

		// Validate parent comment belongs to same activity.
		if ( $parent_comment_id ) {
			$parent_comment = $this->get_by_id( $parent_comment_id );
			if ( ! $parent_comment || 'activity_comment' !== $parent_comment->type || (int) $parent_comment->item_id !== $parent_id ) {
				$parent_comment_id = 0;
			}
		}

		if ( $this->contains_banned_word( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Your comment contains prohibited content.', '6arshid-social-community-main' ) ), 422 );
		}

		$user       = get_userdata( $user_id );
		$comment_id = $this->add(
			array(
				'user_id'           => $user_id,
				'type'              => 'activity_comment',
				'component'         => 'activity',
				'content'           => $content,
				'privacy'           => $parent->privacy,
				'item_id'           => $parent_id,
				'secondary_item_id' => $parent_comment_id,
				'action'            => sprintf(
					/* translators: %s: member display name with profile link */
					__( '%s replied to a post', '6arshid-social-community-main' ),
					'<a href="' . esc_url( home_url( '/members/' . $user->user_nicename . '/' ) ) . '">' . esc_html( $user->display_name ) . '</a>'
				),
			)
		);

		if ( ! $comment_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to post comment.', '6arshid-social-community-main' ) ), 500 );
		}

		$this->process_mentions( $comment_id, $content, $user_id );

		do_action( 'arshid6social_activity_commented', $comment_id, $parent_id, $user_id, $parent_comment_id );

		wp_send_json_success( array(
			'comment' => $this->format_activity( $this->get_by_id( $comment_id ) ),
			'message' => __( 'Comment posted.', '6arshid-social-community-main' ),
		) );
	}

	/**
	 * AJAX: Returns paginated comments for an activity item, newest first.
	 */
	public function ajax_get_comments(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification
		$activity_id = absint( $_GET['activity_id'] ?? 0 );
		$page        = max( 1, absint( $_GET['page'] ?? 1 ) );
		// phpcs:enable

		if ( ! $activity_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid activity ID.', '6arshid-social-community-main' ) ), 400 );
		}

		$per_page = 10;
		$offset   = ( $page - 1 ) * $per_page;

		// Paginate only top-level comments; replies are loaded alongside their parent.
		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity WHERE item_id = %d AND type = 'activity_comment' AND secondary_item_id = 0 AND is_spam = 0",
				$activity_id
			)
		);

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sn_activity WHERE item_id = %d AND type = 'activity_comment' AND secondary_item_id = 0 AND is_spam = 0 ORDER BY date_recorded DESC LIMIT %d OFFSET %d",
				$activity_id,
				$per_page,
				$offset
			)
		);

		// Batch-format top-level comments (avoids N queries per comment).
		$comments = $this->format_activities_batch( $rows );

		if ( ! empty( $rows ) ) {
			// Batch-load ALL replies for these comments in one query.
			$top_ids    = array_map( static fn( $r ) => (int) $r->id, $rows );
			$top_ph     = implode( ',', array_fill( 0, count( $top_ids ), '%d' ) );
			$reply_rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}sn_activity WHERE secondary_item_id IN ($top_ph) AND type = 'activity_comment' AND is_spam = 0 ORDER BY date_recorded DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$top_ids
				)
			);
			// Batch-format all replies at once, then group them by parent.
			$formatted_replies = $this->format_activities_batch( $reply_rows );
			$replies_by_parent = array();
			foreach ( $reply_rows as $i => $rr ) {
				$replies_by_parent[ (int) $rr->secondary_item_id ][] = $formatted_replies[ $i ];
			}
			// Attach pre-grouped replies to each formatted top-level comment.
			foreach ( $comments as &$comment ) {
				$comment['replies'] = $replies_by_parent[ $comment['id'] ] ?? array();
			}
			unset( $comment );
		}

		wp_send_json_success(
			array(
				'comments'     => $comments,
				'total'        => $total,
				'total_pages'  => (int) ceil( $total / $per_page ),
				'current_page' => $page,
			)
		);
	}

	/**
	 * Handles media file uploads for an activity post.
	 *
	 * @param int   $activity_id Activity ID the files belong to.
	 * @param array $files       PHP $_FILES['media_files'] sub-array.
	 */
	private function handle_media_upload( int $activity_id, array $files ): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$allowed_types = (array) get_option( 'arshid6social_activity_allowed_media_types', array( 'image' ) );
		$max_bytes     = (int) get_option( 'arshid6social_max_upload_size_mb', 5 ) * MB_IN_BYTES;

		$mime_map = array(
			'image'    => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
			'video'    => array( 'video/mp4', 'video/webm', 'video/ogg' ),
			'audio'    => array( 'audio/mpeg', 'audio/wav', 'audio/ogg' ),
			'document' => array( 'application/pdf' ),
		);

		$allowed_mimes = array();
		foreach ( $allowed_types as $type ) {
			if ( isset( $mime_map[ $type ] ) ) {
				$allowed_mimes = array_merge( $allowed_mimes, $mime_map[ $type ] );
			}
		}

		// Support both single file and multi-file upload structures.
		$names     = is_array( $files['name'] )     ? $files['name']     : array( $files['name'] );
		$types     = is_array( $files['type'] )     ? $files['type']     : array( $files['type'] );
		$tmp_names = is_array( $files['tmp_name'] ) ? $files['tmp_name'] : array( $files['tmp_name'] );
		$errors    = is_array( $files['error'] )    ? $files['error']    : array( $files['error'] );
		$sizes     = is_array( $files['size'] )     ? $files['size']     : array( $files['size'] );

		$subdir_filter = function ( array $dir ) use ( $activity_id ): array {
			$dir['subdir'] = '/social-network/activity/' . $activity_id;
			$dir['path']   = $dir['basedir'] . $dir['subdir'];
			$dir['url']    = $dir['baseurl'] . $dir['subdir'];
			return $dir;
		};

		add_filter( 'upload_dir', $subdir_filter );
		$upload_dir_info = wp_upload_dir();
		wp_mkdir_p( $upload_dir_info['path'] );

		global $wpdb;

		foreach ( array_slice( $names, 0, 10 ) as $i => $name ) {
			if ( (int) $errors[ $i ] !== UPLOAD_ERR_OK ) {
				continue;
			}

			if ( (int) $sizes[ $i ] > $max_bytes ) {
				continue;
			}

			$finfo     = new \finfo( FILEINFO_MIME_TYPE );
			$real_mime = $finfo->file( $tmp_names[ $i ] );

			if ( ! in_array( $real_mime, $allowed_mimes, true ) ) {
				continue;
			}

			$media_type = 'document';
			foreach ( $mime_map as $type => $mimes ) {
				if ( in_array( $real_mime, $mimes, true ) ) {
					$media_type = $type;
					break;
				}
			}

			$file_data = array(
				'name'     => $name,
				'type'     => $real_mime,
				'tmp_name' => $tmp_names[ $i ],
				'error'    => (int) $errors[ $i ],
				'size'     => (int) $sizes[ $i ],
			);

			$moved = wp_handle_upload( $file_data, array( 'test_form' => false ) );

			if ( isset( $moved['file'] ) && ! isset( $moved['error'] ) ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prefix . 'sn_activity_media',
					array(
						'activity_id'  => $activity_id,
						'media_type'   => $media_type,
						'file_url'     => $moved['url'],
						'file_path'    => $moved['file'],
						'file_name'    => sanitize_file_name( $name ),
						'file_size'    => (int) $sizes[ $i ],
						'mime_type'    => $real_mime,
						'date_created' => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
				);

				\Arshid6Social\Media_Handler::register_to_media_library(
					$moved['file'],
					$moved['url'],
					$real_mime,
					sanitize_file_name( $name ),
					get_current_user_id()
				);
			}
		}

		remove_filter( 'upload_dir', $subdir_filter );
	}

	/**
	 * Extracts @mentions from content and fires a notification for each.
	 *
	 * @param int    $activity_id Activity ID.
	 * @param string $content     Activity content.
	 * @param int    $poster_id   User who posted.
	 */
	private function process_mentions( int $activity_id, string $content, int $poster_id ): void {
		preg_match_all( '/@([a-zA-Z0-9_\-]+)/', wp_strip_all_tags( $content ), $matches );

		foreach ( array_unique( $matches[1] ) as $username ) {
			$mentioned_user = get_user_by( 'login', $username );
			if ( ! $mentioned_user || (int) $mentioned_user->ID === $poster_id ) {
				continue;
			}

			do_action(
				'arshid6social_activity_mention',
				array(
					'user_id'    => $mentioned_user->ID,
					'poster_id'  => $poster_id,
					'activity_id' => $activity_id,
				)
			);
		}
	}

	/**
	 * Extracts #hashtags from content and stores them as activity meta.
	 *
	 * @param int    $activity_id Activity ID.
	 * @param string $content     Activity content.
	 */
	private function process_hashtags( int $activity_id, string $content ): void {
		preg_match_all( '/#([a-zA-Z0-9_]+)/', wp_strip_all_tags( $content ), $matches );

		if ( empty( $matches[1] ) ) {
			return;
		}

		global $wpdb;
		foreach ( array_unique( $matches[1] ) as $tag ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'sn_activity_meta',
				array(
					'activity_id' => $activity_id,
					'meta_key'    => 'hashtag',
					'meta_value'  => sanitize_text_field( strtolower( $tag ) ),
				),
				array( '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Checks whether content contains a banned word.
	 *
	 * The word list is parsed from the DB option once per request and held in a
	 * static variable so repeated calls (post + comment on the same request) never
	 * re-read the option or re-explode the string.
	 *
	 * @param string $content Content to check.
	 * @return bool
	 */
	private function contains_banned_word( string $content ): bool {
		static $word_list = null;

		if ( null === $word_list ) {
			$raw       = (string) get_option( 'arshid6social_banned_words', '' );
			$word_list = $raw
				? array_values( array_filter( array_map( 'trim', explode( "\n", strtolower( $raw ) ) ) ) )
				: array();
		}

		if ( empty( $word_list ) ) {
			return false;
		}

		$lower = strtolower( wp_strip_all_tags( $content ) );
		foreach ( $word_list as $word ) {
			if ( str_contains( $lower, $word ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether content is spam via Akismet.
	 *
	 * @param int    $user_id User posting the content.
	 * @param string $content Content to check.
	 * @return bool True if spam.
	 */
	private function is_spam( int $user_id, string $content ): bool {
		if ( ! function_exists( 'akismet_http_post' ) ) {
			return false;
		}

		$user   = get_userdata( $user_id );
		$params = array(
			'comment_type'         => 'comment',
			'comment_content'      => $content,
			'comment_author'       => $user ? $user->display_name : '',
			'comment_author_email' => $user ? $user->user_email : '',
			'user_ip'              => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'user_agent'           => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'referrer'             => sanitize_text_field( $_SERVER['HTTP_REFERER'] ?? '' ),
			'blog'                 => home_url(),
		);

		$response = akismet_http_post( http_build_query( $params ), 'rest.akismet.com', '/1.1/comment-check' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return isset( $response[1] ) && 'true' === trim( $response[1] );
	}

	/**
	 * Enforces a transient-based rate limit for a user action.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $action    Action key (posts, messages, friends).
	 * @param int    $max       Maximum allowed within the window.
	 * @param int    $window    Window in seconds (default 1 hour).
	 * @return bool True if under the limit (allowed), false if exceeded.
	 */
	private function check_rate_limit( int $user_id, string $action, int $max, int $window = HOUR_IN_SECONDS ): bool {
		$key   = "arshid6social_rl_{$action}_{$user_id}";
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return false;
		}

		if ( ! $count ) {
			set_transient( $key, 1, $window );
		} else {
			set_transient( $key, $count + 1, $window );
		}

		return true;
	}

	/**
	 * Registers REST API routes for the Activity component.
	 */
	public function register_rest_routes(): void {
		$controller = new Activity_REST();
		$controller->register_routes();
	}
}
