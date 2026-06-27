<?php
namespace Arshid6Social\Components\Ads;

/**
 * Ads component — shortcode rendering, click/impression tracking, media upload.
 *
 * @package Arshid6Social\Components\Ads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Ads
 */
class Ads {

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_ad_click',        array( $this, 'ajax_track_click' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_ad_click', array( $this, 'ajax_track_click' ) );
		add_action( 'wp_ajax_arshid6social_upload_ad_media', array( $this, 'ajax_upload_media' ) );
	}

	// ── AJAX: track click ─────────────────────────────────────────────────────

	public function ajax_track_click(): void {
		check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce' );
		$ad_id = absint( $_POST['ad_id'] ?? 0 );
		if ( ! $ad_id ) {
			wp_send_json_error();
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"UPDATE {$wpdb->prefix}sn_ads SET clicks = clicks + 1 WHERE id = %d",
			$ad_id
		) );

		wp_send_json_success();
	}

	// ── AJAX: upload media for admin ad creation ──────────────────────────────

	public function ajax_upload_media(): void {
		check_ajax_referer( 'arshid6social_admin_ads_nonce', 'nonce' );
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( __( 'Forbidden', '6arshid-social-community-main' ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file received.', '6arshid-social-community-main' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id->get_error_message() );
		}

		wp_send_json_success( array(
			'url' => wp_get_attachment_url( $attachment_id ),
			'id'  => $attachment_id,
		) );
	}

	// ── Static helpers ────────────────────────────────────────────────────────

	/**
	 * Returns active ads for a given placement context.
	 *
	 * @param string $placement 'sidebar' | 'feed' | 'both'
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_ads( string $placement ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'sn_ads';
		$today = current_time( 'Y-m-d' );

		// A 'both'-placement ad is shown everywhere; a placement-specific ad only
		// in its own slot. So for 'sidebar' we want rows where placement IN ('sidebar','both').
		$slots = ( 'sidebar' === $placement )
			? array( 'sidebar', 'both' )
			: array( 'feed', 'both' );

		$placeholders = implode( ', ', array_fill( 0, count( $slots ), '%s' ) );

		$args = array_merge( $slots, array( $today, $today ) );

		$results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$table}
			 WHERE status = 'active'
			   AND placement IN ($placeholders)
			   AND (start_date IS NULL OR start_date <= %s)
			   AND (end_date   IS NULL OR end_date   >= %s)
			 ORDER BY id DESC",
			...$args
		), ARRAY_A );

		return $results ?: array();
	}

	/**
	 * Returns ads data array for JS feed injection.
	 *
	 * @return array{ads: list<array<string,mixed>>, every_n_posts: int}
	 */
	public static function get_feed_ads_for_js(): array {
		$raw = self::get_ads( 'feed' );
		if ( ! $raw ) {
			return array( 'ads' => array(), 'every_n_posts' => 5 );
		}

		$every_n = (int) ( $raw[0]['every_n_posts'] ?? 5 );

		$ads = array_map( static function ( array $ad ) {
			return array(
				'id'           => (int) $ad['id'],
				'title'        => $ad['title'],
				'ad_type'      => $ad['ad_type'],
				'file_url'     => $ad['file_url'],
				'click_url'    => $ad['click_url'],
				'js_code'      => $ad['js_code'],
				'every_n_posts' => (int) $ad['every_n_posts'],
			);
		}, $raw );

		return array( 'ads' => $ads, 'every_n_posts' => $every_n );
	}

	/**
	 * Renders a single ad card as HTML string.
	 *
	 * @param array<string, mixed> $ad Row from sn_ads.
	 */
	public static function render_ad( array $ad ): string {
		$ad_id     = (int) $ad['id'];
		$click_url = esc_url( $ad['click_url'] );
		$title     = esc_html( $ad['title'] );

		$inner = '';

		switch ( $ad['ad_type'] ) {
			case 'image':
				$img = '<img src="' . esc_url( $ad['file_url'] ) . '" alt="' . $title . '" loading="lazy" class="arshid6social-ad-card__img">';
				$inner = $click_url
					? '<a href="' . $click_url . '" target="_blank" rel="noopener sponsored" class="arshid6social-ad-card__link" data-ad-id="' . $ad_id . '">' . $img . '</a>'
					: $img;
				break;

			case 'video':
				$onclick = $click_url
					? ' data-ad-click-url="' . $click_url . '" data-ad-id="' . $ad_id . '"'
					: '';
				$inner = '<video controls playsinline preload="metadata" class="arshid6social-ad-card__video"' . $onclick . '>'
					. '<source src="' . esc_url( $ad['file_url'] ) . '">'
					. '</video>';
				break;

			case 'html':
			case 'js':
				// Admin-only content — output as-is (admins can already inject arbitrary HTML).
				$inner = $ad['js_code'];
				break;
		}

		$label = esc_html__( 'Sponsored', '6arshid-social-community-main' );

		return '<div class="arshid6social-ad-card" data-ad-id="' . $ad_id . '">'
			. '<div class="arshid6social-ad-card__label">' . $label . '</div>'
			. ( $title ? '<div class="arshid6social-ad-card__title">' . $title . '</div>' : '' )
			. '<div class="arshid6social-ad-card__content">' . $inner . '</div>'
			. '</div>';
	}

	/**
	 * Renders sidebar ads wrapped in a panel block.
	 *
	 * @param int $limit Max number of sidebar ads to show (0 = all).
	 */
	public static function render_sidebar_ads( int $limit = 0 ): string {
		$ads = self::get_ads( 'sidebar' );
		if ( ! $ads ) {
			return '';
		}

		if ( $limit > 0 ) {
			$ads = array_slice( $ads, 0, $limit );
		}

		$html = '<div class="arshid6social-ads-sidebar">';
		foreach ( $ads as $ad ) {
			$html .= self::render_ad( $ad );
		}
		$html .= '</div>';

		return $html;
	}
}
