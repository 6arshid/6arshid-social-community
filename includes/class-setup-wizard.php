<?php
namespace Arshid6Social;

/**
 * Setup wizard shown on first activation.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

class Setup_Wizard {

	public function __construct() {
		if ( ! is_admin() || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			return;
		}
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_arshid6social_wizard_save', array( $this, 'ajax_save' ) );
		add_action( 'admin_head', array( $this, 'suppress_admin_chrome' ) );
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'dashboard_page_arshid6social-setup' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'arshid6social-admin',
			ARSHID6SOCIAL_ASSETS_URL . 'css/admin.css',
			array(),
			ARSHID6SOCIAL_VERSION
		);

		wp_enqueue_script(
			'arshid6social-admin',
			ARSHID6SOCIAL_ASSETS_URL . 'js/admin.js',
			array( 'wp-api' ),
			ARSHID6SOCIAL_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
	public function suppress_admin_chrome(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard_page_arshid6social-setup' !== $screen->id ) {
			return;
		}
		$chrome_css = '#wpadminbar,#adminmenuback,#adminmenuwrap,#adminmenu{display:none!important}#wpcontent{margin-left:0!important}#wpbody-content{padding-bottom:0!important}#wpbody{padding-top:0!important}body.wp-admin{background:#f8fafc!important;margin:0!important}';


		$wizard_css = '*{box-sizing:border-box;}.sn6w{min-height:100vh;display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;color:#0f172a;}.sn6w-bar{position:fixed;top:0;left:0;right:0;height:3px;background:#e2e8f0;z-index:200;}.sn6w-bar-fill{height:100%;background:#2563eb;transition:width .35s ease;width:16.66%;}.sn6w-hdr{background:#fff;border-bottom:1px solid #f1f5f9;height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 2rem;margin-top:3px;position:sticky;top:3px;z-index:100;}.sn6w-logo{font-size:1rem;font-weight:700;color:#0f172a;letter-spacing:-.02em;}.sn6w-logo em{color:#2563eb;font-style:normal;}.sn6w-hdr-step{font-size:.8125rem;color:#64748b;font-weight:500;}.sn6w-skip{font-size:.8125rem;color:#94a3b8;text-decoration:none;}.sn6w-skip:hover{color:#64748b;}.sn6w-body{flex:1;display:flex;justify-content:center;padding:2rem 1.5rem 5.5rem;}.sn6w-step{display:none;width:100%;max-width:700px;}.sn6w-step.is-active{display:block;}.sn6w-card{background:#fff;border:1px solid #e8edf2;border-radius:16px;box-shadow:0 1px 3px rgb(0 0 0/.05),0 4px 16px rgb(0 0 0/.04);overflow:hidden;}.sn6w-card-hd{padding:1.75rem 2rem 1.25rem;border-bottom:1px solid #f4f6f8;}.sn6w-card-hd h2{margin:0 0 .25rem;font-size:1.25rem;font-weight:700;color:#0f172a;letter-spacing:-.02em;}.sn6w-card-hd p{margin:0;color:#64748b;font-size:.9rem;line-height:1.55;}.sn6w-card-bd{padding:1.5rem 2rem;}.sn6w-sec{margin-bottom:1.5rem;}.sn6w-sec:last-child{margin-bottom:0;}.sn6w-sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;}.sn6w-sec-ttl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;}.sn6w-tog-all{background:none;border:none;cursor:pointer;font-size:.8125rem;color:#2563eb;font-weight:500;padding:0;line-height:1;font-family:inherit;}.sn6w-tog-all:hover{text-decoration:underline;}.sn6w-grid{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;}.sn6w-feat{position:relative;}.sn6w-feat input{position:absolute;opacity:0;pointer-events:none;}.sn6w-feat-lbl{display:flex;align-items:flex-start;gap:.625rem;padding:.7rem .875rem;border:1.5px solid #e8edf2;border-radius:10px;cursor:pointer;transition:border-color .12s,background .12s;height:100%;user-select:none;-webkit-user-select:none;}.sn6w-feat-lbl:hover{border-color:#bfdbfe;background:#fafcff;}.sn6w-feat input:checked~.sn6w-feat-lbl{border-color:#2563eb;background:#eff6ff;}.sn6w-feat-ico{font-size:.9rem;width:28px;height:28px;background:#f1f5f9;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;transition:background .12s;}.sn6w-feat input:checked~.sn6w-feat-lbl .sn6w-feat-ico{background:#dbeafe;}.sn6w-feat-txt{flex:1;min-width:0;}.sn6w-feat-name{font-size:.8375rem;font-weight:600;color:#1e293b;display:block;line-height:1.3;}.sn6w-feat-desc{font-size:.72rem;color:#94a3b8;display:block;line-height:1.35;margin-top:2px;}.sn6w-feat-chk{width:15px;height:15px;border-radius:50%;border:1.5px solid #cbd5e1;flex-shrink:0;margin-top:3px;transition:all .12s;display:flex;align-items:center;justify-content:center;font-size:8px;color:transparent;}.sn6w-feat input:checked~.sn6w-feat-lbl .sn6w-feat-chk{background:#2563eb;border-color:#2563eb;color:#fff;}.sn6w-feat-ico svg,.sn6w-mode-ico svg,.sn6w-media-lbl svg,.sn6w-feat-chk svg,.sn6w-tc-chk svg,.tp-df-ic svg,.sn6w-done-ic svg{display:block;}.sn6w-feat-ico svg{color:#475569;}.sn6w-feat input:checked~.sn6w-feat-lbl .sn6w-feat-ico svg{color:#2563eb;}.sn6w-done-ic{color:#16a34a;}.sn6w-div{height:1px;background:#f4f6f8;margin:1.25rem 0;}.sn6w-field{margin-bottom:1.375rem;}.sn6w-field:last-child{margin-bottom:0;}.sn6w-field-lbl{display:block;font-size:.875rem;font-weight:600;color:#1e293b;margin-bottom:.625rem;}.sn6w-color-wrap{display:flex;align-items:center;gap:1rem;}input[type="color"].sn6w-color-inp{width:42px;height:42px;border:1.5px solid #e2e8f0;border-radius:10px;padding:3px;cursor:pointer;background:#fff;}.sn6w-presets{display:flex;gap:.5rem;flex-wrap:wrap;}.sn6w-preset{width:26px;height:26px;border-radius:50%;border:2.5px solid transparent;cursor:pointer;transition:transform .12s;outline:none;}.sn6w-preset:hover{transform:scale(1.18);}.sn6w-preset.on{outline:2.5px solid #0f172a;outline-offset:2px;}.sn6w-mode-row{display:flex;gap:.5rem;}.sn6w-mode{flex:1;position:relative;}.sn6w-mode input{position:absolute;opacity:0;}.sn6w-mode-lbl{display:flex;flex-direction:column;align-items:center;gap:.25rem;padding:.875rem .5rem;border:1.5px solid #e8edf2;border-radius:10px;cursor:pointer;text-align:center;transition:all .12s;}.sn6w-mode-lbl:hover{border-color:#bfdbfe;}.sn6w-mode input:checked+.sn6w-mode-lbl{border-color:#2563eb;background:#eff6ff;}.sn6w-mode-ico{font-size:1.375rem;}.sn6w-mode-nm{font-size:.8125rem;font-weight:600;color:#334155;}.sn6w-mode-ht{font-size:.7rem;color:#94a3b8;}.sn6w-media-row{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.625rem;}.sn6w-media{position:relative;}.sn6w-media input{position:absolute;opacity:0;}.sn6w-media-lbl{display:flex;align-items:center;gap:.375rem;padding:.375rem .75rem;border:1.5px solid #e8edf2;border-radius:6px;cursor:pointer;font-size:.8125rem;font-weight:500;color:#64748b;transition:all .12s;}.sn6w-media-lbl:hover{border-color:#bfdbfe;color:#2563eb;}.sn6w-media input:checked+.sn6w-media-lbl{border-color:#2563eb;background:#eff6ff;color:#2563eb;}.sn6w-theme-grid{display:grid;grid-template-columns:1fr 1fr;gap:.875rem;}.sn6w-tc{position:relative;}.sn6w-tc input{position:absolute;opacity:0;}.sn6w-tc-card{border:2px solid #e8edf2;border-radius:12px;overflow:hidden;cursor:pointer;transition:all .12s;background:#fff;display:block;}.sn6w-tc-card:hover{border-color:#93c5fd;}.sn6w-tc input:checked+.sn6w-tc-card{border-color:#2563eb;box-shadow:0 0 0 3px rgb(37 99 235/.1);}.sn6w-tc-rec{position:absolute;top:8px;left:8px;background:#2563eb;color:#fff;font-size:.62rem;font-weight:700;padding:2px 8px;border-radius:99px;text-transform:uppercase;letter-spacing:.05em;z-index:2;}.sn6w-tc-chk{position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:50%;background:#2563eb;color:#fff;display:none;align-items:center;justify-content:center;font-size:11px;z-index:2;}.sn6w-tc input:checked+.sn6w-tc-card .sn6w-tc-chk{display:flex;}.sn6w-tc-preview{width:100%;height:110px;overflow:hidden;}.sn6w-tc-body{padding:.75rem 1rem .875rem;}.sn6w-tc-name{font-weight:700;font-size:.9rem;color:#0f172a;margin:0 0 3px;}.sn6w-tc-desc{font-size:.78rem;color:#64748b;margin:0 0 .5rem;line-height:1.4;}.sn6w-tc-tags{display:flex;flex-wrap:wrap;gap:4px;}.sn6w-tag{font-size:.68rem;font-weight:600;padding:2px 7px;border-radius:99px;}.tag-b{background:#eff6ff;color:#2563eb;}.tag-g{background:#f0fdf4;color:#16a34a;}.tag-p{background:#faf5ff;color:#7c3aed;}.tag-s{background:#f1f5f9;color:#475569;}.tp-sn6{background:#000;display:flex;height:100%;}.tp-sn6-sb{width:28%;padding:8px 6px;display:flex;flex-direction:column;gap:6px;border-right:1px solid #2f3336;}.tp-sn6-si{height:8px;border-radius:4px;background:#2f3336;}.tp-sn6-si.on{background:#fff;width:60%;}.tp-sn6-fd{flex:1;padding:6px;display:flex;flex-direction:column;gap:5px;}.tp-sn6-st{display:flex;gap:4px;margin-bottom:4px;}.tp-sn6-s{width:22px;height:22px;border-radius:50%;border:2px solid #2563eb;background:#1a1a2e;flex-shrink:0;}.tp-sn6-po{background:#111;border:1px solid #2f3336;border-radius:6px;padding:5px;}.tp-sn6-av{width:12px;height:12px;border-radius:50%;background:#2563eb;display:inline-block;vertical-align:middle;margin-right:4px;}.tp-sn6-ln{height:5px;border-radius:3px;background:#2f3336;margin-top:4px;}.tp-sn6-ln.s{width:60%;}.tp-df{background:#f0f0f0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;height:100%;}.tp-df-ic{font-size:28px;}.tp-df-tx{font-size:10px;color:#64748b;font-weight:500;}.sn6w-chk-row{display:flex;align-items:flex-start;gap:.625rem;padding:.875rem;border:1.5px solid #e8edf2;border-radius:10px;cursor:pointer;margin-bottom:.5rem;transition:border-color .12s;}.sn6w-chk-row:last-of-type{margin-bottom:0;}.sn6w-chk-row:hover{border-color:#bfdbfe;}.sn6w-chk-row input[type="checkbox"]{margin-top:2px;flex-shrink:0;accent-color:#2563eb;width:16px;height:16px;cursor:pointer;}.sn6w-chk-nm{font-size:.875rem;font-weight:600;color:#1e293b;display:block;line-height:1.3;}.sn6w-chk-ht{font-size:.8rem;color:#94a3b8;display:block;margin-top:2px;}input[type="number"].sn6w-num{width:80px;height:38px;border:1.5px solid #e2e8f0;border-radius:8px;padding:0 .625rem;font-size:.9375rem;color:#0f172a;}input[type="number"].sn6w-num:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgb(37 99 235/.1);}.sn6w-done{text-align:center;padding:1.5rem 0 .5rem;}.sn6w-done-ic{font-size:3rem;margin-bottom:.875rem;}.sn6w-done h2{margin:0 0 .375rem;font-size:1.375rem;font-weight:700;}.sn6w-done>p{color:#64748b;margin:0 0 1.5rem;}.sn6w-sample{border:1.5px solid #e8edf2;border-radius:12px;padding:1.25rem 1.5rem;background:#f8fafc;text-align:left;margin:0 auto 1.5rem;max-width:440px;}.sn6w-sample h3{margin:0 0 .375rem;font-size:.9375rem;font-weight:700;}.sn6w-sample p{margin:0 0 1rem;color:#64748b;font-size:.875rem;line-height:1.5;}.sn6w-done-acts{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;}.sn6w-alert{padding:.75rem 1rem;border-radius:8px;font-size:.875rem;display:none;}.sn6w-alert-info{background:#eff6ff;color:#1e3a8a;border-left:3px solid #2563eb;}.sn6w-alert-ok{background:#f0fdf4;color:#14532d;border-left:3px solid #16a34a;}.sn6w-footer{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #f1f5f9;padding:.875rem 2rem;display:flex;align-items:center;justify-content:space-between;z-index:100;}.sn6w-btn{display:inline-flex;align-items:center;gap:.375rem;padding:.5rem 1.25rem;border-radius:8px;font-size:.9375rem;font-weight:500;border:none;cursor:pointer;height:42px;text-decoration:none;transition:all .12s;font-family:inherit;}.sn6w-btn-primary{background:#2563eb;color:#fff;}.sn6w-btn-primary:hover{background:#1d4ed8;color:#fff;}.sn6w-btn-ghost{background:#f8fafc;color:#64748b;border:1.5px solid #e2e8f0;}.sn6w-btn-ghost:hover{border-color:#cbd5e1;color:#334155;}.sn6w-btn:disabled{opacity:.6;pointer-events:none;}.sn6w-step-lbl{font-size:.875rem;color:#94a3b8;font-weight:500;}';
		echo '<style id="arshid6social-setup-css">' . $chrome_css . $wizard_css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function register_page(): void {
		add_dashboard_page(
			__( 'Social Network Setup', '6arshid-social-community' ),
			__( 'Social Network Setup', '6arshid-social-community' ),
			'arshid6social_manage_settings',
			'arshid6social-setup',
			array( $this, 'render' )
		);
	}

	public function maybe_redirect(): void {
		if ( get_transient( 'arshid6social_setup_redirect' ) ) {
			delete_transient( 'arshid6social_setup_redirect' );
			wp_safe_redirect( admin_url( 'index.php?page=arshid6social-setup' ) );
			exit;
		}
	}

	/**
	 * Render a Bootstrap SVG icon by name.
	 */
	private function icon( string $name, int $size = 22 ): string {
		return \Arshid6Social\Admin\Admin_Page_Icons::instance()->get_icon_svg( $name, $size );
	}

	public function render(): void {
		?>
		<div class="sn6w-bar"><div class="sn6w-bar-fill" id="sn6w-bar"></div></div>

		<div class="sn6w">
			<div class="sn6w-hdr">
				<div class="sn6w-logo">Social <em>Network 6</em></div>
				<div class="sn6w-hdr-step" id="sn6w-hdr-step">Core Features</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=arshid6social-dashboard' ) ); ?>" class="sn6w-skip">Skip →</a>
			</div>

			<div class="sn6w-body">

				<!-- Step 1: Core Components -->
				<div class="sn6w-step is-active" id="step-1">
					<div class="sn6w-card">
						<div class="sn6w-card-hd">
							<h2><?php esc_html_e( 'Core Features', '6arshid-social-community' ); ?></h2>
							<p><?php esc_html_e( 'Choose which components to enable. These are the building blocks of your community.', '6arshid-social-community' ); ?></p>
						</div>
						<div class="sn6w-card-bd">

							<div class="sn6w-sec">
								<div class="sn6w-sec-hd">
									<span class="sn6w-sec-ttl"><?php esc_html_e( 'Components', '6arshid-social-community' ); ?></span>
									<button type="button" class="sn6w-tog-all" data-grp="comp"><?php esc_html_e( 'Select all', '6arshid-social-community' ); ?></button>
								</div>
								<div class="sn6w-grid" id="grp-comp">
									<?php
									$components = array(
										'activity'      => array( 'ico' => 'newspaper', 'label' => __( 'Activity Streams', '6arshid-social-community' ),  'desc' => __( 'News feed, posts, reactions, comments', '6arshid-social-community' ) ),
										'groups'        => array( 'ico' => 'people', 'label' => __( 'Groups', '6arshid-social-community' ),             'desc' => __( 'Public, private, and hidden groups', '6arshid-social-community' ) ),
										'friends'       => array( 'ico' => 'person-plus', 'label' => __( 'Friends & Follow', '6arshid-social-community' ),   'desc' => __( 'Friend requests, follow, block', '6arshid-social-community' ) ),
										'messages'      => array( 'ico' => 'chat-dots', 'label' => __( 'Private Messages', '6arshid-social-community' ),   'desc' => __( 'One-to-one and group messaging', '6arshid-social-community' ) ),
										'notifications' => array( 'ico' => 'bell', 'label' => __( 'Notifications', '6arshid-social-community' ),      'desc' => __( 'On-site and email notifications', '6arshid-social-community' ) ),
										'moderation'    => array( 'ico' => 'shield-check', 'label' => __( 'Moderation', '6arshid-social-community' ),        'desc' => __( 'Reports, bans, audit log', '6arshid-social-community' ) ),
									);
									$saved_comp = (array) get_option( 'arshid6social_enabled_components', array_keys( $components ) );
									foreach ( $components as $key => $info ) :
									?>
									<div class="sn6w-feat">
										<input type="checkbox" name="components[]" value="<?php echo esc_attr( $key ); ?>" id="c-<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $saved_comp, true ) ); ?> />
										<label class="sn6w-feat-lbl" for="c-<?php echo esc_attr( $key ); ?>">
											<span class="sn6w-feat-ico"><?php echo $this->icon( $info['ico'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="sn6w-feat-txt">
												<span class="sn6w-feat-name"><?php echo esc_html( $info['label'] ); ?></span>
												<span class="sn6w-feat-desc"><?php echo esc_html( $info['desc'] ); ?></span>
											</span>
											<span class="sn6w-feat-chk"><?php echo $this->icon( 'check', 10 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</label>
									</div>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="sn6w-div"></div>

							<div class="sn6w-sec">
								<div class="sn6w-sec-hd">
									<span class="sn6w-sec-ttl"><?php esc_html_e( 'Engagement Pack', '6arshid-social-community' ); ?></span>
									<button type="button" class="sn6w-tog-all" data-grp="pack"><?php esc_html_e( 'Select all', '6arshid-social-community' ); ?></button>
								</div>
								<div class="sn6w-grid" id="grp-pack">
									<?php
									$pack = array(
										'arshid6social_stories_enabled'      => array( 'ico' => 'film', 'label' => __( 'Stories', '6arshid-social-community' ),             'desc' => __( '24-hour ephemeral photo, video & text stories', '6arshid-social-community' ) ),
										'arshid6social_verification_enabled' => array( 'ico' => 'patch-check',  'label' => __( 'Verification Badges', '6arshid-social-community' ), 'desc' => __( 'Verified badge & admin approval queue', '6arshid-social-community' ) ),
										'arshid6social_blocking_enabled'     => array( 'ico' => 'slash-circle',  'label' => __( 'Block System', '6arshid-social-community' ),         'desc' => __( 'Block / unblock users with optional reason', '6arshid-social-community' ) ),
										'arshid6social_activity_stats_bar'   => array( 'ico' => 'bar-chart',  'label' => __( 'Activity Stats Bar', '6arshid-social-community' ),   'desc' => __( 'Engagement counts below each post', '6arshid-social-community' ) ),
									);
									foreach ( $pack as $opt_key => $info ) :
									?>
									<div class="sn6w-feat">
										<input type="checkbox" name="pack_features[]" value="<?php echo esc_attr( $opt_key ); ?>" id="p-<?php echo esc_attr( $opt_key ); ?>"
											<?php checked( (bool) get_option( $opt_key, 'arshid6social_blocking_enabled' === $opt_key ) ); ?> />
										<label class="sn6w-feat-lbl" for="p-<?php echo esc_attr( $opt_key ); ?>">
											<span class="sn6w-feat-ico"><?php echo $this->icon( $info['ico'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="sn6w-feat-txt">
												<span class="sn6w-feat-name"><?php echo esc_html( $info['label'] ); ?></span>
												<span class="sn6w-feat-desc"><?php echo esc_html( $info['desc'] ); ?></span>
											</span>
											<span class="sn6w-feat-chk"><?php echo $this->icon( 'check', 10 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</label>
									</div>
									<?php endforeach; ?>
								</div>
							</div>

						</div>
					</div>
				</div><!-- #step-1 -->

				<!-- Step 2: Engagement & Content -->
				<div class="sn6w-step" id="step-2">
					<div class="sn6w-card">
						<div class="sn6w-card-hd">
							<h2><?php esc_html_e( 'Engagement & Content', '6arshid-social-community' ); ?></h2>
							<p><?php esc_html_e( 'Extra tools members can use. All optional — change anytime from Settings → Engagement.', '6arshid-social-community' ); ?></p>
						</div>
						<div class="sn6w-card-bd">

							<div class="sn6w-sec">
								<div class="sn6w-sec-hd">
									<span class="sn6w-sec-ttl"><?php esc_html_e( 'Engagement Features', '6arshid-social-community' ); ?></span>
									<button type="button" class="sn6w-tog-all" data-grp="eng"><?php esc_html_e( 'Select all', '6arshid-social-community' ); ?></button>
								</div>
								<div class="sn6w-grid" id="grp-eng">
									<?php
									$eng_features = array(
										'hashtags'             => array( 'ico' => 'hash', 'label' => __( 'Hashtags', '6arshid-social-community' ),            'desc' => __( '#tags on posts, trending & follow', '6arshid-social-community' ) ),
										'tag_friends'          => array( 'ico' => 'at',    'label' => __( 'Tag & @Mentions', '6arshid-social-community' ),      'desc' => __( 'Mention members in posts and comments', '6arshid-social-community' ) ),
										'bookmarks'            => array( 'ico' => 'bookmark',   'label' => __( 'Bookmarks', '6arshid-social-community' ),            'desc' => __( 'Save posts to a personal collection', '6arshid-social-community' ) ),
										'sticky_posts'         => array( 'ico' => 'pin-angle',   'label' => __( 'Sticky Posts', '6arshid-social-community' ),         'desc' => __( 'Pin posts to the top of the feed', '6arshid-social-community' ) ),
										'share_posts'          => array( 'ico' => 'arrow-repeat',   'label' => __( 'Share / Repost', '6arshid-social-community' ),       'desc' => __( 'Reshare posts within the network', '6arshid-social-community' ) ),
										'polls'                => array( 'ico' => 'list-check',   'label' => __( 'Polls', '6arshid-social-community' ),                'desc' => __( 'Single and multiple-choice polls', '6arshid-social-community' ) ),
										'advanced_polls'       => array( 'ico' => 'ui-checks-grid',  'label' => __( 'Advanced Polls', '6arshid-social-community' ),       'desc' => __( 'Image polls, quiz mode, ranked choice', '6arshid-social-community' ) ),
										'comments_gifs'        => array( 'ico' => 'camera-reels',   'label' => __( 'GIFs in Comments', '6arshid-social-community' ),     'desc' => __( 'Giphy / Tenor GIF picker in comments', '6arshid-social-community' ) ),
										'comments_attachments' => array( 'ico' => 'paperclip',   'label' => __( 'Comment Attachments', '6arshid-social-community' ),  'desc' => __( 'Upload images or files in comments', '6arshid-social-community' ) ),
										'messages_attachments' => array( 'ico' => 'upload',   'label' => __( 'Message Attachments', '6arshid-social-community' ),  'desc' => __( 'Send files in private messages', '6arshid-social-community' ) ),
									);
									foreach ( $eng_features as $ekey => $einfo ) :
									?>
									<div class="sn6w-feat">
										<input type="checkbox" name="engagement_features[]" value="<?php echo esc_attr( $ekey ); ?>" id="e-<?php echo esc_attr( $ekey ); ?>"
											<?php checked( (bool) get_option( 'arshid6social_eng_' . $ekey, false ) ); ?> />
										<label class="sn6w-feat-lbl" for="e-<?php echo esc_attr( $ekey ); ?>">
											<span class="sn6w-feat-ico"><?php echo $this->icon( $einfo['ico'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="sn6w-feat-txt">
												<span class="sn6w-feat-name"><?php echo esc_html( $einfo['label'] ); ?></span>
												<span class="sn6w-feat-desc"><?php echo esc_html( $einfo['desc'] ); ?></span>
											</span>
											<span class="sn6w-feat-chk"><?php echo $this->icon( 'check', 10 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</label>
									</div>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="sn6w-div"></div>

							<div class="sn6w-sec">
								<div class="sn6w-sec-hd">
									<span class="sn6w-sec-ttl"><?php esc_html_e( 'Marketplace & Media', '6arshid-social-community' ); ?></span>
								</div>

								<div class="sn6w-grid" style="grid-template-columns:1fr;gap:.4rem;margin-bottom:.4rem;">
									<div class="sn6w-feat">
										<input type="checkbox" name="marketplace_enabled" value="1" id="marketplace" <?php checked( (bool) get_option( 'arshid6social_marketplace_enabled', false ) ); ?> />
										<label class="sn6w-feat-lbl" for="marketplace">
											<span class="sn6w-feat-ico"><?php echo $this->icon( 'cart', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="sn6w-feat-txt">
												<span class="sn6w-feat-name"><?php esc_html_e( 'Marketplace', '6arshid-social-community' ); ?></span>
												<span class="sn6w-feat-desc"><?php esc_html_e( 'Peer-to-peer listings, categories, and seller messaging', '6arshid-social-community' ); ?></span>
											</span>
											<span class="sn6w-feat-chk"><?php echo $this->icon( 'check', 10 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</label>
									</div>
									<div class="sn6w-feat">
										<input type="checkbox" name="allow_media" value="1" id="allow-media" <?php checked( get_option( 'arshid6social_activity_allow_media', true ) ); ?> />
										<label class="sn6w-feat-lbl" for="allow-media">
											<span class="sn6w-feat-ico"><?php echo $this->icon( 'image', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="sn6w-feat-txt">
												<span class="sn6w-feat-name"><?php esc_html_e( 'Media Uploads', '6arshid-social-community' ); ?></span>
												<span class="sn6w-feat-desc"><?php esc_html_e( 'Allow members to attach files to activity posts', '6arshid-social-community' ); ?></span>
											</span>
											<span class="sn6w-feat-chk"><?php echo $this->icon( 'check', 10 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										</label>
									</div>
								</div>

								<div id="media-types-wrap" style="padding-left:.25rem;margin-top:.5rem;">
									<div style="font-size:.75rem;font-weight:600;color:#94a3b8;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em;"><?php esc_html_e( 'Allowed file types', '6arshid-social-community' ); ?></div>
									<div class="sn6w-media-row">
										<?php
										$allowed_media_types = (array) get_option( 'arshid6social_activity_allowed_media_types', array( 'image' ) );
										$media_types = array(
											'image'    => array( 'ico' => 'image', 'label' => 'Images' ),
											'video'    => array( 'ico' => 'camera-video', 'label' => 'Videos' ),
											'audio'    => array( 'ico' => 'music-note-beamed', 'label' => 'Audio' ),
											'document' => array( 'ico' => 'file-earmark-pdf', 'label' => 'PDF' ),
										);
										foreach ( $media_types as $mt_key => $mt ) :
										?>
										<div class="sn6w-media">
											<input type="checkbox" name="allowed_media_types[]" value="<?php echo esc_attr( $mt_key ); ?>" id="mt-<?php echo esc_attr( $mt_key ); ?>"
												<?php checked( in_array( $mt_key, $allowed_media_types, true ) ); ?> />
											<label class="sn6w-media-lbl" for="mt-<?php echo esc_attr( $mt_key ); ?>">
												<?php echo $this->icon( $mt['ico'], 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $mt['label'] ); ?>
											</label>
										</div>
										<?php endforeach; ?>
									</div>
								</div>
							</div>

						</div>
					</div>
				</div><!-- #step-2 -->

				<!-- Step 3: Appearance -->
				<div class="sn6w-step" id="step-3">
					<div class="sn6w-card">
						<div class="sn6w-card-hd">
							<h2><?php esc_html_e( 'Appearance', '6arshid-social-community' ); ?></h2>
							<p><?php esc_html_e( 'Set your brand color and default display mode for the community.', '6arshid-social-community' ); ?></p>
						</div>
						<div class="sn6w-card-bd">

							<div class="sn6w-field">
								<label class="sn6w-field-lbl"><?php esc_html_e( 'Brand Color', '6arshid-social-community' ); ?></label>
								<div class="sn6w-color-wrap">
									<input type="color" id="wizard-color" name="primary_color" class="sn6w-color-inp"
										value="<?php echo esc_attr( get_option( 'arshid6social_primary_color', '#2563eb' ) ); ?>" />
									<div class="sn6w-presets">
										<?php
										$presets     = array( '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#0f172a' );
										$saved_color = get_option( 'arshid6social_primary_color', '#2563eb' );
										foreach ( $presets as $p ) :
										?>
										<button type="button" class="sn6w-preset <?php echo ( $saved_color === $p ) ? 'on' : ''; ?>"
											style="background:<?php echo esc_attr( $p ); ?>;"
											data-color="<?php echo esc_attr( $p ); ?>"
											aria-label="<?php echo esc_attr( $p ); ?>"></button>
										<?php endforeach; ?>
									</div>
								</div>
							</div>

							<div class="sn6w-div"></div>

							<div class="sn6w-field">
								<label class="sn6w-field-lbl"><?php esc_html_e( 'Display Mode', '6arshid-social-community' ); ?></label>
								<div class="sn6w-mode-row">
									<?php
									$modes = array(
										'off'  => array( 'ico' => 'sun', 'name' => __( 'Light', '6arshid-social-community' ), 'ht' => __( 'Always light', '6arshid-social-community' ) ),
										'auto' => array( 'ico' => 'circle-half', 'name' => __( 'Auto', '6arshid-social-community' ),  'ht' => __( 'Follows system', '6arshid-social-community' ) ),
										'on'   => array( 'ico' => 'moon', 'name' => __( 'Dark', '6arshid-social-community' ),  'ht' => __( 'Always dark', '6arshid-social-community' ) ),
									);
									$saved_mode = get_option( 'arshid6social_dark_mode', 'auto' );
									foreach ( $modes as $val => $mode ) :
									?>
									<div class="sn6w-mode">
										<input type="radio" name="dark_mode" id="dm-<?php echo esc_attr( $val ); ?>" value="<?php echo esc_attr( $val ); ?>" <?php checked( $saved_mode, $val ); ?> />
										<label class="sn6w-mode-lbl" for="dm-<?php echo esc_attr( $val ); ?>">
											<span class="sn6w-mode-ico"><?php echo $this->icon( $mode['ico'], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<span class="sn6w-mode-nm"><?php echo esc_html( $mode['name'] ); ?></span>
											<span class="sn6w-mode-ht"><?php echo esc_html( $mode['ht'] ); ?></span>
										</label>
									</div>
									<?php endforeach; ?>
								</div>
							</div>

						</div>
					</div>
				</div><!-- #step-3 -->

				<!-- Step 4: Theme -->
				<div class="sn6w-step" id="step-4">
					<div class="sn6w-card">
						<div class="sn6w-card-hd">
							<h2><?php esc_html_e( 'Choose a Theme', '6arshid-social-community' ); ?></h2>
							<p><?php esc_html_e( 'Pick a theme for your social network. You can switch anytime from Appearance → Themes.', '6arshid-social-community' ); ?></p>
						</div>
						<div class="sn6w-card-bd">
							<?php $saved_theme = get_option( 'arshid6social_chosen_theme', '6arshid-social-community' ); ?>
							<div class="sn6w-theme-grid">

								<div class="sn6w-tc">
									<input type="radio" name="chosen_theme" id="th-socialnetworksix" value="sixarshidsocialcomunity" <?php checked( $saved_theme, '6arshid-social-community' ); ?> />
									<label class="sn6w-tc-card" for="th-socialnetworksix">
										<span class="sn6w-tc-rec"><?php esc_html_e( 'Recommended', '6arshid-social-community' ); ?></span>
										<span class="sn6w-tc-chk"><?php echo $this->icon( 'check', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<div class="sn6w-tc-preview">
											<div class="tp-sn6">
												<div class="tp-sn6-sb">
													<div class="tp-sn6-si on"></div>
													<div class="tp-sn6-si"></div>
													<div class="tp-sn6-si"></div>
													<div class="tp-sn6-si"></div>
												</div>
												<div class="tp-sn6-fd">
													<div class="tp-sn6-st">
														<div class="tp-sn6-s"></div>
														<div class="tp-sn6-s" style="border-color:#a855f7;"></div>
														<div class="tp-sn6-s" style="border-color:#ec4899;"></div>
													</div>
													<div class="tp-sn6-po">
														<span class="tp-sn6-av"></span>
														<div class="tp-sn6-ln"></div>
														<div class="tp-sn6-ln s"></div>
													</div>
													<div class="tp-sn6-po" style="opacity:.6;">
														<span class="tp-sn6-av" style="background:#a855f7;"></span>
														<div class="tp-sn6-ln"></div>
													</div>
												</div>
											</div>
										</div>
										<div class="sn6w-tc-body">
											<p class="sn6w-tc-name">6Arshid Social Community</p>
											<p class="sn6w-tc-desc"><?php esc_html_e( 'Modern social layout with Stories, dark mode, and Site Editor support.', '6arshid-social-community' ); ?></p>
											<div class="sn6w-tc-tags">
												<span class="sn6w-tag tag-b">Stories</span>
												<span class="sn6w-tag tag-g">Dark Mode</span>
												<span class="sn6w-tag tag-p">Responsive</span>
											</div>
										</div>
									</label>
								</div>

								<div class="sn6w-tc">
									<input type="radio" name="chosen_theme" id="th-default" value="default" <?php checked( $saved_theme, 'default' ); ?> />
									<label class="sn6w-tc-card" for="th-default">
										<span class="sn6w-tc-chk"><?php echo $this->icon( 'check', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<div class="sn6w-tc-preview">
											<div class="tp-df">
												<span class="tp-df-ic"><?php echo $this->icon( 'palette', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<span class="tp-df-tx"><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></span>
											</div>
										</div>
										<div class="sn6w-tc-body">
											<p class="sn6w-tc-name"><?php esc_html_e( 'Keep Current Theme', '6arshid-social-community' ); ?></p>
											<p class="sn6w-tc-desc"><?php esc_html_e( 'Keep your active WordPress theme and style through Customizer.', '6arshid-social-community' ); ?></p>
											<div class="sn6w-tc-tags">
												<span class="sn6w-tag tag-s"><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></span>
											</div>
										</div>
									</label>
								</div>

							</div>

							<div class="sn6w-alert sn6w-alert-info" id="theme-install-note" style="margin-top:1rem;">
								<?php esc_html_e( 'This theme is not currently installed. It will be installed and activated automatically when you continue.', '6arshid-social-community' ); ?>
							</div>
						</div>
					</div>
				</div><!-- #step-4 -->

				<!-- Step 5: Security -->
				<div class="sn6w-step" id="step-5">
					<div class="sn6w-card">
						<div class="sn6w-card-hd">
							<h2><?php esc_html_e( 'Security', '6arshid-social-community' ); ?></h2>
							<p><?php esc_html_e( 'Configure moderation and spam protection for your community.', '6arshid-social-community' ); ?></p>
						</div>
						<div class="sn6w-card-bd">

							<label class="sn6w-chk-row">
								<input type="checkbox" name="new_member_moderation" value="1" <?php checked( get_option( 'arshid6social_new_member_moderation', false ) ); ?> />
								<span>
									<span class="sn6w-chk-nm"><?php esc_html_e( 'New member moderation', '6arshid-social-community' ); ?></span>
									<span class="sn6w-chk-ht"><?php esc_html_e( 'Require admin approval before new members can post', '6arshid-social-community' ); ?></span>
								</span>
							</label>

							<label class="sn6w-chk-row">
								<input type="checkbox" name="enable_akismet" value="1" <?php checked( get_option( 'arshid6social_enable_akismet', true ) ); ?> />
								<span>
									<span class="sn6w-chk-nm"><?php esc_html_e( 'Akismet spam filtering', '6arshid-social-community' ); ?></span>
									<span class="sn6w-chk-ht"><?php esc_html_e( 'Requires Akismet plugin to be installed and configured', '6arshid-social-community' ); ?></span>
								</span>
							</label>

							<div class="sn6w-div"></div>

							<div class="sn6w-field">
								<label class="sn6w-field-lbl" for="wizard-threshold"><?php esc_html_e( 'Auto-suspend threshold', '6arshid-social-community' ); ?></label>
								<div style="display:flex;align-items:center;gap:.75rem;">
									<input type="number" id="wizard-threshold" name="auto_suspend_threshold" class="sn6w-num"
										min="0" max="100" value="<?php echo esc_attr( get_option( 'arshid6social_auto_suspend_threshold', 5 ) ); ?>" />
									<span style="font-size:.875rem;color:#64748b;"><?php esc_html_e( 'reports (0 = disabled)', '6arshid-social-community' ); ?></span>
								</div>
								<div style="font-size:.8rem;color:#94a3b8;margin-top:.5rem;"><?php esc_html_e( 'Automatically suspend users that receive this many reports.', '6arshid-social-community' ); ?></div>
							</div>

						</div>
					</div>
				</div><!-- #step-5 -->

				<!-- Step 6: Done -->
				<div class="sn6w-step" id="step-6">
					<div class="sn6w-card">
						<div class="sn6w-card-bd">
							<div class="sn6w-done">
								<div class="sn6w-done-ic"><?php echo $this->icon( 'check-circle-fill', 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
								<h2><?php esc_html_e( "You're all set!", '6arshid-social-community' ); ?></h2>
								<p><?php esc_html_e( 'Your social network is ready. Visit your site to see it in action!', '6arshid-social-community' ); ?></p>

								<div id="theme-done-note" class="sn6w-alert sn6w-alert-ok" style="max-width:440px;margin:0 auto 1.5rem;text-align:left;"></div>

								<div class="sn6w-sample">
									<h3><?php esc_html_e( 'Import Sample Data', '6arshid-social-community' ); ?></h3>
									<p><?php esc_html_e( 'Populate with demo content: 50 users, activities, notifications, marketplace listings, groups, messages, stories, and more.', '6arshid-social-community' ); ?></p>
									<button type="button" id="wizard-import-sample" class="sn6w-btn sn6w-btn-primary"
										<?php echo get_option( 'arshid6social_sample_data_imported' ) ? 'disabled' : ''; ?>>
										<?php echo get_option( 'arshid6social_sample_data_imported' )
											? esc_html__( 'Sample data already imported', '6arshid-social-community' )
											: esc_html__( 'Import Sample Data', '6arshid-social-community' ); ?>
									</button>
									<span id="wizard-import-status" style="display:none;margin-left:.75rem;font-size:.875rem;"></span>
								</div>

								<div class="sn6w-done-acts">
									<a href="<?php echo esc_url( home_url( '/members/' ) ); ?>" class="sn6w-btn sn6w-btn-primary" target="_blank">
										<?php esc_html_e( 'View Members', '6arshid-social-community' ); ?>
									</a>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=arshid6social-settings' ) ); ?>" class="sn6w-btn sn6w-btn-ghost">
										<?php esc_html_e( 'Go to Settings', '6arshid-social-community' ); ?>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div><!-- #step-6 -->

			</div><!-- .sn6w-body -->
		</div><!-- .sn6w -->

		<div class="sn6w-footer">
			<button class="sn6w-btn sn6w-btn-ghost" id="sn6w-back" style="visibility:hidden;">← <?php esc_html_e( 'Back', '6arshid-social-community' ); ?></button>
			<span class="sn6w-step-lbl" id="sn6w-step-lbl"><?php esc_html_e( 'Step 1 of 6', '6arshid-social-community' ); ?></span>
			<button class="sn6w-btn sn6w-btn-primary" id="sn6w-next"><?php esc_html_e( 'Continue', '6arshid-social-community' ); ?> →</button>
		</div>

		<?php
		$wizard_js = '(function(){
			const TOTAL = 6;
			let cur = 1;
			const nonce      = ' . wp_json_encode( wp_create_nonce( 'arshid6social_wizard_save' ) ) . ';
			const ajaxUrl    = ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';
			const instThemes = ' . wp_json_encode( array_keys( wp_get_themes() ) ) . ';
			const stepNames  = ["Core Features","Engagement","Appearance","Theme","Security","Done"];
			const i18n = {
				finish:       ' . wp_json_encode( __( 'Finish', '6arshid-social-community' ) ) . ',
				continue:     ' . wp_json_encode( __( 'Continue', '6arshid-social-community' ) ) . ',
				unselectAll:  ' . wp_json_encode( __( 'Unselect all', '6arshid-social-community' ) ) . ',
				selectAll:    ' . wp_json_encode( __( 'Select all', '6arshid-social-community' ) ) . ',
				importing:    ' . wp_json_encode( __( 'Importing…', '6arshid-social-community' ) ) . ',
				imported:     ' . wp_json_encode( __( 'Imported!', '6arshid-social-community' ) ) . ',
				importSample: ' . wp_json_encode( __( 'Import Sample Data', '6arshid-social-community' ) ) . ',
				importFailed: ' . wp_json_encode( __( 'Import failed.', '6arshid-social-community' ) ) . ',
				sampleNonce:  ' . wp_json_encode( wp_create_nonce( 'arshid6social_sample_data' ) ) . ',
				dashUrl:      ' . wp_json_encode( admin_url( 'admin.php?page=arshid6social-dashboard' ) ) . ',
			};

			function show(step) {
				document.querySelectorAll(".sn6w-step").forEach(s => s.classList.remove("is-active"));
				const el = document.getElementById("step-" + step);
				if (el) el.classList.add("is-active");

				const back = document.getElementById("sn6w-back");
				const next = document.getElementById("sn6w-next");
				const lbl  = document.getElementById("sn6w-step-lbl");
				const hdr  = document.getElementById("sn6w-hdr-step");
				const bar  = document.getElementById("sn6w-bar");

				back.style.visibility = step > 1 ? "visible" : "hidden";
				lbl.textContent = `Step ${step} of ${TOTAL}`;
				if (hdr) hdr.textContent = stepNames[step - 1] || "";
				if (bar) bar.style.width = ((step / TOTAL) * 100) + "%";
				next.textContent = step === TOTAL ? i18n.finish : i18n.continue + " →";

				window.scrollTo(0, 0);
			}

			/* ── Select all / Unselect all ── */
			function syncTogBtn(btn) {
				const grp = document.getElementById("grp-" + btn.dataset.grp);
				if (!grp) return;
				const boxes = grp.querySelectorAll("input[type=\'checkbox\']");
				const all   = Array.from(boxes).every(c => c.checked);
				btn.textContent = all ? i18n.unselectAll : i18n.selectAll;
			}
			document.querySelectorAll(".sn6w-tog-all").forEach(btn => {
				syncTogBtn(btn);
				btn.addEventListener("click", () => {
					const grp = document.getElementById("grp-" + btn.dataset.grp);
					if (!grp) return;
					const boxes      = grp.querySelectorAll("input[type=\'checkbox\']");
					const allChecked = Array.from(boxes).every(c => c.checked);
					boxes.forEach(c => { c.checked = !allChecked; });
					syncTogBtn(btn);
				});
				const grp = document.getElementById("grp-" + btn.dataset.grp);
				if (grp) grp.querySelectorAll("input").forEach(cb => cb.addEventListener("change", () => syncTogBtn(btn)));
			});

			/* ── Color presets ── */
			const colorInp = document.getElementById("wizard-color");
			document.querySelectorAll(".sn6w-preset").forEach(btn => {
				btn.addEventListener("click", () => {
					document.querySelectorAll(".sn6w-preset").forEach(b => b.classList.remove("on"));
					btn.classList.add("on");
					if (colorInp) colorInp.value = btn.dataset.color;
				});
			});
			if (colorInp) {
				colorInp.addEventListener("input", () => {
					document.querySelectorAll(".sn6w-preset").forEach(b => b.classList.toggle("on", b.dataset.color === colorInp.value));
				});
			}

			/* ── Theme install note ── */
			document.querySelectorAll("[name=\'chosen_theme\']").forEach(r => {
				r.addEventListener("change", () => {
					const note = document.getElementById("theme-install-note");
					if (note) note.style.display = (r.value !== "default" && !instThemes.includes(r.value)) ? "block" : "none";
				});
			});

			/* ── Media types fade when uploads disabled ── */
			const allowMediaCb = document.getElementById("allow-media");
			const mediaWrap    = document.getElementById("media-types-wrap");
			function toggleMedia() {
				if (!mediaWrap) return;
				mediaWrap.style.opacity       = allowMediaCb?.checked ? "1" : "0.4";
				mediaWrap.style.pointerEvents = allowMediaCb?.checked ? "" : "none";
			}
			if (allowMediaCb) { allowMediaCb.addEventListener("change", toggleMedia); toggleMedia(); }

			/* ── Save step via AJAX ── */
			async function saveStep(step) {
				const fd = new FormData();
				fd.append("action", "arshid6social_wizard_save");
				fd.append("nonce", nonce);
				fd.append("step", step);

				if (1 === step) {
					document.querySelectorAll("[name=\'components[]\']:checked").forEach(c => fd.append("components[]", c.value));
					document.querySelectorAll("[name=\'pack_features[]\']:checked").forEach(c => fd.append("pack_features[]", c.value));
				} else if (2 === step) {
					document.querySelectorAll("[name=\'engagement_features[]\']:checked").forEach(c => fd.append("engagement_features[]", c.value));
					const mk = document.querySelector("[name=\'marketplace_enabled\']");
					fd.append("marketplace_enabled", mk?.checked ? "1" : "0");
					const am = document.getElementById("allow-media");
					fd.append("allow_media", am?.checked ? "1" : "0");
					document.querySelectorAll("[name=\'allowed_media_types[]\']:checked").forEach(c => fd.append("allowed_media_types[]", c.value));
				} else if (3 === step) {
					fd.append("primary_color", colorInp ? colorInp.value : "#2563eb");
					const dm = document.querySelector("[name=\'dark_mode\']:checked");
					fd.append("dark_mode", dm ? dm.value : "auto");
				} else if (4 === step) {
					const tr = document.querySelector("[name=\'chosen_theme\']:checked");
					fd.append("chosen_theme", tr ? tr.value : "sixarshidsocialcomunity");
				} else if (5 === step) {
					const mod = document.querySelector("[name=\'new_member_moderation\']");
					fd.append("new_member_moderation", mod?.checked ? "1" : "0");
					const ak = document.querySelector("[name=\'enable_akismet\']");
					fd.append("enable_akismet", ak?.checked ? "1" : "0");
					fd.append("auto_suspend_threshold", document.getElementById("wizard-threshold").value);
				}

				const resp = await fetch(ajaxUrl, { method:"POST", body:fd });
				return resp.json().catch(() => ({}));
			}

			/* ── Next / Finish ── */
			const nextBtn = document.getElementById("sn6w-next");
			nextBtn.addEventListener("click", async () => {
				nextBtn.disabled = true;
				if (cur < TOTAL) {
					const res = await saveStep(cur);
					cur++;
					show(cur);
					if (cur === TOTAL && res?.data?.theme_message) {
						const dn = document.getElementById("theme-done-note");
						if (dn) { dn.textContent = res.data.theme_message; dn.style.display = "block"; }
					}
				} else {
					await saveStep(cur);
					window.location.href = i18n.dashUrl;
				}
				nextBtn.disabled = false;
			});

			document.getElementById("sn6w-back").addEventListener("click", () => {
				if (cur > 1) { cur--; show(cur); }
			});

			/* ── Import sample data ── */
			const importBtn = document.getElementById("wizard-import-sample");
			if (importBtn) {
				importBtn.addEventListener("click", async () => {
					importBtn.disabled = true;
					importBtn.textContent = i18n.importing;
					const statusEl = document.getElementById("wizard-import-status");
					statusEl.style.display = "none";
					const fd = new FormData();
					fd.append("action", "arshid6social_import_sample_data");
					fd.append("nonce", i18n.sampleNonce);
					try {
						const resp = await fetch(ajaxUrl, { method:"POST", body:fd });
						const json = await resp.json();
						statusEl.style.display = "inline";
						if (json.success) {
							importBtn.textContent = i18n.imported;
							statusEl.style.color  = "#16a34a";
							statusEl.textContent  = json.data?.message ?? "";
						} else {
							importBtn.disabled    = false;
							importBtn.textContent = i18n.importSample;
							statusEl.style.color  = "#dc2626";
							statusEl.textContent  = json.data?.message ?? i18n.importFailed;
						}
					} catch(e) {
						importBtn.disabled    = false;
						importBtn.textContent = i18n.importSample;
					}
				});
			}

			show(cur);
		})();';
		wp_add_inline_script( 'arshid6social-admin', $wizard_js );
		?>
		<?php
	}

	/**
	 * AJAX: Saves a wizard step's settings.
	 *
	 * Steps are now 1-6:
	 *   1 → components + pack
	 *   2 → engagement features + marketplace + media
	 *   3 → appearance
	 *   4 → theme
	 *   5 → security
	 *   6 → mark complete
	 */
	public function ajax_save(): void {
		if ( ! check_ajax_referer( 'arshid6social_wizard_save', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community' ) ), 403 );
		}

		$step = absint( $_POST['step'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		switch ( $step ) {
			case 1:
				$components   = isset( $_POST['components'] ) && is_array( $_POST['components'] ) // phpcs:ignore WordPress.Security.NonceVerification
					? array_map( 'sanitize_key', $_POST['components'] ) // phpcs:ignore WordPress.Security.NonceVerification
					: array();
				$components[] = 'members';
				update_option( 'arshid6social_enabled_components', array_unique( $components ) );

				$pack_features    = isset( $_POST['pack_features'] ) && is_array( $_POST['pack_features'] ) // phpcs:ignore WordPress.Security.NonceVerification
					? array_map( 'sanitize_key', $_POST['pack_features'] ) // phpcs:ignore WordPress.Security.NonceVerification
					: array();
				$all_pack_options = array( 'arshid6social_stories_enabled', 'arshid6social_verification_enabled', 'arshid6social_blocking_enabled', 'arshid6social_activity_stats_bar' );
				foreach ( $all_pack_options as $pack_opt ) {
					update_option( $pack_opt, in_array( $pack_opt, $pack_features, true ) );
				}
				break;

			case 2:
				$eng_features = isset( $_POST['engagement_features'] ) && is_array( $_POST['engagement_features'] ) // phpcs:ignore WordPress.Security.NonceVerification
					? array_map( 'sanitize_key', $_POST['engagement_features'] ) // phpcs:ignore WordPress.Security.NonceVerification
					: array();
				$all_eng_keys = array(
					'hashtags', 'tag_friends', 'bookmarks', 'sticky_posts',
					'share_posts', 'polls', 'advanced_polls', 'comments_gifs',
					'comments_attachments', 'messages_attachments',
				);
				foreach ( $all_eng_keys as $eng_key ) {
					update_option( 'arshid6social_eng_' . $eng_key, in_array( $eng_key, $eng_features, true ) );
				}

				$marketplace_enabled = '1' === ( $_POST['marketplace_enabled'] ?? '0' ); // phpcs:ignore WordPress.Security.NonceVerification
				update_option( 'arshid6social_marketplace_enabled', $marketplace_enabled );
				if ( $marketplace_enabled ) {
					\Arshid6Social\Components\Marketplace\Marketplace_Settings::maybe_create_marketplace_page();
				}

				update_option( 'arshid6social_activity_allow_media', '1' === ( $_POST['allow_media'] ?? '0' ) ); // phpcs:ignore WordPress.Security.NonceVerification
				$allowed_media_types = isset( $_POST['allowed_media_types'] ) && is_array( $_POST['allowed_media_types'] ) // phpcs:ignore WordPress.Security.NonceVerification
					? array_map( 'sanitize_key', $_POST['allowed_media_types'] ) // phpcs:ignore WordPress.Security.NonceVerification
					: array();
				update_option( 'arshid6social_activity_allowed_media_types', $allowed_media_types );
				break;

			case 3:
				update_option( 'arshid6social_primary_color', sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '#2563eb' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
				update_option( 'arshid6social_dark_mode', sanitize_key( $_POST['dark_mode'] ?? 'auto' ) ); // phpcs:ignore WordPress.Security.NonceVerification
				break;

			case 4:
				$chosen_theme  = sanitize_key( $_POST['chosen_theme'] ?? '6arshid-social-community' ); // phpcs:ignore WordPress.Security.NonceVerification
				update_option( 'arshid6social_chosen_theme', $chosen_theme );
				$theme_message = '';
				if ( 'default' !== $chosen_theme ) {
					$theme_message = self::activate_theme( $chosen_theme );
				}
				wp_send_json_success( array( 'theme_message' => $theme_message ) );
				return;

			case 5:
				update_option( 'arshid6social_new_member_moderation', (bool) ( $_POST['new_member_moderation'] ?? false ) ); // phpcs:ignore WordPress.Security.NonceVerification
				update_option( 'arshid6social_enable_akismet', (bool) ( $_POST['enable_akismet'] ?? true ) ); // phpcs:ignore WordPress.Security.NonceVerification
				update_option( 'arshid6social_auto_suspend_threshold', absint( $_POST['auto_suspend_threshold'] ?? 5 ) ); // phpcs:ignore WordPress.Security.NonceVerification
				break;

			case 6:
				update_option( 'arshid6social_setup_complete', true );
				break;
		}

		wp_send_json_success();
	}

	/**
	 * Tries to activate a theme by slug.
	 */
	private static function activate_theme( string $slug ): string {
		$current = get_stylesheet();
		if ( '6arshid-social-community' !== $current ) {
			update_option( 'arshid6social_theme_before_activation', $current );
		}

		$plugin_themes_dir = ARSHID6SOCIAL_PLUGIN_DIR . 'themes';
		register_theme_directory( $plugin_themes_dir );
		delete_site_transient( 'theme_roots' );

		$theme = wp_get_theme( $slug );
		if ( $theme->exists() ) {
			switch_theme( $slug );
			return sprintf(
				/* translators: %s: theme name */
				__( 'Theme "%s" activated successfully.', '6arshid-social-community' ),
				$theme->get( 'Name' )
			);
		}

		$bundled_path = $plugin_themes_dir . '/' . $slug . '/style.css';
		if ( file_exists( $bundled_path ) ) {
			if ( function_exists( 'search_theme_directories' ) ) {
				search_theme_directories( true );
			}
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				switch_theme( $slug );
				return sprintf(
					/* translators: %s: theme name */
					__( 'Theme "%s" activated successfully.', '6arshid-social-community' ),
					$theme->get( 'Name' )
				);
			}
			switch_theme( $slug );
			return __( '6Arshid Social Community theme activated. Reload the page to see it applied.', '6arshid-social-community' );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
		$zip_url  = 'https://downloads.wordpress.org/theme/' . $slug . '.latest-stable.zip';
		$result   = $upgrader->install( $zip_url );

		if ( ! is_wp_error( $result ) && $result ) {
			switch_theme( $slug );
			$installed = wp_get_theme( $slug );
			return sprintf(
				/* translators: %s: theme name */
				__( 'Theme "%s" installed and activated.', '6arshid-social-community' ),
				$installed->exists() ? $installed->get( 'Name' ) : $slug
			);
		}

		return sprintf(
			/* translators: %s: theme slug */
			__( 'Could not install "%s". Please install it manually from Appearance → Themes.', '6arshid-social-community' ),
			$slug
		);
	}
}
