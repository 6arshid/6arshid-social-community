<?php
namespace Arshid6Social\Engagement\Features;

/**
 * Social Embeds – main feature class.
 *
 * Responsibilities:
 *  1. Hook into content filters to replace bare URLs with embed blocks.
 *  2. Register REST endpoints /embeds/preview and /embeds/providers.
 *  3. Schedule daily cache-prune cron.
 *
 * Content filters hooked:
 *  - arshid6social_activity_content (priority 30, 2 args) – activity posts + comments.
 *  - arshid6social_message_content  (priority 30, 1 arg)  – private messages.
 *
 * Allowed-locations setting (arshid6social_eng_embed_locations array):
 *  'activity'  → activity posts (type != activity_comment)
 *  'comments'  → activity comments (type == activity_comment)
 *  'messages'  → private messages
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Social_Embeds {

	/**
	 * Captures plain-text URLs that are NOT inside HTML attribute values
	 * (i.e., not preceded by  =  "  or  ').
	 */
	const URL_RE = '~(?<![="\'])https?://[^\s<>"\')\]]+~u';

	public function __construct() {
		add_filter( 'arshid6social_activity_content', array( $this, 'filter_activity' ), 30, 2 );
		add_filter( 'arshid6social_message_content',  array( $this, 'filter_message' ),  30 );

		// Daily cache prune.
		add_action( 'arshid6social_embed_cache_prune', array( Social_Embeds_Cache::class, 'prune' ) );
		if ( ! wp_next_scheduled( 'arshid6social_embed_cache_prune' ) ) {
			wp_schedule_event( time(), 'daily', 'arshid6social_embed_cache_prune' );
		}
	}

	// ── Content filters ───────────────────────────────────────────────────────

	/**
	 * Called by arshid6social_activity_content (priority 30, accepts 2 args).
	 *
	 * The second argument ($type) is added in class-activity.php so we can
	 * distinguish posts from comments and respect the 'allowed locations' setting.
	 *
	 * @param string $content
	 * @param string $type     'activity_update' | 'activity_comment' | '' (legacy callers pass nothing).
	 * @return string
	 */
	public function filter_activity( string $content, string $type = '' ): string {
		$locations = $this->allowed_locations();

		if ( 'activity_comment' === $type ) {
			if ( ! in_array( 'comments', $locations, true ) ) {
				return $content;
			}
		} else {
			if ( ! in_array( 'activity', $locations, true ) ) {
				return $content;
			}
		}

		return $this->process( $content );
	}

	/**
	 * Called by arshid6social_message_content (priority 30).
	 */
	public function filter_message( string $content ): string {
		if ( ! in_array( 'messages', $this->allowed_locations(), true ) ) {
			return $content;
		}
		return $this->process( $content );
	}

	// ── Core processor ────────────────────────────────────────────────────────

	/**
	 * Scan $content for embeddable URLs and replace each bare URL with its
	 * embed block (up to the max-per-post limit).
	 *
	 * @param string $content
	 * @return string
	 */
	public function process( string $content ): string {
		if ( empty( trim( $content ) ) ) {
			return $content;
		}

		$max       = max( 1, (int) get_option( 'arshid6social_eng_embed_max_per_post', 3 ) );
		$strip_trk = (bool) get_option( 'arshid6social_eng_embed_strip_tracking', '1' );

		if ( ! preg_match_all( self::URL_RE, $content, $matches ) ) {
			return $content;
		}

		$found  = 0;
		$embeds = array(); // raw_url => embed_html

		foreach ( array_unique( $matches[0] ) as $raw_url ) {
			if ( $found >= $max ) {
				break;
			}

			$url = $strip_trk ? Social_Embeds_Fetcher::strip_tracking_params( $raw_url ) : $raw_url;
			$url = esc_url_raw( $url );

			if ( $this->is_banned( $url ) ) {
				continue;
			}

			$provider = Social_Embeds_Providers::match( $url );
			if ( ! $provider ) {
				continue;
			}

			$result = Social_Embeds_Fetcher::fetch( $url, $provider );
			if ( ! $result ) {
				continue;
			}

			$embeds[ $raw_url ] = Social_Embeds_Renderer::wrap(
				$result['html'],
				$result['data'],
				$url,
				$provider
			);

			++$found;
		}

		foreach ( $embeds as $raw_url => $embed_html ) {
			// Replace only plain-text occurrence (not inside href="" etc.).
			$content = preg_replace(
				'~(?<![="\'])' . preg_quote( $raw_url, '~' ) . '(?!["\'])~u',
				$embed_html,
				$content,
				1
			);
		}

		return $content;
	}

	// ── REST endpoints ────────────────────────────────────────────────────────

	public function register_rest_routes(): void {
		register_rest_route(
			'arshid6social/v1',
			'/embeds/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_preview' ),
				'permission_callback' => static fn() => is_user_logged_in(),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => static fn( $v ) => (bool) filter_var( $v, FILTER_VALIDATE_URL ),
					),
				),
			)
		);

		register_rest_route(
			'arshid6social/v1',
			'/embeds/providers',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_providers' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_preview( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$url       = (string) $request->get_param( 'url' );
		$strip_trk = (bool) get_option( 'arshid6social_eng_embed_strip_tracking', '1' );

		if ( $strip_trk ) {
			$url = Social_Embeds_Fetcher::strip_tracking_params( $url );
		}

		if ( $this->is_banned( $url ) ) {
			return new \WP_Error(
				'arshid6social_embed_banned',
				__( 'This domain is not allowed.', '6arshid-social-community-main' ),
				array( 'status' => 403 )
			);
		}

		if ( ! Social_Embeds_Fetcher::validate_url( $url ) ) {
			return new \WP_Error(
				'arshid6social_embed_invalid',
				__( 'Invalid or unsafe URL.', '6arshid-social-community-main' ),
				array( 'status' => 400 )
			);
		}

		$provider = Social_Embeds_Providers::match( $url );

		// If no specific provider matched, try generic OG (if enabled).
		if ( ! $provider ) {
			if ( ! get_option( 'arshid6social_eng_embed_og_generic', '1' ) ) {
				return new \WP_Error(
					'arshid6social_embed_no_provider',
					__( 'No embed provider matched this URL.', '6arshid-social-community-main' ),
					array( 'status' => 404 )
				);
			}
			$all = Social_Embeds_Providers::all();
			$provider = $all['og_generic'] ?? null;
		}

		if ( ! $provider ) {
			return new \WP_Error(
				'arshid6social_embed_no_provider',
				__( 'No embed provider matched this URL.', '6arshid-social-community-main' ),
				array( 'status' => 404 )
			);
		}

		$result = Social_Embeds_Fetcher::fetch( $url, $provider );
		if ( ! $result ) {
			return new \WP_Error(
				'arshid6social_embed_fetch_failed',
				__( 'Could not retrieve embed data for this URL.', '6arshid-social-community-main' ),
				array( 'status' => 502 )
			);
		}

		$html = Social_Embeds_Renderer::wrap( $result['html'], $result['data'], $url, $provider );

		return new \WP_REST_Response(
			array(
				'html'     => $html,
				'data'     => $result['data'],
				'provider' => $provider['id'],
			),
			200
		);
	}

	public function rest_providers( \WP_REST_Request $request ): \WP_REST_Response {
		$out = array();
		foreach ( Social_Embeds_Providers::enabled() as $p ) {
			$out[] = array(
				'id'   => $p['id'],
				'name' => $p['name'],
			);
		}
		return new \WP_REST_Response( $out, 200 );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/** @return string[] */
	private function allowed_locations(): array {
		return (array) get_option(
			'arshid6social_eng_embed_locations',
			array( 'activity', 'comments', 'messages' )
		);
	}

	private function is_banned( string $url ): bool {
		$raw = (string) get_option( 'arshid6social_eng_embed_banned_domains', '' );
		if ( ! $raw ) {
			return false;
		}

		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$banned = array_filter( array_map( 'trim', explode( "\n", strtolower( $raw ) ) ) );

		foreach ( $banned as $domain ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return true;
			}
		}

		return false;
	}
}
