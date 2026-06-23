<?php
namespace Arshid6Social\Admin;

/**
 * Plugin settings page (tabbed).
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Settings
 *
 * Renders a tabbed settings page and registers all options with the Settings API.
 * Each tab maps to a component or a top-level concern.
 */
final class Admin_Settings {

	private static ?Admin_Settings $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_footer',                        array( $this, 'print_video_uploader_script' ) );
		add_action( 'wp_ajax_arshid6social_upload_video',        array( $this, 'ajax_upload_video' ) );
		add_action( 'admin_enqueue_scripts',               array( $this, 'enqueue_settings_assets' ) );
	}

	public function enqueue_settings_assets(): void {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ( $_GET['page'] ?? '' ) !== 'arshid6social-settings' ) {
			return;
		}
		wp_enqueue_media();
	}

	/**
	 * AJAX: upload a video file to the WP Media Library and return its URL.
	 */
	public function ajax_upload_video(): void {
		check_ajax_referer( 'arshid6social_upload_video', 'nonce' );

		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid social community' ) ) );
		}

		if ( empty( $_FILES['arshid6social_video_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file received.', '6arshid social community' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'arshid6social_video_file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$url = wp_get_attachment_url( $attachment_id );
		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Outputs the video-uploader JS in the footer.
	 */
	public function print_video_uploader_script(): void {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ( $_GET['page'] ?? '' ) !== 'arshid6social-settings' ) {
			return;
		}

		$bundled_video = plugins_url( 'assets/videos/home-bg.mp4', ARSHID6SOCIAL_PLUGIN_FILE );
		$nonce         = wp_create_nonce( 'arshid6social_upload_video' );
		$ajax_url      = admin_url( 'admin-ajax.php' );
		?>
		<script>
		jQuery(function($){
			var $btn          = $('#arshid6social-video-upload-btn');
			var $rmBtn        = $('#arshid6social-video-remove-btn');
			var $fileInput    = $('#arshid6social-video-file-input');
			var $urlText      = $('#arshid6social-video-url-text');
			var $hidden       = $('#arshid6social-video-url');
			var $bgType       = $('#arshid6social-bg-type');
			var $previewImg   = $('#arshid6social-video-preview');
			var $previewVid   = $('#arshid6social-video-preview-video');
			var $desc         = $('#arshid6social-video-desc');
			var $progress     = $('#arshid6social-video-progress');

			var bundled    = <?php echo wp_json_encode( $bundled_video ); ?>;
			var ajaxUrl    = <?php echo wp_json_encode( $ajax_url ); ?>;
			var nonce      = <?php echo wp_json_encode( $nonce ); ?>;

			if ( ! $btn.length ) return;

			var pendingType = null;

			/* ── Upload button → trigger hidden file input ── */
			$btn.on('click', function(e){
				e.preventDefault();
				$fileInput.trigger('click');
			});

			/* ── File selected → upload via AJAX ── */
			$fileInput.on('change', function(){
				var file = this.files[0];
				if ( ! file ) return;

				pendingType = file.type.startsWith('image/') ? 'image' : 'video';

				var fd = new FormData();
				fd.append('action',             'arshid6social_upload_video');
				fd.append('nonce',              nonce);
				fd.append('arshid6social_video_file', file, file.name);

				$btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Uploading…', '6arshid social community' ) ); ?>);
				$progress.show();

				$.ajax({
					url        : ajaxUrl,
					type       : 'POST',
					data       : fd,
					processData: false,
					contentType: false,
					xhr: function(){
						var xhr = new window.XMLHttpRequest();
						xhr.upload.addEventListener('progress', function(ev){
							if ( ev.lengthComputable ){
								var pct = Math.round( ev.loaded / ev.total * 100 );
								$progress.find('.arshid6social-progress-bar').css('width', pct + '%');
								$progress.find('.arshid6social-progress-label').text( pct + '%' );
							}
						}, false);
						return xhr;
					},
					success: function(res){
						if ( res.success ){
							setMedia( res.data.url, pendingType || 'video' );
						} else {
							alert( res.data.message || <?php echo wp_json_encode( __( 'Upload failed.', '6arshid social community' ) ); ?> );
						}
					},
					error: function(){
						alert(<?php echo wp_json_encode( __( 'Upload error. Please try again.', '6arshid social community' ) ); ?>);
					},
					complete: function(){
						$btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Choose / Upload Video or Image', '6arshid social community' ) ); ?>);
						$progress.hide();
						$fileInput.val('');
						pendingType = null;
					}
				});
			});

			/* ── Remove button ── */
			$rmBtn.on('click', function(e){
				e.preventDefault();
				$hidden.val('');
				$bgType.val('video');
				$urlText.val('');
				$previewImg.hide();
				$previewVid.attr('src', bundled).show()[0].load();
				$desc.text(<?php echo wp_json_encode( __( 'Using default video.', '6arshid social community' ) ); ?>);
				$(this).hide();
			});

			/* ── Paste URL directly ── */
			$urlText.on('input change', function(){
				var url = $(this).val().trim();
				if ( url ) {
					var type = /\.(jpe?g|png|gif|webp|avif|svg)(\?.*)?$/i.test( url ) ? 'image' : 'video';
					setMedia( url, type );
				}
			});

			function setMedia( url, type ){
				$hidden.val( url );
				$bgType.val( type );
				$urlText.val( url );
				if ( type === 'image' ) {
					$previewVid.hide();
					$previewImg.attr('src', url).show();
				} else {
					$previewImg.hide();
					$previewVid.attr('src', url).show()[0].load();
				}
				$desc.text(<?php echo wp_json_encode( __( 'Custom background is active.', '6arshid social community' ) ); ?>);
				$rmBtn.show();
			}
		});
		</script>
		<?php
	}

	/** @return array<string, string> Tab key → label. */
	private function tabs(): array {
		return apply_filters(
			'arshid6social_settings_tabs',
			array(
				'general'       => __( 'General', '6arshid social community' ),
				'components'    => __( 'Components', '6arshid social community' ),
				'members'       => __( 'Members', '6arshid social community' ),
				'activity'      => __( 'Activity', '6arshid social community' ),
				'groups'        => __( 'Groups', '6arshid social community' ),
				'messages'      => __( 'Messages', '6arshid social community' ),
				'notifications' => __( 'Notifications', '6arshid social community' ),
				'security'      => __( 'Security', '6arshid social community' ),
				'emails'        => __( 'Emails', '6arshid social community' ),
				'appearance'    => __( 'Appearance', '6arshid social community' ),
				'search'        => __( 'Search', '6arshid social community' ),
				'tools'         => __( 'Tools', '6arshid social community' ),
				'permalinks'    => __( 'Permalinks', '6arshid social community' ),
			)
		);
	}

	/**
	 * Registers all plugin options with the WP Settings API.
	 */
	public function register(): void {
		$option_groups = array(
			'arshid6social_general'       => array(
				'arshid6social_allow_registration',
				'arshid6social_date_format',
				'arshid6social_invitation_limit',
			),
			'arshid6social_components'    => array( 'arshid6social_enabled_components', 'arshid6social_stories_enabled', 'arshid6social_verification_enabled', 'arshid6social_blocking_enabled', 'arshid6social_activity_stats_bar' ),
			'arshid6social_members'       => array(
				'arshid6social_members_per_page',
				'arshid6social_members_pagination_type',
				'arshid6social_who_to_follow_per_page',
				'arshid6social_members_show_friend_count',
				'arshid6social_profile_photo_size',
				'arshid6social_cover_photo_width',
				'arshid6social_cover_photo_height',
				'arshid6social_max_upload_size_mb',
				'arshid6social_allowed_upload_types',
				'arshid6social_verification_enabled',
				'arshid6social_verification_types',
				'arshid6social_verification_badge_image',
				'arshid6social_verification_require_doc',
				'arshid6social_verification_expiry_months',
				'arshid6social_verification_doc_purge',
				'arshid6social_verification_rate_limit',
			),
			'arshid6social_activity'      => array(
				'arshid6social_activity_per_page',
				'arshid6social_activity_allow_comments',
				'arshid6social_activity_allow_media',
				'arshid6social_activity_allowed_media_types',
				'arshid6social_activity_pagination_type',
				'arshid6social_stories_enabled',
				'arshid6social_stories_expiry_hours',
				'arshid6social_stories_max_video_secs',
				'arshid6social_stories_allow_video',
				'arshid6social_stories_highlights',
				'arshid6social_stories_rate_limit',
				'arshid6social_stories_bottom_bar',
				'arshid6social_stories_bottom_bar_marketplace',
				'arshid6social_stories_bottom_bar_messages',
			),
			'arshid6social_groups'        => array( 'arshid6social_groups_per_page' ),
			'arshid6social_messages'      => array( 'arshid6social_messages_per_page', 'arshid6social_messages_story_enabled' ),
			'arshid6social_notifications' => array(
				'arshid6social_email_notifications',
				'arshid6social_email_digest',
			),
			'arshid6social_security'      => array(
				'arshid6social_enable_akismet',
				'arshid6social_enable_recaptcha',
				'arshid6social_recaptcha_site_key',
				'arshid6social_recaptcha_secret_key',
				'arshid6social_new_member_moderation',
				'arshid6social_auto_suspend_threshold',
				'arshid6social_banned_words',
				'arshid6social_rate_limit_posts',
				'arshid6social_rate_limit_messages',
				'arshid6social_rate_limit_friends',
				'arshid6social_blocking_enabled',
				'arshid6social_blocking_show_reason',
				'arshid6social_report_reasons',
				'arshid6social_suspend_reasons',
				'arshid6social_report_allow_attachments',
				'arshid6social_reserved_usernames',
				'arshid6social_username_min_length',
			),
			'arshid6social_emails'        => array(),
			'arshid6social_appearance'    => array(
				'arshid6social_primary_color',
				'arshid6social_dark_mode',
				'arshid6social_home_video_url',
				'arshid6social_home_bg_type',
				'arshid6social_logo_mobile',
			),
			'arshid6social_permalinks'    => array(
				'arshid6social_permalink_tag_base',
				'arshid6social_permalink_activity_base',
				'arshid6social_activity_uid_enabled',
				'arshid6social_marketplace_slug',
			),
			'arshid6social_search'        => array(
				'arshid6social_search_pagination_type',
				'arshid6social_search_results_per_section',
				'arshid6social_search_per_page',
			),
		);

		foreach ( $option_groups as $group => $options ) {
			foreach ( $options as $option_name ) {
				register_setting( $group, $option_name, array( $this, 'sanitize_option' ) );
			}
		}
	}

	/**
	 * Sanitizes any plugin option based on its name.
	 *
	 * @param mixed $value Raw value from the form.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_option( mixed $value ): mixed {
		// Derive the option name from the current filter hook (sanitize_option_{name}).
		$current_hook = current_filter();
		$option       = str_starts_with( $current_hook, 'sanitize_option_' )
			? substr( $current_hook, strlen( 'sanitize_option_' ) )
			: '';

		// Arrays (checkboxes, multi-selects).
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		// Integers.
		if ( in_array( $option, array( 'arshid6social_members_per_page', 'arshid6social_who_to_follow_per_page', 'arshid6social_activity_per_page', 'arshid6social_groups_per_page', 'arshid6social_messages_per_page', 'arshid6social_profile_photo_size', 'arshid6social_cover_photo_width', 'arshid6social_cover_photo_height', 'arshid6social_max_upload_size_mb', 'arshid6social_auto_suspend_threshold', 'arshid6social_rate_limit_posts', 'arshid6social_rate_limit_messages', 'arshid6social_rate_limit_friends', 'arshid6social_invitation_limit', 'arshid6social_search_results_per_section', 'arshid6social_search_per_page' ), true ) ) {
			return absint( $value );
		}

		// Hex colour.
		if ( 'arshid6social_primary_color' === $option ) {
			return sanitize_hex_color( $value ) ?? '#2563eb';
		}

		// Booleans stored as 1/0.
		if ( in_array( $option, array( 'arshid6social_allow_registration', 'arshid6social_enable_akismet', 'arshid6social_enable_recaptcha', 'arshid6social_new_member_moderation', 'arshid6social_email_notifications', 'arshid6social_activity_allow_comments', 'arshid6social_activity_allow_media', 'arshid6social_activity_uid_enabled', 'arshid6social_members_show_friend_count' ), true ) ) {
			return (bool) $value;
		}

		// Background media URL — sanitize as URL; empty string means "use bundled default".
		if ( 'arshid6social_home_video_url' === $option ) {
			return esc_url_raw( (string) $value );
		}

		// Background media type: 'video' or 'image'.
		if ( 'arshid6social_home_bg_type' === $option ) {
			return in_array( $value, array( 'video', 'image' ), true ) ? $value : 'video';
		}

		// Permalink base slugs — lowercase letters, digits, hyphens only.
		if ( in_array( $option, array( 'arshid6social_permalink_tag_base', 'arshid6social_permalink_activity_base' ), true ) ) {
			$slug = sanitize_title( (string) $value );
			if ( '' === $slug ) {
				$slug = ( 'arshid6social_permalink_tag_base' === $option ) ? 'hashtags' : 'activity';
			}
			return $slug;
		}

		if ( 'arshid6social_marketplace_slug' === $option ) {
			return sanitize_title( (string) $value ) ?: 'marketplace';
		}

		// Pagination type.
		if ( 'arshid6social_activity_pagination_type' === $option ) {
			return in_array( $value, array( 'infinite_scroll', 'pagination' ), true ) ? $value : 'infinite_scroll';
		}
		if ( 'arshid6social_members_pagination_type' === $option ) {
			return in_array( $value, array( 'infinite_scroll', 'pagination' ), true ) ? $value : 'pagination';
		}
		if ( 'arshid6social_search_pagination_type' === $option ) {
			return in_array( $value, array( 'infinite_scroll', 'pagination' ), true ) ? $value : 'pagination';
		}

		// Textarea fields.
		if ( in_array( $option, array( 'arshid6social_banned_words', 'arshid6social_report_reasons', 'arshid6social_suspend_reasons', 'arshid6social_reserved_usernames' ), true ) ) {
			return sanitize_textarea_field( $value );
		}

		// Username min length.
		if ( 'arshid6social_username_min_length' === $option ) {
			$val = absint( $value );
			return max( 1, min( 60, $val ) );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Renders the full settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', '6arshid social community' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs        = $this->tabs();

		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = 'general';
		}

		$option_group = 'ARSHID6SOCIAL_' . $current_tab;
		?>
		<div class="wrap arshid6social-admin-settings">
			<h1><?php esc_html_e( 'Social Network Settings', '6arshid social community' ); ?></h1>

			<nav class="nav-tab-wrapper arshid6social-nav-tabs">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=arshid6social-settings&tab=' . $tab ) ); ?>"
					   class="nav-tab <?php echo ( $current_tab === $tab ) ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'tools' === $current_tab ) : ?>
				<?php $this->render_tools_tab(); ?>
			<?php else : ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( $option_group );
					$this->render_tab( $current_tab );
					submit_button( __( 'Save Settings', '6arshid social community' ) );
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders settings fields for the given tab.
	 *
	 * @param string $tab Active tab key.
	 */
	private function render_tab( string $tab ): void {
		switch ( $tab ) {
			case 'general':
				$this->render_general_tab();
				break;
			case 'components':
				$this->render_components_tab();
				break;
			case 'members':
				$this->render_members_tab();
				break;
			case 'activity':
				$this->render_activity_tab();
				break;
			case 'groups':
				$this->render_groups_tab();
				break;
			case 'messages':
				$this->render_messages_tab();
				break;
			case 'notifications':
				$this->render_notifications_tab();
				break;
			case 'security':
				$this->render_security_tab();
				break;
			case 'emails':
				$this->render_emails_tab();
				break;
			case 'appearance':
				$this->render_appearance_tab();
				break;
			case 'permalinks':
				$this->render_permalinks_tab();
				break;
			case 'search':
				$this->render_search_tab();
				break;
			default:
				do_action( 'arshid6social_settings_tab_' . $tab );
		}
	}

	private function render_search_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Search Pagination Style', '6arshid social community' ); ?></th>
				<td>
					<select name="arshid6social_search_pagination_type">
						<option value="pagination" <?php selected( get_option( 'arshid6social_search_pagination_type', 'pagination' ), 'pagination' ); ?>>
							<?php esc_html_e( 'Page Numbers (Basic Pagination)', '6arshid social community' ); ?>
						</option>
						<option value="infinite_scroll" <?php selected( get_option( 'arshid6social_search_pagination_type', 'pagination' ), 'infinite_scroll' ); ?>>
							<?php esc_html_e( 'Infinite Scroll', '6arshid social community' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'How results are paginated when viewing a single search section (People, Groups, etc.).', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Results Per Section (Overview)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_search_results_per_section" min="3" max="20"
						value="<?php echo esc_attr( get_option( 'arshid6social_search_results_per_section', 5 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Minimum number of items shown per section in the "All" overview tab. Must be between 3 and 20.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Results Per Page (Section View)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_search_per_page" min="5" max="50"
						value="<?php echo esc_attr( get_option( 'arshid6social_search_per_page', 10 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Number of items per page when viewing a specific section (Activity, People, Groups, or Marketplace).', '6arshid social community' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_tools_tab(): void {
		$imported = (bool) get_option( 'arshid6social_sample_data_imported' );
		$nonce    = wp_create_nonce( 'arshid6social_sample_data' );
		?>
		<div style="max-width:640px;margin-top:1.5rem;">

			<h2 style="margin-top:0;"><?php esc_html_e( 'Sample Data', '6arshid social community' ); ?></h2>
			<p style="color:#64748b;">
				<?php esc_html_e( 'Use the tools below to import or remove demo content from your social network.', '6arshid social community' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Import Sample Data', '6arshid social community' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:.75rem;">
							<?php esc_html_e( 'Creates 50 users, 50 activity posts, 100 notifications for admin, 50 marketplace listings, 50 groups, 50 saved posts (admin), 50 message threads (to admin), 30 text stories, and 1 ad.', '6arshid social community' ); ?>
						</p>
						<button type="button" id="arshid6social-import-sample" class="button button-primary"
							<?php disabled( $imported ); ?>>
							<?php echo $imported
								? esc_html__( 'Already Imported', '6arshid social community' )
								: esc_html__( 'Import Sample Data', '6arshid social community' ); ?>
						</button>
						<span id="arshid6social-import-status" style="display:none;margin-inline-start:.75rem;font-size:.875rem;vertical-align:middle;"></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Delete Sample Data', '6arshid social community' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:.75rem;">
							<?php esc_html_e( 'Permanently removes all previously imported sample users, posts, notifications, listings, groups, and bookmarks.', '6arshid social community' ); ?>
						</p>
						<button type="button" id="arshid6social-delete-sample" class="button button-secondary"
							<?php disabled( ! $imported ); ?>>
							<?php esc_html_e( 'Delete Sample Data', '6arshid social community' ); ?>
						</button>
						<span id="arshid6social-delete-status" style="display:none;margin-inline-start:.75rem;font-size:.875rem;vertical-align:middle;"></span>
					</td>
				</tr>
			</table>
		</div>

		<script>
		( function() {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

			async function runAction( action, btn, statusEl, successLabel, errorLabel ) {
				btn.disabled = true;
				statusEl.style.display = 'none';
				const orig = btn.textContent;
				btn.textContent = '<?php echo esc_js( __( 'Working…', '6arshid social community' ) ); ?>';

				const body = new FormData();
				body.append( 'action', action );
				body.append( 'nonce', nonce );

				try {
					const resp = await fetch( ajaxUrl, { method: 'POST', body } );
					const json = await resp.json();
					statusEl.style.display = 'inline';
					if ( json.success ) {
						btn.textContent = successLabel;
						statusEl.style.color = '#16a34a';
						statusEl.textContent = json.data?.message ?? '';
						setTimeout( () => location.reload(), 1200 );
					} else {
						btn.disabled = false;
						btn.textContent = orig;
						statusEl.style.color = '#dc2626';
						statusEl.textContent = json.data?.message ?? errorLabel;
					}
				} catch ( e ) {
					btn.disabled = false;
					btn.textContent = orig;
				}
			}

			const importBtn = document.getElementById( 'arshid6social-import-sample' );
			if ( importBtn ) {
				importBtn.addEventListener( 'click', () => runAction(
					'arshid6social_import_sample_data',
					importBtn,
					document.getElementById( 'arshid6social-import-status' ),
					<?php echo wp_json_encode( __( 'Imported!', '6arshid social community' ) ); ?>,
					<?php echo wp_json_encode( __( 'Import failed.', '6arshid social community' ) ); ?>
				) );
			}

			const deleteBtn = document.getElementById( 'arshid6social-delete-sample' );
			if ( deleteBtn ) {
				deleteBtn.addEventListener( 'click', () => {
					if ( ! confirm( <?php echo wp_json_encode( __( 'Delete all sample data? This cannot be undone.', '6arshid social community' ) ); ?> ) ) return;
					runAction(
						'arshid6social_delete_sample_data',
						deleteBtn,
						document.getElementById( 'arshid6social-delete-status' ),
						<?php echo wp_json_encode( __( 'Deleted!', '6arshid social community' ) ); ?>,
						<?php echo wp_json_encode( __( 'Delete failed.', '6arshid social community' ) ); ?>
					);
				} );
			}
		} )();
		</script>
		<?php
	}

	private function render_general_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow Registration', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_allow_registration" value="1"
							<?php checked( get_option( 'arshid6social_allow_registration', true ) ); ?> />
						<?php esc_html_e( 'Allow new users to register on the social network.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Date Format', '6arshid social community' ); ?></th>
				<td>
					<select name="arshid6social_date_format">
						<option value="relative" <?php selected( get_option( 'arshid6social_date_format', 'relative' ), 'relative' ); ?>>
							<?php esc_html_e( 'Relative (e.g. 5 minutes ago)', '6arshid social community' ); ?>
						</option>
						<option value="absolute" <?php selected( get_option( 'arshid6social_date_format', 'relative' ), 'absolute' ); ?>>
							<?php esc_html_e( 'Absolute (e.g. June 12, 2026)', '6arshid social community' ); ?>
						</option>
						<option value="jalali" <?php selected( get_option( 'arshid6social_date_format', 'relative' ), 'jalali' ); ?>>
							<?php esc_html_e( 'Jalali / Persian calendar', '6arshid social community' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Invitation Limit', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_invitation_limit" min="0" max="1000"
						value="<?php echo esc_attr( get_option( 'arshid6social_invitation_limit', 20 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Maximum invitations a member can send. 0 = unlimited.', '6arshid social community' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_components_tab(): void {
		$enabled = (array) get_option( 'arshid6social_enabled_components', array() );
		$all     = array(
			'activity'      => array( 'label' => __( 'Activity Streams', '6arshid social community' ),  'desc' => __( 'News feed, posts, reactions, comments', '6arshid social community' ) ),
			'groups'        => array( 'label' => __( 'Groups', '6arshid social community' ),             'desc' => __( 'Public, private, and hidden groups', '6arshid social community' ) ),
			'friends'       => array( 'label' => __( 'Friends & Follow', '6arshid social community' ),   'desc' => __( 'Friend requests, follow, block', '6arshid social community' ) ),
			'messages'      => array( 'label' => __( 'Private Messages', '6arshid social community' ),   'desc' => __( 'One-to-one and group messaging', '6arshid social community' ) ),
			'notifications' => array( 'label' => __( 'Notifications', '6arshid social community' ),      'desc' => __( 'On-site and email notifications', '6arshid social community' ) ),
			'moderation'    => array( 'label' => __( 'Moderation', '6arshid social community' ),         'desc' => __( 'Reports, bans, audit log', '6arshid social community' ) ),
		);

		// Engagement Pack options (stored in their own flags, not arshid6social_enabled_components).
		$pack = array(
			'arshid6social_stories_enabled'      => array(
				'label' => __( 'Stories', '6arshid social community' ),
				'desc'  => __( '24-hour ephemeral photo, video, and text stories', '6arshid social community' ),
			),
			'arshid6social_verification_enabled' => array(
				'label' => __( 'Verification Badges', '6arshid social community' ),
				'desc'  => __( 'Verified badge + user request flow and admin queue', '6arshid social community' ),
			),
			'arshid6social_blocking_enabled'     => array(
				'label' => __( 'Block System', '6arshid social community' ),
				'desc'  => __( 'Block / unblock users with optional reason', '6arshid social community' ),
			),
			'arshid6social_activity_stats_bar'   => array(
				'label' => __( 'Activity Stats Bar', '6arshid social community' ),
				'desc'  => __( 'Show engagement counts (comments, reposts, likes, views) below each post', '6arshid social community' ),
			),
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active Components', '6arshid social community' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'The Members component is always active.', '6arshid social community' ); ?></p>
					<?php foreach ( $all as $key => $info ) : ?>
						<label style="display:block;margin-top:8px;">
							<input type="checkbox" name="arshid6social_enabled_components[]"
								value="<?php echo esc_attr( $key ); ?>"
								<?php checked( in_array( $key, $enabled, true ) ); ?> />
							<strong><?php echo esc_html( $info['label'] ); ?></strong>
							<span style="color:#64748b;margin-left:6px;">— <?php echo esc_html( $info['desc'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Engagement Pack', '6arshid social community' ); ?></th>
				<td>
					<p class="description" style="margin-bottom:8px;">
						<?php esc_html_e( 'Optional features. Each has its own settings under the relevant tab.', '6arshid social community' ); ?>
					</p>
					<?php foreach ( $pack as $option_key => $info ) : ?>
						<label style="display:block;margin-top:8px;">
							<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>"
								value="1"
								<?php checked( (bool) get_option( $option_key, false ) ); ?> />
							<strong><?php echo esc_html( $info['label'] ); ?></strong>
							<span style="color:#64748b;margin-left:6px;">— <?php echo esc_html( $info['desc'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_members_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Members Per Page', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_members_per_page" min="5" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_members_per_page', 20 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Members Pagination Style', '6arshid social community' ); ?></th>
				<td>
					<select name="arshid6social_members_pagination_type">
						<option value="pagination" <?php selected( get_option( 'arshid6social_members_pagination_type', 'pagination' ), 'pagination' ); ?>>
							<?php esc_html_e( 'Page Numbers (Basic Pagination)', '6arshid social community' ); ?>
						</option>
						<option value="infinite_scroll" <?php selected( get_option( 'arshid6social_members_pagination_type', 'pagination' ), 'infinite_scroll' ); ?>>
							<?php esc_html_e( 'Infinite Scroll', '6arshid social community' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'How members are paginated on the /members/ directory page.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '"Who to Follow" Count', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_who_to_follow_per_page" min="1" max="20"
						value="<?php echo esc_attr( get_option( 'arshid6social_who_to_follow_per_page', 3 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Number of suggested members shown in the right sidebar "Who to Follow" widget.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show Friend Count in Members Directory', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_members_show_friend_count" value="1"
							<?php checked( get_option( 'arshid6social_members_show_friend_count', false ) ); ?> />
						<?php esc_html_e( 'Display the number of friends each member has on the /members/ directory page.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Avatar Size (px)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_profile_photo_size" min="50" max="500"
						value="<?php echo esc_attr( get_option( 'arshid6social_profile_photo_size', 150 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cover Photo Width (px)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_cover_photo_width" min="400" max="3840"
						value="<?php echo esc_attr( get_option( 'arshid6social_cover_photo_width', 1200 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cover Photo Height (px)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_cover_photo_height" min="100" max="1000"
						value="<?php echo esc_attr( get_option( 'arshid6social_cover_photo_height', 350 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Upload Size (MB)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_max_upload_size_mb" min="1" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_max_upload_size_mb', 5 ) ); ?>" />
				</td>
			</tr>
		</table>
		<?php
		$this->render_verification_settings();
	}

	private function render_verification_settings(): void {
		$types        = (array) get_option( 'arshid6social_verification_types', array() );
		$badge_img_id  = (int) get_option( 'arshid6social_verification_badge_image', 0 );
		$badge_img_url = $badge_img_id ? wp_get_attachment_image_url( $badge_img_id, array( 32, 32 ) ) : '';
		?>
		<h2><?php esc_html_e( 'Verification Badges', '6arshid social community' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Verification', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_verification_enabled" value="1"
							<?php checked( get_option( 'arshid6social_verification_enabled', false ) ); ?> />
						<?php esc_html_e( 'Show verified badge on profiles, posts, and stories.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Require Document Upload', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_verification_require_doc" value="1"
							<?php checked( get_option( 'arshid6social_verification_require_doc', false ) ); ?> />
						<?php esc_html_e( 'Make document upload mandatory in the verification request form.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Badge Expiry (months)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_verification_expiry_months" min="0" max="120"
						value="<?php echo esc_attr( get_option( 'arshid6social_verification_expiry_months', 0 ) ); ?>" />
					<p class="description"><?php esc_html_e( '0 = never expires.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-Purge Documents After Decision', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_verification_doc_purge" value="1"
							<?php checked( get_option( 'arshid6social_verification_doc_purge', true ) ); ?> />
						<?php esc_html_e( 'Delete uploaded identity documents after the request is approved or rejected.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit: Requests per hour', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_verification_rate_limit" min="1" max="20"
						value="<?php echo esc_attr( get_option( 'arshid6social_verification_rate_limit', 3 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Badge Image', '6arshid social community' ); ?></th>
				<td>
					<input type="hidden" name="arshid6social_verification_badge_image" id="arshid6social-badge-img-id"
						value="<?php echo esc_attr( $badge_img_id ?: '' ); ?>" />

					<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
						<div id="arshid6social-badge-img-preview" style="width:48px;height:48px;border:2px dashed #d1d5db;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f9fafb;">
							<?php if ( $badge_img_url ) : ?>
								<img src="<?php echo esc_url( $badge_img_url ); ?>" style="width:100%;height:100%;object-fit:contain;" alt="" />
							<?php else : ?>
								<span style="color:#9ca3af;font-size:20px;">🏅</span>
							<?php endif; ?>
						</div>

						<button type="button" class="button" id="arshid6social-badge-img-select">
							<?php esc_html_e( 'Select / Upload Image', '6arshid social community' ); ?>
						</button>

						<button type="button" class="button" id="arshid6social-badge-img-remove"
							<?php echo $badge_img_id ? '' : 'style="display:none;"'; ?>>
							<?php esc_html_e( 'Remove', '6arshid social community' ); ?>
						</button>
					</div>

					<p class="description" style="margin-top:8px;">
						<?php esc_html_e( 'Upload a custom image for the verified badge (PNG/SVG recommended, square, 32–64px). Leave empty to use the text badge character defined in each type below.', '6arshid social community' ); ?>
					</p>

					<script>
					( function () {
						var frame;
						var btnSelect  = document.getElementById( 'arshid6social-badge-img-select' );
						var btnRemove  = document.getElementById( 'arshid6social-badge-img-remove' );
						var inputId    = document.getElementById( 'arshid6social-badge-img-id' );
						var preview    = document.getElementById( 'arshid6social-badge-img-preview' );

						btnSelect.addEventListener( 'click', function () {
							if ( frame ) { frame.open(); return; }
							frame = wp.media( {
								title:    '<?php echo esc_js( __( 'Select Verification Badge Image', '6arshid social community' ) ); ?>',
								button:   { text: '<?php echo esc_js( __( 'Use this image', '6arshid social community' ) ); ?>' },
								multiple: false,
								library:  { type: 'image' },
							} );
							frame.on( 'select', function () {
								var attachment = frame.state().get( 'selection' ).first().toJSON();
								inputId.value    = attachment.id;
								var src          = attachment.sizes && attachment.sizes.thumbnail
									? attachment.sizes.thumbnail.url
									: attachment.url;
								preview.innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:contain;" alt="" />';
								btnRemove.style.display = '';
							} );
							frame.open();
						} );

						btnRemove.addEventListener( 'click', function () {
							inputId.value     = '';
							preview.innerHTML = '<span style="color:#9ca3af;font-size:20px;">🏅</span>';
							btnRemove.style.display = 'none';
						} );
					} )();
					</script>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Verification Types (JSON)', '6arshid social community' ); ?></th>
				<td>
					<textarea name="arshid6social_verification_types_json" rows="8" class="large-text code"
						id="arshid6social-vtypes-json"><?php echo esc_textarea( wp_json_encode( $types, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'JSON array — each item: { "key": "general", "label": "Verified", "badge": "✓", "color": "#2563eb" }.', '6arshid social community' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_activity_tab(): void {
		$allowed_media_types = (array) get_option( 'arshid6social_activity_allowed_media_types', array( 'image' ) );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Activity Items Per Page', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_activity_per_page" min="5" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_activity_per_page', 20 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Pagination Style', '6arshid social community' ); ?></th>
				<td>
					<select name="arshid6social_activity_pagination_type">
						<option value="infinite_scroll" <?php selected( get_option( 'arshid6social_activity_pagination_type', 'infinite_scroll' ), 'infinite_scroll' ); ?>>
							<?php esc_html_e( 'Infinite Scroll', '6arshid social community' ); ?>
						</option>
						<option value="pagination" <?php selected( get_option( 'arshid6social_activity_pagination_type', 'infinite_scroll' ), 'pagination' ); ?>>
							<?php esc_html_e( 'Page Numbers (Basic Pagination)', '6arshid social community' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Infinite scroll loads more posts automatically; basic pagination shows numbered pages.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow Comments', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_activity_allow_comments" value="1"
							<?php checked( get_option( 'arshid6social_activity_allow_comments', true ) ); ?> />
						<?php esc_html_e( 'Allow members to comment on activity posts.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow Media Uploads', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_activity_allow_media" value="1"
							<?php checked( get_option( 'arshid6social_activity_allow_media', true ) ); ?> />
						<?php esc_html_e( 'Allow members to attach files to activity posts.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allowed Media Types', '6arshid social community' ); ?></th>
				<td>
					<?php
					$media_types = array(
						'image'    => __( 'Images (JPEG, PNG, GIF, WebP)', '6arshid social community' ),
						'video'    => __( 'Videos (MP4, WebM, OGG)', '6arshid social community' ),
						'audio'    => __( 'Audio (MP3, WAV, OGG)', '6arshid social community' ),
						'document' => __( 'Documents (PDF)', '6arshid social community' ),
					);
					foreach ( $media_types as $key => $label ) :
						?>
						<label style="display:block;margin-top:4px;">
							<input type="checkbox" name="arshid6social_activity_allowed_media_types[]"
								value="<?php echo esc_attr( $key ); ?>"
								<?php checked( in_array( $key, $allowed_media_types, true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Only applies when "Allow Media Uploads" is enabled above.', '6arshid social community' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		$this->render_stories_settings();
	}

	private function render_groups_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Groups Per Page', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_groups_per_page" min="5" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_groups_per_page', 20 ) ); ?>" />
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_messages_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Messages Per Page', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_messages_per_page" min="5" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_messages_per_page', 20 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Story Reply in Messages', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_messages_story_enabled" value="1"
							<?php checked( get_option( 'arshid6social_messages_story_enabled', false ) ); ?> />
						<?php esc_html_e( 'Allow users to reply to stories via private message.', '6arshid social community' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When disabled, the reply input is hidden from the story viewer and story replies are blocked. Disabled by default.', '6arshid social community' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_notifications_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Email Notifications', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_email_notifications" value="1"
							<?php checked( get_option( 'arshid6social_email_notifications', true ) ); ?> />
						<?php esc_html_e( 'Send email notifications to members.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Email Digest', '6arshid social community' ); ?></th>
				<td>
					<select name="arshid6social_email_digest">
						<option value="none" <?php selected( get_option( 'arshid6social_email_digest', 'daily' ), 'none' ); ?>>
							<?php esc_html_e( 'Disabled', '6arshid social community' ); ?>
						</option>
						<option value="daily" <?php selected( get_option( 'arshid6social_email_digest', 'daily' ), 'daily' ); ?>>
							<?php esc_html_e( 'Daily digest', '6arshid social community' ); ?>
						</option>
						<option value="weekly" <?php selected( get_option( 'arshid6social_email_digest', 'daily' ), 'weekly' ); ?>>
							<?php esc_html_e( 'Weekly digest', '6arshid social community' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_stories_settings(): void {
		?>
		<h2><?php esc_html_e( 'Stories', '6arshid social community' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Stories', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_stories_enabled" value="1"
							<?php checked( get_option( 'arshid6social_stories_enabled', false ) ); ?> />
						<?php esc_html_e( 'Show 24-hour ephemeral stories tray on activity page and profiles.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Story Expiry (hours)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_stories_expiry_hours" min="1" max="72"
						value="<?php echo esc_attr( get_option( 'arshid6social_stories_expiry_hours', 24 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'How long stories stay visible before auto-expiring.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Video Length (seconds)', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_stories_max_video_secs" min="5" max="300"
						value="<?php echo esc_attr( get_option( 'arshid6social_stories_max_video_secs', 30 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow Video Stories', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_stories_allow_video" value="1"
							<?php checked( get_option( 'arshid6social_stories_allow_video', true ) ); ?> />
						<?php esc_html_e( 'Members can upload short video stories.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Highlights', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_stories_highlights" value="1"
							<?php checked( get_option( 'arshid6social_stories_highlights', true ) ); ?> />
						<?php esc_html_e( 'Allow members to save expired stories as permanent Highlights on their profile.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit: Stories per hour', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_stories_rate_limit" min="1" max="200"
						value="<?php echo esc_attr( get_option( 'arshid6social_stories_rate_limit', 20 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show Bottom Bar', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_stories_bottom_bar" value="1"
							<?php checked( get_option( 'arshid6social_stories_bottom_bar', false ) ); ?> />
						<?php esc_html_e( 'Show a fixed stories bar at the bottom of every page on the site.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show in Marketplace', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_stories_bottom_bar_marketplace" value="1"
							<?php checked( get_option( 'arshid6social_stories_bottom_bar_marketplace', false ) ); ?> />
						<?php esc_html_e( 'Show the stories bar on the Marketplace page. Disabled by default.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show in Messages &amp; Inbox', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_stories_bottom_bar_messages" value="1"
							<?php checked( get_option( 'arshid6social_stories_bottom_bar_messages', false ) ); ?> />
						<?php esc_html_e( 'Show the stories bar on the Messages and Inbox pages. Disabled by default.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php if ( get_option( 'arshid6social_stories_enabled', false ) ) : ?>
			<?php $this->render_stories_bar_preview(); ?>
		<?php endif; ?>
		<?php
	}

	private function render_stories_bar_preview(): void {
		$viewer_id = get_current_user_id();

		// Reuse the already-booted Stories instance to avoid duplicate hooks.
		$tray        = array();
		$stories_obj = \Arshid6Social\Plugin::instance()->component( 'stories' );
		if ( $stories_obj && method_exists( $stories_obj, 'get_tray' ) ) {
			$tray = $stories_obj->get_tray( $viewer_id );
		}
		?>
		<h3 style="margin-top:2em;"><?php esc_html_e( 'Stories Bar Preview', '6arshid social community' ); ?></h3>
		<p class="description"><?php esc_html_e( 'This is how the fixed bottom stories bar appears to logged-in users on the site.', '6arshid social community' ); ?></p>

		<div style="
			background:#1a1a2e;
			border:1px solid #3a3a5c;
			border-radius:10px;
			padding:12px 16px;
			display:flex;
			align-items:flex-end;
			gap:16px;
			overflow-x:auto;
			max-width:700px;
			margin-top:12px;
		">
			<!-- Your Story bubble -->
			<div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;">
				<div style="position:relative;width:56px;height:56px;">
					<div style="
						position:absolute;inset:0;border-radius:50%;
						border:2px dashed #555;
					"></div>
					<img src="<?php echo esc_url( get_avatar_url( $viewer_id, array( 'size' => 52 ) ) ); ?>"
					     style="width:52px;height:52px;border-radius:50%;object-fit:cover;margin:2px;" />
					<span style="
						position:absolute;bottom:0;right:0;
						background:#2563eb;color:#fff;
						border-radius:50%;width:18px;height:18px;
						display:flex;align-items:center;justify-content:center;
						font-size:14px;font-weight:bold;line-height:1;
						border:2px solid #1a1a2e;
					">+</span>
				</div>
				<span style="color:#ccc;font-size:11px;max-width:60px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
					<?php esc_html_e( 'Your Story', '6arshid social community' ); ?>
				</span>
			</div>

			<?php if ( empty( $tray ) ) : ?>
			<!-- Placeholder bubbles when no stories exist -->
			<?php
			$placeholder_colors = array( '#e04343', '#43b0e0', '#43e08e' );
			$placeholder_labels = array( 'User A', 'User B', 'User C' );
			foreach ( $placeholder_colors as $i => $color ) : ?>
			<div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;opacity:0.45;">
				<div style="position:relative;width:56px;height:56px;">
					<div style="
						position:absolute;inset:0;border-radius:50%;
						border:2.5px solid <?php echo esc_attr( $color ); ?>;
					"></div>
					<div style="
						width:48px;height:48px;margin:4px;border-radius:50%;
						background:<?php echo esc_attr( $color ); ?>33;
						display:flex;align-items:center;justify-content:center;
						color:<?php echo esc_attr( $color ); ?>;font-size:20px;font-weight:bold;
					"><?php echo esc_html( strtoupper( substr( $placeholder_labels[ $i ], 5, 1 ) ) ); ?></div>
				</div>
				<span style="color:#aaa;font-size:11px;"><?php echo esc_html( $placeholder_labels[ $i ] ); ?></span>
			</div>
			<?php endforeach; ?>
			<span style="color:#666;font-size:12px;align-self:center;padding-left:4px;font-style:italic;">
				<?php esc_html_e( '← placeholder (no active stories)', '6arshid social community' ); ?>
			</span>

			<?php else : ?>
			<!-- Real stories from the database -->
			<?php foreach ( array_slice( $tray, 0, 8 ) as $story ) :
				$story_uid  = (int) $story->user_id;
				$has_unseen = (int) $story->unseen_count > 0;
				$ring_color = $has_unseen ? 'linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)' : '#555';
				?>
			<div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;">
				<div style="position:relative;width:56px;height:56px;">
					<div style="
						position:absolute;inset:0;border-radius:50%;
						<?php if ( $has_unseen ) : ?>
						background:<?php echo esc_attr( $ring_color ); ?>;padding:2.5px;
						<?php else : ?>
						border:2.5px solid #555;
						<?php endif; ?>
					">
						<?php if ( $has_unseen ) : ?>
						<div style="width:100%;height:100%;border-radius:50%;background:#1a1a2e;"></div>
						<?php endif; ?>
					</div>
					<img src="<?php echo esc_url( get_avatar_url( $story_uid, array( 'size' => 48 ) ) ); ?>"
					     style="position:absolute;inset:<?php echo $has_unseen ? '4px' : '2px'; ?>;border-radius:50%;object-fit:cover;" />
				</div>
				<span style="color:#ccc;font-size:11px;max-width:60px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
					<?php echo esc_html( $story->display_name ); ?>
				</span>
			</div>
			<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_security_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Akismet Integration', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_enable_akismet" value="1"
							<?php checked( get_option( 'arshid6social_enable_akismet', true ) ); ?> />
						<?php esc_html_e( 'Use Akismet to filter activity and message spam.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'reCAPTCHA / Turnstile', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_enable_recaptcha" value="1"
							<?php checked( get_option( 'arshid6social_enable_recaptcha', false ) ); ?> />
						<?php esc_html_e( 'Enable CAPTCHA on registration and contact forms.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'CAPTCHA Site Key', '6arshid social community' ); ?></th>
				<td>
					<input type="text" name="arshid6social_recaptcha_site_key" class="regular-text"
						value="<?php echo esc_attr( get_option( 'arshid6social_recaptcha_site_key', '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'CAPTCHA Secret Key', '6arshid social community' ); ?></th>
				<td>
					<input type="password" name="arshid6social_recaptcha_secret_key" class="regular-text"
						value="<?php echo esc_attr( get_option( 'arshid6social_recaptcha_secret_key', '' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Moderate New Members', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_new_member_moderation" value="1"
							<?php checked( get_option( 'arshid6social_new_member_moderation', false ) ); ?> />
						<?php esc_html_e( 'Hold new members for admin approval before they can post.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-Suspend Threshold', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_auto_suspend_threshold" min="0" max="100"
						value="<?php echo esc_attr( get_option( 'arshid6social_auto_suspend_threshold', 5 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Number of reports before a user is auto-suspended. 0 = disabled.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Banned Words', '6arshid social community' ); ?></th>
				<td>
					<textarea name="arshid6social_banned_words" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_banned_words', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One word or phrase per line. Matched content will be blocked.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit: Posts per hour', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_rate_limit_posts" min="1" max="500"
						value="<?php echo esc_attr( get_option( 'arshid6social_rate_limit_posts', 10 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit: Messages per hour', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_rate_limit_messages" min="1" max="500"
						value="<?php echo esc_attr( get_option( 'arshid6social_rate_limit_messages', 20 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limit: Friend Requests per hour', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_rate_limit_friends" min="1" max="500"
						value="<?php echo esc_attr( get_option( 'arshid6social_rate_limit_friends', 50 ) ); ?>" />
				</td>
			</tr>
		</table>
		<?php
		$this->render_username_restrictions();
		$this->render_blocking_settings();
		$this->render_reporting_settings();
	}

	private function render_username_restrictions(): void {
		?>
		<h2><?php esc_html_e( 'Username Restrictions', '6arshid social community' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Minimum Username Length', '6arshid social community' ); ?></th>
				<td>
					<input type="number" name="arshid6social_username_min_length" min="1" max="60"
						value="<?php echo esc_attr( get_option( 'arshid6social_username_min_length', 4 ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Minimum number of characters required for a username. Default: 4.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reserved Usernames', '6arshid social community' ); ?></th>
				<td>
					<textarea name="arshid6social_reserved_usernames" rows="8" class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_reserved_usernames', '' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One username per line. These usernames cannot be registered by anyone. Case-insensitive.', '6arshid social community' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_blocking_settings(): void {
		?>
		<h2><?php esc_html_e( 'User Blocking', '6arshid social community' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Block System', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_blocking_enabled" value="1"
							<?php checked( get_option( 'arshid6social_blocking_enabled', true ) ); ?> />
						<?php esc_html_e( 'Allow members to block other members.', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow Block Reasons', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_blocking_show_reason" value="1"
							<?php checked( get_option( 'arshid6social_blocking_show_reason', true ) ); ?> />
						<?php esc_html_e( 'Show optional reason field when blocking (private, for blocker only).', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_reporting_settings(): void {
		$default_report_reasons  = "Spam\nHarassment or bullying\nHate speech\nInappropriate content\nFalse information\nImpersonation\nOther";
		$default_suspend_reasons = "Spam activity\nHarassment\nHate speech or discrimination\nInappropriate content\nMultiple violations\nViolation of community guidelines\nOther";
		?>
		<h2><?php esc_html_e( 'Reporting', '6arshid social community' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Report Reasons', '6arshid social community' ); ?></th>
				<td>
					<textarea name="arshid6social_report_reasons" rows="8" class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_report_reasons', $default_report_reasons ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One reason per line. Shown to users when reporting a profile or group.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Suspension Reasons', '6arshid social community' ); ?></th>
				<td>
					<textarea name="arshid6social_suspend_reasons" rows="8" class="large-text"><?php echo esc_textarea( get_option( 'arshid6social_suspend_reasons', $default_suspend_reasons ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One reason per line. Shown to admins when suspending a user from the Members or Moderation pages.', '6arshid social community' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Allow File Attachments in Reports', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_report_allow_attachments" value="1"
							<?php checked( get_option( 'arshid6social_report_allow_attachments', false ) ); ?> />
						<?php esc_html_e( 'Allow users to attach a screenshot when submitting a report (images only).', '6arshid social community' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_emails_tab(): void {
		?>
		<div class="arshid6social-admin-notice notice notice-info inline">
			<p><?php esc_html_e( 'Email templates can be overridden by placing them in your theme\'s /social-network/emails/ folder.', '6arshid social community' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'New Friendship Request', '6arshid social community' ); ?></th>
				<td><code><?php echo esc_html( get_template_directory() . '/social-network/emails/new-friendship-request.php' ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'New Message', '6arshid social community' ); ?></th>
				<td><code><?php echo esc_html( get_template_directory() . '/social-network/emails/new-message.php' ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Activity Mention', '6arshid social community' ); ?></th>
				<td><code><?php echo esc_html( get_template_directory() . '/social-network/emails/activity-mention.php' ); ?></code></td>
			</tr>
		</table>
		<?php
	}

	private function render_appearance_tab(): void {
		wp_enqueue_media();
		$default_video    = '/assets/videos/home-bg.mp4';
		$saved_video      = (string) get_option( 'arshid6social_home_video_url', '' );
		$saved_type       = (string) get_option( 'arshid6social_home_bg_type', 'video' );
		$preview_url      = $saved_video ?: $default_video;
		$logo_mobile_id   = (int) get_option( 'arshid6social_logo_mobile', 0 );
		$logo_mobile_url  = $logo_mobile_id  ? wp_get_attachment_image_url( $logo_mobile_id,  'thumbnail' ) : '';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Desktop Logo', '6arshid social community' ); ?></th>
				<td>
					<p class="description" style="max-width:560px;">
						<?php
						printf(
							/* translators: %s: link to the Site Editor */
							esc_html__( 'The desktop sidebar logo is now managed with the native WordPress Site Logo block. Edit it directly in the left sidebar via %s — click the logo placeholder to upload an image.', '6arshid social community' ),
							'<a href="' . esc_url( admin_url( 'site-editor.php' ) ) . '">' . esc_html__( 'Appearance → Editor', '6arshid social community' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Mobile / Tablet Logo', '6arshid social community' ); ?></th>
				<td>
					<input type="hidden" id="arshid6social-logo-mobile-id" name="arshid6social_logo_mobile" value="<?php echo esc_attr( (string) $logo_mobile_id ); ?>" />
					<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
						<div id="arshid6social-logo-mobile-preview" style="width:64px;height:64px;border:1px solid #ddd;border-radius:6px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;overflow:hidden;">
							<?php if ( $logo_mobile_url ) : ?>
								<img src="<?php echo esc_url( $logo_mobile_url ); ?>" style="width:100%;height:100%;object-fit:contain;" alt="" />
							<?php else : ?>
								<span style="color:#9ca3af;font-size:22px;">📱</span>
							<?php endif; ?>
						</div>
						<div>
							<button type="button" id="arshid6social-logo-mobile-select" class="button button-primary">
								<?php esc_html_e( 'Select Image', '6arshid social community' ); ?>
							</button>
							<button type="button" id="arshid6social-logo-mobile-remove" class="button button-link-delete" style="margin-left:8px;<?php echo $logo_mobile_id ? '' : 'display:none;'; ?>">
								<?php esc_html_e( 'Remove', '6arshid social community' ); ?>
							</button>
							<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Shown in the mobile/tablet side menu. Falls back to the WordPress site logo if not set.', '6arshid social community' ); ?></p>
						</div>
					</div>
					<script>
					( function () {
						var frame;
						var btnSelect  = document.getElementById( 'arshid6social-logo-mobile-select' );
						var btnRemove  = document.getElementById( 'arshid6social-logo-mobile-remove' );
						var inputId    = document.getElementById( 'arshid6social-logo-mobile-id' );
						var preview    = document.getElementById( 'arshid6social-logo-mobile-preview' );
						btnSelect.addEventListener( 'click', function () {
							if ( frame ) { frame.open(); return; }
							frame = wp.media( {
								title:    '<?php echo esc_js( __( 'Select Mobile Logo', '6arshid social community' ) ); ?>',
								button:   { text: '<?php echo esc_js( __( 'Use this image', '6arshid social community' ) ); ?>' },
								multiple: false,
								library:  { type: 'image' },
							} );
							frame.on( 'select', function () {
								var att = frame.state().get( 'selection' ).first().toJSON();
								inputId.value = att.id;
								var src = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
								preview.innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:contain;" alt="" />';
								btnRemove.style.display = '';
							} );
							frame.open();
						} );
						btnRemove.addEventListener( 'click', function () {
							inputId.value     = '0';
							preview.innerHTML = '<span style="color:#9ca3af;font-size:22px;">📱</span>';
							btnRemove.style.display = 'none';
						} );
					} )();
					</script>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary Colour', '6arshid social community' ); ?></th>
				<td>
					<input type="color" name="arshid6social_primary_color"
						value="<?php echo esc_attr( get_option( 'arshid6social_primary_color', '#2563eb' ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Dark Mode', '6arshid social community' ); ?></th>
				<td>
					<select name="arshid6social_dark_mode">
						<option value="off" <?php selected( get_option( 'arshid6social_dark_mode', 'auto' ), 'off' ); ?>>
							<?php esc_html_e( 'Always off', '6arshid social community' ); ?>
						</option>
						<option value="auto" <?php selected( get_option( 'arshid6social_dark_mode', 'auto' ), 'auto' ); ?>>
							<?php esc_html_e( 'Follow system preference', '6arshid social community' ); ?>
						</option>
						<option value="on" <?php selected( get_option( 'arshid6social_dark_mode', 'auto' ), 'on' ); ?>>
							<?php esc_html_e( 'Always on', '6arshid social community' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Home Page Background', '6arshid social community' ); ?></th>
				<td>
					<input type="hidden" id="arshid6social-video-url"  name="arshid6social_home_video_url" value="<?php echo esc_attr( $saved_video ); ?>" />
					<input type="hidden" id="arshid6social-bg-type"    name="arshid6social_home_bg_type"   value="<?php echo esc_attr( $saved_type ); ?>" />
					<input type="file"   id="arshid6social-video-file-input" accept="video/mp4,video/webm,video/ogg,image/jpeg,image/png,image/gif,image/webp,image/avif" style="display:none;" />

					<div style="display:flex;align-items:flex-start;gap:1.5rem;flex-wrap:wrap;">
						<div style="flex:1;min-width:260px;">

							<div style="margin-bottom:10px;">
								<button type="button" id="arshid6social-video-upload-btn" class="button button-primary">
									<?php esc_html_e( 'Choose / Upload Video or Image', '6arshid social community' ); ?>
								</button>
								<button type="button" id="arshid6social-video-remove-btn" class="button button-link-delete"
									style="margin-left:10px;<?php echo $saved_video ? '' : 'display:none;'; ?>">
									<?php esc_html_e( 'Remove (use default)', '6arshid social community' ); ?>
								</button>
							</div>

							<div id="arshid6social-video-progress" style="display:none;margin-bottom:10px;max-width:300px;">
								<div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;">
									<div class="arshid6social-progress-bar" style="width:0%;height:100%;background:#2271b1;transition:width .2s;"></div>
								</div>
								<span class="arshid6social-progress-label" style="font-size:12px;color:#666;">0%</span>
							</div>

							<div style="margin-bottom:10px;">
								<label style="display:block;margin-bottom:4px;font-size:12px;color:#666;">
									<?php esc_html_e( '-- or paste a URL directly --', '6arshid social community' ); ?>
								</label>
								<input type="url" id="arshid6social-video-url-text"
									placeholder="https://example.com/video.mp4 or image.jpg"
									value="<?php echo esc_attr( $saved_video ); ?>"
									style="width:100%;max-width:360px;" />
							</div>

							<p id="arshid6social-video-desc" class="description">
								<?php echo $saved_video
									? esc_html__( 'Custom background is active.', '6arshid social community' )
									: esc_html__( 'Using default video.', '6arshid social community' );
								?>
							</p>
						</div>

						<div>
							<p style="margin:0 0 6px;font-size:12px;color:#666;"><?php esc_html_e( 'Preview:', '6arshid social community' ); ?></p>
							<?php if ( $saved_video && $saved_type === 'image' ) : ?>
							<img id="arshid6social-video-preview"
								src="<?php echo esc_url( $preview_url ); ?>"
								style="width:260px;height:146px;object-fit:cover;border-radius:8px;border:1px solid #ddd;background:#000;display:block;" />
							<video id="arshid6social-video-preview-video" style="display:none;width:260px;height:146px;object-fit:cover;border-radius:8px;border:1px solid #ddd;background:#000;" autoplay loop muted playsinline></video>
							<?php else : ?>
							<video id="arshid6social-video-preview-video"
								src="<?php echo esc_url( $preview_url ); ?>"
								autoplay loop muted playsinline
								style="width:260px;height:146px;object-fit:cover;border-radius:8px;border:1px solid #ddd;background:#000;">
							</video>
							<img id="arshid6social-video-preview" style="display:none;width:260px;height:146px;object-fit:cover;border-radius:8px;border:1px solid #ddd;background:#000;" />
							<?php endif; ?>
						</div>
					</div>
				</td>
			</tr>		</table>
		<?php
	}

	private function render_permalinks_tab(): void {
		$activity_base = get_option( 'arshid6social_permalink_activity_base', 'activity' );
		$tag_base      = get_option( 'arshid6social_permalink_tag_base', 'hashtags' );
		$uid_enabled   = (bool) get_option( 'arshid6social_activity_uid_enabled', false );
		?>
		<div class="notice notice-info inline" style="margin:12px 0;">
			<p><?php esc_html_e( 'After saving, WordPress rewrite rules are flushed automatically. No need to visit Settings → Permalinks.', '6arshid social community' ); ?></p>
		</div>
		<table class="form-table" role="presentation">
			<?php if ( get_option( 'arshid6social_marketplace_enabled', false ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Marketplace Slug', '6arshid social community' ); ?></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code>
					<input type="text" name="arshid6social_marketplace_slug" class="regular-text"
						value="<?php echo esc_attr( get_option( 'arshid6social_marketplace_slug', 'marketplace' ) ); ?>"
						placeholder="marketplace"
						pattern="[a-z0-9\-]+"
						title="<?php esc_attr_e( 'Lowercase letters, digits and hyphens only.', '6arshid social community' ); ?>" />
					<code>/</code>
					<p class="description">
						<?php
						$mkt_slug = get_option( 'arshid6social_marketplace_slug', 'marketplace' );
						/* translators: %s example URL */
						printf( esc_html__( 'Current example: %s', '6arshid social community' ), '<code>' . esc_html( home_url( '/' . $mkt_slug . '/' ) ) . '</code>' );
						?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tag Base', '6arshid social community' ); ?></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code>
					<input type="text" name="arshid6social_permalink_tag_base" class="regular-text"
						value="<?php echo esc_attr( $tag_base ); ?>"
						placeholder="hashtags"
						pattern="[a-z0-9\-]+"
						title="<?php esc_attr_e( 'Lowercase letters, digits and hyphens only.', '6arshid social community' ); ?>" />
					<code>/&lt;hashtag&gt;/</code>
					<p class="description">
						<?php
						/* translators: %s example URL */
						printf( esc_html__( 'Current example: %s', '6arshid social community' ), '<code>' . esc_html( home_url( '/' . $tag_base . '/php/' ) ) . '</code>' );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Activity Base', '6arshid social community' ); ?></th>
				<td>
					<code><?php echo esc_html( home_url( '/' ) ); ?></code>
					<input type="text" name="arshid6social_permalink_activity_base" class="regular-text"
						value="<?php echo esc_attr( $activity_base ); ?>"
						placeholder="activity"
						pattern="[a-z0-9\-]+"
						title="<?php esc_attr_e( 'Lowercase letters, digits and hyphens only.', '6arshid social community' ); ?>" />
					<code>/&lt;id&gt;/</code>
					<p class="description">
						<?php
						$example_id = $uid_enabled ? '64c3f4a2b1e8f' : '123';
						/* translators: %s: example URL */
						printf( esc_html__( 'Current example: %s', '6arshid social community' ), '<code>' . esc_html( home_url( '/' . $activity_base . '/' . $example_id . '/' ) ) . '</code>' );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Activity ID Format', '6arshid social community' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="arshid6social_activity_uid_enabled" value="1"
							<?php checked( $uid_enabled ); ?> />
						<?php esc_html_e( 'Use unique ID (uniqid) instead of numeric ID in activity URLs.', '6arshid social community' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, activity links use a 13-character hexadecimal unique ID (e.g. 64c3f4a2b1e8f). Numeric links to older posts continue to work.', '6arshid social community' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}
