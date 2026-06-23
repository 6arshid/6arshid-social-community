<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Social Embeds – provider registry.
 *
 * Each provider definition:
 *   id              string   Unique slug (matches option key suffix).
 *   name            string   Display name.
 *   patterns        string[] Regexes to detect URLs (PCRE, u flag).
 *   method          string   'oembed' | 'iframe' | 'og'.
 *   oembed_endpoint string   Full oEmbed JSON endpoint (oembed method only).
 *   token_option    string   Option name for FB/IG access token (optional).
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Social_Embeds_Providers {

	/** @var array<string, array<string, mixed>> */
	private static array $registry = array();

	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		/**
		 * Filter the social-embed provider registry.
		 *
		 * Each entry must include: id, name, patterns (array), method (oembed|iframe|og).
		 * Optional: oembed_endpoint, token_option, iframe_builder.
		 *
		 * @param array<string, array<string, mixed>> $providers
		 */
		self::$registry = (array) apply_filters( 'arshid6social_embed_providers', self::defaults() );
	}

	/** @return array<string, array<string, mixed>> */
	public static function all(): array {
		self::init();
		return self::$registry;
	}

	/** @return array<string, array<string, mixed>> Providers whose settings checkbox is on. */
	public static function enabled(): array {
		self::init();
		return array_filter(
			self::$registry,
			static fn( $p ) => (bool) get_option( 'arshid6social_eng_embed_' . $p['id'], '1' )
		);
	}

	/**
	 * Return the first enabled provider that matches $url, or null.
	 *
	 * @param string $url
	 * @return array<string, mixed>|null
	 */
	public static function match( string $url ): ?array {
		foreach ( self::enabled() as $provider ) {
			foreach ( $provider['patterns'] as $pattern ) {
				if ( preg_match( $pattern, $url ) ) {
					return $provider;
				}
			}
		}
		return null;
	}

	/** @return array<string, array<string, mixed>> */
	private static function defaults(): array {
		$p = array();

		$p['youtube'] = array(
			'id'              => 'youtube',
			'name'            => 'YouTube',
			'patterns'        => array(
				'#https?://(?:www\.)?youtube\.com/watch\?(?:[^&\s]*&)*v=([A-Za-z0-9_-]+)#u',
				'#https?://youtu\.be/([A-Za-z0-9_-]+)#u',
				'#https?://(?:www\.)?youtube\.com/shorts/([A-Za-z0-9_-]+)#u',
				'#https?://(?:www\.)?youtube\.com/embed/([A-Za-z0-9_-]+)#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://www.youtube.com/oembed',
		);

		$p['vimeo'] = array(
			'id'              => 'vimeo',
			'name'            => 'Vimeo',
			'patterns'        => array(
				'#https?://(?:www\.)?vimeo\.com/(\d+)(?:/[^\s<>"\']*)?#u',
				'#https?://player\.vimeo\.com/video/(\d+)#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://vimeo.com/api/oembed.json',
		);

		$p['twitter'] = array(
			'id'              => 'twitter',
			'name'            => 'X / Twitter',
			'patterns'        => array(
				'#https?://(?:www\.)?twitter\.com/\w{1,50}/status/\d+#u',
				'#https?://x\.com/\w{1,50}/status/\d+#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://publish.twitter.com/oembed',
		);

		$p['instagram'] = array(
			'id'           => 'instagram',
			'name'         => 'Instagram',
			'patterns'     => array(
				'#https?://(?:www\.)?instagram\.com/(?:p|reel|tv)/([A-Za-z0-9_-]+)/?#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://graph.facebook.com/v18.0/instagram_oembed',
			'token_option'    => 'arshid6social_eng_embed_ig_token',
		);

		$p['facebook'] = array(
			'id'           => 'facebook',
			'name'         => 'Facebook',
			'patterns'     => array(
				'#https?://(?:www\.)?facebook\.com/(?:video\.php\?v=\d+|[^/\s]+/(?:videos|posts|photos)/[^\s<>"\']+)#u',
				'#https?://fb\.watch/[A-Za-z0-9_-]+#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://graph.facebook.com/v18.0/oembed_post',
			'token_option'    => 'arshid6social_eng_embed_fb_token',
		);

		$p['tiktok'] = array(
			'id'              => 'tiktok',
			'name'            => 'TikTok',
			'patterns'        => array(
				'#https?://(?:www\.)?tiktok\.com/@[^/\s]+/video/\d+#u',
				'#https?://vm\.tiktok\.com/[A-Za-z0-9]+/?#u',
				'#https?://(?:www\.)?tiktok\.com/t/[A-Za-z0-9]+/?#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://www.tiktok.com/oembed',
		);

		$p['spotify'] = array(
			'id'              => 'spotify',
			'name'            => 'Spotify',
			'patterns'        => array(
				'#https?://open\.spotify\.com/(track|album|playlist|episode|show)/([A-Za-z0-9]+)#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://open.spotify.com/oembed',
		);

		$p['soundcloud'] = array(
			'id'              => 'soundcloud',
			'name'            => 'SoundCloud',
			'patterns'        => array(
				'#https?://soundcloud\.com/[^/\s<>"\']+/[^/\s<>"\']+#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://soundcloud.com/oembed',
		);

		$p['pinterest'] = array(
			'id'              => 'pinterest',
			'name'            => 'Pinterest',
			'patterns'        => array(
				'#https?://(?:www\.)?pinterest\.[a-z]{2,6}(?:\.[a-z]{2})?/pin/\d+/?#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://www.pinterest.com/oembed.json',
		);

		$p['reddit'] = array(
			'id'              => 'reddit',
			'name'            => 'Reddit',
			'patterns'        => array(
				'#https?://(?:www\.)?reddit\.com/r/[A-Za-z0-9_]+/comments/[A-Za-z0-9]+[^\s<>"\']*#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://www.reddit.com/oembed',
		);

		$p['twitch'] = array(
			'id'      => 'twitch',
			'name'    => 'Twitch',
			'patterns' => array(
				'#https?://clips\.twitch\.tv/[A-Za-z0-9_-]+#u',
				'#https?://(?:www\.)?twitch\.tv/[A-Za-z0-9_]+/clip/[A-Za-z0-9_-]+#u',
				'#https?://(?:www\.)?twitch\.tv/[A-Za-z0-9_]+/?(?:\?[^\s<>"\']*)?$#u',
			),
			'method'  => 'iframe',
		);

		$p['dailymotion'] = array(
			'id'              => 'dailymotion',
			'name'            => 'Dailymotion',
			'patterns'        => array(
				'#https?://(?:www\.)?dailymotion\.com/video/([A-Za-z0-9]+)#u',
				'#https?://dai\.ly/([A-Za-z0-9]+)#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://www.dailymotion.com/services/oembed',
		);

		$p['apple_music'] = array(
			'id'      => 'apple_music',
			'name'    => 'Apple Music / Podcasts',
			'patterns' => array(
				'#https?://music\.apple\.com/[a-z]{2}/(?:album|playlist|artist|song)/[^\s<>"\']+#u',
				'#https?://podcasts\.apple\.com/[a-z]{2}/podcast/[^\s<>"\']+#u',
			),
			'method'  => 'iframe',
		);

		$p['linkedin'] = array(
			'id'      => 'linkedin',
			'name'    => 'LinkedIn',
			'patterns' => array(
				'#https?://(?:www\.)?linkedin\.com/(?:posts|pulse)/[^\s<>"\']+#u',
			),
			'method'  => 'og',
		);

		$p['telegram'] = array(
			'id'      => 'telegram',
			'name'    => 'Telegram',
			'patterns' => array(
				'#https?://t\.me/([A-Za-z0-9_]{5,})/(\d+)#u',
			),
			'method'  => 'iframe',
		);

		$p['threads'] = array(
			'id'              => 'threads',
			'name'            => 'Threads',
			'patterns'        => array(
				'#https?://(?:www\.)?threads\.(?:net|com)/@?[^/\s]+/post/[A-Za-z0-9_-]+#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://www.threads.net/oembed/',
		);

		$p['bluesky'] = array(
			'id'      => 'bluesky',
			'name'    => 'Bluesky',
			'patterns' => array(
				'#https?://bsky\.app/profile/[^/\s]+/post/[A-Za-z0-9]+#u',
			),
			'method'  => 'og',
		);

		$p['aparat'] = array(
			'id'              => 'aparat',
			'name'            => 'آپارات (Aparat)',
			'patterns'        => array(
				'#https?://(?:www\.)?aparat\.com/v/([A-Za-z0-9]+)#u',
			),
			'method'          => 'oembed',
			'oembed_endpoint' => 'https://www.aparat.com/oembed.json',
		);

		// Generic OG fallback — matches any URL; must stay last.
		$p['og_generic'] = array(
			'id'      => 'og_generic',
			'name'    => __( 'Generic Link Preview (Open Graph)', 'social-network-6' ),
			'patterns' => array(
				'#https?://[^\s<>"\']+#u',
			),
			'method'  => 'og',
		);

		return $p;
	}
}
