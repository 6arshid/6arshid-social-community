<?php
namespace Arshid6Social\Engagement\Features;

/**
 * External Social Share feature.
 *
 * Adds share-to-external-network buttons on activity posts.
 * Supports selected common networks. All share URLs are generated client-side.
 *
 * @package Arshid6Social\Engagement\Features
 */

defined( 'ABSPATH' ) || exit;

class Social_Share_External {

	// ── Network registry ──────────────────────────────────────────────────────

	/**
	 * Returns all supported networks.
	 * Each entry: label, color (hex), url ({URL}/{TITLE} placeholders),
	 * optional: action ('copy'|'native'), target ('_self').
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function networks(): array {
		return array(
			'facebook'   => array(
				'label' => 'Facebook',
				'color' => '#1877f2',
				'url'   => 'https://www.facebook.com/sharer/sharer.php?u={URL}',
			),
			'x'          => array(
				'label' => 'X',
				'color' => '#000000',
				'url'   => 'https://twitter.com/intent/tweet?text={TITLE}&url={URL}',
			),
			'twitter'    => array(
				'label' => 'Twitter',
				'color' => '#1da1f2',
				'url'   => 'https://twitter.com/intent/tweet?text={TITLE}&url={URL}',
			),
			'whatsapp'   => array(
				'label' => 'WhatsApp',
				'color' => '#25d366',
				'url'   => 'https://api.whatsapp.com/send?text={TITLE}%20{URL}',
			),
			'telegram'   => array(
				'label' => 'Telegram',
				'color' => '#2ca5e0',
				'url'   => 'https://t.me/share/url?url={URL}&text={TITLE}',
			),
			'linkedin'   => array(
				'label' => 'LinkedIn',
				'color' => '#0a66c2',
				'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url={URL}',
			),
			'reddit'     => array(
				'label' => 'Reddit',
				'color' => '#ff4500',
				'url'   => 'https://reddit.com/submit?url={URL}&title={TITLE}',
			),
			'email'      => array(
				'label'  => 'Email',
				'color'  => '#7a7a7a',
				'url'    => 'mailto:?subject={TITLE}&body={URL}',
				'target' => '_self',
			),
			'copy_link'  => array(
				'label'  => 'Copy Link',
				'color'  => '#555555',
				'url'    => '',
				'action' => 'copy',
			),
			'threads'    => array(
				'label' => 'Threads',
				'color' => '#000000',
				'url'   => 'https://www.threads.net/intent/post?text={TITLE}%20{URL}',
			),
			'bluesky'    => array(
				'label' => 'Bluesky',
				'color' => '#0085ff',
				'url'   => 'https://bsky.app/intent/compose?text={TITLE}%20{URL}',
			),
			'pinterest'  => array(
				'label' => 'Pinterest',
				'color' => '#e60023',
				'url'   => 'https://pinterest.com/pin/create/button/?url={URL}&description={TITLE}',
			),
			'viber'      => array(
				'label'  => 'Viber',
				'color'  => '#7360f2',
				'url'    => 'viber://forward?text={TITLE}%20{URL}',
				'target' => '_self',
			),
			'line'       => array(
				'label' => 'Line',
				'color' => '#00c300',
				'url'   => 'https://social-plugins.line.me/lineit/share?url={URL}',
			),
			'gmail'      => array(
				'label' => 'Gmail',
				'color' => '#ea4335',
				'url'   => 'https://mail.google.com/mail/?view=cm&to=&su={TITLE}&body={URL}',
			),
			'yahoo_mail' => array(
				'label' => 'Yahoo Mail',
				'color' => '#7b0099',
				'url'   => 'https://compose.mail.yahoo.com/?subject={TITLE}&body={URL}',
			),
			'outlook'    => array(
				'label' => 'Outlook.com',
				'color' => '#0078d4',
				'url'   => 'https://outlook.live.com/owa/?path=/mail/action/compose&subject={TITLE}&body={URL}',
			),
			'aol_mail'   => array(
				'label' => 'AOL Mail',
				'color' => '#ff0b00',
				'url'   => 'https://mail.aol.com/mail/compose-message.aspx?subject={TITLE}&body={URL}',
			),
			'message'    => array(
				'label'  => 'SMS',
				'color'  => '#07c160',
				'url'    => 'sms:?body={TITLE}%20{URL}',
				'target' => '_self',
			),
			'send_dm'    => array(
				'label'  => 'Send as Message',
				'color'  => '#2563eb',
				'url'    => '',
				'action' => 'send_dm',
			),
		);
	}

	// ── Default network selection (pre-checked in admin) ──────────────────────

	public static function default_networks(): array {
		return array( 'facebook', 'x', 'whatsapp', 'telegram', 'linkedin', 'reddit', 'email', 'copy_link', 'send_dm', 'twitter', 'threads', 'bluesky', 'pinterest', 'viber', 'line' );
	}

	// ── Constructor ───────────────────────────────────────────────────────────

	public function __construct() {
		add_filter( 'arshid6social_format_activity', array( $this, 'add_share_data' ), 10, 2 );
	}

	/**
	 * Appends extShareUrl / extShareTitle to each formatted activity so the JS
	 * can build share links without a server round-trip.
	 */
	public function add_share_data( array $formatted, object $activity ): array {
		if ( 'activity_comment' === ( $activity->type ?? '' ) ) {
			return $formatted;
		}

		$url   = $formatted['permalink'] ?? $formatted['primaryLink'] ?? home_url( '/' );
		$raw   = wp_strip_all_tags( $formatted['content'] ?? '' );
		$title = mb_substr( $raw, 0, 200 );

		$formatted['extShareUrl']   = esc_url( $url );
		$formatted['extShareTitle'] = esc_attr( $title );

		return $formatted;
	}

	// ── Helper: build the JS network map for localization ─────────────────────

	/**
	 * Returns only the enabled networks as a JS-ready array.
	 *
	 * @param  array $enabled_keys Network keys enabled by admin.
	 * @return array<string, array<string,string>>
	 */
	public static function enabled_networks_for_js( array $enabled_keys ): array {
		$all    = self::networks();
		$result = array();
		foreach ( $enabled_keys as $key ) {
			if ( isset( $all[ $key ] ) ) {
				$result[ $key ] = $all[ $key ];
			}
		}
		return $result;
	}
}
