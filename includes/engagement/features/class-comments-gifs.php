<?php
namespace Arshid6Social\Engagement\Features;

/**
 * GIF Picker for comments and messages.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Comments_GIFs {

	public function __construct() {
		add_action( 'wp_ajax_arshid6social_gif_trending',        array( $this, 'ajax_trending' ) );
		add_action( 'wp_ajax_arshid6social_gif_search',          array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_arshid6social_gif_recent',          array( $this, 'ajax_recent' ) );
		add_action( 'wp_ajax_arshid6social_gif_record_use',      array( $this, 'ajax_record_use' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_gif_trending', array( $this, 'ajax_trending' ) );
		add_action( 'wp_ajax_nopriv_arshid6social_gif_search',   array( $this, 'ajax_search' ) );
	}

	// ── Provider ──────────────────────────────────────────────────────────────

	private function provider(): string {
		return get_option( 'arshid6social_eng_gif_provider', 'giphy' );
	}

	private function api_key(): string {
		return 'giphy' === $this->provider()
			? (string) get_option( 'arshid6social_eng_giphy_api_key', '' )
			: (string) get_option( 'arshid6social_eng_tenor_api_key', '' );
	}

	private function is_configured(): bool {
		return '' !== $this->api_key();
	}

	// ── API calls ─────────────────────────────────────────────────────────────

	/**
	 * Fetches trending GIFs from GIPHY.
	 */
	private function fetch_giphy_trending( int $limit = 25 ): array {
		$key      = $this->api_key();
		$cache_key = 'arshid6social_gif_trending_giphy_' . $limit;
		$cached   = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( add_query_arg( array(
			'api_key' => $key,
			'limit'   => $limit,
			'rating'  => 'g',
		), 'https://api.giphy.com/v1/gifs/trending' ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$gifs = $this->normalize_giphy( $body['data'] ?? array() );
		set_transient( $cache_key, $gifs, 10 * MINUTE_IN_SECONDS );
		return $gifs;
	}

	private function fetch_giphy_search( string $q, int $limit = 25 ): array {
		$key = $this->api_key();
		$response = wp_remote_get( add_query_arg( array(
			'api_key' => $key,
			'q'       => sanitize_text_field( $q ),
			'limit'   => $limit,
			'rating'  => 'g',
		), 'https://api.giphy.com/v1/gifs/search' ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $this->normalize_giphy( $body['data'] ?? array() );
	}

	private function normalize_giphy( array $data ): array {
		return array_map( function( array $gif ): array {
			return array(
				'id'       => esc_attr( $gif['id'] ?? '' ),
				'title'    => esc_html( $gif['title'] ?? '' ),
				'url'      => esc_url( $gif['images']['fixed_height']['url'] ?? $gif['url'] ?? '' ),
				'thumb'    => esc_url( $gif['images']['fixed_height_small']['url'] ?? '' ),
				'width'    => (int) ( $gif['images']['fixed_height']['width'] ?? 0 ),
				'height'   => (int) ( $gif['images']['fixed_height']['height'] ?? 200 ),
				'provider' => 'giphy',
			);
		}, $data );
	}

	private function fetch_tenor_trending( int $limit = 25 ): array {
		$key       = $this->api_key();
		$cache_key = 'arshid6social_gif_trending_tenor_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( add_query_arg( array(
			'key'        => $key,
			'limit'      => $limit,
			'contentfilter' => 'high',
			'media_filter' => 'gif',
		), 'https://tenor.googleapis.com/v2/featured' ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$gifs = $this->normalize_tenor( $body['results'] ?? array() );
		set_transient( $cache_key, $gifs, 10 * MINUTE_IN_SECONDS );
		return $gifs;
	}

	private function fetch_tenor_search( string $q, int $limit = 25 ): array {
		$key      = $this->api_key();
		$response = wp_remote_get( add_query_arg( array(
			'key'           => $key,
			'q'             => sanitize_text_field( $q ),
			'limit'         => $limit,
			'contentfilter' => 'high',
			'media_filter'  => 'gif',
		), 'https://tenor.googleapis.com/v2/search' ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $this->normalize_tenor( $body['results'] ?? array() );
	}

	private function normalize_tenor( array $data ): array {
		return array_map( function( array $gif ): array {
			$media = $gif['media_formats']['gif'] ?? $gif['media_formats']['tinygif'] ?? array();
			$thumb = $gif['media_formats']['tinygif'] ?? $media;
			return array(
				'id'       => esc_attr( $gif['id'] ?? '' ),
				'title'    => esc_html( $gif['title'] ?? '' ),
				'url'      => esc_url( $media['url'] ?? '' ),
				'thumb'    => esc_url( $thumb['url'] ?? '' ),
				'width'    => (int) ( $media['dims'][0] ?? 0 ),
				'height'   => (int) ( $media['dims'][1] ?? 200 ),
				'provider' => 'tenor',
			);
		}, $data );
	}

	// ── Recent GIFs (per-user user-meta) ──────────────────────────────────────

	public function record_recent( int $user_id, array $gif ): void {
		$recent = (array) get_user_meta( $user_id, 'arshid6social_recent_gifs', true );
		// Remove existing entry for this GIF ID.
		$recent = array_filter( $recent, fn( $g ) => ( $g['id'] ?? '' ) !== $gif['id'] );
		array_unshift( $recent, $gif );
		$recent = array_slice( $recent, 0, 20 );
		update_user_meta( $user_id, 'arshid6social_recent_gifs', $recent );
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public function ajax_trending(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'No GIF API key configured. Contact your administrator.', '6arshid-social-community-main' ) ), 503 );
		}

		$gifs = 'tenor' === $this->provider()
			? $this->fetch_tenor_trending()
			: $this->fetch_giphy_trending();

		wp_send_json_success( $gifs );
	}

	public function ajax_search(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'No GIF API key configured.', '6arshid-social-community-main' ) ), 503 );
		}

		$q = sanitize_text_field( wp_unslash( $_REQUEST['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $q ) {
			wp_send_json_error( null, 400 );
		}

		$gifs = 'tenor' === $this->provider()
			? $this->fetch_tenor_search( $q )
			: $this->fetch_giphy_search( $q );

		wp_send_json_success( $gifs );
	}

	public function ajax_recent(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}
		$recent = (array) get_user_meta( get_current_user_id(), 'arshid6social_recent_gifs', true );
		wp_send_json_success( $recent );
	}

	public function ajax_record_use(): void {
		if ( ! check_ajax_referer( 'arshid6social_ajax_nonce', 'nonce', false ) || ! is_user_logged_in() ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$gif = array(
			'id'    => sanitize_key( $_POST['gif_id'] ?? '' ),
			'url'   => esc_url_raw( wp_unslash( $_POST['gif_url'] ?? '' ) ),
			'thumb' => esc_url_raw( wp_unslash( $_POST['gif_thumb'] ?? '' ) ),
			'title' => sanitize_text_field( wp_unslash( $_POST['gif_title'] ?? '' ) ),
		);
		// phpcs:enable

		if ( ! $gif['id'] || ! $gif['url'] ) {
			wp_send_json_error( null, 400 );
		}

		$this->record_recent( get_current_user_id(), $gif );
		wp_send_json_success();
	}
}
