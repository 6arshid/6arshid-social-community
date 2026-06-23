<?php
namespace Arshid6Social;

/**
 * Conditional asset enqueueing.
 *
 * @package Arshid6Social
 */

use ARSHID6SOCIAL\Components\Ads\Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 *
 * Registers and conditionally enqueues plugin scripts and styles.
 * Assets are only loaded on pages that need them (never site-wide).
 */
final class Assets {

	private static ?Assets $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_action( 'wp_head', array( $this, 'output_inline_vars' ) );
		add_action( 'wp_head', array( $this, 'output_nav_auth_css' ) );
	}

	/**
	 * Returns cached asset suffixes and versions, computing them at most once per
	 * plugin version (stored in a site option so filesystem calls only happen when
	 * the plugin is updated, not on every page load).
	 *
	 * @return array{css_suffix: string, js_suffix: string, js_ver: string}
	 */
	private function get_asset_variants(): array {
		static $variants = null;
		if ( null !== $variants ) {
			return $variants;
		}

		$cache_key = 'arshid6social_asset_variants_' . ARSHID6SOCIAL_ASSET_VER;
		$cached    = get_site_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$variants = $cached;
			return $variants;
		}

		$debug      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$av         = ARSHID6SOCIAL_ASSET_VER;
		$css_dir    = ARSHID6SOCIAL_PLUGIN_DIR . 'assets/css/';
		$js_dir     = ARSHID6SOCIAL_PLUGIN_DIR . 'assets/js/';

		$has_min_css = ! $debug && file_exists( $css_dir . 'social-network.min.css' );
		$css_suffix  = $has_min_css ? '.min' : '';

		$has_min_js = ! $debug && file_exists( $js_dir . 'social-network.min.js' );
		if ( $has_min_js ) {
			$src_mtime = (int) ( @filemtime( $js_dir . 'social-network.js' ) ?: 0 );
			$min_mtime = (int) ( @filemtime( $js_dir . 'social-network.min.js' ) ?: 0 );
			$js_suffix = ( $min_mtime >= $src_mtime ) ? '.min' : '';
			$js_ver    = $js_suffix ? (string) $min_mtime : (string) $src_mtime;
		} else {
			$js_suffix = '';
			$js_ver    = (string) ( @filemtime( $js_dir . 'social-network.js' ) ?: $av );
		}

		$variants = compact( 'css_suffix', 'js_suffix', 'js_ver' );
		set_site_transient( $cache_key, $variants, WEEK_IN_SECONDS );
		return $variants;
	}

	/**
	 * Enqueues frontend assets only on plugin pages.
	 */
	public function enqueue_frontend(): void {
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		$av                                    = ARSHID6SOCIAL_ASSET_VER;
		[ 'css_suffix' => $suffix,
		  'js_suffix'  => $js_suffix,
		  'js_ver'     => $js_ver ]            = $this->get_asset_variants();

		wp_enqueue_style(
			'arshid6social-main',
			ARSHID6SOCIAL_ASSETS_URL . "css/social-network{$suffix}.css",
			array(),
			$av
		);

		// RTL stylesheet.
		if ( is_rtl() ) {
			wp_enqueue_style(
				'arshid6social-rtl',
				ARSHID6SOCIAL_ASSETS_URL . 'css/rtl.css',
				array( 'arshid6social-main' ),
				$av
			);
		}

		wp_enqueue_script(
			'arshid6social-main',
			ARSHID6SOCIAL_ASSETS_URL . "js/social-network{$js_suffix}.js",
			array(),
			$js_ver,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		wp_localize_script(
			'arshid6social-main',
			'ARSHID6SOCIALConfig',
			$this->frontend_script_data()
		);

		// Feed ads config.
		$ads_data = Ads::get_feed_ads_for_js();
		wp_localize_script( 'arshid6social-main', 'ARSHID6SOCIALAds', array(
			'ads'         => $ads_data['ads'],
			'everyNPosts' => $ads_data['every_n_posts'],
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'arshid6social_ajax_nonce' ),
		) );

		// Messages page script (external file avoids inline-script encoding issues).
		wp_enqueue_script(
			'arshid6social-messages',
			ARSHID6SOCIAL_ASSETS_URL . 'js/messages.js',
			array( 'arshid6social-main' ),
			$av,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		// Stories assets.
		if ( get_option( 'arshid6social_stories_enabled', false ) ) {
			wp_enqueue_style( 'arshid6social-stories', ARSHID6SOCIAL_ASSETS_URL . 'css/stories.css', array( 'arshid6social-main' ), $av );
			wp_enqueue_script(
				'arshid6social-stories',
				ARSHID6SOCIAL_ASSETS_URL . 'js/stories.js',
				array( 'arshid6social-main' ),
				$av,
				array( 'strategy' => 'defer', 'in_footer' => true )
			);
			wp_localize_script( 'arshid6social-stories', 'ARSHID6SOCIALStories', array(
				'nonce'        => wp_create_nonce( 'arshid6social_ajax_nonce' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'viewerId'     => get_current_user_id(),
				'storyReply'   => (bool) get_option( 'arshid6social_messages_story_enabled', false ),
				'i18n'         => array(
					'confirmDelete'  => __( 'Delete this story?', 'social-network-6' ),
					'highlightTitle' => __( 'Highlight name:', 'social-network-6' ),
					'noViewers'      => __( 'No viewers yet.', 'social-network-6' ),
				),
			) );
		}

		// Blocking assets.
		if ( get_option( 'arshid6social_blocking_enabled', true ) ) {
			wp_enqueue_style( 'arshid6social-blocking', ARSHID6SOCIAL_ASSETS_URL . 'css/blocking.css', array( 'arshid6social-main' ), $av );
			wp_enqueue_script(
				'arshid6social-blocking',
				ARSHID6SOCIAL_ASSETS_URL . 'js/blocking.js',
				array( 'arshid6social-main' ),
				$av,
				array( 'strategy' => 'defer', 'in_footer' => true )
			);
			wp_localize_script( 'arshid6social-blocking', 'ARSHID6SOCIALBlocking', array(
				'nonce'      => wp_create_nonce( 'arshid6social_ajax_nonce' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'showReason' => (bool) get_option( 'arshid6social_blocking_show_reason', true ),
				'i18n'       => array(
					'reasonPrompt'   => __( 'Reason for blocking (optional):', 'social-network-6' ),
					'confirmBlock'   => __( 'Block this user?', 'social-network-6' ),
					'confirmUnblock' => __( 'Unblock this user?', 'social-network-6' ),
					'blocked'        => __( 'Blocked', 'social-network-6' ),
					'block'          => __( 'Block', 'social-network-6' ),
					'blockError'     => __( 'Could not block user. Please try again.', 'social-network-6' ),
					'emptyList'      => __( 'You have not blocked anyone.', 'social-network-6' ),
				),
			) );
		}

		// Verification assets.
		if ( get_option( 'arshid6social_verification_enabled', false ) ) {
			wp_enqueue_style( 'arshid6social-verification', ARSHID6SOCIAL_ASSETS_URL . 'css/verification.css', array( 'arshid6social-main' ), $av );
			wp_enqueue_script(
				'arshid6social-verification',
				ARSHID6SOCIAL_ASSETS_URL . 'js/verification.js',
				array( 'arshid6social-main' ),
				$av,
				array( 'strategy' => 'defer', 'in_footer' => true )
			);
			wp_localize_script( 'arshid6social-verification', 'ARSHID6SOCIALVerification', array(
				'nonce'   => wp_create_nonce( 'arshid6social_ajax_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'selectType'       => __( 'Please select a verification type.', 'social-network-6' ),
					'nameRequired'     => __( 'Please enter your full name.', 'social-network-6' ),
					'requestSubmitted' => __( 'Your request has been submitted. We\'ll review it and notify you.', 'social-network-6' ),
					'submitError'      => __( 'Could not submit request.', 'social-network-6' ),
				),
			) );
		}

		// Search assets — loaded on every search page (WordPress /?s= queries).
		if ( is_search() ) {
			$search_dir = ARSHID6SOCIAL_PLUGIN_DIR . 'assets/';
			$search_css_ver = (string) ( @filemtime( $search_dir . 'css/search.css' ) ?: $av );
			$search_js_ver  = (string) ( @filemtime( $search_dir . 'js/search.js' )  ?: $av );
			wp_enqueue_style( 'arshid6social-search', ARSHID6SOCIAL_ASSETS_URL . 'css/search.css', array(), $search_css_ver );
			wp_enqueue_script(
				'arshid6social-search',
				ARSHID6SOCIAL_ASSETS_URL . 'js/search.js',
				array(),
				$search_js_ver,
				array( 'strategy' => 'defer', 'in_footer' => true )
			);
			wp_localize_script(
				'arshid6social-search',
				'ARSHID6SOCIALSearch',
				array(
					'restUrl'        => esc_url_raw( rest_url( 'arshid6social/v1/' ) ),
					'searchEndpoint' => esc_url_raw( rest_url( 'arshid6social/v1/search' ) ),
					'nonce'          => wp_create_nonce( 'wp_rest' ),
					'siteUrl'        => esc_url( home_url( '/' ) ),
					'paginationType' => sanitize_key( get_option( 'arshid6social_search_pagination_type', 'pagination' ) ),
					'i18n'           => array(
						'all'         => __( 'All', 'social-network-6' ),
						'activity'    => __( 'Activity', 'social-network-6' ),
						'members'     => __( 'People', 'social-network-6' ),
						'groups'      => __( 'Groups', 'social-network-6' ),
						'marketplace' => __( 'Marketplace', 'social-network-6' ),
						'search'      => __( 'Search', 'social-network-6' ),
						'noResults'   => __( 'No results found', 'social-network-6' ),
						'noResultsSub'=> __( 'Try a different keyword.', 'social-network-6' ),
						'seeAll'      => __( 'See all', 'social-network-6' ),
						'results'     => __( 'results', 'social-network-6' ),
						'loadMore'    => __( 'Load more', 'social-network-6' ),
						'loading'     => __( 'Loading…', 'social-network-6' ),
						'viewPost'    => __( 'View post', 'social-network-6' ),
						'free'        => __( 'Free', 'social-network-6' ),
						'members_lc'  => __( 'members', 'social-network-6' ),
						'prev'        => __( '← Prev', 'social-network-6' ),
						'next'        => __( 'Next →', 'social-network-6' ),
					),
				)
			);
		}
	}

	/**
	 * Enqueues admin assets on plugin admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'arshid6social' ) && ! str_contains( $hook_suffix, 'social-network' ) ) {
			return;
		}

		wp_enqueue_style(
			'arshid6social-admin',
			ARSHID6SOCIAL_ASSETS_URL . 'css/admin.css',
			array(),
			ARSHID6SOCIAL_VERSION
		);

		wp_enqueue_script(
			'arshid6social-admin',
			ARSHID6SOCIAL_ASSETS_URL . 'js/admin.js',
			array( 'wp-api' ),
			ARSHID6SOCIAL_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		wp_localize_script(
			'arshid6social-admin',
			'ARSHID6SOCIALAdminConfig',
			array(
				'nonce'   => wp_create_nonce( 'arshid6social_admin_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( rest_url( 'arshid6social/v1/' ) ),
				'i18n'    => $this->admin_i18n(),
			)
		);
	}

	/**
	 * Outputs inline CSS on every frontend page to show/hide nav items by auth state.
	 * Uses body.logged-in (added by WordPress) + href attribute selectors.
	 * Works with classic menus, Gutenberg block nav, and Elementor — no PHP filter needed.
	 */
	public function output_nav_auth_css(): void {
		if ( is_admin() ) {
			return;
		}

		$guest_slugs  = array( 'login', 'register' );
		$member_slugs = array( 'activity', 'dashboard', 'groups', 'messages', 'notifications', 'saved-posts' );

		$member_hide = array();
		foreach ( $member_slugs as $slug ) {
			$member_hide[] = 'body:not(.logged-in) .wp-block-navigation-item:has(>a[href$="/' . $slug . '/"])';
			$member_hide[] = 'body:not(.logged-in) li.menu-item:has(>a[href$="/' . $slug . '/"])';
		}

		$guest_hide = array();
		foreach ( $guest_slugs as $slug ) {
			$guest_hide[] = 'body.logged-in .wp-block-navigation-item:has(>a[href$="/' . $slug . '/"])';
			$guest_hide[] = 'body.logged-in li.menu-item:has(>a[href$="/' . $slug . '/"])';
		}

		$css  = implode( ',' . "\n", $member_hide ) . '{display:none!important;}' . "\n";
		$css .= implode( ',' . "\n", $guest_hide ) . '{display:none!important;}';

		wp_add_inline_style( 'arshid6social-main', $css );
	}

	/**
	 * Outputs inline CSS for admin-configurable values (colour, dark mode).
	 * Also applies dark mode data attribute to <body> when needed.
	 */
	public function output_inline_vars(): void {
		$primary = sanitize_hex_color( get_option( 'arshid6social_primary_color', '#2563eb' ) ) ?: '#2563eb';

		// Primary colour variable is output on every front-end page so nav badges
		// (which appear site-wide) always inherit the configured accent colour.
		// Register a minimal handle so wp_add_inline_style always has something to attach to,
		// even on non-plugin pages where arshid6social-main is not enqueued.
		if ( ! wp_style_is( 'arshid6social-main', 'enqueued' ) ) {
			wp_register_style( 'arshid6social-inline-vars', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
			wp_enqueue_style( 'arshid6social-inline-vars' );
			$style_handle = 'arshid6social-inline-vars';
		} else {
			$style_handle = 'arshid6social-main';
		}

		$vars_css = ':root{--arshid6social-primary:' . esc_html( $primary ) . ';--arshid6social-primary-dark:' . esc_html( $this->darken_hex( $primary ) ) . ';}';
		wp_add_inline_style( $style_handle, $vars_css );

		if ( ! $this->is_plugin_page() ) {
			return;
		}

		$dark_mode = sanitize_key( get_option( 'arshid6social_dark_mode', 'off' ) );

		if ( 'on' === $dark_mode ) {
			// Force dark mode immediately — no flash.
			wp_add_inline_script(
				'arshid6social-main',
				'document.documentElement.setAttribute("data-arshid6social-dark","true");document.body&&document.body.setAttribute("data-arshid6social-dark","true");',
				'before'
			);
		} elseif ( 'auto' === $dark_mode ) {
			// Follow system preference via JS — never uses CSS media query so
			// it can't override a theme that doesn't support dark mode.
			wp_add_inline_script(
				'arshid6social-main',
				'(function(){function arshid6social_apply(mq){var v=mq.matches?"true":"false";document.documentElement.setAttribute("data-arshid6social-dark",v);if(document.body)document.body.setAttribute("data-arshid6social-dark",v);}var mq=window.matchMedia("(prefers-color-scheme:dark)");arshid6social_apply(mq);mq.addEventListener("change",arshid6social_apply);})()',
				'before'
			);
		}
		// 'off' = no dark styles (default — theme-compatible light mode).
	}

	/**
	 * Naive hex darkening — reduces each RGB channel by ~15%.
	 *
	 * @param string $hex Hex colour e.g. '#2563eb'.
	 * @return string Darkened hex colour.
	 */
	private function darken_hex( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - 30 );
		$g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - 30 );
		$b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - 30 );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Returns data to be passed to the frontend JS bundle.
	 *
	 * @return array<string, mixed>
	 */
	private function frontend_script_data(): array {
		return array(
			'restUrl'           => esc_url_raw( rest_url( 'arshid6social/v1/' ) ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'ajaxNonce'         => wp_create_nonce( 'arshid6social_ajax_nonce' ),
			'userId'            => get_current_user_id(),
			'currentUserName'   => is_user_logged_in() ? esc_html( wp_get_current_user()->display_name ) : '',
			'currentUserAvatar' => is_user_logged_in()
				? ( function() {
					$members = ARSHID6SOCIAL()->component( 'members' );
					return $members ? esc_url( $members->avatar->get_avatar_url( get_current_user_id(), 40 ) ) : esc_url( get_avatar_url( get_current_user_id(), array( 'size' => 40 ) ) );
				} )()
				: '',
			'isLoggedIn'        => is_user_logged_in(),
			'siteUrl'           => esc_url( home_url( '/' ) ),
			'dateFormat'        => sanitize_text_field( get_option( 'arshid6social_date_format', 'relative' ) ),
			'locale'            => get_locale(),
			'rtl'               => is_rtl(),
			'allowComments'     => (bool) get_option( 'arshid6social_activity_allow_comments', true ),
			'allowMedia'        => (bool) get_option( 'arshid6social_activity_allow_media', true ),
			'allowedMediaTypes' => (array) get_option( 'arshid6social_activity_allowed_media_types', array( 'image', 'video', 'document' ) ),
			'paginationType'          => sanitize_key( get_option( 'arshid6social_activity_pagination_type', 'infinite_scroll' ) ),
			'membersPaginationType'   => sanitize_key( get_option( 'arshid6social_members_pagination_type', 'pagination' ) ),
			'memberShowFriendCount'   => (bool) get_option( 'arshid6social_members_show_friend_count', false ),
			'maxUploadSizeMb'   => (int) get_option( 'arshid6social_max_upload_size_mb', 5 ),
			'reactionStyle'     => is_user_logged_in() ? ( get_user_meta( get_current_user_id(), 'arshid6social_reaction_style', true ) ?: 'emoji' ) : 'emoji',
			'activityFeedTab'   => is_user_logged_in() ? ( get_user_meta( get_current_user_id(), 'arshid6social_activity_feed_tab', true ) ?: 'all' ) : 'all',
			'userSlug'          => is_user_logged_in()
				? ( get_userdata( get_current_user_id() )->user_nicename ?? '' )
				: '',
			'reportReasons'        => $this->get_report_reasons(),
			'reportAllowAttachments' => (bool) get_option( 'arshid6social_report_allow_attachments', false ),
			'statsBar'          => (bool) get_option( 'arshid6social_activity_stats_bar', false ),
			'sixarshidscEnabled'    => (bool) get_option( 'sixarshidsc_enabled', false ),
			'sixarshidscRestUrl'    => esc_url_raw( rest_url( 'sixarshidsc/v1/' ) ),
			'sixarshidscStripePubKey' => get_option( 'sixarshidsc_enabled', false )
				? \Arshid6Social\Components\Monetization\Monetization_Crypto::get_stripe_pub_key()
				: '',
			'i18n'              => $this->frontend_i18n(),
		);
	}

	/**
	 * Determines whether the current page belongs to the plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_page(): bool {
		return (bool) apply_filters( 'arshid6social_is_plugin_page', $this->detect_plugin_page() );
	}

	/**
	 * Auto-detects plugin pages based on rewrite endpoints, WP page IDs, shortcode content, or the global flag.
	 */
	private function detect_plugin_page(): bool {
		global $arshid6social_is_page;

		if ( $arshid6social_is_page ) {
			return true;
		}

		// WordPress search pages always need plugin assets (search JS, CSS).
		if ( is_search() ) {
			return true;
		}

		// Check query vars set by rewrite rules.
		$vars = array( 'arshid6social_members', 'arshid6social_activity', 'arshid6social_groups', 'arshid6social_messages', 'arshid6social_profile', 'arshid6social_group', 'arshid6social_eng_hashtag' );
		foreach ( $vars as $var ) {
			if ( get_query_var( $var ) ) {
				return true;
			}
		}

		if ( is_singular() ) {
			$post = get_post();

			// Primary: match auto-created page IDs.
			if ( $post ) {
				$page_options = array( 'arshid6social_page_members', 'arshid6social_page_activity', 'arshid6social_page_groups', 'arshid6social_page_messages', 'arshid6social_page_register', 'arshid6social_page_login', 'arshid6social_page_dashboard', 'arshid6social_page_notifications' );
				foreach ( $page_options as $opt ) {
					if ( (int) get_option( $opt, 0 ) === $post->ID ) {
						return true;
					}
				}

				// Fallback: any page/post whose content contains a WPSN shortcode.
				if ( $post->post_content && str_contains( $post->post_content, '[ARSHID6SOCIAL_' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Strings exposed to the frontend JS bundle.
	 *
	 * @return array<string, string>
	 */
	private function frontend_i18n(): array {
		return array(
			'loading'          => __( 'Loading…', 'social-network-6' ),
			'noResults'        => __( 'No results found.', 'social-network-6' ),
			'error'            => __( 'Something went wrong. Please try again.', 'social-network-6' ),
			'confirm'          => __( 'Are you sure?', 'social-network-6' ),
			'likeActivity'     => __( 'Like', 'social-network-6' ),
			'unlikeActivity'   => __( 'Unlike', 'social-network-6' ),
			'addFriend'        => __( 'Add Friend', 'social-network-6' ),
			'cancelRequest'    => __( 'Cancel Request', 'social-network-6' ),
			'follow'           => __( 'Follow', 'social-network-6' ),
			'unfollow'         => __( 'Unfollow', 'social-network-6' ),
			'sendMessage'      => __( 'Send Message', 'social-network-6' ),
			'report'           => __( 'Report', 'social-network-6' ),
			'block'            => __( 'Block User', 'social-network-6' ),
			'unblock'          => __( 'Unblock User', 'social-network-6' ),
			'deleteActivity'   => __( 'Delete', 'social-network-6' ),
			'seeMore'          => __( 'See more', 'social-network-6' ),
			'seeLess'          => __( 'See less', 'social-network-6' ),
			'comment'          => __( 'Comment', 'social-network-6' ),
			'comments'         => __( 'Comments', 'social-network-6' ),
			'writeComment'     => __( 'Write a comment…', 'social-network-6' ),
			'loadMore'         => __( 'Load More', 'social-network-6' ),
			'attachMedia'      => __( 'Attach media', 'social-network-6' ),
			'reply'            => __( 'Reply', 'social-network-6' ),
			'replyingTo'       => __( 'Replying to', 'social-network-6' ),
			'react'               => __( 'React', 'social-network-6' ),
			'allNotifications'    => __( 'See all notifications', 'social-network-6' ),
			'markAllRead'         => __( 'Mark all read', 'social-network-6' ),
			'markRead'            => __( 'Mark as read', 'social-network-6' ),
			'noNotifications'     => __( 'No notifications yet.', 'social-network-6' ),
			'delete'              => __( 'Delete', 'social-network-6' ),
			'justNow'          => __( 'just now', 'social-network-6' ),
			/* translators: %d: number of minutes */
			'minuteAgo'        => __( '%d minute ago', 'social-network-6' ),
			/* translators: %d: number of minutes */
			'minutesAgo'       => __( '%d minutes ago', 'social-network-6' ),
			/* translators: %d: number of hours */
			'hourAgo'          => __( '%d hour ago', 'social-network-6' ),
			/* translators: %d: number of hours */
			'hoursAgo'         => __( '%d hours ago', 'social-network-6' ),
			/* translators: %d: number of days */
			'dayAgo'           => __( '%d day ago', 'social-network-6' ),
			/* translators: %d: number of days */
			'daysAgo'          => __( '%d days ago', 'social-network-6' ),
			'feedTabAll'       => __( 'All', 'social-network-6' ),
			'feedTabFollow'    => __( 'Follow', 'social-network-6' ),
			'noFollowActivity' => __( 'No activity from users or hashtags you follow yet.', 'social-network-6' ),
			'logoutFirst'      => __( 'You are already logged in. Please log out first.', 'social-network-6' ),
			'privacyPublic'    => __( 'Public', 'social-network-6' ),
			'privacyFriends'   => __( 'Friends', 'social-network-6' ),
			'privacyPrivate'   => __( 'Only Me', 'social-network-6' ),
			'privacyPaid'        => __( 'Paid', 'social-network-6' ),
			'paidContent'        => __( 'Paid content', 'social-network-6' ),
			// translators: %s is the price or plan name required to unlock the content.
			'unlockFor'          => __( 'Unlock for %s', 'social-network-6' ),
			'loginToUnlock'      => __( 'Login to unlock', 'social-network-6' ),
			'payNow'             => __( 'Pay now', 'social-network-6' ),
			'paymentProcessing'  => __( 'Payment confirmed! Unlocking…', 'social-network-6' ),
			'paymentFailed'      => __( 'Payment failed. Please try again.', 'social-network-6' ),
		);
	}

	/**
	 * Returns the report reason list from settings.
	 *
	 * @return string[]
	 */
	private function get_report_reasons(): array {
		$default = "Spam\nHarassment or bullying\nHate speech\nInappropriate content\nFalse information\nImpersonation\nOther";
		$raw     = get_option( 'arshid6social_report_reasons', $default );
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	/**
	 * Strings exposed to the admin JS bundle.
	 *
	 * @return array<string, string>
	 */
	private function admin_i18n(): array {
		return array(
			'saved'           => __( 'Settings saved.', 'social-network-6' ),
			'error'           => __( 'Error saving settings.', 'social-network-6' ),
			'confirm'         => __( 'Are you sure?', 'social-network-6' ),
			'suspended'       => __( 'User suspended.', 'social-network-6' ),
			'unsuspended'     => __( 'User unsuspended.', 'social-network-6' ),
			'approved'        => __( 'Content approved.', 'social-network-6' ),
			'deleted'         => __( 'Item deleted.', 'social-network-6' ),
			'selectReason'    => __( 'Please select a reason.', 'social-network-6' ),
			'suspendUser'     => __( 'Suspend user', 'social-network-6' ),
			/* translators: %s: user name */
			'suspendConfirm'  => __( 'Suspend %s?', 'social-network-6' ),
			/* translators: %s: user name */
			'unsuspendConfirm' => __( 'Unsuspend %s?', 'social-network-6' ),
		);
	}
}
