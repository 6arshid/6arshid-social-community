<?php
namespace Arshid6Social;

/**
 * Core plugin orchestrator.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Singleton that boots all active components and shared services.
 */
final class Plugin {

	/** @var Plugin|null Singleton instance. */
	private static ?Plugin $instance = null;

	/** @var array<string, object> Loaded component instances. */
	private array $components = array();

	/** @var Template_Loader|null */
	private ?Template_Loader $template_loader = null;

	/** @var Cache|null */
	private ?Cache $cache = null;

	/**
	 * Returns (and creates on first call) the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/** Private constructor — use instance(). */
	private function __construct() {}

	/** No cloning. */
	private function __clone() {}

	/**
	 * Bootstraps the plugin: loads text domain, services, and active components.
	 */
	private function init(): void {
		require_once ARSHID6SOCIAL_INCLUDES_DIR . 'functions.php';
		$this->load_i18n();
		add_action( 'init', array( $this, 'load_i18n' ), 1 );
		$this->load_services();
		$this->load_components();
		$this->load_admin();
		$this->load_rest();
		$this->load_blocks_and_shortcodes();
		$this->maybe_flush_rewrite_rules();
		$this->load_nav_hooks();
		$this->ensure_auth_page_templates();

		do_action( 'arshid6social_loaded', $this );
	}

	/**
	 * Flushes rewrite rules once whenever the plugin version changes.
	 * This clears stale rules left from previous versions automatically.
	 */
	private function maybe_flush_rewrite_rules(): void {
		// Migrate tag base from old default 'tag' to 'hashtags'.
		if ( 'tag' === get_option( 'arshid6social_permalink_tag_base' ) ) {
			update_option( 'arshid6social_permalink_tag_base', 'hashtags' );
		}

		if ( get_option( 'arshid6social_rewrite_version' ) !== ARSHID6SOCIAL_VERSION ) {
			add_action( 'init', static function () {
				flush_rewrite_rules( false );
				update_option( 'arshid6social_rewrite_version', ARSHID6SOCIAL_VERSION );
				// Migrate dark_mode: 'auto' caused plugin to go dark when OS
				// was dark even though the WP theme was light. Switch to 'off'.
				if ( 'auto' === get_option( 'arshid6social_dark_mode' ) ) {
					update_option( 'arshid6social_dark_mode', 'off' );
				}
			}, 999 );
		}

		// Flush rewrite rules whenever permalink base slugs change.
		add_action( 'update_option_arshid6social_permalink_tag_base',      static function () { flush_rewrite_rules( false ); } );
		add_action( 'update_option_arshid6social_permalink_activity_base', static function () { flush_rewrite_rules( false ); } );
	}

