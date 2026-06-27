<?php
/**
 * 6Arshid Social Community theme — functions.php
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/*
 * Flush DB-stored templates — runs until the option matches $ver.
 *
 * IMPORTANT: When a user edits a template in the Site Editor, WordPress saves a
 * wp_template / wp_template_part post whose post_name is JUST the slug (e.g.
 * "index", "nav-sidebar") and links it to the theme via the `wp_theme`
 * taxonomy.  A LIKE 'sixarshidsocialcomunity//%' filter does NOT match these, so user
 * customizations survive and keep overriding the theme files.  We therefore
 * delete EVERY wp_template / wp_template_part post tied to this theme through
 * the wp_theme taxonomy term, plus any file-based auto-draft copies.
 */
function a6sc_flush_templates() {
	$ver = 'v3.0.3';
	if ( get_option( 'socialnetworksix_tpl_ver' ) === $ver ) {
		return;
	}

	global $wpdb;
	$theme = 'sixarshidsocialcomunity';

	// 1) Delete every template/part linked to this theme (or the old slug) via the wp_theme taxonomy.
	foreach ( array( $theme, '6arshid social community' ) as $slug ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE p FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				 INNER JOIN {$wpdb->term_taxonomy} tt        ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t                 ON t.term_id = tt.term_id
				 WHERE p.post_type IN ('wp_template','wp_template_part')
				   AND tt.taxonomy = 'wp_theme'
				   AND t.slug = %s",
				$slug
			)
		);
	}

	// 2) Delete any file-based auto-draft copies (post_name = 'slug//template').
	// Also purge stale entries that were stored under the old '6arshid social community' slug.
	foreach ( array( $theme, '6arshid social community' ) as $slug ) {
		$like = $wpdb->esc_like( $slug . '//' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->posts}
				 WHERE post_type IN ('wp_template','wp_template_part')
				   AND post_name LIKE %s",
				$like
			)
		);
	}

	// 3) Clean up orphaned postmeta + term relationships.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"DELETE pm FROM {$wpdb->postmeta} pm
		 LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.ID IS NULL"
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"DELETE tr FROM {$wpdb->term_relationships} tr
		 LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
		 WHERE p.ID IS NULL"
	);

	// 4) Bust the block-template object cache so WP re-reads the files immediately.
	wp_cache_flush();
	if ( function_exists( 'wp_clean_themes_cache' ) ) {
		wp_clean_themes_cache();
	}

	update_option( 'socialnetworksix_tpl_ver', $ver );
}
add_action( 'admin_init',        'a6sc_flush_templates', 1 );
add_action( 'template_redirect', 'a6sc_flush_templates', 1 );

// ─── 1. Theme support ─────────────────────────────────────────────────────────

add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo' );

	add_editor_style( 'assets/css/theme.css' );
	add_editor_style( 'assets/css/editor-overrides.css' );

	register_nav_menus( array(
		'socialnetworksix-primary' => __( 'Primary Navigation', '6arshid social community' ),
	) );
} );

// ─── 1b. Viewport meta (required for mobile responsive breakpoints) ───────────

add_action( 'wp_head', function () {
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
}, 1 );

