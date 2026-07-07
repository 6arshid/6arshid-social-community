<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Social Embeds – SSRF-safe URL fetcher + oEmbed/OG resolver.
 *
 * Security guarantees:
 *  - Only http / https schemes accepted.
 *  - DNS-resolved IP checked against RFC-1918 / loopback / link-local CIDRs.
 *  - Response body capped at 512 KB.
 *  - Uses wp_safe_remote_get with explicit timeout.
 *  - Rate-limited to 30 remote fetches per minute site-wide (transient).
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Social_Embeds_Fetcher {

	/** IPv4 + IPv6 CIDRs that must never be fetched. */
	private const BLOCKED_CIDRS = array(
		'127.0.0.0/8',
		'10.0.0.0/8',
		'172.16.0.0/12',
		'192.168.0.0/16',
		'169.254.0.0/16',   // link-local / AWS metadata
		'100.64.0.0/10',    // shared address space
		'::1/128',
		'fc00::/7',
		'fe80::/10',
	);

	/** Query parameters stripped when "strip tracking params" is enabled. */
	private const TRACKING_PARAMS = array(
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
		'fbclid', 'gclid', 'msclkid', 'dclid', 'zanpid',
		'igshid', 'twclid', 'ref',
		'_hsenc', '_hsmi', 'mc_eid', 'mc_cid',
	);

	/**
	 * Validate a URL for SSRF safety.
	 *
	 * Returns true only for public-internet HTTP(S) URLs.
	 */
	public static function validate_url( string $url ): bool {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return false;
		}

		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['host'] ) || empty( $parsed['scheme'] ) ) {
			return false;
		}

		if ( ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = strtolower( $parsed['host'] );

		// Block localhost aliases.
		if ( in_array( $host, array( 'localhost', 'localhost.localdomain', '0.0.0.0' ), true ) ) {
			return false;
		}

		// Resolve host → IP; block if resolution fails (prevents DNS rebinding risk).
		$ip = gethostbyname( $host );
		if ( $ip === $host && ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		foreach ( self::BLOCKED_CIDRS as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) {
				return false;
			}
		}

		return true;
	}

	/** Strip known tracking query parameters from a URL. */
	public static function strip_tracking_params( string $url ): string {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['query'] ) ) {
			return $url;
		}

		parse_str( $parsed['query'], $params );
		foreach ( self::TRACKING_PARAMS as $key ) {
			unset( $params[ $key ] );
		}

		$base  = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
		$base .= ( $parsed['path'] ?? '' );
		$query = $params ? '?' . http_build_query( $params ) : '';
		$frag  = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

		return $base . $query . $frag;
	}

	/**
	 * Fetch embed data for a URL.
	 *
	 * Checks the DB cache first.  On miss, calls the correct resolver for the
	 * provider's method.  Falls back to OG on oEmbed failure when the setting
	 * is on.  Stores successful results in cache.
	 *
	 * @param string               $url
	 * @param array<string, mixed> $provider
	 * @return array{html:string,data:array<string,mixed>}|null
	 */
	public static function fetch( string $url, array $provider ): ?array {
		$cache_hours = max( 1, (int) get_option( 'arshid6social_eng_embed_cache_hours', 24 ) );

		$cached = Social_Embeds_Cache::get( $url );
		if ( null !== $cached ) {
			return $cached;
		}

		// Site-wide rate limit: 30 remote fetches / minute.
		$rate_key = 'arshid6social_embed_fetch_rate';
		$rate     = (int) get_transient( $rate_key );
		if ( $rate >= 30 ) {
			return null;
		}
		set_transient( $rate_key, $rate + 1, MINUTE_IN_SECONDS );

		if ( ! self::validate_url( $url ) ) {
			return null;
		}

		$result = null;

		switch ( $provider['method'] ) {
			case 'oembed':
				$result = self::fetch_oembed( $url, $provider );
				break;
			case 'iframe':
				$result = self::build_iframe( $url, $provider );
				break;
			case 'og':
			default:
				$result = self::fetch_og( $url );
				break;
		}

		// OG fallback when oEmbed returned nothing.
		if ( null === $result && 'oembed' === ( $provider['method'] ?? '' )
			&& get_option( 'arshid6social_eng_embed_og_fallback', '1' ) ) {
			$result = self::fetch_og( $url );
		}

		if ( null !== $result ) {
			Social_Embeds_Cache::set( $url, $provider['id'], $result['html'], $result['data'], $cache_hours );
		}

		return $result;
	}

	// ── oEmbed ────────────────────────────────────────────────────────────────

	/**
	 * @param string               $url
	 * @param array<string, mixed> $provider
	 * @return array{html:string,data:array<string,mixed>}|null
	 */
	private static function fetch_oembed( string $url, array $provider ): ?array {
		$endpoint = $provider['oembed_endpoint'] ?? '';
		if ( ! $endpoint ) {
			return null;
		}

		$args = array( 'url' => $url, 'format' => 'json' );

		// Token-gated providers (Instagram, Facebook).
		if ( ! empty( $provider['token_option'] ) ) {
			$token = (string) get_option( $provider['token_option'], '' );
			if ( ! $token ) {
				return null; // No token → fall through to OG.
			}
			$args['access_token'] = $token;
		}

		$fetch_url = add_query_arg( $args, $endpoint );

		if ( ! self::validate_url( $fetch_url ) ) {
			return null;
		}

		$response = wp_safe_remote_get(
			$fetch_url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > 524288 ) { // 512 KB cap.
			return null;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$html = (string) ( $data['html'] ?? '' );

		// Some providers return no html key (e.g., Spotify on certain plans).
		if ( ! $html ) {
			$html = Social_Embeds_Renderer::build_from_oembed_data( $data, $url, $provider );
		}

		if ( ! $html ) {
			return null;
		}

		return array( 'html' => $html, 'data' => $data );
	}

	// ── iframe builders ───────────────────────────────────────────────────────

	/**
	 * @param string               $url
	 * @param array<string, mixed> $provider
	 * @return array{html:string,data:array<string,mixed>}|null
	 */
	private static function build_iframe( string $url, array $provider ): ?array {
		switch ( $provider['id'] ) {
			case 'twitch':
				return self::build_twitch( $url );
			case 'apple_music':
				return self::build_apple_music( $url );
			case 'telegram':
				return self::build_telegram( $url );
		}
		return null;
	}

	/** @return array{html:string,data:array<string,mixed>}|null */
	private static function build_twitch( string $url ): ?array {
		$parent   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$sandbox  = 'allow-scripts allow-same-origin allow-popups allow-presentation';
		$common   = 'allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" sandbox="' . esc_attr( $sandbox ) . '"';

		// Clip.
		if ( preg_match( '#twitch\.tv/[^/]+/clip/([A-Za-z0-9_-]+)#', $url, $m )
			|| preg_match( '#clips\.twitch\.tv/([A-Za-z0-9_-]+)#', $url, $m ) ) {
			$src  = 'https://clips.twitch.tv/embed?clip=' . urlencode( $m[1] ) . '&parent=' . urlencode( $parent );
			$html = '<iframe src="' . esc_url( $src ) . '" ' . $common . '></iframe>';
			return array( 'html' => $html, 'data' => array( 'type' => 'video', 'provider_name' => 'Twitch' ) );
		}

		// Live channel.
		if ( preg_match( '#twitch\.tv/([A-Za-z0-9_]+)#', $url, $m ) ) {
			$src  = 'https://player.twitch.tv/?channel=' . urlencode( $m[1] ) . '&parent=' . urlencode( $parent );
			$html = '<iframe src="' . esc_url( $src ) . '" ' . $common . '></iframe>';
			return array( 'html' => $html, 'data' => array( 'type' => 'video', 'provider_name' => 'Twitch', 'title' => $m[1] ) );
		}

		return null;
	}

	/** @return array{html:string,data:array<string,mixed>}|null */
	private static function build_apple_music( string $url ): ?array {
		$embed = str_replace(
			array( 'music.apple.com', 'podcasts.apple.com' ),
			array( 'embed.music.apple.com', 'embed.podcasts.apple.com' ),
			$url
		);
		$sandbox = 'allow-forms allow-popups allow-same-origin allow-scripts allow-top-navigation-by-user-activation';
		$html    = '<iframe allow="autoplay *; encrypted-media *; fullscreen *; clipboard-write" src="' . esc_url( $embed ) . '" loading="lazy" sandbox="' . esc_attr( $sandbox ) . '"></iframe>';
		return array( 'html' => $html, 'data' => array( 'type' => 'rich', 'provider_name' => 'Apple Music' ) );
	}

	/** @return array{html:string,data:array<string,mixed>}|null */
	private static function build_telegram( string $url ): ?array {
		if ( ! preg_match( '#t\.me/([A-Za-z0-9_]{5,})/(\d+)#', $url, $m ) ) {
			return null;
		}
		$src     = 'https://t.me/' . $m[1] . '/' . $m[2] . '?embed=1&mode=tme';
		$sandbox = 'allow-scripts allow-same-origin allow-popups';
		$html    = '<iframe src="' . esc_url( $src ) . '" loading="lazy" referrerpolicy="no-referrer" sandbox="' . esc_attr( $sandbox ) . '"></iframe>';
		return array( 'html' => $html, 'data' => array( 'type' => 'rich', 'provider_name' => 'Telegram' ) );
	}

	// ── Open Graph ────────────────────────────────────────────────────────────

	/**
	 * Fetch Open Graph / Twitter Card tags and build a link-preview card.
	 *
	 * @param string $url
	 * @return array{html:string,data:array<string,mixed>}|null
	 */
	public static function fetch_og( string $url ): ?array {
		if ( ! self::validate_url( $url ) ) {
			return null;
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo( 'version' ) . '; +' . home_url() . ')',
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return null;
		}
		if ( strlen( $body ) > 524288 ) {
			$body = substr( $body, 0, 524288 );
		}

		$data = self::parse_og_tags( $body, $url );
		if ( empty( $data['title'] ) && empty( $data['image'] ) ) {
			return null;
		}

		$html = Social_Embeds_Renderer::build_og_card( $data, $url );

		return array( 'html' => $html, 'data' => $data );
	}

	/**
	 * Parse OG / Twitter Card meta tags from raw HTML.
	 *
	 * @param string $html
	 * @param string $url
	 * @return array<string, string>
	 */
	private static function parse_og_tags( string $html, string $url ): array {
		$data = array( 'url' => $url );

		// We only need the <head> section.
		$head = '';
		if ( preg_match( '/<head[^>]*>(.*?)<\/head>/is', $html, $m ) ) {
			$head = $m[1];
		} else {
			$head = substr( $html, 0, 8192 );
		}

		$meta_re = '/<meta\s[^>]+>/i';
		preg_match_all( $meta_re, $head, $tags );

		$og = array();
		foreach ( $tags[0] as $tag ) {
			// property="og:x" content="..."
			if ( preg_match( '/(?:property|name)=["\']([^"\']+)["\']/', $tag, $prop )
				&& preg_match( '/content=["\']([^"\']*)["\']/', $tag, $cont ) ) {
				$og[ $prop[1] ] = html_entity_decode( $cont[1], ENT_QUOTES | ENT_HTML5 );
			}
		}

		$data['title']       = $og['og:title'] ?? $og['twitter:title'] ?? '';
		$data['description'] = $og['og:description'] ?? $og['twitter:description'] ?? '';
		$data['image']       = $og['og:image'] ?? $og['twitter:image'] ?? '';
		$data['site_name']   = $og['og:site_name'] ?? ( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		// <title> fallback.
		if ( ! $data['title'] && preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $head, $m ) ) {
			$data['title'] = html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5 );
		}

		if ( $data['image'] ) {
			$data['image'] = esc_url_raw( $data['image'] );
		}

		return array_filter( $data );
	}

	// ── SSRF helpers ──────────────────────────────────────────────────────────

	private static function ip_in_cidr( string $ip, string $cidr ): bool {
		[ $subnet, $bits ] = explode( '/', $cidr, 2 );

		// IPv6.
		if ( str_contains( $cidr, ':' ) ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				return false;
			}
			$ip_bin  = inet_pton( $ip );
			$sub_bin = inet_pton( $subnet );
			if ( false === $ip_bin || false === $sub_bin ) {
				return false;
			}
			$prefix = (int) $bits;
			$bytes  = (int) floor( $prefix / 8 );
			$rem    = $prefix % 8;
			if ( substr( $ip_bin, 0, $bytes ) !== substr( $sub_bin, 0, $bytes ) ) {
				return false;
			}
			if ( $rem > 0 && isset( $ip_bin[ $bytes ], $sub_bin[ $bytes ] ) ) {
				$mask = 0xFF & ( 0xFF << ( 8 - $rem ) );
				return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $sub_bin[ $bytes ] ) & $mask );
			}
			return true;
		}

		// IPv4.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}
		$mask = -1 << ( 32 - (int) $bits );
		return ( ip2long( $ip ) & $mask ) === ( ip2long( $subnet ) & $mask );
	}
}
