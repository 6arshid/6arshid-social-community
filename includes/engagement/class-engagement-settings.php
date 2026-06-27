<?php
namespace Arshid6Social\Engagement;

/**
 * Engagement Pack – admin settings tab.
 *
 * @package Arshid6Social\Engagement
 */

defined( 'ABSPATH' ) || exit;

class Engagement_Settings {

	private static array $features = array();

	public function __construct() {
		self::$features = array(
			'hashtags'             => __( 'Hashtags', '6arshid-social-community-main' ),
			'tag_friends'          => __( 'Tag Friends / @Mentions', '6arshid-social-community-main' ),
			'bookmarks'            => __( 'Bookmarks', '6arshid-social-community-main' ),
			'sticky_posts'         => __( 'Sticky Posts', '6arshid-social-community-main' ),
			'share_posts'          => __( 'Share / Repost', '6arshid-social-community-main' ),
			'polls'                => __( 'Polls', '6arshid-social-community-main' ),
			'advanced_polls'       => __( 'Advanced Polls', '6arshid-social-community-main' ),
			'comments_gifs'        => __( 'GIFs in Comments', '6arshid-social-community-main' ),
			'comments_attachments' => __( 'Comment Attachments', '6arshid-social-community-main' ),
			'messages_attachments' => __( 'Message Attachments', '6arshid-social-community-main' ),
		);

		add_filter( 'arshid6social_settings_tabs',            array( $this, 'add_tab' ) );
		add_action( 'admin_init',                       array( $this, 'register_settings' ) );
		add_action( 'arshid6social_settings_tab_engagement',  array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts',             array( $this, 'enqueue_sortable' ) );
	}

	public function add_tab( array $tabs ): array {
		$tabs['engagement'] = __( 'Engagement', '6arshid-social-community-main' );
		return $tabs;
	}

	public function enqueue_sortable(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ( $_GET['page'] ?? '' ) !== 'arshid6social-settings' || ( $_GET['tab'] ?? '' ) !== 'engagement' ) {
			return;
		}
		// phpcs:enable
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	public function register_settings(): void {
		$options = array(
			// Social Embeds
			'arshid6social_eng_social_embeds',
			'arshid6social_eng_embed_locations',
			'arshid6social_eng_embed_max_per_post',
			'arshid6social_eng_embed_lazy_load',
			'arshid6social_eng_embed_cache_hours',
			'arshid6social_eng_embed_strip_tracking',
			'arshid6social_eng_embed_og_fallback',
			'arshid6social_eng_embed_og_generic',
			'arshid6social_eng_embed_fb_token',
			'arshid6social_eng_embed_ig_token',
			'arshid6social_eng_embed_banned_domains',

			// Per-provider toggles (arshid6social_eng_embed_{id}).
			'arshid6social_eng_embed_youtube',
			'arshid6social_eng_embed_vimeo',
			'arshid6social_eng_embed_twitter',
			'arshid6social_eng_embed_instagram',
			'arshid6social_eng_embed_facebook',
			'arshid6social_eng_embed_tiktok',
			'arshid6social_eng_embed_spotify',
			'arshid6social_eng_embed_soundcloud',
			'arshid6social_eng_embed_pinterest',
			'arshid6social_eng_embed_reddit',
			'arshid6social_eng_embed_twitch',
			'arshid6social_eng_embed_dailymotion',
			'arshid6social_eng_embed_apple_music',
			'arshid6social_eng_embed_linkedin',
			'arshid6social_eng_embed_telegram',
			'arshid6social_eng_embed_threads',
			'arshid6social_eng_embed_bluesky',
			'arshid6social_eng_embed_aparat',
			'arshid6social_eng_embed_og_generic_provider',

			// Hashtags
			'arshid6social_eng_hashtags',
			'arshid6social_eng_tag_friends',
			'arshid6social_eng_bookmarks',
			'arshid6social_eng_sticky_posts',
			'arshid6social_eng_share_posts',
			'arshid6social_eng_polls',
			'arshid6social_eng_advanced_polls',
			'arshid6social_eng_comments_gifs',
			'arshid6social_eng_comments_attachments',
			'arshid6social_eng_messages_attachments',

			'arshid6social_eng_polls_max_options',
			'arshid6social_eng_polls_allow_voter_suggest',
			'arshid6social_eng_hashtag_banned',
			'arshid6social_eng_tag_photo_tags',
			'arshid6social_eng_tag_privacy',
			'arshid6social_eng_tag_review',
			'arshid6social_eng_bookmark_collections',
			'arshid6social_eng_share_external',
			'arshid6social_eng_sticky_multiple',
			'arshid6social_eng_giphy_api_key',
			'arshid6social_eng_gif_cache',
			'arshid6social_eng_comment_att_max_mb',
			'arshid6social_eng_comment_att_types',
			'arshid6social_eng_msg_att_max_mb',
			'arshid6social_eng_msg_att_types',

			// External Social Share
			'arshid6social_eng_social_share_external',
			'arshid6social_eng_social_share_networks',
			'arshid6social_eng_social_share_network_order',
			'arshid6social_eng_social_share_position',
			'arshid6social_eng_social_share_pages',
			'arshid6social_eng_social_share_style',
			'arshid6social_eng_social_share_max_visible',
			'arshid6social_eng_social_share_native',
		);

		foreach ( $options as $opt ) {
			register_setting( 'arshid6social_engagement', $opt, array( $this, 'sanitize' ) );
		}

		// Special-case sanitizers for social-embed fields.
		register_setting( 'arshid6social_engagement', 'arshid6social_eng_embed_banned_domains', 'sanitize_textarea_field' );
		register_setting( 'arshid6social_engagement', 'arshid6social_eng_embed_max_per_post', 'absint' );
		register_setting( 'arshid6social_engagement', 'arshid6social_eng_embed_cache_hours', 'absint' );
		register_setting( 'arshid6social_engagement', 'arshid6social_eng_embed_fb_token', 'sanitize_text_field' );
		register_setting( 'arshid6social_engagement', 'arshid6social_eng_embed_ig_token', 'sanitize_text_field' );
	}

	public function sanitize( mixed $value ): mixed {
		// Derive the actual option name being saved from the current filter name.
		// WordPress calls the sanitize callback via a filter named "sanitize_option_{option_name}".
		$option = str_replace( 'sanitize_option_', '', current_filter() );

		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		$int_options = array(
			'arshid6social_eng_polls_max_options',
			'arshid6social_eng_comment_att_max_mb',
			'arshid6social_eng_msg_att_max_mb',
			'arshid6social_eng_social_share_max_visible',
		);
		if ( in_array( $option, $int_options, true ) ) {
			return absint( $value );
		}

		$enum_options = array(
			'arshid6social_eng_tag_privacy'           => array( 'public', 'friends', 'private' ),
			'arshid6social_eng_tag_review'            => array( 'auto', 'manual' ),
			'arshid6social_eng_social_share_position' => array( 'top', 'bottom', 'both' ),
			'arshid6social_eng_social_share_style'    => array( 'icon', 'icon_label', 'label' ),
			'arshid6social_eng_social_share_pages'    => array( 'activity', 'single', 'profile', 'group' ),
			'arshid6social_eng_gif_provider'          => array( 'giphy', 'tenor' ),
		);
		if ( isset( $enum_options[ $option ] ) ) {
			$allowed = $enum_options[ $option ];
			if ( is_array( $value ) ) {
				return array_values( array_intersect( array_map( 'sanitize_key', $value ), $allowed ) );
			}
			return in_array( $value, $allowed, true ) ? $value : $allowed[0];
		}

		if ( in_array( $value, array( '0', '1', 0, 1, true, false ), true ) ) {
			return (bool) $value ? '1' : '0';
		}

		return sanitize_text_field( (string) $value );
	}

	public static function enabled( string $feature ): bool {
		return (bool) get_option( 'arshid6social_eng_' . $feature, false );
	}

	public function render(): void {
		wp_add_inline_style( 'arshid6social-admin', '
		.arshid6social-eng-section{margin:24px 0 0;padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;}
		.arshid6social-eng-section h3{margin:0 0 12px;font-size:14px;color:#111827;}
		.arshid6social-eng-toggle{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-weight:600;}
		.arshid6social-eng-sub{padding:8px 0 0 24px;}
		.arshid6social-eng-sub label{display:block;margin-bottom:6px;}
		' );
		?>

		<h2><?php esc_html_e( 'Engagement Features', '6arshid-social-community-main' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Toggle each feature independently. Disabled features unload all their hooks, REST routes, and assets.', '6arshid-social-community-main' ); ?></p>

		<?php $this->render_social_embeds(); ?>
		<?php $this->render_hashtags(); ?>
		<?php $this->render_tag_friends(); ?>
		<?php $this->render_bookmarks(); ?>
		<?php $this->render_sticky_posts(); ?>
		<?php $this->render_share_posts(); ?>
		<?php $this->render_polls(); ?>
		<?php $this->render_comments_gifs(); ?>
		<?php $this->render_comments_attachments(); ?>
		<?php $this->render_messages_attachments(); ?>
		<?php $this->render_social_share_external(); ?>
		<?php
	}

	// phpcs:disable Generic.Files.LineLength
	private function render_social_embeds(): void {
		$providers = array(
			'youtube'       => 'YouTube',
			'vimeo'         => 'Vimeo',
			'twitter'       => 'X / Twitter',
			'instagram'     => 'Instagram',
			'facebook'      => 'Facebook',
			'tiktok'        => 'TikTok',
			'spotify'       => 'Spotify',
			'soundcloud'    => 'SoundCloud',
			'pinterest'     => 'Pinterest',
			'reddit'        => 'Reddit',
			'twitch'        => 'Twitch',
			'dailymotion'   => 'Dailymotion',
			'apple_music'   => 'Apple Music / Podcasts',
			'linkedin'      => 'LinkedIn',
			'telegram'      => 'Telegram',
			'threads'       => 'Threads',
			'bluesky'       => 'Bluesky',
			'aparat'        => 'آپارات (Aparat)',
			'og_generic'    => __( 'Generic Link Preview (Open Graph)', '6arshid-social-community-main' ),
		);

		$locations = (array) get_option( 'arshid6social_eng_embed_locations', array( 'activity', 'comments', 'messages' ) );
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Social Embeds', '6arshid-social-community-main' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Auto-embed links from popular platforms when a supported URL is pasted into a post, comment, or message.', '6arshid-social-community-main' ); ?></p>

			<?php $this->toggle( 'arshid6social_eng_social_embeds', __( 'Enable Social Embeds', '6arshid-social-community-main' ) ); ?>

			<div class="arshid6social-eng-sub">

				<!-- Per-platform checkboxes -->
				<p><strong><?php esc_html_e( 'Enabled platforms:', '6arshid-social-community-main' ); ?></strong></p>
				<div style="display:flex;gap:6px;margin-bottom:10px;">
					<button type="button" class="button button-small arshid6social-embed-toggle-all" data-state="1"><?php esc_html_e( 'Enable all', '6arshid-social-community-main' ); ?></button>
					<button type="button" class="button button-small arshid6social-embed-toggle-all" data-state="0"><?php esc_html_e( 'Disable all', '6arshid-social-community-main' ); ?></button>
				</div>
				<div class="arshid6social-embed-provider-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:4px 12px;margin-bottom:14px;">
					<?php foreach ( $providers as $id => $label ) : ?>
					<label style="display:flex;align-items:center;gap:5px;">
						<input type="checkbox" class="arshid6social-embed-provider-cb"
							name="arshid6social_eng_embed_<?php echo esc_attr( $id ); ?>" value="1"
							<?php checked( get_option( 'arshid6social_eng_embed_' . $id, '1' ) ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
					<?php endforeach; ?>
				</div>

				<!-- Facebook / Instagram tokens -->
				<p style="margin-top:12px;"><strong><?php esc_html_e( 'Facebook & Instagram (official oEmbed):', '6arshid-social-community-main' ); ?></strong><br>
				<span class="description"><?php esc_html_e( 'Without tokens, both fall back to Open Graph preview automatically.', '6arshid-social-community-main' ); ?></span></p>
				<table class="form-table" style="margin:0;">
					<tr>
						<td style="padding:4px 0;"><label for="arshid6social_eng_embed_fb_token"><?php esc_html_e( 'Facebook App Token:', '6arshid-social-community-main' ); ?></label></td>
						<td style="padding:4px 0 4px 10px;"><input type="password" id="arshid6social_eng_embed_fb_token" name="arshid6social_eng_embed_fb_token" class="regular-text" value="<?php echo esc_attr( get_option( 'arshid6social_eng_embed_fb_token', '' ) ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<td style="padding:4px 0;"><label for="arshid6social_eng_embed_ig_token"><?php esc_html_e( 'Instagram Access Token:', '6arshid-social-community-main' ); ?></label></td>
						<td style="padding:4px 0 4px 10px;"><input type="password" id="arshid6social_eng_embed_ig_token" name="arshid6social_eng_embed_ig_token" class="regular-text" value="<?php echo esc_attr( get_option( 'arshid6social_eng_embed_ig_token', '' ) ); ?>" autocomplete="off" /></td>
					</tr>
				</table>

				<!-- Allowed locations -->
				<p style="margin-top:14px;"><strong><?php esc_html_e( 'Embed in:', '6arshid-social-community-main' ); ?></strong></p>
				<?php
				$loc_options = array(
					'activity' => __( 'Activity posts', '6arshid-social-community-main' ),
					'comments' => __( 'Comments', '6arshid-social-community-main' ),
					'messages' => __( 'Private messages', '6arshid-social-community-main' ),
				);
				foreach ( $loc_options as $val => $lbl ) :
				?>
				<label style="display:block;margin-bottom:5px;">
					<input type="checkbox" name="arshid6social_eng_embed_locations[]" value="<?php echo esc_attr( $val ); ?>"
						<?php checked( in_array( $val, $locations, true ) ); ?> />
					<?php echo esc_html( $lbl ); ?>
				</label>
				<?php endforeach; ?>

				<!-- Behaviour options -->
				<p style="margin-top:14px;"><strong><?php esc_html_e( 'Behaviour:', '6arshid-social-community-main' ); ?></strong></p>
				<label style="display:block;margin-bottom:6px;">
					<?php esc_html_e( 'Max embeds per content item:', '6arshid-social-community-main' ); ?>
					<input type="number" name="arshid6social_eng_embed_max_per_post" min="1" max="10"
						value="<?php echo esc_attr( get_option( 'arshid6social_eng_embed_max_per_post', 3 ) ); ?>" style="width:60px;" />
				</label>
				<label style="display:block;margin-bottom:6px;">
					<?php esc_html_e( 'Cache duration (hours):', '6arshid-social-community-main' ); ?>
					<input type="number" name="arshid6social_eng_embed_cache_hours" min="1" max="720"
						value="<?php echo esc_attr( get_option( 'arshid6social_eng_embed_cache_hours', 24 ) ); ?>" style="width:70px;" />
				</label>
				<label style="display:block;margin-bottom:6px;">
					<input type="checkbox" name="arshid6social_eng_embed_lazy_load" value="1"
						<?php checked( get_option( 'arshid6social_eng_embed_lazy_load', '1' ) ); ?> />
					<?php esc_html_e( 'Lazy / click-to-load (privacy-first: no third-party request until user clicks)', '6arshid-social-community-main' ); ?>
				</label>
				<label style="display:block;margin-bottom:6px;">
					<input type="checkbox" name="arshid6social_eng_embed_strip_tracking" value="1"
						<?php checked( get_option( 'arshid6social_eng_embed_strip_tracking', '1' ) ); ?> />
					<?php esc_html_e( 'Strip tracking parameters from URLs (utm_*, fbclid, etc.) before embedding', '6arshid-social-community-main' ); ?>
				</label>
				<label style="display:block;margin-bottom:6px;">
					<input type="checkbox" name="arshid6social_eng_embed_og_fallback" value="1"
						<?php checked( get_option( 'arshid6social_eng_embed_og_fallback', '1' ) ); ?> />
					<?php esc_html_e( 'Fallback to Open Graph preview card when a provider fails', '6arshid-social-community-main' ); ?>
				</label>
				<label style="display:block;margin-bottom:6px;">
					<input type="checkbox" name="arshid6social_eng_embed_og_generic" value="1"
						<?php checked( get_option( 'arshid6social_eng_embed_og_generic', '1' ) ); ?> />
					<?php esc_html_e( 'Show Open Graph preview for any URL that doesn\'t match a specific provider', '6arshid-social-community-main' ); ?>
				</label>

				<!-- Banned domains -->
				<p style="margin-top:12px;"><strong><?php esc_html_e( 'Blocked domains (one per line):', '6arshid-social-community-main' ); ?></strong><br>
				<span class="description"><?php esc_html_e( 'URLs from these domains will never be embedded.', '6arshid-social-community-main' ); ?></span></p>
				<textarea name="arshid6social_eng_embed_banned_domains" rows="4" class="large-text" style="font-family:monospace;"><?php echo esc_textarea( get_option( 'arshid6social_eng_embed_banned_domains', '' ) ); ?></textarea>

			</div><!-- .arshid6social-eng-sub -->
		</div><!-- .arshid6social-eng-section -->

		<?php
		wp_add_inline_script(
			'arshid6social-admin',
			'(function(){
				document.querySelectorAll(".arshid6social-embed-toggle-all").forEach(function(btn){
					btn.addEventListener("click", function(){
						var state = btn.dataset.state === "1";
						document.querySelectorAll(".arshid6social-embed-provider-cb").forEach(function(cb){ cb.checked = state; });
					});
				});
			}());'
		);
		?>
		<?php
	}
	// phpcs:enable

	private function toggle( string $key, string $label ): void {
		?>
		<div class="arshid6social-eng-toggle">
			<input type="checkbox" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="1"
				<?php checked( get_option( $key, false ) ); ?> />
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
		</div>
		<?php
	}

	private function render_hashtags(): void {
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Hashtags', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_hashtags', __( 'Enable Hashtags', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<?php esc_html_e( 'Banned hashtags (one per line):', '6arshid-social-community-main' ); ?><br>
					<textarea name="arshid6social_eng_hashtag_banned" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_eng_hashtag_banned', '' ) ); ?></textarea>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_tag_friends(): void {
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Tag Friends / @Mentions', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_tag_friends', __( 'Enable Tag Friends', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<input type="checkbox" name="arshid6social_eng_tag_photo_tags" value="1"
						<?php checked( get_option( 'arshid6social_eng_tag_photo_tags', false ) ); ?> />
					<?php esc_html_e( 'Allow photo hotspot tagging', '6arshid-social-community-main' ); ?>
				</label>
				<label>
					<input type="checkbox" name="arshid6social_eng_tag_review" value="1"
						<?php checked( get_option( 'arshid6social_eng_tag_review', false ) ); ?> />
					<?php esc_html_e( 'Require tag approval before appearing on profile', '6arshid-social-community-main' ); ?>
				</label>
				<label>
					<?php esc_html_e( 'Default taggability:', '6arshid-social-community-main' ); ?>
					<select name="arshid6social_eng_tag_privacy">
						<?php
						$cur = get_option( 'arshid6social_eng_tag_privacy', 'everyone' );
						foreach ( array( 'everyone' => __( 'Everyone', '6arshid-social-community-main' ), 'friends' => __( 'Friends only', '6arshid-social-community-main' ), 'nobody' => __( 'Nobody', '6arshid-social-community-main' ) ) as $v => $l ) :
							?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $cur, $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_bookmarks(): void {
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Bookmarks', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_bookmarks', __( 'Enable Bookmarks', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<input type="checkbox" name="arshid6social_eng_bookmark_collections" value="1"
						<?php checked( get_option( 'arshid6social_eng_bookmark_collections', true ) ); ?> />
					<?php esc_html_e( 'Allow named collections/folders', '6arshid-social-community-main' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_sticky_posts(): void {
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Sticky Posts', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_sticky_posts', __( 'Enable Sticky Posts', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<input type="checkbox" name="arshid6social_eng_sticky_multiple" value="1"
						<?php checked( get_option( 'arshid6social_eng_sticky_multiple', false ) ); ?> />
					<?php esc_html_e( 'Allow multiple site-wide sticky posts at once', '6arshid-social-community-main' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_share_posts(): void {
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Share / Repost', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_share_posts', __( 'Enable Share / Repost', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<input type="checkbox" name="arshid6social_eng_share_external" value="1"
						<?php checked( get_option( 'arshid6social_eng_share_external', true ) ); ?> />
					<?php esc_html_e( 'Enable external share buttons (X, Facebook, WhatsApp, Telegram)', '6arshid-social-community-main' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_polls(): void {
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Polls', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_polls', __( 'Enable Polls', '6arshid-social-community-main' ) ); ?>
			<?php $this->toggle( 'arshid6social_eng_advanced_polls', __( 'Enable Advanced Polls (image polls, quiz mode, ranked choice, CSV export)', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<?php esc_html_e( 'Max options per poll:', '6arshid-social-community-main' ); ?>
					<input type="number" name="arshid6social_eng_polls_max_options" min="2" max="50"
						value="<?php echo esc_attr( get_option( 'arshid6social_eng_polls_max_options', 10 ) ); ?>" style="width:70px;" />
				</label>
				<label>
					<input type="checkbox" name="arshid6social_eng_polls_allow_voter_suggest" value="1"
						<?php checked( get_option( 'arshid6social_eng_polls_allow_voter_suggest', false ) ); ?> />
					<?php esc_html_e( 'Allow voters to suggest new options (goes to moderation queue)', '6arshid-social-community-main' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_comments_gifs(): void {
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'GIFs in Comments &amp; Messages', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_comments_gifs', __( 'Enable GIF Picker', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<?php esc_html_e( 'GIPHY API Key:', '6arshid-social-community-main' ); ?>
					<input type="text" name="arshid6social_eng_giphy_api_key" class="regular-text"
						value="<?php echo esc_attr( get_option( 'arshid6social_eng_giphy_api_key', '' ) ); ?>" />
				</label>
				<label>
					<input type="checkbox" name="arshid6social_eng_gif_cache" value="1"
						<?php checked( get_option( 'arshid6social_eng_gif_cache', false ) ); ?> />
					<?php esc_html_e( 'Cache GIF URLs locally (saves bandwidth)', '6arshid-social-community-main' ); ?>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_comments_attachments(): void {
		$types = (array) get_option( 'arshid6social_eng_comment_att_types', array( 'image' ) );
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Comment Attachments', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_comments_attachments', __( 'Allow file attachments in comments', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<?php esc_html_e( 'Max size per file (MB):', '6arshid-social-community-main' ); ?>
					<input type="number" name="arshid6social_eng_comment_att_max_mb" min="1" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_eng_comment_att_max_mb', 5 ) ); ?>" style="width:70px;" />
				</label>
				<?php foreach ( array( 'image' => __( 'Images (JPEG, PNG, GIF, WebP)', '6arshid-social-community-main' ), 'document' => __( 'Documents (PDF)', '6arshid-social-community-main' ) ) as $k => $l ) : ?>
					<label>
						<input type="checkbox" name="arshid6social_eng_comment_att_types[]" value="<?php echo esc_attr( $k ); ?>"
							<?php checked( in_array( $k, $types, true ) ); ?> />
						<?php echo esc_html( $l ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function render_messages_attachments(): void {
		$types = (array) get_option( 'arshid6social_eng_msg_att_types', array( 'image', 'audio' ) );
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'Message Attachments', '6arshid-social-community-main' ); ?></h3>
			<?php $this->toggle( 'arshid6social_eng_messages_attachments', __( 'Allow file attachments in messages', '6arshid-social-community-main' ) ); ?>
			<div class="arshid6social-eng-sub">
				<label>
					<?php esc_html_e( 'Max size per file (MB):', '6arshid-social-community-main' ); ?>
					<input type="number" name="arshid6social_eng_msg_att_max_mb" min="1" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_eng_msg_att_max_mb', 10 ) ); ?>" style="width:70px;" />
				</label>
				<?php foreach ( array( 'image' => __( 'Images', '6arshid-social-community-main' ), 'audio' => __( 'Voice notes / Audio', '6arshid-social-community-main' ), 'document' => __( 'Documents (PDF)', '6arshid-social-community-main' ) ) as $k => $l ) : ?>
					<label>
						<input type="checkbox" name="arshid6social_eng_msg_att_types[]" value="<?php echo esc_attr( $k ); ?>"
							<?php checked( in_array( $k, $types, true ) ); ?> />
						<?php echo esc_html( $l ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function render_social_share_external(): void {
		$all_networks    = Features\Social_Share_External::networks();
		$enabled_nets    = (array) get_option( 'arshid6social_eng_social_share_networks', Features\Social_Share_External::default_networks() );
		$position        = get_option( 'arshid6social_eng_social_share_position', 'bottom' );
		$pages           = (array) get_option( 'arshid6social_eng_social_share_pages', array( 'feed', 'single', 'profile', 'group' ) );
		$style           = get_option( 'arshid6social_eng_social_share_style', 'icon_text' );
		$max_visible     = (int) get_option( 'arshid6social_eng_social_share_max_visible', 8 );
		$use_native      = (bool) get_option( 'arshid6social_eng_social_share_native', true );

		// Sort all_networks by saved drag-order.
		$saved_order_raw = get_option( 'arshid6social_eng_social_share_network_order', '' );
		$saved_order     = $saved_order_raw
			? array_filter( array_map( 'sanitize_key', explode( ',', $saved_order_raw ) ) )
			: array();

		if ( $saved_order ) {
			$ordered = array();
			foreach ( $saved_order as $k ) {
				if ( isset( $all_networks[ $k ] ) ) {
					$ordered[ $k ] = $all_networks[ $k ];
				}
			}
			// Append any networks added after the last save.
			foreach ( $all_networks as $k => $v ) {
				if ( ! isset( $ordered[ $k ] ) ) {
					$ordered[ $k ] = $v;
				}
			}
			$all_networks = $ordered;
		}
		?>
		<div class="arshid6social-eng-section">
			<h3><?php esc_html_e( 'External Social Sharing', '6arshid-social-community-main' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Let visitors share activity posts to 80+ external networks (Facebook, WhatsApp, Telegram, X, and more).', '6arshid-social-community-main' ); ?></p>
			<?php $this->toggle( 'arshid6social_eng_social_share_external', __( 'Enable External Social Sharing', '6arshid-social-community-main' ) ); ?>

			<div class="arshid6social-eng-sub">

				<!-- Networks picker – sortable via drag & drop -->
				<p>
					<strong><?php esc_html_e( 'Networks:', '6arshid-social-community-main' ); ?></strong>
					<span class="description">
						<?php esc_html_e( 'Check to enable · drag ⠿ to reorder · order is reflected in the share modal.', '6arshid-social-community-main' ); ?>
					</span>
				</p>
				<ul id="arshid6social-sn-net-list" style="display:flex;flex-wrap:wrap;gap:5px;margin:6px 0 14px;padding:0;list-style:none;">
					<?php foreach ( $all_networks as $key => $net ) : ?>
					<li class="arshid6social-sn-net-item" data-network="<?php echo esc_attr( $key ); ?>"
						style="display:inline-flex;align-items:center;gap:4px;padding:3px 6px 3px 4px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;background:#fff;white-space:nowrap;user-select:none;">
						<span class="arshid6social-sn-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', '6arshid-social-community-main' ); ?>"
							style="cursor:grab;opacity:.45;font-size:15px;line-height:1;padding:0 2px;flex-shrink:0;">⠿</span>
						<label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;margin:0;">
							<input type="checkbox" name="arshid6social_eng_social_share_networks[]"
								value="<?php echo esc_attr( $key ); ?>"
								<?php checked( in_array( $key, $enabled_nets, true ) ); ?> />
							<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?php echo esc_attr( $net['color'] ); ?>;flex-shrink:0;"></span>
							<?php echo esc_html( $net['label'] ); ?>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
				<input type="hidden" id="arshid6social-sn-net-order"
					name="arshid6social_eng_social_share_network_order"
					value="<?php echo esc_attr( implode( ',', array_keys( $all_networks ) ) ); ?>" />

				<?php
				wp_add_inline_script(
					'jquery-ui-sortable',
					'jQuery( function( $ ) {
					var $list  = $( "#arshid6social-sn-net-list" );
					var $order = $( "#arshid6social-sn-net-order" );

					$list.sortable( {
						handle:    ".arshid6social-sn-drag-handle",
						tolerance: "pointer",
						cursor:    "grabbing",
						items:     ".arshid6social-sn-net-item",
						update: function() {
							var keys = $list.find( ".arshid6social-sn-net-item" ).map( function() {
								return $( this ).data( "network" );
							} ).get();
							$order.val( keys.join( "," ) );
						}
					} );

					// Visual: darken handle on hover
					$list.on( "mouseenter", ".arshid6social-sn-drag-handle", function() {
						$( this ).css( "opacity", ".9" );
					} ).on( "mouseleave", ".arshid6social-sn-drag-handle", function() {
						if ( ! $list.hasClass( "ui-sortable-helper" ) ) {
							$( this ).css( "opacity", ".45" );
						}
					} );

					// Highlight dragged item
					$list.on( "sortstart", function( e, ui ) {
						ui.item.css( { "opacity": ".6", "box-shadow": "0 4px 12px rgba(0,0,0,.18)" } );
					} ).on( "sortstop", function( e, ui ) {
						ui.item.css( { "opacity": "", "box-shadow": "" } );
					} );
				} );'
				);
				?>

				<!-- Position -->
				<label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
					<strong><?php esc_html_e( 'Button position:', '6arshid-social-community-main' ); ?></strong>
					<select name="arshid6social_eng_social_share_position">
						<?php foreach ( array(
							'bottom'   => __( 'In actions bar (bottom of post)', '6arshid-social-community-main' ),
							'top'      => __( 'Above post content', '6arshid-social-community-main' ),
							'floating' => __( 'Floating button (fixed on screen)', '6arshid-social-community-main' ),
						) as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $position, $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<!-- Pages -->
				<p style="margin-bottom:4px;"><strong><?php esc_html_e( 'Show on pages:', '6arshid-social-community-main' ); ?></strong></p>
				<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
					<?php foreach ( array(
						'feed'    => __( 'Activity Feed', '6arshid-social-community-main' ),
						'single'  => __( 'Single Activity', '6arshid-social-community-main' ),
						'profile' => __( 'Member Profiles', '6arshid-social-community-main' ),
						'group'   => __( 'Group Pages', '6arshid-social-community-main' ),
					) as $v => $l ) : ?>
					<label style="display:inline-flex;align-items:center;gap:5px;">
						<input type="checkbox" name="arshid6social_eng_social_share_pages[]"
							value="<?php echo esc_attr( $v ); ?>"
							<?php checked( in_array( $v, $pages, true ) ); ?> />
						<?php echo esc_html( $l ); ?>
					</label>
					<?php endforeach; ?>
				</div>

				<!-- Style -->
				<label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
					<strong><?php esc_html_e( 'Button style:', '6arshid-social-community-main' ); ?></strong>
					<select name="arshid6social_eng_social_share_style">
						<?php foreach ( array(
							'icon_text' => __( 'Icon + Label', '6arshid-social-community-main' ),
							'icon_only' => __( 'Icon only', '6arshid-social-community-main' ),
							'text_only' => __( 'Text only', '6arshid-social-community-main' ),
						) as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $style, $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<!-- Max visible -->
				<label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
					<strong><?php esc_html_e( 'Visible networks before "More…":', '6arshid-social-community-main' ); ?></strong>
					<input type="number" name="arshid6social_eng_social_share_max_visible"
						min="2" max="30" style="width:65px;"
						value="<?php echo esc_attr( $max_visible ); ?>" />
					<span class="description"><?php esc_html_e( '(0 = show all)', '6arshid-social-community-main' ); ?></span>
				</label>

				<!-- Native share API -->
				<label style="display:inline-flex;align-items:center;gap:6px;">
					<input type="checkbox" name="arshid6social_eng_social_share_native" value="1"
						<?php checked( $use_native ); ?> />
					<?php esc_html_e( 'Use native share sheet on mobile (iOS/Android)', '6arshid-social-community-main' ); ?>
				</label>

			</div>
		</div>
		<?php
	}
}