// ─── 2. Enqueue front-end assets ──────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {

	// Use the file's modification time so the browser cache busts automatically
	// whenever theme.css changes (a fixed string would serve stale CSS forever).
	$theme_css_path = get_template_directory() . '/assets/css/theme.css';
	$theme_ver      = (string) ( @filemtime( $theme_css_path ) ?: '1.3.0' );

	wp_enqueue_style(
		'sixarshidsocialcomunity-theme',
		get_template_directory_uri() . '/assets/css/theme.css',
		array(),
		$theme_ver
	);

	// Plugin CSS — only when the plugin constant is available.
	if ( defined( 'ARSHID6SOCIAL_ASSETS_URL' ) && defined( 'ARSHID6SOCIAL_VERSION' ) && defined( 'ARSHID6SOCIAL_PLUGIN_DIR' ) ) {
		$css_dir    = ARSHID6SOCIAL_PLUGIN_DIR . 'assets/css/';
		$plugin_ver = (string) ( @filemtime( $css_dir . 'social-network.css' ) ?: ARSHID6SOCIAL_VERSION );

		wp_enqueue_style( 'arshid6social-core', ARSHID6SOCIAL_ASSETS_URL . 'css/social-network.css', array( 'sixarshidsocialcomunity-theme' ), $plugin_ver );

		if ( get_option( 'arshid6social_stories_enabled' ) ) {
			$stories_ver = (string) ( @filemtime( $css_dir . 'stories.css' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_style( 'arshid6social-stories', ARSHID6SOCIAL_ASSETS_URL . 'css/stories.css', array( 'arshid6social-core' ), $stories_ver );
		}
		if ( get_option( 'arshid6social_blocking_enabled', true ) ) {
			$blocking_ver = (string) ( @filemtime( $css_dir . 'blocking.css' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_style( 'arshid6social-blocking', ARSHID6SOCIAL_ASSETS_URL . 'css/blocking.css', array( 'arshid6social-core' ), $blocking_ver );
		}
		if ( get_option( 'arshid6social_verification_enabled' ) ) {
			$verif_ver = (string) ( @filemtime( $css_dir . 'verification.css' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_style( 'arshid6social-verification', ARSHID6SOCIAL_ASSETS_URL . 'css/verification.css', array( 'arshid6social-core' ), $verif_ver );
		}
		if ( is_rtl() ) {
			$rtl_ver = (string) ( @filemtime( $css_dir . 'rtl.css' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_style( 'arshid6social-rtl', ARSHID6SOCIAL_ASSETS_URL . 'css/rtl.css', array( 'arshid6social-core' ), $rtl_ver );
		}
	}
} );

// ─── 3. Dark / Light / Dim mode ───────────────────────────────────────────────

add_filter( 'language_attributes', function ( $output ) {

	// Per-user override (requires the plugin to be active).
	$user_mode = '';
	if ( is_user_logged_in() && function_exists( 'get_user_meta' ) ) {
		$user_mode = (string) get_user_meta( get_current_user_id(), 'arshid6social_theme_mode', true );
	}

	// 'system' means follow OS — no attribute, CSS media query handles it.
	if ( $user_mode === 'system' ) {
		return $output;
	}

	// Fall back to site-wide setting for unrecognized / empty values.
	if ( ! in_array( $user_mode, array( 'light', 'dark', 'dim' ), true ) ) {
		$site = get_option( 'arshid6social_dark_mode', 'auto' );
		$map  = array( 'off' => 'light', 'on' => 'dark', 'dim' => 'dim' );
		$user_mode = $map[ $site ] ?? '';
	}

	if ( $user_mode ) {
		$output .= ' data-a6sc-theme="' . esc_attr( $user_mode ) . '"';
	}

	return $output;
} );

// ─── 4. Body classes ──────────────────────────────────────────────────────────

add_filter( 'body_class', function ( $classes ) {
	$classes[] = is_user_logged_in() ? 'a6sc-logged-in' : 'a6sc-logged-out';
	if ( defined( 'ARSHID6SOCIAL_VERSION' ) ) {
		$classes[] = 'a6sc-plugin-active';
	}
	if ( get_option( 'arshid6social_stories_enabled' ) ) {
		$classes[] = 'a6sc-stories-on';
	}
	return $classes;
} );

// ─── 5. Theme-mode toggle (AJAX) ──────────────────────────────────────────────

add_action( 'wp_ajax_a6sc_set_theme_mode', function () {
	check_ajax_referer( 'a6sc_theme_toggle', 'nonce' );
	$mode = sanitize_key( $_POST['mode'] ?? '' );
	if ( ! in_array( $mode, array( 'light', 'dark', 'dim', 'system' ), true ) ) {
		wp_send_json_error();
	}
	update_user_meta( get_current_user_id(), 'arshid6social_theme_mode', $mode );
	wp_send_json_success( array( 'mode' => $mode ) );
} );

// ─── 6. Footer: theme-toggle JS ───────────────────────────────────────────────

add_action( 'wp_footer', function () {
	$nonce = wp_create_nonce( 'a6sc_theme_toggle' );
	?>
	<script>
	(function(){
		var stored = localStorage.getItem('a6sc-theme');
		if (stored === 'system') {
			document.documentElement.removeAttribute('data-a6sc-theme');
		} else if (stored) {
			document.documentElement.setAttribute('data-a6sc-theme', stored);
		}

		window.a6scThemeNonce   = <?php echo wp_json_encode( $nonce ); ?>;
		window.a6scThemeAjaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

		document.addEventListener('click', function(e){
			var btn = e.target.closest('.a6sc-theme-toggle');
			if (!btn) return;
			var html = document.documentElement;
			var cur  = html.getAttribute('data-a6sc-theme') || 'light';
			var next = (cur === 'dark') ? 'light' : 'dark';
			html.setAttribute('data-a6sc-theme', next);
			localStorage.setItem('a6sc-theme', next);
			var fd = new FormData();
			fd.append('action', 'a6sc_set_theme_mode');
			fd.append('nonce', window.a6scThemeNonce);
			fd.append('mode', next);
			fetch(window.a6scThemeAjaxUrl, {method:'POST',body:fd});
		});
	})();
	</script>
	<?php
} );

// ─── 7. Helper shortcodes (used in template parts) ────────────────────────────

/**
 * [a6sc_activity] — safe wrapper around [arshid6social_activity].
 * Returns an editor placeholder when the plugin isn't loaded or throws.
 */
add_shortcode( 'a6sc_activity', function () {
	if ( ! shortcode_exists( 'arshid6social_activity' ) ) {
		return '<div class="socialnetworksix-feed__placeholder" style="padding:32px 16px;color:#536471;text-align:center;">'
			. esc_html__( 'Activity feed loads here.', '6arshid social community' )
			. '</div>';
	}
	try {
		return wp_kses_post( do_shortcode( '[arshid6social_activity]' ) );
	} catch ( \Throwable $e ) {
		return '';
	}
} );

/**
 * [a6sc_story] — Stories tray on its own (placeable anywhere).
 * Wraps the plugin's [sn_stories_tray] (only registered when Stories are enabled).
 */
add_shortcode( 'a6sc_story', function () {
	if ( shortcode_exists( 'sn_stories_tray' ) ) {
		try {
			return wp_kses_post( do_shortcode( '[sn_stories_tray]' ) );
		} catch ( \Throwable $e ) {
			return '';
		}
	}
	// Stories feature is disabled — show a hint to logged-in users only.
	if ( ! is_user_logged_in() ) {
		return '';
	}
	return '<div class="socialnetworksix-feed__placeholder" style="padding:24px 16px;color:#536471;text-align:center;">'
		. esc_html__( 'Stories are turned off.', '6arshid social community' )
		. '</div>';
} );

/**
 * [a6sc_new_activity] — Composer box only (no feed, no stories).
 * On the front-end the composer links to the page's activity feed block
 * ([a6sc_activity_list] or [a6sc_activity]) and prepends new posts into it.
 */
add_shortcode( 'a6sc_new_activity', function () {
	if ( ! shortcode_exists( 'arshid6social_activity' ) ) {
		return '<div class="socialnetworksix-feed__placeholder" style="padding:32px 16px;color:#536471;text-align:center;">'
			. esc_html__( 'Post composer loads here.', '6arshid social community' )
			. '</div>';
	}
	try {
		return wp_kses_post( do_shortcode( '[arshid6social_activity show_composer="true" show_feed="false" show_stories="false"]' ) );
	} catch ( \Throwable $e ) {
		return '';
	}
} );

/**
 * [a6sc_activity_list] — Activity feed only (no composer, no stories).
 * Accepts per_page and scope, mirroring [arshid6social_activity].
 */
add_shortcode( 'a6sc_activity_list', function ( $atts ) {
	$atts = shortcode_atts(
		array(
			'per_page' => 10,
			'scope'    => 'site',
		),
		$atts,
		'a6sc_activity_list'
	);
	if ( ! shortcode_exists( 'arshid6social_activity' ) ) {
		return '<div class="socialnetworksix-feed__placeholder" style="padding:32px 16px;color:#536471;text-align:center;">'
			. esc_html__( 'Activity feed loads here.', '6arshid social community' )
			. '</div>';
	}
	try {
		return wp_kses_post( do_shortcode( sprintf(
			'[arshid6social_activity show_composer="false" show_feed="true" show_stories="false" per_page="%d" scope="%s"]',
			absint( $atts['per_page'] ),
			sanitize_key( $atts['scope'] )
		) ) );
	} catch ( \Throwable $e ) {
		return '';
	}
} );

/**
 * [a6sc_compose_button] — "Post" button that links to the activity page.
 * Shows only to logged-in users.
 */
add_shortcode( 'a6sc_compose_button', function () {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$page_id = (int) get_option( 'arshid6social_page_activity', 0 );
	$url     = $page_id ? get_permalink( $page_id ) : home_url( '/activity/' );
	return '<a href="' . esc_url( (string) $url ) . '" class="a6sc-compose-btn">'
		. '<span class="a6sc-compose-btn__icon" aria-hidden="true">' . a6sc_svg( 'edit' ) . '</span>'
		. '<span class="a6sc-compose-btn__label">' . esc_html__( 'Post', '6arshid social community' ) . '</span>'
		. '</a>';
} );

/**
 * [a6sc_user_menu] — Avatar + display-name + logout link for the sidebar.
 */
add_shortcode( 'a6sc_user_menu', function () {
	if ( ! is_user_logged_in() ) {
		$page_id   = (int) get_option( 'arshid6social_page_login', 0 );
		$login_url = $page_id ? get_permalink( $page_id ) : wp_login_url();
		return '<a href="' . esc_url( (string) $login_url ) . '" class="a6sc-user-menu a6sc-user-menu--guest">'
			. esc_html__( 'Log in', '6arshid social community' ) . '</a>';
	}

	$user        = wp_get_current_user();
	$avatar      = get_avatar( $user->ID, 40, '', '', array( 'class' => 'a6sc-user-menu__avatar', 'extra_attr' => 'loading="lazy"' ) );
	$profile_url = home_url( '/members/' . $user->user_nicename . '/' );
	$logout_url  = wp_logout_url( home_url() );

	return '<div class="a6sc-user-menu">'
		. '<a href="' . esc_url( $profile_url ) . '" class="a6sc-user-menu__inner">'
		. $avatar
		. '<span class="a6sc-user-menu__info">'
		. '<strong class="a6sc-user-menu__name">' . esc_html( $user->display_name ) . '</strong>'
		. '<span class="a6sc-user-menu__handle">@' . esc_html( $user->user_login ) . '</span>'
		. '</span>'
		. '</a>'
		. '<a href="' . esc_url( $logout_url ) . '" class="a6sc-user-menu__logout" title="' . esc_attr__( 'Log out', '6arshid social community' ) . '">' . a6sc_svg( 'exit' ) . '</a>'
		. '</div>';
} );

/**
 * [a6sc_theme_toggle] — Dark/light mode toggle button.
 */
add_shortcode( 'a6sc_theme_toggle', function () {
	return '<button class="a6sc-theme-toggle" aria-label="' . esc_attr__( 'Toggle dark mode', '6arshid social community' ) . '">'
		. '<span class="a6sc-icon-sun"  aria-hidden="true">' . a6sc_svg( 'sun' )  . '</span>'
		. '<span class="a6sc-icon-moon" aria-hidden="true">' . a6sc_svg( 'moon' ) . '</span>'
		. '</button>';
} );

// ─── 8. SVG icons ─────────────────────────────────────────────────────────────

function a6sc_svg( string $name ): string {
	static $icons = null;
	if ( null === $icons ) {
		$icons = array(
			'edit'          => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>',
			'sun'           => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>',
			'moon'          => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
			'home'            => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>',
			'home-filled'     => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>',
			'explore'         => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
			'explore-filled'  => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16a6.47 6.47 0 0 0 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>',
			'bell'            => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
			'bell-filled'     => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2zm6-6V11a6 6 0 0 0-5-5.91V4a1 1 0 0 0-2 0v1.09A6 6 0 0 0 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>',
			'message'         => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
			'message-filled'  => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>',
			'bookmark'        => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>',
			'bookmark-filled' => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 3H7a2 2 0 0 0-2 2v16l7-5 7 5V5a2 2 0 0 0-2-2z"/></svg>',
			'users'           => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
			'users-filled'    => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>',
			'verified'        => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L9.09 8.26 2 9.27l5 4.87-1.18 6.88L12 17.77l6.18 3.25L17 14.14l5-4.87-7.09-1.01L12 2z"/></svg>',
			'verified-filled' => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1l2.753 6.878L22 9.265l-5.46 4.9 1.374 7.408L12 18.152l-5.914 3.421 1.374-7.408L2 9.265l7.247-1.387L12 1z"/></svg>',
			'profile'         => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			'profile-filled'  => '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-5.33 0-8 2.67-8 4v2h16v-2c0-1.33-2.67-4-8-4z"/></svg>',
			'exit'            => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/><path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/></svg>',
		);
	}
	return $icons[ $name ] ?? '';
}

// ─── 9. Nav icon helper ────────────────────────────────────────────────────────

/**
 * Returns the best icon SVG for a nav item:
 * 1. Bootstrap Icon assigned to the page via the admin meta box
 * 2. Fallback: built-in a6sc_svg() (outline or filled based on active state)
 */
function a6sc_nav_icon( int $page_id, string $fallback, bool $is_active, int $size = 26 ): string {
	if ( $page_id && function_exists( 'arshid6social_page_icon' ) ) {
		$bi = arshid6social_page_icon( $page_id, $size );
		if ( $bi ) {
			return $bi;
		}
	}
	return a6sc_svg( $is_active ? $fallback . '-filled' : $fallback );
}

// ─── 10. Shared nav items builder ─────────────────────────────────────────────

function a6sc_nav_items(): array {
	$explore_id  = (int) get_option( 'arshid6social_page_members', 0 );
	$notif_id    = (int) get_option( 'arshid6social_page_notifications', 0 );
	$msg_id      = (int) get_option( 'arshid6social_page_messages', 0 );
	$groups_id   = (int) get_option( 'arshid6social_page_groups', 0 );
	$market_id   = (int) get_option( 'arshid6social_page_marketplace', 0 );
	$saved_id    = (int) get_option( 'arshid6social_page_bookmarks', 0 );
	$front_id    = (int) get_option( 'page_on_front', 0 );

	$profile_url = home_url( '/dashboard/' );
	if ( is_user_logged_in() ) {
		$user        = wp_get_current_user();
		$profile_url = home_url( '/members/' . $user->user_nicename . '/' );
	}

	return array(
		array( 'page_id' => $front_id,  'fallback' => 'home',     'label' => __( 'Home',          '6arshid social community' ), 'url' => home_url( '/' ) ),
		array( 'page_id' => $explore_id,'fallback' => 'explore',  'label' => __( 'Explore',       '6arshid social community' ), 'url' => $explore_id  ? get_permalink( $explore_id )  : home_url( '/members/' )     ),
		array( 'page_id' => $notif_id,  'fallback' => 'bell',     'label' => __( 'Notifications', '6arshid social community' ), 'url' => $notif_id    ? get_permalink( $notif_id )    : home_url( '/notifications/' ), 'badge_id' => 'arshid6social-notification-count' ),
		array( 'page_id' => $msg_id,    'fallback' => 'message',  'label' => __( 'Messages',      '6arshid social community' ), 'url' => $msg_id      ? get_permalink( $msg_id )      : home_url( '/messages/' ),     'badge_id' => 'arshid6social-messages-count'     ),
		array( 'page_id' => $saved_id,  'fallback' => 'bookmark', 'label' => __( 'Saved',         '6arshid social community' ), 'url' => $saved_id    ? get_permalink( $saved_id )    : home_url( '/saved/' )       ),
		array( 'page_id' => $groups_id, 'fallback' => 'users',    'label' => __( 'Groups',        '6arshid social community' ), 'url' => $groups_id   ? get_permalink( $groups_id )   : home_url( '/groups/' )      ),
		array( 'page_id' => $market_id, 'fallback' => 'verified', 'label' => __( 'Marketplace',   '6arshid social community' ), 'url' => $market_id   ? get_permalink( $market_id )   : home_url( '/marketplace/' ) ),
		array( 'page_id' => 0,          'fallback' => 'profile',  'label' => __( 'Profile',       '6arshid social community' ), 'url' => $profile_url ),
	);
}

// ─── 11. Desktop sidebar nav shortcode ────────────────────────────────────────

add_shortcode( 'a6sc_sidebar_nav', function () {
	$current = trailingslashit( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
	$out = '<nav class="a6sc-sidenav" aria-label="' . esc_attr__( 'Primary Navigation', '6arshid social community' ) . '">';
	foreach ( a6sc_nav_items() as $item ) {
		$item_path  = trailingslashit( (string) wp_parse_url( $item['url'], PHP_URL_PATH ) );
		$is_active  = ( $item_path !== '/' && strpos( $current, $item_path ) === 0 )
		              || ( $item_path === '/' && $current === '/' );
		$active_cls = $is_active ? ' a6sc-sidenav__item--active' : '';
		$icon_svg   = a6sc_nav_icon( $item['page_id'], $item['fallback'], $is_active, 26 );
		$badge      = isset( $item['badge_id'] )
		              ? '<span id="' . esc_attr( $item['badge_id'] ) . '" class="a6sc-nav-badge" hidden aria-hidden="true">0</span>'
		              : '';
		$out .= '<a href="' . esc_url( $item['url'] ) . '" class="a6sc-sidenav__item' . $active_cls . '">'
		      . '<span class="a6sc-sidenav__icon">' . $icon_svg . '</span>'
		      . '<span class="a6sc-sidenav__label">' . esc_html( $item['label'] ) . '</span>'
		      . $badge
		      . '</a>';
	}
	$out .= '</nav>';
	return $out;
} );

// ─── 11b. Page-list nav (icons from the per-page Page Icon picker) ─────────────

/**
 * Returns the Bootstrap-Icons name stored for a Page (empty if none).
 */
function a6sc_page_icon_name( int $page_id ): string {
	if ( ! $page_id ) {
		return '';
	}
	$key = class_exists( '\Arshid6Social\Admin\Admin_Page_Icons' )
		? \Arshid6Social\Admin\Admin_Page_Icons::META_KEY
		: '_arshid6social_page_icon';
	return (string) get_post_meta( $page_id, $key, true );
}

/**
 * Builds the navigation list from real WordPress Pages.
 *
 * Source order:
 *   1. If a menu is assigned to the "socialnetworksix-primary" location, use it.
 *      Each menu item that points to a Page shows that Page's icon.
 *   2. Otherwise list all top-level published Pages (ordered by Page Attributes
 *      → Order, then title).
 *
 * Each Page's icon comes from the same Bootstrap-Icons picker used in the
 * Pages → edit → "Page Icon" meta box (post meta _arshid6social_page_icon).
 *
 * @return array<int,array{label:string,url:string,icon:string}>
 */
function a6sc_page_nav_items( string $location = 'socialnetworksix-primary', int $icon_size = 26 ): array {
	$items     = array();
	$has_picker = function_exists( 'arshid6social_page_icon' );

	// 1) Prefer a menu assigned to the theme location.
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations[ $location ] ) ) {
		$menu_obj = wp_get_nav_menu_object( $locations[ $location ] );
		if ( $menu_obj ) {
			$menu_items = wp_get_nav_menu_items( $menu_obj->term_id );
			if ( $menu_items ) {
				foreach ( $menu_items as $mi ) {
					if ( (int) $mi->menu_item_parent !== 0 ) {
						continue; // top-level only
					}
					$icon    = '';
					$page_id = ( 'page' === $mi->object && $mi->object_id ) ? (int) $mi->object_id : 0;
					if ( $has_picker && $page_id ) {
						$icon = arshid6social_page_icon( $page_id, $icon_size );
					}
					$items[] = array(
						'label'     => $mi->title,
						'url'       => $mi->url,
						'icon'      => $icon,
						'page_id'   => $page_id,
						'icon_name' => a6sc_page_icon_name( $page_id ),
						'key'       => $page_id ? 'p' . $page_id : 'u:' . $mi->url,
					);
				}
			}
		}
	}

	// 2) Fallback: the curated app nav (Home, Explore, Notifications, …).
	//    This avoids listing auth/utility pages (Login, Register, Reset …) and
	//    gives each item a sensible themed icon when no Page Icon is assigned.
	if ( empty( $items ) ) {
		foreach ( a6sc_nav_items() as $nav ) {
			$page_id = (int) ( $nav['page_id'] ?? 0 );
			$icon    = '';
			if ( $has_picker && $page_id ) {
				$icon = arshid6social_page_icon( $page_id, $icon_size ); // Bootstrap icon from meta.
			}
			if ( ! $icon && function_exists( 'a6sc_svg' ) ) {
				$icon = a6sc_svg( $nav['fallback'] ); // Themed default icon.
			}
			$items[] = array(
				'label'     => $nav['label'],
				'url'       => $nav['url'],
				'icon'      => $icon,
				'page_id'   => $page_id,
				'icon_name' => a6sc_page_icon_name( $page_id ),
				'key'       => $page_id ? 'p' . $page_id : 'u:' . $nav['url'],
			);
		}
	}

	// 3) Apply the custom drag-and-drop order saved from the block editor.
	return a6sc_apply_nav_order( $items );
}

/**
 * Reorders nav items according to the saved drag-and-drop order
 * (option arshid6social_page_nav_order = array of item keys).
 * Items not present in the saved order keep their natural position at the end.
 *
 * @param array<int,array<string,mixed>> $items
 * @return array<int,array<string,mixed>>
 */
function a6sc_apply_nav_order( array $items ): array {
	$order = get_option( 'arshid6social_page_nav_order', array() );
	if ( empty( $order ) || ! is_array( $order ) ) {
		return $items;
	}

	$by_key = array();
	foreach ( $items as $it ) {
		$by_key[ (string) ( $it['key'] ?? '' ) ] = $it;
	}

	$ordered = array();
	foreach ( $order as $key ) {
		if ( isset( $by_key[ $key ] ) ) {
			$ordered[] = $by_key[ $key ];
			unset( $by_key[ $key ] );
		}
	}
	// Append any items that weren't in the saved order (newly added pages).
	foreach ( $by_key as $it ) {
		$ordered[] = $it;
	}
	return $ordered;
}

/**
 * Default fallback icon (outline circle) for Pages with no icon assigned.
 */
function a6sc_page_nav_default_icon( int $size = 26 ): string {
	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/></svg>',
		$size
	);
}

/**
 * Renders the page-list navigation HTML (shared by the shortcode and the block).
 *
 * @param string $menu      Theme-location slug to read the menu from.
 * @param bool   $in_editor When true, each item links to the page's edit screen
 *                          icon meta box so the icon can be changed in place.
 */
function a6sc_render_page_nav( string $menu = 'socialnetworksix-primary', bool $in_editor = false ): string {
	$items = a6sc_page_nav_items( $menu );
	if ( empty( $items ) ) {
		return '<nav class="a6sc-sidenav a6sc-sidenav--empty"><p style="padding:12px;color:#888;font-size:13px;">'
			. esc_html__( 'No pages found. Create a Page (or assign a menu to “Primary Navigation”) to populate this list.', '6arshid social community' )
			. '</p></nav>';
	}

	$current = trailingslashit( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
	$out     = '<nav class="a6sc-sidenav" aria-label="' . esc_attr__( 'Primary Navigation', '6arshid social community' ) . '">';

	foreach ( $items as $item ) {
		$item_path = trailingslashit( (string) wp_parse_url( $item['url'], PHP_URL_PATH ) );
		$is_active = ( $item_path !== '/' && strpos( $current, $item_path ) === 0 )
		             || ( $item_path === '/' && $current === '/' );
		$cls  = $is_active ? ' a6sc-sidenav__item--active' : '';
		$icon = $item['icon'] ?: a6sc_page_nav_default_icon( 26 );

		$out .= '<a href="' . esc_url( $item['url'] ) . '" class="a6sc-sidenav__item' . $cls . '">'
		      . '<span class="a6sc-sidenav__icon">' . $icon . '</span>'
		      . '<span class="a6sc-sidenav__label">' . esc_html( $item['label'] ) . '</span>'
		      . '</a>';
	}

	$out .= '</nav>';
	return $out;
}

/**
 * [a6sc_page_nav] — sidebar navigation rendered from WordPress Pages.
 *
 * Attributes:
 *   menu  — theme-location slug to read the menu from (default socialnetworksix-primary)
 */
add_shortcode( 'a6sc_page_nav', function ( $atts ) {
	$atts = shortcode_atts(
		array( 'menu' => 'socialnetworksix-primary' ),
		$atts,
		'a6sc_page_nav'
	);
	return a6sc_render_page_nav( (string) $atts['menu'] );
} );

// ─── 11c. Page-nav as a dynamic block (live preview in the Site Editor) ───────

/**
 * Registers the sixarshidsocialcomunity/page-nav block.  It is server-rendered, so the
 * Site Editor shows the REAL page list with icons (via ServerSideRender) instead
 * of a raw shortcode placeholder.
 */
add_action( 'init', function () {
	register_block_type(
		'sixarshidsocialcomunity/page-nav',
		array(
			'api_version'     => 2,
			'title'           => __( 'Page Navigation (6Arshid Social Community)', '6arshid social community' ),
			'category'        => 'theme',
			'icon'            => 'menu',
			'description'     => __( 'Lists your Pages with their Page Icons. Change an icon in Pages → edit → Page Icon.', '6arshid social community' ),
			'attributes'      => array(
				'menu' => array(
					'type'    => 'string',
					'default' => 'socialnetworksix-primary',
				),
			),
			'render_callback' => function ( $attributes ) {
				$menu      = isset( $attributes['menu'] ) ? (string) $attributes['menu'] : 'socialnetworksix-primary';
				$in_editor = defined( 'REST_REQUEST' ) && REST_REQUEST; // ServerSideRender uses the REST API.
				return a6sc_render_page_nav( $menu, $in_editor );
			},
		)
	);
} );

/**
 * REST API: list pages for the nav + save an icon to a page — used by the block
 * editor so icons can be picked inline (no need to open each page).
 */
add_action( 'rest_api_init', function () {

	// GET /a6sc/v1/page-nav-items?menu=...  → [{ page_id, label, icon_name }]
	register_rest_route(
		'a6sc/v1',
		'/page-nav-items',
		array(
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
			'args'                => array(
				'menu' => array( 'type' => 'string', 'default' => 'socialnetworksix-primary' ),
			),
			'callback'            => function ( $request ) {
				$menu  = (string) $request->get_param( 'menu' );
				$items = a6sc_page_nav_items( $menu ?: 'socialnetworksix-primary' );
				$out   = array();
				foreach ( $items as $it ) {
					$out[] = array(
						'page_id'   => (int) ( $it['page_id'] ?? 0 ),
						'label'     => (string) $it['label'],
						'icon_name' => (string) ( $it['icon_name'] ?? '' ),
						'key'       => (string) ( $it['key'] ?? '' ),
					);
				}
				return rest_ensure_response( $out );
			},
		)
	);

	// POST /a6sc/v1/page-icon  { page_id, icon } → saves the Page Icon meta.
	register_rest_route(
		'a6sc/v1',
		'/page-icon',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
			'args'                => array(
				'page_id' => array( 'type' => 'integer', 'required' => true ),
				'icon'    => array( 'type' => 'string',  'required' => false, 'default' => '' ),
			),
			'callback'            => function ( $request ) {
				$page_id = (int) $request->get_param( 'page_id' );
				$icon    = sanitize_text_field( (string) $request->get_param( 'icon' ) );

				if ( ! $page_id || ! current_user_can( 'edit_post', $page_id ) ) {
					return new WP_Error( 'a6sc_forbidden', __( 'You cannot edit this page.', '6arshid social community' ), array( 'status' => 403 ) );
				}

				$key = class_exists( '\Arshid6Social\Admin\Admin_Page_Icons' )
					? \Arshid6Social\Admin\Admin_Page_Icons::META_KEY
					: '_arshid6social_page_icon';

				if ( $icon ) {
					update_post_meta( $page_id, $key, $icon );
				} else {
					delete_post_meta( $page_id, $key );
				}

				return rest_ensure_response( array(
					'success'   => true,
					'page_id'   => $page_id,
					'icon_name' => $icon,
				) );
			},
		)
	);

	// POST /a6sc/v1/page-order  { order: [key, key, …] } → saves nav drag order.
	register_rest_route(
		'a6sc/v1',
		'/page-order',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'order' => array( 'type' => 'array', 'required' => true ),
			),
			'callback'            => function ( $request ) {
				$order = (array) $request->get_param( 'order' );
				$clean = array_values( array_map( 'sanitize_text_field', $order ) );
				update_option( 'arshid6social_page_nav_order', $clean );
				return rest_ensure_response( array( 'success' => true, 'order' => $clean ) );
			},
		)
	);
} );

/**
 * Editor assets: register the block with a live ServerSideRender preview and an
 * inline Bootstrap-Icons picker per page.
 */
add_action( 'enqueue_block_editor_assets', function () {
	$dir     = get_stylesheet_directory();
	$uri     = get_stylesheet_directory_uri();
	$js_ver  = (string) ( @filemtime( $dir . '/assets/js/page-nav-block.js' ) ?: '2.9.0' );
	$css_ver = (string) ( @filemtime( $dir . '/assets/css/page-nav-editor.css' ) ?: '2.9.0' );

	wp_enqueue_script(
		'a6sc-page-nav-block',
		$uri . '/assets/js/page-nav-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components', 'wp-api-fetch' ),
		$js_ver,
		true
	);

	wp_enqueue_style(
		'a6sc-page-nav-editor',
		$uri . '/assets/css/page-nav-editor.css',
		array(),
		$css_ver
	);

	$icons_url = defined( 'ARSHID6SOCIAL_ASSETS_URL' )
		? ARSHID6SOCIAL_ASSETS_URL . 'icons/bootstrap-icons.json'
		: '';

	wp_localize_script( 'a6sc-page-nav-block', 'a6scPageNav', array(
		'iconsUrl' => $icons_url,
		'restBase' => esc_url_raw( rest_url( 'a6sc/v1' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
	) );
} );

// ─── 11d. Admin page: manage the sidebar navigation (reorder + icons) ─────────

/**
 * Adds an "Sidebar Navigation" screen under Appearance where the page order and
 * each page's icon can be managed from wp-admin (no Site Editor needed).
 */
add_action( 'admin_menu', function () {
	add_theme_page(
		__( 'Sidebar Navigation', '6arshid social community' ),
		__( 'Sidebar Navigation', '6arshid social community' ),
		'edit_pages',
		'a6sc-sidebar-nav',
		'a6sc_render_nav_admin_page'
	);
} );

/**
 * Renders the container for the Sidebar Navigation admin screen.
 */
function a6sc_render_nav_admin_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Sidebar Navigation', '6arshid social community' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Drag ⠿ (or use ▲▼) to reorder the pages shown in the left sidebar, and click ✎ to change each page’s icon. Changes save automatically.', '6arshid social community' ); ?>
		</p>
		<div id="a6sc-nav-admin" class="a6sc-nav-admin">
			<p><span class="spinner is-active" style="float:none;"></span> <?php esc_html_e( 'Loading pages…', '6arshid social community' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Loads the admin JS + CSS only on the Sidebar Navigation screen.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'appearance_page_a6sc-sidebar-nav' !== $hook ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	$js_ver  = (string) ( @filemtime( $dir . '/assets/js/page-nav-admin.js' ) ?: '1.0.0' );
	$css_ver = (string) ( @filemtime( $dir . '/assets/css/page-nav-editor.css' ) ?: '1.0.0' );

	wp_enqueue_style(
		'a6sc-page-nav-editor',
		$uri . '/assets/css/page-nav-editor.css',
		array(),
		$css_ver
	);

	wp_enqueue_script(
		'a6sc-page-nav-admin',
		$uri . '/assets/js/page-nav-admin.js',
		array( 'wp-api-fetch' ),
		$js_ver,
		true
	);

	$icons_url = defined( 'ARSHID6SOCIAL_ASSETS_URL' )
		? ARSHID6SOCIAL_ASSETS_URL . 'icons/bootstrap-icons.json'
		: '';

	wp_localize_script( 'a6sc-page-nav-admin', 'a6scPageNav', array(
		'iconsUrl' => $icons_url,
		'restBase' => esc_url_raw( rest_url( 'a6sc/v1' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'i18n'     => array(
			'reorder'  => __( 'Drag ⠿ (or use ▲▼) to reorder · click ✎ to change a page’s icon.', '6arshid social community' ),
			'noPages'  => __( 'No pages found. Create a Page first.', '6arshid social community' ),
			'pickIcon' => __( 'Select an Icon', '6arshid social community' ),
			'search'   => __( 'Search icons…', '6arshid social community' ),
			'more'     => __( 'Showing first 150 — keep typing to narrow results.', '6arshid social community' ),
			'moveUp'   => __( 'Move up', '6arshid social community' ),
			'moveDown' => __( 'Move down', '6arshid social community' ),
			'edit'     => __( 'Change icon', '6arshid social community' ),
			'close'    => __( 'Close', '6arshid social community' ),
		),
	) );
} );

// ─── 12. Logo helper & desktop sidebar logo shortcode ─────────────────────────

/**
 * Returns the URL for a logo stored as an attachment ID in a plugin option.
 * Falls back: $option_key → $fallback_key → WordPress custom_logo theme mod.
 */
function a6sc_get_logo_url( string $option_key, string $fallback_key = '' ): string {
	$id = (int) get_option( $option_key, 0 );
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	if ( $fallback_key ) {
		$id = (int) get_option( $fallback_key, 0 );
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) {
				return $url;
			}
		}
	}
	if ( has_custom_logo() ) {
		$url = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
		return $url ?: '';
	}
	return '';
}

add_shortcode( 'a6sc_sidebar_logo', function () {
	$logo_url = a6sc_get_logo_url( 'arshid6social_logo_desktop' );
	if ( ! $logo_url ) {
		return '';
	}
	return '<div class="socialnetworksix-sidebar__logo">'
		. '<a href="' . esc_url( home_url( '/' ) ) . '" class="a6sc-sidebar-logo" aria-label="' . esc_attr( get_bloginfo( 'name' ) ) . '">'
		. '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">'
		. '</a>'
		. '</div>';
} );

// ─── 13. Mobile side nav shortcode ────────────────────────────────────────────

add_shortcode( 'a6sc_bottom_nav', function () {
	$current = trailingslashit( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
	$out = '<nav class="a6sc-bnav" aria-label="' . esc_attr__( 'Mobile Navigation', '6arshid social community' ) . '">';

	// Logo at top of mobile side nav — use mobile logo, fall back to desktop logo, then WP site logo
	$logo_url = a6sc_get_logo_url( 'arshid6social_logo_mobile', 'arshid6social_logo_desktop' );
	if ( $logo_url ) {
		$out .= '<a href="' . esc_url( home_url( '/' ) ) . '" class="a6sc-bnav__logo" aria-label="' . esc_attr( get_bloginfo( 'name' ) ) . '">'
		      . '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">'
		      . '</a>';
	}
	foreach ( a6sc_nav_items() as $item ) {
		$item_path  = trailingslashit( (string) wp_parse_url( $item['url'], PHP_URL_PATH ) );
		$is_active  = ( $item_path !== '/' && strpos( $current, $item_path ) === 0 )
		              || ( $item_path === '/' && $current === '/' );
		$active_cls = $is_active ? ' a6sc-bnav__item--active' : '';
		$icon_svg   = a6sc_nav_icon( $item['page_id'], $item['fallback'], $is_active, 24 );
		$badge_icon = isset( $item['badge_id'] )
		              ? '<span id="' . esc_attr( $item['badge_id'] ) . '" class="a6sc-nav-badge a6sc-nav-badge--icon" hidden aria-hidden="true">0</span>'
		              : '';
		$out .= '<a href="' . esc_url( $item['url'] ) . '" class="a6sc-bnav__item' . $active_cls . '">'
		      . '<span class="a6sc-bnav__icon">' . $icon_svg . $badge_icon . '</span>'
		      . '<span class="a6sc-bnav__label">' . esc_html( $item['label'] ) . '</span>'
		      . '</a>';
	}
	$out .= '</nav>';
	return $out;
} );

// ─── 14. Helper shortcodes ────────────────────────────────────────────────────

/**
 * [a6sc_search] — safe search form shortcode.
 * Using a shortcode instead of wp:search avoids the "This block has encountered
 * an error" message that occurs when the search block's REST preview fails.
 */
add_shortcode( 'a6sc_search', function () {
	$form = get_search_form( array( 'echo' => false ) );
	if ( ! $form ) {
		return '';
	}
	// Merge our class into the existing class attribute (injecting a second
	// class="" attribute would be ignored by browsers and leave the form unstyled).
	if ( strpos( $form, 'class="search-form"' ) !== false ) {
		$form = str_replace( 'class="search-form"', 'class="search-form a6sc-search-widget"', $form );
	} else {
		$form = str_replace( 'role="search"', 'role="search" class="a6sc-search-widget"', $form );
	}
	// Return as-is — get_search_form() is a trusted WP core function.
	// wp_kses_post() would strip <form>, <input>, <button>, <label> elements.
	return $form;
} );

// ─── 15. Full sidebar shortcodes (no wp:group → no block-validation errors) ──

/**
 * [a6sc_sidebar] — entire left sidebar rendered as one PHP unit.
 */
add_shortcode( 'a6sc_sidebar', function () {
	$logo    = wp_kses_post( do_shortcode( '[a6sc_sidebar_logo]' ) );
	$nav     = wp_kses_post( do_shortcode( '[a6sc_sidebar_nav]' ) );
	$compose = wp_kses_post( do_shortcode( '[a6sc_compose_button]' ) );
	$user    = wp_kses_post( do_shortcode( '[a6sc_user_menu]' ) );

	return wp_kses_post( '<aside class="socialnetworksix-sidebar">'
		. $logo
		. $nav
		. $compose
		. '<div class="socialnetworksix-sidebar__user">' . $user . '</div>'
		. '</aside>' );
} );

/**
 * [a6sc_right_sidebar] — entire right sidebar rendered as one PHP unit.
 */
add_shortcode( 'a6sc_right_sidebar', function () {
	$search = get_search_form( array( 'echo' => false, 'aria_label' => 'Search' ) );
	if ( $search ) {
		$search = '<div class="a6sc-search-wrap">' . $search . '</div>';
	}

	$who_to_follow = shortcode_exists( 'arshid6social_who_to_follow' )
		? wp_kses_post( do_shortcode( '[arshid6social_who_to_follow]' ) )
		: '';

	$panel = '';
	if ( $who_to_follow ) {
		$members_id  = (int) get_option( 'arshid6social_page_members', 0 );
		$members_url = $members_id ? (string) get_permalink( $members_id ) : home_url( '/members/' );
		$panel = '<div class="wp-block-group a6sc-panel">'
			. '<h2 class="wp-block-heading a6sc-panel__title">' . esc_html__( 'Who to follow', '6arshid social community' ) . '</h2>'
			. $who_to_follow
			. '<p class="a6sc-panel__show-more"><a href="' . esc_url( $members_url ) . '">' . esc_html__( 'View all members →', '6arshid social community' ) . '</a></p>'
			. '</div>';
	}

	$ads = shortcode_exists( 'arshid6social_ads' )
		? wp_kses_post( do_shortcode( '[arshid6social_ads placement="sidebar"]' ) )
		: '';

	$footer_links = '<p class="a6sc-footer-links">'
		. '<a href="' . esc_url( home_url( '/about/' ) ) . '">About</a>'
		. ' &middot; <a href="' . esc_url( home_url( '/privacy/' ) ) . '">Privacy</a>'
		. ' &middot; <a href="' . esc_url( home_url( '/terms/' ) ) . '">Terms</a>'
		. '</p>';

	// Not using wp_kses_post here — $search contains trusted WP core form HTML
	// that kses would strip (<form>, <input>, <button>, <label>).
	return '<aside class="socialnetworksix-right">'
		. '<div class="socialnetworksix-right-inner">'
		. $search
		. $panel
		. $ads
		. $footer_links
		. '</div>'
		. '</aside>';
} );

