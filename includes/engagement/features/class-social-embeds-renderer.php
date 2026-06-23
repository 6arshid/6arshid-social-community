<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Social Embeds – HTML renderer.
 *
 * Layout strategy
 * ───────────────
 * Video / audio providers (oEmbed / iframe):
 *   – The outer wrapper gets a `padding-bottom` percentage value computed from
 *     the aspect ratio so the container holds its proportions on every screen
 *     width without JavaScript.  height is always 0; all content is positioned
 *     absolutely inside.
 *   – In lazy mode the placeholder fills the wrapper absolutely; thumbnail is
 *     also absolute so it cannot push the container taller.
 *   – In eager mode the iframe fills the wrapper absolutely.
 *
 * OG link-preview cards (method = 'og'):
 *   – No aspect-ratio container; the card has natural block height.
 *   – Lazy mode is skipped for OG cards because they contain no tracking pixels
 *     (the HTML is server-generated, no third-party request at render time).
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Social_Embeds_Renderer {

	/**
	 * Wrap raw provider HTML in the standard responsive embed container.
	 *
	 * @param string               $raw_html  Provider-supplied embed HTML.
	 * @param array<string, mixed> $data      oEmbed / OG metadata.
	 * @param string               $url       Original source URL.
	 * @param array<string, mixed> $provider  Provider definition.
	 * @return string
	 */
	public static function wrap( string $raw_html, array $data, string $url, array $provider ): string {
		$method    = $provider['method'] ?? 'rich';
		$type      = $data['type'] ?? 'rich';
		$prov_id   = esc_attr( $provider['id'] );
		$prov_name = esc_html( $data['provider_name'] ?? ( $provider['name'] ?? '' ) );
		$title     = esc_attr( $data['title'] ?? ( $data['provider_name'] ?? ( $provider['name'] ?? '' ) ) );
		$thumb     = esc_url( $data['thumbnail_url'] ?? ( $data['image'] ?? '' ) );

		$classes = 'arshid6social-embed-wrap arshid6social-embed-type-' . esc_attr( $type ) . ' arshid6social-embed-' . $prov_id;

		$outer_attrs = 'class="' . $classes . '"'
			. ' data-provider="' . $prov_id . '"'
			. ' data-url="' . esc_attr( $url ) . '"';

		// ── OG cards — no aspect-ratio container, no lazy mode ──────────────────
		if ( 'og' === $method ) {
			$og_attrs = 'class="' . $classes . ' arshid6social-embed-og"'
				. ' data-provider="' . $prov_id . '"'
				. ' data-url="' . esc_attr( $url ) . '"';
			return '<div ' . $og_attrs . '>' . $raw_html . '</div>';
		}

		// ── Video / audio / iframe providers ────────────────────────────────────
		$pb    = self::padding_bottom( $data );          // e.g. "56.25%"
		$style = $pb ? ' style="padding-bottom:' . esc_attr( $pb ) . ';"' : '';

		// Sanitise iframes in the raw HTML: remove inline width / height attributes
		// so CSS controls sizing instead of the oEmbed-provided pixel values.
		$clean_html = self::sanitize_iframe_html( $raw_html );

		$lazy = (bool) get_option( 'arshid6social_eng_embed_lazy_load', '1' );

		if ( $lazy ) {
			return self::lazy_wrap(
				$clean_html, $outer_attrs, $style, $thumb, $title, $prov_name, $url
			);
		}

		return '<div ' . $outer_attrs . $style . '>' . $clean_html . '</div>';
	}

	// ── Lazy wrapper ──────────────────────────────────────────────────────────

	/**
	 * @param string $raw_html   Embed HTML stored as data attribute (not rendered yet).
	 * @param string $outer_attrs
	 * @param string $style      Inline padding-bottom style (may be empty).
	 * @param string $thumb      Thumbnail URL (may be empty).
	 * @param string $title      Escaped title for alt / aria.
	 * @param string $prov_name  Escaped provider name.
	 * @param string $url        Source URL.
	 * @return string
	 */
	private static function lazy_wrap(
		string $raw_html,
		string $outer_attrs,
		string $style,
		string $thumb,
		string $title,
		string $prov_name,
		string $url
	): string {
		$thumb_html = $thumb
			? '<img src="' . $thumb . '" alt="' . $title . '" loading="lazy" class="arshid6social-embed-thumb" aria-hidden="true" />'
			: '';

		$btn_label = $prov_name
			/* translators: %s: embed provider name */
			? sprintf( esc_attr__( 'Load %s embed', '6arshid social community' ), $prov_name )
			: esc_attr__( 'Load embed', '6arshid social community' );

		$click_label = $prov_name
			/* translators: %s: embed provider name */
			? sprintf( esc_html__( 'Click to load %s', '6arshid social community' ), $prov_name )
			: esc_html__( 'Click to load', '6arshid social community' );

		$load_btn = '<button type="button" class="arshid6social-embed-load-btn" aria-label="' . $btn_label . '">'
			. '<span class="arshid6social-embed-play-icon" aria-hidden="true"></span>'
			. '<span class="arshid6social-embed-load-label">' . $click_label . '</span>'
			. '</button>';

		$meta = '<div class="arshid6social-embed-meta">'
			. '<span class="arshid6social-embed-provider-name">' . $prov_name . '</span>'
			. '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer nofollow" class="arshid6social-embed-source-link">'
			. esc_html__( 'View original', '6arshid social community' )
			. '</a>'
			. '</div>';

		return '<div ' . $outer_attrs . $style . '>'
			. '<div class="arshid6social-embed-placeholder" data-embed="' . esc_attr( $raw_html ) . '">'
			. $thumb_html
			. $load_btn
			. $meta
			. '</div>'
			. '</div>';
	}

	// ── OG card ───────────────────────────────────────────────────────────────

	/**
	 * Build an OG link-preview card (title, description, thumbnail, site name).
	 *
	 * @param array<string, string> $data
	 * @param string                $url
	 * @return string
	 */
	public static function build_og_card( array $data, string $url ): string {
		$title   = esc_html( wp_trim_words( $data['title'] ?? '', 15, '…' ) );
		$desc    = esc_html( wp_trim_words( $data['description'] ?? '', 30, '…' ) );
		$image   = esc_url( $data['image'] ?? '' );
		$site    = esc_html( $data['site_name'] ?? ( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
		$raw_alt = $data['title'] ?? '';

		$img_html  = $image
			? '<img src="' . $image . '" alt="' . esc_attr( $raw_alt ) . '" loading="lazy" class="arshid6social-og-card-image" />'
			: '';
		$desc_html = $desc ? '<p class="arshid6social-og-card-desc">' . $desc . '</p>' : '';

		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer nofollow" class="arshid6social-og-card">'
			. $img_html
			. '<div class="arshid6social-og-card-body">'
			. '<span class="arshid6social-og-card-site">' . $site . '</span>'
			. '<strong class="arshid6social-og-card-title">' . $title . '</strong>'
			. $desc_html
			. '</div>'
			. '</a>';
	}

	/**
	 * Minimal card from oEmbed data when the provider returned no html key.
	 *
	 * @param array<string, mixed> $data
	 * @param string               $url
	 * @param array<string, mixed> $provider
	 * @return string
	 */
	public static function build_from_oembed_data( array $data, string $url, array $provider ): string {
		return self::build_og_card(
			array(
				'title'       => $data['title'] ?? '',
				'description' => '',
				'image'       => $data['thumbnail_url'] ?? '',
				'site_name'   => $data['provider_name'] ?? ( $provider['name'] ?? '' ),
				'url'         => $url,
			),
			$url
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Calculate the CSS padding-bottom percentage for the responsive embed box.
	 *
	 * Uses oEmbed width/height only when both are realistic pixel values AND the
	 * result is a landscape ratio (width > height) to prevent portrait thumbnails
	 * (e.g. SoundCloud returning width=100 as a percentage) from bloating the embed.
	 *
	 * @param array<string, mixed> $data
	 * @return string  e.g. "56.25%" or "75%" — empty string = no ratio (natural height).
	 */
	private static function padding_bottom( array $data ): string {
		$w = (int) ( $data['width'] ?? 0 );
		$h = (int) ( $data['height'] ?? 0 );

		// Reject suspicious values:
		//  – width ≤ 100 likely means a CSS percentage, not pixels.
		//  – portrait ratio (h > w) would produce a very tall container.
		//  – unrealistically large values guard against malformed data.
		if ( $w > 100 && $h > 0 && $w >= $h && $w <= 7680 && $h <= 4320 ) {
			return round( $h / $w * 100, 4 ) . '%';
		}

		// Default 16:9 for video / rich embed types.
		if ( in_array( $data['type'] ?? '', array( 'video', 'rich' ), true ) ) {
			return '56.25%'; // 9/16
		}

		// Audio players (Spotify, SoundCloud, Apple Music) — use a fixed height.
		if ( 'audio' === ( $data['type'] ?? '' ) ) {
			return '25%'; // roughly 4:1 — tall enough for audio UI, not bloated
		}

		return '';
	}

	/**
	 * Remove inline width / height attributes from <iframe> tags so CSS
	 * (width:100%; height:100%) controls sizing instead.
	 *
	 * @param string $html
	 * @return string
	 */
	private static function sanitize_iframe_html( string $html ): string {
		return (string) preg_replace_callback(
			'/<iframe\b([^>]*)>/i',
			static function ( $m ) {
				$attrs = (string) preg_replace( '/\s+(?:width|height)=["\'][^"\']*["\']/', '', $m[1] );
				return '<iframe' . $attrs . '>';
			},
			$html
		);
	}
}