	/**
	 * Loads the plugin text domain for translations.
	 */
	public function load_i18n(): void {
		load_plugin_textdomain(
			ARSHID6SOCIAL_TEXT_DOMAIN,
			false,
			dirname( ARSHID6SOCIAL_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Loads shared services (assets, template loader, cache, etc.).
	 */
	private function load_services(): void {
		Assets::instance();
		$this->cache           = Cache::instance();
		$this->template_loader = Template_Loader::instance();
	}

	/**
	 * Instantiates each enabled component.
	 *
	 * Components can be individually disabled from the settings panel.
	 * The 'members' component is always active (core dependency).
	 */
	private function load_components(): void {
		$enabled = $this->enabled_components();

		$component_map = array(
			'members'       => Components\Members\Members::class,
			'activity'      => Components\Activity\Activity::class,
			'groups'        => Components\Groups\Groups::class,
			'friends'       => Components\Friends\Friends::class,
			'messages'      => Components\Messages\Messages::class,
			'notifications' => Components\Notifications\Notifications::class,
			'moderation'    => Components\Moderation\Moderation::class,
			'blocking'      => Components\Blocking\Blocking::class,
			'verification'  => Components\Verification\Verification::class,
			'stories'       => Components\Stories\Stories::class,
			'marketplace'   => Components\Marketplace\Marketplace::class,
			'monetization'  => Components\Monetization\Monetization::class,
		);

		foreach ( $component_map as $key => $class ) {
			if ( in_array( $key, $enabled, true ) && class_exists( $class ) ) {
				$this->components[ $key ] = new $class();
			}
		}

		// Ads are always active (no opt-in toggle needed).
		$this->components['ads'] = new Components\Ads\Ads();

		// Search is always active — it queries whichever components are enabled.
		$this->components['search'] = new Components\Search\Search();

		do_action( 'arshid6social_components_loaded', $this->components );
	}

	/**
	 * Loads admin-only classes.
	 */
	private function load_admin(): void {
		Cache_Purge::boot();
		if ( is_admin() ) {
			Admin\Admin::instance();
			new Setup_Wizard();
			// Always register the Marketplace settings tab so it is visible
			// regardless of whether the Marketplace component is active.
			new Components\Marketplace\Marketplace_Settings();

			// Always register the Monetization settings tab so the admin can
			// configure keys and enable the component without a chicken-and-egg
			// problem (the component must be enabled to see the tab).
			new Components\Monetization\Monetization_Settings();
		}
	}

	/**
	 * Loads Gutenberg blocks and shortcodes (both contexts).
	 */
	private function load_blocks_and_shortcodes(): void {
		new Blocks();
		new Shortcodes();
	}

	/**
	 * Registers REST API routes for all loaded components.
	 */
	private function load_rest(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Fires REST route registration on each active component.
	 */
	public function register_rest_routes(): void {
		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'register_rest_routes' ) ) {
				$component->register_rest_routes();
			}
		}
	}

	/**
	 * Returns the list of currently enabled component keys.
	 *
	 * Computed once and stored in the object cache so repeated calls within the
	 * same request (or across requests when a persistent cache is available) do
	 * not issue multiple get_option() calls.
	 *
	 * @return string[]
	 */
	public function enabled_components(): array {
		$cache_key = 'enabled_components';
		$found     = false;
		$cached    = \Arshid6Social\Cache::get( $cache_key, $found );
		if ( $found && is_array( $cached ) ) {
			return $cached;
		}

		$defaults = array( 'members', 'activity', 'groups', 'friends', 'messages', 'notifications', 'moderation', 'blocking' );
		$saved    = get_option( 'arshid6social_enabled_components', $defaults );

		// Auto-enable new opt-in features based on their own settings.
		if ( get_option( 'arshid6social_blocking_enabled', true ) && ! in_array( 'blocking', $saved, true ) ) {
			$saved[] = 'blocking';
		}
		if ( get_option( 'arshid6social_stories_enabled', false ) && ! in_array( 'stories', $saved, true ) ) {
			$saved[] = 'stories';
		}
		if ( get_option( 'arshid6social_verification_enabled', false ) && ! in_array( 'verification', $saved, true ) ) {
			$saved[] = 'verification';
		}
		if ( get_option( 'arshid6social_marketplace_enabled', false ) && ! in_array( 'marketplace', $saved, true ) ) {
			$saved[] = 'marketplace';
		}
		if ( get_option( 'sixarshidsc_enabled', false ) && ! in_array( 'monetization', $saved, true ) ) {
			$saved[] = 'monetization';
		}

		// 'members' is always required.
		if ( ! in_array( 'members', $saved, true ) ) {
			$saved[] = 'members';
		}

		// Cache for current request (no expiry so it survives across requests with Redis/Memcached).
		\Arshid6Social\Cache::set( $cache_key, $saved, 0 );
		return $saved;
	}

	/**
	 * Returns a loaded component instance by key, or null if not loaded.
	 *
	 * @param string $key Component key (e.g. 'activity').
	 * @return object|null
	 */
	public function component( string $key ): ?object {
		return $this->components[ $key ] ?? null;
	}

	/**
	 * Returns all loaded component instances.
	 *
	 * @return array<string, object>
	 */
	public function get_components(): array {
		return $this->components;
	}

	/**
	 * Returns the Template_Loader service.
	 *
	 * @return Template_Loader
	 */
	public function template(): Template_Loader {
		if ( null === $this->template_loader ) {
			$this->template_loader = Template_Loader::instance();
		}
		return $this->template_loader;
	}

	/**
	 * Returns the Cache service.
	 *
	 * @return Cache
	 */
	public function cache(): Cache {
		if ( null === $this->cache ) {
			$this->cache = Cache::instance();
		}
		return $this->cache;
	}

	/**
	 * Registers frontend hooks for auth-aware navigation and page redirects.
	 */
	private function load_nav_hooks(): void {
		// Classic nav menus (wp_nav_menu).
		add_filter( 'wp_nav_menu_objects', array( $this, 'filter_nav_by_auth' ), 10, 2 );

		// Block-editor navigation (Gutenberg core/navigation-link blocks).
		add_filter( 'render_block', array( $this, 'filter_block_nav_by_auth' ), 10, 2 );

		// Redirect dashboard page → user profile (logged in) or login page (guest).
		// Redirect login page → user profile when already logged in.
		add_action( 'template_redirect', array( $this, 'handle_auth_page_redirects' ) );

		// After login, redirect to the activity page.
		add_filter( 'login_redirect', array( $this, 'redirect_after_login' ), 10, 3 );

		// Add body class on the home splash page so CSS can target it.
		add_filter( 'body_class', array( $this, 'home_splash_body_class' ) );

		// Inject unread-count badge spans into classic WP nav menus (non-socialnetworksix themes).
		add_filter( 'walker_nav_menu_start_el', array( $this, 'inject_nav_badges' ), 10, 4 );

	}

	/**
	 * Returns cached auth-page IDs, reading options only once per request.
	 *
	 * @return array{guest: int[], member: int[]}
	 */
	private static function get_auth_page_ids(): array {
		static $ids = null;
		if ( null !== $ids ) {
			return $ids;
		}
		$ids = array(
			'guest'  => array_values( array_filter( array_map( 'intval', array(
				get_option( 'arshid6social_page_login',           0 ),
				get_option( 'arshid6social_page_register',        0 ),
				get_option( 'arshid6social_page_forgot_password', 0 ),
				get_option( 'arshid6social_page_reset_password',  0 ),
				get_option( 'arshid6social_page_home',            0 ),
			) ) ) ),
			'member' => array_values( array_filter( array_map( 'intval', array(
				get_option( 'arshid6social_page_activity',      0 ),
				get_option( 'arshid6social_page_dashboard',     0 ),
				get_option( 'arshid6social_page_groups',        0 ),
				get_option( 'arshid6social_page_messages',      0 ),
				get_option( 'arshid6social_page_notifications', 0 ),
				get_option( 'arshid6social_page_saved_posts',   0 ),
			) ) ) ),
		);
		return $ids;
	}

	/**
	 * Hides guest-only or member-only nav items based on login status.
	 *
	 * Guest-only  : Login, Register  → visible only when NOT logged in.
	 * Member-only : Activity, Dashboard, Groups, Messages, Notifications, Saved Posts
	 *               → visible only when logged in.
	 *
	 * @param \WP_Post[] $items Menu items.
	 * @param object     $args  Menu arguments (unused).
	 * @return \WP_Post[]
	 */
	public function filter_nav_by_auth( array $items, $args ): array {
		$logged_in = is_user_logged_in();

		// Slugs that are guest-only (Login, Register, Forgot/Reset Password, Home splash).
		$guest_slugs = array( 'login', 'register', 'forgot-password', 'reset-password', 'home' );

		// Slugs that are member-only.
		$member_slugs = array( 'activity', 'dashboard', 'groups', 'messages', 'notifications', 'saved-posts' );

		// Page IDs loaded once per request from the static helper.
		$page_ids        = self::get_auth_page_ids();
		$guest_only_ids  = $page_ids['guest'];
		$member_only_ids = $page_ids['member'];

		return array_values( array_filter( $items, static function ( $item ) use (
			$logged_in, $guest_only_ids, $member_only_ids, $guest_slugs, $member_slugs
		) {
			$object_id = (int) $item->object_id;

			// Method 1: match by option page ID (works when options are set).
			$is_guest_page  = in_array( $object_id, $guest_only_ids, true );
			$is_member_page = in_array( $object_id, $member_only_ids, true );

			if ( ! $is_guest_page && ! $is_member_page ) {
				// Method 2: match by page post slug (works for page-type items).
				if ( 'page' === $item->object && $object_id ) {
					$slug = (string) get_post_field( 'post_name', $object_id );
					if ( $slug ) {
						$is_guest_page  = in_array( $slug, $guest_slugs, true );
						$is_member_page = in_array( $slug, $member_slugs, true );
					}
				}
			}

			if ( ! $is_guest_page && ! $is_member_page && ! empty( $item->url ) ) {
				// Method 3: match by URL path segment (works for custom links too).
				$path     = (string) ( wp_parse_url( $item->url, PHP_URL_PATH ) ?? '' );
				$segments = array_filter( explode( '/', $path ) );
				$url_slug = strtolower( (string) end( $segments ) );
				if ( $url_slug ) {
					$is_guest_page  = in_array( $url_slug, $guest_slugs, true );
					$is_member_page = in_array( $url_slug, $member_slugs, true );
				}
			}

			if ( $logged_in && $is_guest_page ) {
				return false;
			}
			if ( ! $logged_in && $is_member_page ) {
				return false;
			}
			return true;
		} ) );
	}

	/**
	 * Appends unread-count badge spans inside nav menu links that point to the
	 * notifications or messages pages. Works with any theme that uses wp_nav_menu().
	 *
	 * @param string   $output  The menu item's starting HTML output.
	 * @param \WP_Post $item    Menu item data object.
	 * @param int      $depth   Depth of menu item (unused).
	 * @param object   $args    Menu arguments (unused).
	 * @return string
	 */
	public function inject_nav_badges( string $output, $item, int $depth, $args ): string {
		if ( ! is_user_logged_in() ) {
			return $output;
		}

		// IDs loaded once per request — no repeated get_option() per menu item.
		static $badge_map = null;
		if ( null === $badge_map ) {
			$badge_map = array(
				(int) get_option( 'arshid6social_page_notifications', 0 ) => 'arshid6social-notification-count',
				(int) get_option( 'arshid6social_page_messages',      0 ) => 'arshid6social-messages-count',
			);
		}

		$object_id = (int) $item->object_id;
		if ( ! isset( $badge_map[ $object_id ] ) || 0 === $object_id ) {
			return $output;
		}

		$badge_id = $badge_map[ $object_id ];
		$badge    = '<span id="' . esc_attr( $badge_id ) . '" class="arshid6social-nav-badge" hidden aria-hidden="true">0</span>';

		// Insert badge just before the closing </a> tag.
		return preg_replace( '#(</a>)#i', $badge . '$1', $output, 1 ) ?? $output;
	}

	/**
	 * Hides guest-only or member-only Gutenberg navigation-link blocks based on login status.
	 *
	 * Fires on render_block for core/navigation-link blocks (block-editor themes).
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block data including attrs.
	 * @return string Empty string to hide, original string to keep.
	 */
	public function filter_block_nav_by_auth( string $block_content, array $block ): string {
		$name = $block['blockName'] ?? '';
		if ( 'core/navigation-link' !== $name && 'core/navigation-submenu' !== $name ) {
			return $block_content;
		}

		$url = $block['attrs']['url'] ?? '';
		if ( ! $url ) {
			return $block_content;
		}

		// Extract trailing slug from URL (e.g. /login/ → "login").
		$path     = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		$segments = array_filter( explode( '/', $path ) );
		$slug     = strtolower( (string) ( end( $segments ) ?: '' ) );

		$guest_slugs  = array( 'login', 'register' );
		$member_slugs = array( 'activity', 'dashboard', 'groups', 'messages', 'notifications', 'saved-posts' );

		$page_id    = (int) ( $block['attrs']['id'] ?? 0 );
		$page_ids   = self::get_auth_page_ids();
		$guest_ids  = $page_ids['guest'];
		$member_ids = $page_ids['member'];

		$is_guest_page  = ( $page_id && in_array( $page_id, $guest_ids, true ) )
			|| ( $slug && in_array( $slug, $guest_slugs, true ) );

		$is_member_page = ( $page_id && in_array( $page_id, $member_ids, true ) )
			|| ( $slug && in_array( $slug, $member_slugs, true ) );

		$logged_in = is_user_logged_in();

		if ( $logged_in && $is_guest_page ) {
			return '';
		}
		if ( ! $logged_in && $is_member_page ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Writes _wp_page_template = 'no-sidebars' directly to the DB for all auth
	 * pages so the block theme uses the full-width template — same as login.
	 * Runs once per plugin version change.
	 */
	private function ensure_auth_page_templates(): void {
		if ( get_option( 'arshid6social_auth_tpl_ver' ) === ARSHID6SOCIAL_VERSION ) {
			return;
		}

		$options = array(
			'arshid6social_page_login'           => 'no-sidebars',
			'arshid6social_page_register'        => 'no-sidebars',
			'arshid6social_page_forgot_password' => 'no-sidebars',
			'arshid6social_page_reset_password'  => 'no-sidebars',
			'arshid6social_page_home'            => 'home-splash',
		);

		foreach ( $options as $opt => $tpl ) {
			$id = (int) get_option( $opt, 0 );
			if ( $id ) {
				update_post_meta( $id, '_wp_page_template', $tpl );
			}
		}

		update_option( 'arshid6social_auth_tpl_ver', ARSHID6SOCIAL_VERSION );
	}

	/**
	 * Redirects auth-aware pages at the right time (before output).
	 */
	public function handle_auth_page_redirects(): void {
		$page_ids     = self::get_auth_page_ids();
		$login_id     = (int) get_option( 'arshid6social_page_login', 0 );
		$dashboard_id = (int) get_option( 'arshid6social_page_dashboard', 0 );
		$home_id      = (int) get_option( 'arshid6social_page_home', 0 );

		// Home splash: redirect logged-in users to activity feed.
		if ( $home_id && is_page( $home_id ) && is_user_logged_in() ) {
			$activity_id = (int) get_option( 'arshid6social_page_activity', 0 );
			$url = $activity_id ? get_permalink( $activity_id ) : home_url( '/activity/' );
			wp_safe_redirect( $url );
			exit;
		}

		// Dashboard: send logged-in users to their profile, guests to login.
		if ( $dashboard_id && is_page( $dashboard_id ) ) {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				wp_safe_redirect( home_url( '/members/' . $user->user_nicename . '/' ) );
			} else {
				$url = $login_id ? get_permalink( $login_id ) : wp_login_url( get_permalink() );
				wp_safe_redirect( $url );
			}
			exit;
		}

		// Login page: send already-logged-in users to their profile.
		if ( $login_id && is_page( $login_id ) && is_user_logged_in() ) {
			$user = wp_get_current_user();
			wp_safe_redirect( add_query_arg( 'arshid6social_notice', 'logout_first', home_url( '/members/' . $user->user_nicename . '/' ) ) );
			exit;
		}

		// Register, forgot-password, reset-password: redirect logged-in users away with a notice.
		if ( is_user_logged_in() ) {
			$register_id = (int) get_option( 'arshid6social_page_register', 0 );
			$forgot_id   = (int) get_option( 'arshid6social_page_forgot_password', 0 );
			$reset_id    = (int) get_option( 'arshid6social_page_reset_password', 0 );

			$guest_only_pages = array_filter( array( $register_id, $forgot_id, $reset_id ) );

			if ( $guest_only_pages && is_page( $guest_only_pages ) ) {
				$activity_id = (int) get_option( 'arshid6social_page_activity', 0 );
				$url         = $activity_id ? get_permalink( $activity_id ) : home_url( '/' );
				wp_safe_redirect( add_query_arg( 'arshid6social_notice', 'logout_first', $url ) );
				exit;
			}
		}
	}

	public function redirect_after_login( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( ! ( $user instanceof \WP_User ) ) {
			return $redirect_to;
		}

		$activity_id = (int) get_option( 'arshid6social_page_activity', 0 );
		if ( $activity_id ) {
			return get_permalink( $activity_id );
		}

		return $redirect_to;
	}

	/**
	 * Adds body classes for the cinematic splash pages (home, login, register, etc.)
	 * so CSS can hide the sidebar and strip theme chrome.
	 *
	 * @param string[] $classes
	 * @return string[]
	 */
	public function home_splash_body_class( array $classes ): array {
		$splash_options = array(
			'arshid6social_page_home',
			'arshid6social_page_login',
			'arshid6social_page_register',
			'arshid6social_page_forgot_password',
			'arshid6social_page_reset_password',
		);

		foreach ( $splash_options as $opt ) {
			$id = (int) get_option( $opt, 0 );
			if ( $id && is_page( $id ) ) {
				$classes[] = 'arshid6social-home-splash-page';
				break;
			}
		}

		return $classes;
	}
}
