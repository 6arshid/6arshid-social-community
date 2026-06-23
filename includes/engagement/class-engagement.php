<?php
namespace Arshid6Social\Engagement;

/**
 * Engagement Pack – main loader.
 *
 * @package Arshid6Social\Engagement
 */

defined( 'ABSPATH' ) || exit;

class Engagement {

	private static ?Engagement $instance = null;

	/** @var array<string, object> Loaded feature instances. */
	private array $features = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function boot(): void {
		Engagement_DB::maybe_upgrade();
		new Engagement_Settings();
		$this->load_features();
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	private function load_features(): void {
		$map = array(
			'hashtags'              => Features\Hashtags::class,
			'tag_friends'           => Features\Tag_Friends::class,
			'bookmarks'             => Features\Bookmarks::class,
			'sticky_posts'          => Features\Sticky_Posts::class,
			'share_posts'           => Features\Share_Posts::class,
			'polls'                 => Features\Polls::class,
			'comments_gifs'         => Features\Comments_GIFs::class,
			'comments_attachments'  => Features\Comments_Attachments::class,
			'messages_attachments'  => Features\Messages_Attachments::class,
			'social_share_external' => Features\Social_Share_External::class,
			'social_embeds'         => Features\Social_Embeds::class,
		);

		foreach ( $map as $key => $class ) {
			if ( ! Engagement_Settings::enabled( $key ) ) {
				continue;
			}
			if ( ! class_exists( $class ) ) {
				continue;
			}
			try {
				$this->features[ $key ] = new $class();
			} catch ( \Throwable $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[WPSN Engagement] Failed to load feature "' . $key . '": ' . $e->getMessage() );
				}
			}
		}

		// Advanced polls extends polls – only load if both are on.
		if ( Engagement_Settings::enabled( 'advanced_polls' ) && Engagement_Settings::enabled( 'polls' ) ) {
			try {
				if ( class_exists( Features\Advanced_Polls::class ) ) {
					$this->features['advanced_polls'] = new Features\Advanced_Polls();
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[WPSN Engagement] Failed to load advanced_polls: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				}
			}
		}
	}

	public function register_rest_routes(): void {
		foreach ( $this->features as $feature ) {
			if ( method_exists( $feature, 'register_rest_routes' ) ) {
				$feature->register_rest_routes();
			}
		}
	}

	public function enqueue_assets(): void {
		// Load on plugin pages (arshid6social-main enqueued) OR on the Saved Posts page.
		$on_saved_posts_page = false;
		$saved_posts_id      = (int) get_option( 'arshid6social_page_saved_posts', 0 );
		if ( $saved_posts_id && is_page( $saved_posts_id ) ) {
			$on_saved_posts_page = true;
		}
		// Fallback: detect via shortcode in post content (covers cases where the option is not set).
		if ( ! $on_saved_posts_page && is_singular() ) {
			global $post;
			if ( $post && (
				has_shortcode( $post->post_content, 'arshid6social_bookmarks' ) ||
				has_shortcode( $post->post_content, 'sn_bookmarks' )
			) ) {
				$on_saved_posts_page = true;
			}
		}
		if ( $on_saved_posts_page ) {
			wp_enqueue_style( 'arshid6social-main', ARSHID6SOCIAL_ASSETS_URL . 'css/social-network.css', array(), ARSHID6SOCIAL_VERSION );
		}
		if ( ! wp_script_is( 'arshid6social-main', 'enqueued' ) && ! $on_saved_posts_page ) {
			return;
		}

		$url     = ARSHID6SOCIAL_ASSETS_URL . 'engagement/';
		$dir     = ARSHID6SOCIAL_PLUGIN_DIR . 'assets/engagement/';

		// Use file modification time as version so any upload immediately busts browser cache.
		$css_ver = (string) ( @filemtime( $dir . 'css/engagement.css' ) ?: ARSHID6SOCIAL_VERSION );
		wp_enqueue_style( 'arshid6social-engagement', $url . 'css/engagement.css', array(), $css_ver );

		// Build enabled map from OPTIONS (not from loaded classes) so buttons
		// appear even when a feature class fails to instantiate.
		$all_keys   = array(
			'hashtags', 'tag_friends', 'bookmarks', 'sticky_posts',
			'share_posts', 'polls', 'advanced_polls', 'comments_gifs',
			'comments_attachments', 'messages_attachments',
			'social_share_external', 'social_embeds',
		);
		$enabled_js = array();
		foreach ( $all_keys as $key ) {
			if ( Engagement_Settings::enabled( $key ) ) {
				$enabled_js[ $key ] = true;
			}
		}

		// External share CSS (loaded separately so it can be cached independently).
		if ( Engagement_Settings::enabled( 'social_share_external' ) ) {
			$ext_css_ver = (string) ( @filemtime( $dir . 'css/social-share-external.css' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_style( 'arshid6social-ext-share', $url . 'css/social-share-external.css', array( 'arshid6social-engagement' ), $ext_css_ver );
		}

		// Social Embeds CSS.
		if ( Engagement_Settings::enabled( 'social_embeds' ) ) {
			$emb_css_ver = (string) ( @filemtime( $dir . 'css/social-embeds.css' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_style( 'arshid6social-social-embeds', $url . 'css/social-embeds.css', array( 'arshid6social-engagement' ), $emb_css_ver );
		}

		// Per-feature scripts — version = file mtime so cache breaks on every upload.
		// NOTE: social_embeds is intentionally omitted here; it is enqueued AFTER
		// arshid6social-engagement so that window.ARSHID6SOCIALEng is already defined when
		// social-embeds-composer.js runs.
		$scripts = array(
			'hashtags'              => 'hashtags.js',
			'tag_friends'           => 'tag-friends.js',
			'bookmarks'             => 'bookmarks.js',
			'sticky_posts'          => 'sticky.js',
			'share_posts'           => 'share.js',
			'polls'                 => 'polls.js',
			'comments_gifs'         => 'comments-gifs.js',
			'comments_attachments'  => 'comments-attachments.js',
			'messages_attachments'  => 'messages-attachments.js',
			'social_share_external' => 'social-share-external.js',
		);

		$feature_handles = array();
		foreach ( $scripts as $key => $file ) {
			if ( Engagement_Settings::enabled( $key ) ) {
				$handle            = 'arshid6social-eng-' . str_replace( '_', '-', $key );
				$feature_handles[] = $handle;
				$js_ver            = (string) ( @filemtime( $dir . 'js/' . $file ) ?: ARSHID6SOCIAL_VERSION );
				wp_register_script( $handle, $url . 'js/' . $file, array(), $js_ver, true );
				wp_enqueue_script( $handle );
			}
		}

		$eng_ver = (string) ( @filemtime( $dir . 'js/engagement.js' ) ?: ARSHID6SOCIAL_VERSION );
		wp_enqueue_script(
			'arshid6social-engagement',
			$url . 'js/engagement.js',
			$feature_handles,
			$eng_ver,
			true
		);

		$localize_data = array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'arshid6social_ajax_nonce' ),
			'restUrl'   => rest_url( 'arshid6social/v1/' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'enabled'   => $enabled_js,
			'userId'    => get_current_user_id(),
			'isRtl'     => is_rtl(),
		);

		// Attach external-share settings when the feature is on.
		if ( Engagement_Settings::enabled( 'social_share_external' ) ) {
			$enabled_nets = (array) get_option(
				'arshid6social_eng_social_share_networks',
				Features\Social_Share_External::default_networks()
			);

			// Respect admin drag-and-drop order.
			$order_raw = get_option( 'arshid6social_eng_social_share_network_order', '' );
			if ( $order_raw ) {
				$order_keys   = array_filter( array_map( 'sanitize_key', explode( ',', $order_raw ) ) );
				$ordered_nets = array();
				foreach ( $order_keys as $k ) {
					if ( in_array( $k, $enabled_nets, true ) ) {
						$ordered_nets[] = $k;
					}
				}
				// Append any enabled nets not yet in the saved order.
				foreach ( $enabled_nets as $k ) {
					if ( ! in_array( $k, $ordered_nets, true ) ) {
						$ordered_nets[] = $k;
					}
				}
				$enabled_nets = $ordered_nets;
			}

			$localize_data['extShare'] = array(
				'networks'   => Features\Social_Share_External::enabled_networks_for_js( $enabled_nets ),
				'position'   => get_option( 'arshid6social_eng_social_share_position', 'bottom' ),
				'pages'      => (array) get_option( 'arshid6social_eng_social_share_pages', array( 'feed', 'single', 'profile', 'group' ) ),
				'style'      => get_option( 'arshid6social_eng_social_share_style', 'icon_text' ),
				'maxVisible' => (int) get_option( 'arshid6social_eng_social_share_max_visible', 8 ),
				'useNative'  => (bool) get_option( 'arshid6social_eng_social_share_native', true ),
				'i18n'       => array(
					'share'          => __( 'Share', 'social-network-6' ),
					'shareTitle'     => __( 'Share this post', 'social-network-6' ),
					'shareTo'        => __( 'Share to', 'social-network-6' ),
					'search'         => __( 'Search networks…', 'social-network-6' ),
					'close'          => __( 'Close', 'social-network-6' ),
					'copied'         => __( 'Link copied!', 'social-network-6' ),
					'more'           => __( 'Show more networks', 'social-network-6' ),
					'sendAsMessage'  => __( 'Send as Message', 'social-network-6' ),
					'dmSearchPlaceholder' => __( 'Search users…', 'social-network-6' ),
					'dmSent'         => __( 'Message sent!', 'social-network-6' ),
					'dmError'        => __( 'Could not send message.', 'social-network-6' ),
					'dmLoginRequired' => __( 'You must be logged in to send messages.', 'social-network-6' ),
					'back'           => __( 'Back', 'social-network-6' ),
				),
			);
		}

		// Attach social-embeds settings when the feature is on.
		if ( Engagement_Settings::enabled( 'social_embeds' ) ) {
			$localize_data['socialEmbeds'] = array(
				'lazyLoad'   => (bool) get_option( 'arshid6social_eng_embed_lazy_load', '1' ),
				'i18n'       => array(
					'dismissEmbed' => __( 'Remove embed preview', 'social-network-6' ),
					'loadEmbed'    => __( 'Click to load', 'social-network-6' ),
				),
			);
		}

		wp_localize_script( 'arshid6social-engagement', 'ARSHID6SOCIALEng', $localize_data );

		// social-embeds-composer.js must load AFTER arshid6social-engagement so that
		// window.ARSHID6SOCIALEng (localized above) is already defined when the script runs.
		if ( Engagement_Settings::enabled( 'social_embeds' ) ) {
			$emb_js_ver = (string) ( @filemtime( $dir . 'js/social-embeds-composer.js' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_script(
				'arshid6social-eng-social-embeds',
				$url . 'js/social-embeds-composer.js',
				array( 'arshid6social-engagement' ),
				$emb_js_ver,
				true
			);
		}

		// Dedicated JS for the Saved Posts page (infinite scroll feed).
		if ( $on_saved_posts_page ) {
			$bp_ver = (string) ( @filemtime( $dir . 'js/bookmarks-page.js' ) ?: ARSHID6SOCIAL_VERSION );
			wp_enqueue_script(
				'arshid6social-bookmarks-page',
				$url . 'js/bookmarks-page.js',
				array( 'arshid6social-engagement' ),
				$bp_ver,
				true
			);
		}
	}

	public function feature( string $key ): ?object {
		return $this->features[ $key ] ?? null;
	}

}
