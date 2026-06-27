<?php
namespace Arshid6Social;

/**
 * Shortcode registration and rendering.
 *
 * Supported shortcodes:
 *  [arshid6social_members]           — Member directory
 *  [arshid6social_activity]          — Activity feed
 *  [arshid6social_groups]            — Group list
 *  [arshid6social_messages]          — Private messages inbox
 *  [arshid6social_profile]           — Single user profile (uses current user if no id/slug)
 *  [arshid6social_login_form]        — WordPress login form styled for the plugin
 *  [arshid6social_register_form]     — Registration form (when registration is enabled)
 *
 * All shortcodes delegate to the same PHP render logic used by Gutenberg blocks.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcodes
 */
class Shortcodes {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register(): void {
		add_shortcode( 'arshid6social_members',         array( $this, 'members' ) );
		add_shortcode( 'arshid6social_who_to_follow',   array( $this, 'who_to_follow' ) );
		add_shortcode( 'arshid6social_activity',        array( $this, 'activity' ) );
		add_shortcode( 'arshid6social_groups',          array( $this, 'groups' ) );
		add_shortcode( 'arshid6social_messages',        array( $this, 'messages' ) );
		add_shortcode( 'arshid6social_notifications',   array( $this, 'notifications' ) );
		add_shortcode( 'arshid6social_profile',         array( $this, 'profile' ) );
		add_shortcode( 'arshid6social_login_form',         array( $this, 'login_form' ) );
		add_shortcode( 'arshid6social_register_form',      array( $this, 'register_form' ) );
		add_shortcode( 'arshid6social_forgot_password',    array( $this, 'forgot_password' ) );
		add_shortcode( 'arshid6social_reset_password',     array( $this, 'reset_password' ) );
		add_shortcode( 'arshid6social_dashboard',       array( $this, 'dashboard' ) );
		add_shortcode( 'arshid6social_marketplace',     array( $this, 'marketplace' ) );
		add_shortcode( 'arshid6social_listing_form',    array( $this, 'listing_form' ) );
		add_shortcode( 'arshid6social_my_listings',     array( $this, 'my_listings' ) );
		add_shortcode( 'arshid6social_saved_listings',  array( $this, 'saved_listings' ) );
		add_shortcode( 'arshid6social_seller_listings', array( $this, 'seller_listings' ) );
		add_shortcode( 'arshid6social_ads',             array( $this, 'ads' ) );
		add_shortcode( 'arshid6social_home',            array( $this, 'home' ) );
	}

	// -------------------------------------------------------------------------
	// [arshid6social_members per_page="12" show_search="true" type="newest"]
	// -------------------------------------------------------------------------

	/**
	 * Renders the member directory.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function members( $atts ): string {
		$atts = shortcode_atts(
			array(
				'per_page'    => 12,
				'show_search' => 'true',
				'type'        => 'newest',
			),
			$atts,
			'arshid6social_members'
		);

		/** @var Components\Members\Members|null $comp */
		$comp = ARSHID6SOCIAL()->component( 'members' );
		if ( ! $comp ) {
			return '';
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		$per_page = absint( $atts['per_page'] );
		$result   = $comp->get_members( array( 'type' => sanitize_key( $atts['type'] ), 'per_page' => $per_page ) );

		return ARSHID6SOCIAL()->template()->get_template(
			'members/directory.php',
			array(
				'members'     => $result['members'],
				'total'       => $result['total'],
				'per_page'    => $per_page,
				'show_search' => filter_var( $atts['show_search'], FILTER_VALIDATE_BOOLEAN ),
				'block_mode'  => true,
			),
			true
		);
	}

	// -------------------------------------------------------------------------
	// [arshid6social_who_to_follow per_page="3" type="newest"]
	// -------------------------------------------------------------------------

	/**
	 * Renders a compact "Who to follow" member list for sidebars.
	 *
	 * Unlike [arshid6social_members] (which is removed on profile pages to avoid
	 * conflict), this shortcode is never removed and renders server-side so
	 * the right sidebar always shows suggested members.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function who_to_follow( $atts ): string {
		$atts = shortcode_atts(
			array(
				'per_page' => (int) get_option( 'arshid6social_who_to_follow_per_page', 3 ),
				'type'     => 'newest',
			),
			$atts,
			'arshid6social_who_to_follow'
		);

		/** @var Components\Members\Members|null $comp */
		$comp = ARSHID6SOCIAL()->component( 'members' );
		if ( ! $comp ) {
			return '';
		}

		$viewer_id = get_current_user_id();
		$exclude   = $viewer_id ? array( $viewer_id ) : array();

		$result = $comp->get_members( array(
			'type'     => sanitize_key( $atts['type'] ),
			'per_page' => absint( $atts['per_page'] ),
			'exclude'  => $exclude,
		) );

		if ( empty( $result['members'] ) ) {
			return '';
		}

		$friends_comp    = ARSHID6SOCIAL()->component( 'friends' );
		$fallback_avatar = esc_url( get_avatar_url( 0, array( 'default' => 'mm', 'size' => 48 ) ) );

		$primary_color = esc_attr( get_option( 'arshid6social_primary_color', '#1d9bf0' ) );

		$s_row  = 'display:flex;flex-direction:row;align-items:center;gap:12px;padding:8px 16px;';
		$s_avtr = 'display:block;flex-shrink:0;width:44px;height:44px;border-radius:50%;overflow:hidden;line-height:0;';
		$s_img  = 'width:44px;height:44px;max-width:44px;border-radius:50%;object-fit:cover;display:block;';
		$s_name = 'flex:1;min-width:0;font-size:14px;font-weight:600;line-height:1.3;color:var(--a6sc-text);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;';
		$icons = array(
			'not_friends'      => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
			'pending_sent'     => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
			'pending_received' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
			'friends'          => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
		);

		ob_start();
		echo '<div class="arshid6social-who-to-follow" role="list">';
		foreach ( $result['members'] as $m ) {
			if ( (int) $m['id'] === $viewer_id ) {
				continue;
			}
			$status = ( $viewer_id && $friends_comp )
				? $friends_comp->get_friendship_status( $viewer_id, (int) $m['id'] )
				: 'not_friends';
			$labels = array(
				'not_friends'      => __( 'Add Friend', '6arshid-social-community-main' ),
				'pending_sent'     => __( 'Pending', '6arshid-social-community-main' ),
				'pending_received' => __( 'Accept', '6arshid-social-community-main' ),
				'friends'          => __( 'Friends', '6arshid-social-community-main' ),
			);
			$btn_label = $labels[ $status ] ?? __( 'Add Friend', '6arshid-social-community-main' );
			$btn_icon  = $icons[ $status ] ?? $icons['not_friends'];
			?>
			<div class="arshid6social-wtf-row" role="listitem" style="<?php echo esc_attr( $s_row ); ?>">
				<a class="arshid6social-wtf-avatar" href="<?php echo esc_url( $m['profileUrl'] ); ?>" style="<?php echo esc_attr( $s_avtr ); ?>">
					<img src="<?php echo esc_url( $m['avatarUrl'] ); ?>"
						alt="<?php echo esc_attr( $m['name'] ); ?>"
						width="44" height="44" loading="lazy"
						style="<?php echo esc_attr( $s_img ); ?>"
						onerror="this.onerror=null;this.src='<?php echo $fallback_avatar; // phpcs:ignore WordPress.Security.EscapeOutput ?>';"
					/>
				</a>
				<a class="arshid6social-wtf-name" href="<?php echo esc_url( $m['profileUrl'] ); ?>" style="<?php echo esc_attr( $s_name ); ?>">
					<?php echo esc_html( $m['name'] ); ?>
				</a>
				<?php if ( $viewer_id ) : ?>
				<?php
				$is_active = in_array( $status, array( 'not_friends', 'pending_received' ), true );
				$btn_style = $is_active
					? 'background:' . $primary_color . ';border:1.5px solid ' . $primary_color . ';color:#fff;'
					: 'background:transparent;border:1.5px solid var(--a6sc-border);color:var(--a6sc-text-muted);';
				?>
				<button class="arshid6social-friend-btn arshid6social-wtf-btn arshid6social-wtf-btn--<?php echo esc_attr( $status ); ?>"
					style="<?php echo esc_attr( $btn_style ); ?>"
					data-user-id="<?php echo esc_attr( $m['id'] ); ?>"
					data-status="<?php echo esc_attr( $status ); ?>"
					data-primary-color="<?php echo esc_attr( $primary_color ); ?>">
					<?php echo $btn_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo esc_html( $btn_label ); ?>
				</button>
				<?php endif; ?>
			</div>
			<?php
		}
		echo '</div>';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [arshid6social_activity per_page="10" show_composer="true" show_stories="true" show_feed="true" scope="site"]
	// -------------------------------------------------------------------------

	/**
	 * Renders the activity feed.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function activity( $atts ): string {
		$atts = shortcode_atts(
			array(
				'per_page'      => 10,
				'show_composer' => 'true',
				'show_stories'  => 'true',
				'show_feed'     => 'true',
				'scope'         => 'site',
			),
			$atts,
			'arshid6social_activity'
		);

		/** @var Components\Activity\Activity|null $comp */
		$comp = ARSHID6SOCIAL()->component( 'activity' );
		if ( ! $comp ) {
			return '';
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		$per_page      = absint( $atts['per_page'] );
		$show_composer = filter_var( $atts['show_composer'], FILTER_VALIDATE_BOOLEAN );
		$show_stories  = filter_var( $atts['show_stories'], FILTER_VALIDATE_BOOLEAN );
		$show_feed     = filter_var( $atts['show_feed'], FILTER_VALIDATE_BOOLEAN );
		$scope         = sanitize_key( $atts['scope'] );
		$nonce         = wp_create_nonce( 'arshid6social_activity' );

		ob_start();
		?>
		<div class="arshid6social-directory-wrap">
		<div class="arshid6social-activity-block" data-scope="<?php echo esc_attr( $scope ); ?>" data-per-page="<?php echo esc_attr( $per_page ); ?>">
			<?php
			// Stories tray — injected above composer when Stories feature is enabled.
			if ( $show_stories && get_option( 'arshid6social_stories_enabled', false ) ) {
				$stories_comp = ARSHID6SOCIAL()->component( 'stories' );
				if ( $stories_comp ) {
					$viewer_id = get_current_user_id();
					$stories   = $stories_comp->get_tray( $viewer_id );
					// Show tray if viewer is logged in (to add story) OR if there are visible stories.
					if ( is_user_logged_in() || ! empty( $stories ) ) {
						\Arshid6Social\Template_Loader::instance()->get_template(
							'stories/tray.php',
							array(
								'stories'     => $stories,
								'viewer_id'   => $viewer_id,
								'stories_obj' => $stories_comp,
							)
						);
					}
				}
			}
			?>
			<?php if ( $show_composer && is_user_logged_in() ) : ?>
			<form class="arshid6social-activity-form arshid6social-card" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<?php wp_nonce_field( 'arshid6social_activity', 'arshid6social_nonce' ); ?>
				<textarea name="content" class="arshid6social-activity-composer"
					placeholder="<?php esc_attr_e( "What's on your mind?", '6arshid-social-community-main' ); ?>"
					rows="3" maxlength="5000" required></textarea>
				<?php if ( get_option( 'sixarshidsc_enabled' ) ) : ?>
				<div class="sixarshidsc-price-row" style="display:none;align-items:center;gap:6px;margin:0 0 8px;flex-wrap:wrap;">
					<label class="sixarshidsc-price-label" for="sixarshidsc-ppv-price-input" style="font-size:.875rem;white-space:nowrap;">
						<?php esc_html_e( '💰 Price to unlock:', '6arshid-social-community-main' ); ?>
					</label>
					<input type="number" id="sixarshidsc-ppv-price-input" name="ppv_price"
						min="0.50" step="0.01" placeholder="<?php esc_attr_e( 'e.g. 10.00', '6arshid-social-community-main' ); ?>"
						class="arshid6social-input sixarshidsc-ppv-price-input"
						style="width:110px;" />
					<span class="sixarshidsc-price-currency" style="font-size:.875rem;color:var(--sn-text-muted,#6b7280);">
						<?php echo esc_html( strtoupper( (string) get_option( 'sixarshidsc_currency', 'USD' ) ) ); ?>
					</span>
				</div>
				<?php endif; ?>
				<div class="arshid6social-activity-form-footer">
					<select name="privacy" class="arshid6social-privacy-select">
						<option value="public"><?php esc_html_e( '🌐 Public', '6arshid-social-community-main' ); ?></option>
						<option value="friends"><?php esc_html_e( '👥 Friends', '6arshid-social-community-main' ); ?></option>
						<option value="private"><?php esc_html_e( '🔒 Only Me', '6arshid-social-community-main' ); ?></option>
						<?php if ( get_option( 'sixarshidsc_enabled' ) ) : ?>
						<option value="paid"><?php esc_html_e( '💰 Paid', '6arshid-social-community-main' ); ?></option>
						<?php endif; ?>
					</select>
					<button type="submit" class="arshid6social-btn arshid6social-btn--primary">
						<?php esc_html_e( 'Post', '6arshid-social-community-main' ); ?>
					</button>
				</div>
			</form>
			<?php endif; ?>
			<?php if ( $show_feed ) : ?>
			<div class="arshid6social-activity-feed" aria-label="<?php esc_attr_e( 'Activity feed', '6arshid-social-community-main' ); ?>"></div>
			<div class="arshid6social-load-more-sentinel" style="height:1px;"></div>
			<?php endif; ?>
		</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [arshid6social_groups per_page="9" show_search="true" show_create="true" status="public"]
	// -------------------------------------------------------------------------

	/**
	 * Renders the group list.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function groups( $atts ): string {
		$atts = shortcode_atts(
			array(
				'per_page'    => 9,
				'show_search' => 'true',
				'show_create' => 'true',
				'status'      => 'public',
			),
			$atts,
			'arshid6social_groups'
		);

		/** @var Components\Groups\Groups|null $comp */
		$comp = ARSHID6SOCIAL()->component( 'groups' );
		if ( ! $comp ) {
			return '';
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		$per_page = absint( $atts['per_page'] );
		$status   = sanitize_key( $atts['status'] );
		$statuses = ( 'all' === $status ) ? array( 'public', 'private' ) : array( 'public' );
		$result   = $comp->get_groups( array( 'status' => $statuses, 'per_page' => $per_page ) );

		return ARSHID6SOCIAL()->template()->get_template(
			'groups/directory.php',
			array(
				'groups'             => $result['groups'],
				'total'              => $result['total'],
				'per_page'           => $per_page,
				'show_search'        => filter_var( $atts['show_search'], FILTER_VALIDATE_BOOLEAN ),
				'show_create_button' => filter_var( $atts['show_create'], FILTER_VALIDATE_BOOLEAN ),
				'block_mode'         => true,
			),
			true
		);
	}

	// -------------------------------------------------------------------------
	// [arshid6social_messages]
	// -------------------------------------------------------------------------

	/**
	 * Renders the private messages inbox.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function messages( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="arshid6social-notice arshid6social-notice-info">' .
				wp_kses(
					sprintf(
						/* translators: %s: login URL */
						__( 'Please <a href="%s">log in</a> to view your messages.', '6arshid-social-community-main' ),
						esc_url( wp_login_url( get_permalink() ) )
					),
					array( 'a' => array( 'href' => array() ) )
				) .
			'</div>';
		}

		/** @var Components\Messages\Messages|null $comp */
		$comp = ARSHID6SOCIAL()->component( 'messages' );
		if ( ! $comp ) {
			return '';
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		$thread_id            = 0;
		$compose_recipient_id = 0;

		// phpcs:disable WordPress.Security.NonceVerification
		$thread_uid = sanitize_text_field( wp_unslash( $_GET['thread'] ?? '' ) );
		if ( $thread_uid ) {
			$thread_id = \Arshid6Social\Components\Messages\Messages::thread_id_from_uid( $thread_uid );
		}

		$user_uid = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) );
		if ( $user_uid ) {
			$compose_recipient_id = \Arshid6Social\Components\Messages\Messages::user_id_from_uid( $user_uid );
		}
		// phpcs:enable

		return ARSHID6SOCIAL()->template()->get_template( 'messages/inbox.php', array(
			'thread_id'            => $thread_id,
			'compose_recipient_id' => $compose_recipient_id,
		), true );
	}

	// -------------------------------------------------------------------------
	// [arshid6social_notifications]
	// -------------------------------------------------------------------------

	/**
	 * Renders the current user's full notifications page.
	 *
	 * @param array|string $atts Shortcode attributes (none currently).
	 */
	public function notifications( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="arshid6social-notice arshid6social-notice-info">' .
				wp_kses(
					sprintf(
						/* translators: %s: login URL */
						__( 'Please <a href="%s">log in</a> to view your notifications.', '6arshid-social-community-main' ),
						esc_url( wp_login_url( get_permalink() ) )
					),
					array( 'a' => array( 'href' => array() ) )
				) .
			'</div>';
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		return ARSHID6SOCIAL()->template()->get_template( 'notifications/page.php', array(), true );
	}

	// -------------------------------------------------------------------------
	// [arshid6social_profile id="42"]  or  [arshid6social_profile slug="john-doe"]
	// -------------------------------------------------------------------------

	/**
	 * Renders a user profile card inline.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function profile( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'arshid6social_profile'
		);

		$user_id = absint( $atts['id'] );

		if ( ! $user_id && $atts['slug'] ) {
			$user = get_user_by( 'slug', sanitize_title( $atts['slug'] ) );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return '';
		}

		/** @var Components\Members\Members|null $comp */
		$comp = ARSHID6SOCIAL()->component( 'members' );
		if ( ! $comp ) {
			return '';
		}

		global $arshid6social_is_page;
		$arshid6social_is_page = true;

		$member = $comp->format_member( get_userdata( $user_id ) );

		return ARSHID6SOCIAL()->template()->get_template(
			'members/profile.php',
			array(
				'member'      => $member,
				'profile_user' => get_userdata( $user_id ),
			),
			true
		);
	}

	// -------------------------------------------------------------------------
	// Cinematic splash wrapper helpers (shared by all auth pages)
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the Google Fonts stylesheet used by the cinematic splash pages.
	 *
	 * Called from within the splash markup; styles enqueued after wp_head are
	 * printed in the footer by WordPress. Registered preconnect hints are added
	 * via the wp_resource_hints filter.
	 */
	private function enqueue_splash_fonts(): void {
		$handle = 'arshid6social-splash-fonts';
		if ( ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_enqueue_style(
				$handle,
				'https://fonts.googleapis.com/css2?family=Instrument+Serif&family=Inter:wght@400;500&display=swap',
				array(),
				ARSHID6SOCIAL_VERSION
			);
		}
	}

	/**
	 * Returns the background media URL for the splash.
	 * Priority: admin-uploaded option → CDN default video → bundled plugin asset.
	 */
	private function splash_video_url(): string {
		$custom = (string) get_option( 'arshid6social_home_video_url', '' );
		if ( $custom ) {
			return apply_filters( 'arshid6social_home_video_url', $custom );
		}
		$bundled = plugins_url( 'assets/videos/home-bg.mp4', ARSHID6SOCIAL_PLUGIN_FILE );
		return apply_filters( 'arshid6social_home_video_url', $bundled );
	}

	/** Returns 'image' when the admin uploaded a still image, 'video' otherwise. */
	private function splash_bg_type(): string {
		if ( ! get_option( 'arshid6social_home_video_url', '' ) ) {
			return 'video';
		}
		$type = (string) get_option( 'arshid6social_home_bg_type', 'video' );
		return in_array( $type, array( 'video', 'image' ), true ) ? $type : 'video';
	}

	/**
	 * Outputs the opening HTML for the cinematic splash wrapper.
	 * Call ob_start() before this, then echo your card content, then call splash_wrap_end().
	 *
	 * @param string $title    Card heading.
	 * @param string $subtitle Optional subtitle below the heading.
	 */
	private function splash_wrap_start( string $title, string $subtitle = '' ): void {
		$site_name = get_bloginfo( 'name' );
		$home_url  = get_option( 'arshid6social_page_home', 0 )
			? get_permalink( (int) get_option( 'arshid6social_page_home' ) )
			: home_url( '/' );
		?>
		<?php $this->enqueue_splash_fonts(); ?>
		<div class="a6scsplash">
			<?php if ( $this->splash_bg_type() === 'image' ) : ?>
			<img class="a6scsplash__video" src="<?php echo esc_url( $this->splash_video_url() ); ?>" alt="" aria-hidden="true">
			<?php else : ?>
			<video class="a6scsplash__video" autoplay loop muted playsinline>
				<source src="<?php echo esc_url( $this->splash_video_url() ); ?>" type="video/mp4">
			</video>
			<?php endif; ?>
			<div class="a6scsplash__overlay" aria-hidden="true"></div>

			<nav class="a6scsplash__nav animate-fade-rise">
				<?php
				$splash_logo_id  = (int) get_option( 'arshid6social_logo_desktop', 0 );
				$splash_logo_url = $splash_logo_id ? wp_get_attachment_image_url( $splash_logo_id, 'full' ) : '';
				if ( ! $splash_logo_url && has_custom_logo() ) {
					$splash_logo_url = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
				}
				?>
				<a class="a6scsplash__nav-logo" href="<?php echo esc_url( $home_url ); ?>">
					<?php if ( $splash_logo_url ) : ?>
						<img src="<?php echo esc_url( $splash_logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" class="a6scsplash__nav-logo-img">
					<?php else : ?>
						<?php echo esc_html( $site_name ); ?>
					<?php endif; ?>
				</a>
			</nav>

			<div class="a6scsplash__hero a6scsplash__hero--auth">
				<div class="a6scsplash__card a6scsplash-glass animate-fade-rise">
					<h1 class="a6scsplash__card-title"><?php echo esc_html( $title ); ?></h1>
					<?php if ( $subtitle ) : ?>
						<p class="a6scsplash__card-sub"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
		<?php
	}

	/**
	 * Outputs the closing HTML for the cinematic splash wrapper.
	 */
	private function splash_wrap_end(): void {
		?>
				</div><!-- /.a6scsplash__card -->
			</div><!-- /.a6scsplash__hero -->
		</div><!-- /.a6scsplash -->
		<?php
	}

	// -------------------------------------------------------------------------
	// [arshid6social_login_form redirect=""]
	// -------------------------------------------------------------------------

	/**
	 * Renders a styled login form inside the cinematic splash layout.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function login_form( $atts ): string {
		if ( is_user_logged_in() ) {
			return '';
		}

		$atts = shortcode_atts(
			array( 'redirect' => '' ),
			$atts,
			'arshid6social_login_form'
		);

		$redirect = esc_url_raw( $atts['redirect'] ?: home_url( '/members/' ) );

		$register_id  = (int) get_option( 'arshid6social_page_register', 0 );
		$register_url = $register_id ? get_permalink( $register_id ) : '';
		$forgot_id    = (int) get_option( 'arshid6social_page_forgot_password', 0 );
		$forgot_url   = $forgot_id ? get_permalink( $forgot_id ) : wp_lostpassword_url();

		ob_start();
		$this->splash_wrap_start( __( 'Sign in', '6arshid-social-community-main' ) );
		?>
		<?php
		wp_login_form( array(
			'redirect'       => $redirect,
			'label_username' => __( 'Username or Email', '6arshid-social-community-main' ),
			'label_password' => __( 'Password', '6arshid-social-community-main' ),
			'label_remember' => __( 'Remember Me', '6arshid-social-community-main' ),
			'label_log_in'   => __( 'Log In', '6arshid-social-community-main' ),
			'id_username'    => 'arshid6social-login-username',
			'id_password'    => 'arshid6social-login-password',
			'id_remember'    => 'arshid6social-login-remember',
			'id_submit'      => 'arshid6social-login-submit',
			'form_id'        => 'arshid6social-login-form',
		) );
		?>
		<p class="a6scsplash__forgot">
			<a href="<?php echo esc_url( $forgot_url ); ?>"><?php esc_html_e( 'Forgot password?', '6arshid-social-community-main' ); ?></a>
		</p>
		<?php if ( $register_url ) : ?>
			<div class="a6scsplash__register-row">
				<span><?php esc_html_e( "Don't have an account?", '6arshid-social-community-main' ); ?></span>
				<a class="a6scsplash__register-btn a6scsplash-glass" href="<?php echo esc_url( $register_url ); ?>">
					<?php esc_html_e( 'Create account', '6arshid-social-community-main' ); ?>
				</a>
			</div>
		<?php endif; ?>
		<?php
		$this->splash_wrap_end();
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [arshid6social_register_form]
	// -------------------------------------------------------------------------

	/**
	 * Renders a registration form when user registration is enabled.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function register_form( $atts ): string {
		if ( is_user_logged_in() ) {
			return '';
		}

		if ( ! get_option( 'arshid6social_allow_registration', true ) ) {
			return '<p class="arshid6social-notice arshid6social-notice-info">' . esc_html__( 'Registration is currently closed.', '6arshid-social-community-main' ) . '</p>';
		}

		if ( ! get_option( 'users_can_register' ) ) {
			return '<p class="arshid6social-notice arshid6social-notice-info">' . esc_html__( 'Registration is currently closed.', '6arshid-social-community-main' ) . '</p>';
		}

		$login_id  = (int) get_option( 'arshid6social_page_login', 0 );
		$login_url = $login_id ? get_permalink( $login_id ) : wp_login_url();

		$nonce    = wp_create_nonce( 'arshid6social_register' );
		$messages = array();

		// Process form submission.
		if ( isset( $_POST['arshid6social_register_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! check_ajax_referer( 'arshid6social_register', 'arshid6social_register_nonce', false ) ) {
				$messages[] = array( 'type' => 'error', 'text' => __( 'Security check failed.', '6arshid-social-community-main' ) );
			} else {
				$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
				$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
				$password = wp_unslash( $_POST['password'] ?? '' ); // Passwords are NOT sanitized.

				$errors = new \WP_Error();
				if ( ! $username ) {
					$errors->add( 'empty_username', __( 'Please enter a username.', '6arshid-social-community-main' ) );
				} else {
					$restriction_errors = \Arshid6Social\Components\Members\Members::validate_username_restrictions( $username );
					if ( $restriction_errors->has_errors() ) {
						foreach ( $restriction_errors->get_error_codes() as $code ) {
							$errors->add( $code, $restriction_errors->get_error_message( $code ) );
						}
					} elseif ( username_exists( $username ) ) {
						$errors->add( 'username_exists', __( 'That username is already taken.', '6arshid-social-community-main' ) );
					}
				}
				if ( ! $email ) {
					$errors->add( 'empty_email', __( 'Please enter your email address.', '6arshid-social-community-main' ) );
				} elseif ( email_exists( $email ) ) {
					$errors->add( 'email_exists', __( 'That email is already registered.', '6arshid-social-community-main' ) );
				}
				if ( strlen( $password ) < 8 ) {
					$errors->add( 'weak_password', __( 'Password must be at least 8 characters.', '6arshid-social-community-main' ) );
				}

				if ( ! $errors->has_errors() ) {
					$user_id = wp_create_user( $username, $password, $email );
					if ( is_wp_error( $user_id ) ) {
						$messages[] = array( 'type' => 'error', 'text' => $user_id->get_error_message() );
					} else {
						wp_set_current_user( $user_id );
						wp_set_auth_cookie( $user_id );
						do_action( 'arshid6social_user_registered', $user_id );
						wp_safe_redirect( home_url( '/members/' . get_userdata( $user_id )->user_nicename . '/' ) );
						exit;
					}
				} else {
					foreach ( $errors->get_error_messages() as $msg ) {
						$messages[] = array( 'type' => 'error', 'text' => $msg );
					}
				}
			}
		}

		ob_start();
		$this->splash_wrap_start( __( 'Create your account', '6arshid-social-community-main' ) );
		?>
		<?php foreach ( $messages as $msg ) : ?>
			<div class="arshid6social-notice arshid6social-notice-<?php echo esc_attr( $msg['type'] ); ?>">
				<?php echo esc_html( $msg['text'] ); ?>
			</div>
		<?php endforeach; ?>

		<form class="a6scsplash__inner-form" method="post" novalidate>
			<?php wp_nonce_field( 'arshid6social_register', 'arshid6social_register_nonce' ); ?>

			<div class="a6scsplash__field">
				<label for="arshid6social-reg-username"><?php esc_html_e( 'Username', '6arshid-social-community-main' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" id="arshid6social-reg-username" name="username"
					required autocomplete="username" maxlength="60"
					value="<?php echo isset( $_POST['username'] ) ? esc_attr( sanitize_user( wp_unslash( $_POST['username'] ) ) ) : ''; ?>" /> <?php // phpcs:ignore WordPress.Security.NonceVerification ?>
				<span id="arshid6social-username-feedback" class="a6scsplash__field-hint"></span>
			</div>

			<div class="a6scsplash__field">
				<label for="arshid6social-reg-email"><?php esc_html_e( 'Email address', '6arshid-social-community-main' ); ?> <span aria-hidden="true">*</span></label>
				<input type="email" id="arshid6social-reg-email" name="email"
					required autocomplete="email"
					value="<?php echo isset( $_POST['email'] ) ? esc_attr( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : ''; ?>" /> <?php // phpcs:ignore WordPress.Security.NonceVerification ?>
			</div>

			<div class="a6scsplash__field">
				<label for="arshid6social-reg-password"><?php esc_html_e( 'Password', '6arshid-social-community-main' ); ?> <span aria-hidden="true">*</span></label>
				<input type="password" id="arshid6social-reg-password" name="password"
					required autocomplete="new-password" minlength="8" />
			</div>

			<!-- Honeypot field; must remain empty. -->
			<div style="position:absolute;left:-9999px;" aria-hidden="true">
				<label for="arshid6social-hp"><?php esc_html_e( 'Leave this field empty', '6arshid-social-community-main' ); ?></label>
				<input type="text" id="arshid6social-hp" name="hp_field" tabindex="-1" autocomplete="off" />
			</div>

			<button type="submit" class="a6scsplash__submit-btn"><?php esc_html_e( 'Create Account', '6arshid-social-community-main' ); ?></button>

			<div class="a6scsplash__register-row" style="margin-top:1.25rem;">
				<span><?php esc_html_e( 'Already have an account?', '6arshid-social-community-main' ); ?></span>
				<a class="a6scsplash__register-btn a6scsplash-glass" href="<?php echo esc_url( $login_url ); ?>">
					<?php esc_html_e( 'Sign in', '6arshid-social-community-main' ); ?>
				</a>
			</div>
		</form>
		<?php
		$js_reg  = '(function(){';
		$js_reg .= 'var nonce=' . wp_json_encode( wp_create_nonce( 'arshid6social_check_username' ) ) . ';';
		$js_reg .= 'var ajaxUrl=' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';';
		$js_reg .= 'var minLen=' . (int) get_option( 'arshid6social_username_min_length', 4 ) . ';';
		$js_reg .= 'var txtChecking=' . wp_json_encode( __( 'Checking…', '6arshid-social-community-main' ) ) . ';';
		$js_reg .= <<<'ENDJS'
var input=document.getElementById('arshid6social-reg-username');
var feedback=document.getElementById('arshid6social-username-feedback');
var timer=null;
if(!input||!feedback)return;
input.addEventListener('input',function(){
	clearTimeout(timer);var val=input.value.trim();feedback.style.display='none';
	if(val.length<minLen)return;
	feedback.style.display='block';feedback.style.color='#555';feedback.textContent=txtChecking;
	timer=setTimeout(function(){
		var data=new FormData();data.append('action','arshid6social_check_username');data.append('nonce',nonce);data.append('username',val);
		fetch(ajaxUrl,{method:'POST',body:data})
			.then(function(r){return r.json();})
			.then(function(res){feedback.style.display='block';if(res.success){feedback.style.color='#15803d';feedback.textContent=res.data.message;}else{feedback.style.color='#b91c1c';feedback.textContent=res.data.message;}})
			.catch(function(){feedback.style.display='none';});
	},500);
});
})();
ENDJS;
		wp_add_inline_script( 'arshid6social-main', $js_reg );
		$this->splash_wrap_end();
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [arshid6social_dashboard]
	// -------------------------------------------------------------------------

	/**
	 * Dashboard placeholder — redirect is handled by template_redirect (see Plugin class).
	 * This shortcode outputs nothing; the redirect fires before rendering.
	 */
	public function dashboard( $atts ): string {
		return '';
	}

	// -------------------------------------------------------------------------
	// [arshid6social_marketplace]
	// -------------------------------------------------------------------------

	/**
	 * Renders the full Marketplace homepage: search bar, category grid, listings.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function marketplace( $atts ): string {
		if ( ! get_option( 'arshid6social_marketplace_enabled', false ) ) {
			return '';
		}

		global $arshid6social_is_page, $wpdb;
		$arshid6social_is_page = true;

		// Route sub-views via ?action= query param.
		$action = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'post' === $action || 'edit' === $action ) {
			return $this->listing_form( $atts );
		}

		if ( 'view' === $action ) {
			return $this->listing_detail();
		}

		// Guests: check access setting.
		$allow_guests = (bool) get_option( 'arshid6social_marketplace_allow_guests', true );
		if ( ! $allow_guests && ! is_user_logged_in() ) {
			return '<div class="arshid6social-notice arshid6social-notice-info">' .
				wp_kses(
					sprintf(
						/* translators: %s: login URL */
						__( 'Please <a href="%s">log in</a> to browse the Marketplace.', '6arshid-social-community-main' ),
						esc_url( wp_login_url( get_permalink() ) )
					),
					array( 'a' => array( 'href' => array() ) )
				) . '</div>';
		}

		// Load top-level categories.
		$categories = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}arshid6social_categories WHERE parent_id = 0 ORDER BY sort_order ASC, id ASC"
		) ?: array();

		return ARSHID6SOCIAL()->template()->get_template(
			'marketplace/main.php',
			array(
				'categories'   => $categories,
				'current_user' => wp_get_current_user(),
			),
			true
		);
	}

	// -------------------------------------------------------------------------
	// [arshid6social_listing_form]
	// -------------------------------------------------------------------------

	/**
	 * Renders the Create / Edit Listing wizard.
	 * Requires the user to be logged in.
	 */
	public function listing_form( $atts ): string {
		if ( ! get_option( 'arshid6social_marketplace_enabled', false ) ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			return '<div class="arshid6social-notice arshid6social-notice-info">' .
				wp_kses(
					sprintf(
						/* translators: %s: login URL */
						__( 'Please <a href="%s">log in</a> to post a listing.', '6arshid-social-community-main' ),
						esc_url( wp_login_url( get_permalink() ) )
					),
					array( 'a' => array( 'href' => array() ) )
				) . '</div>';
		}

		// Verified-only guard (soft check — AJAX will enforce too).
		if ( get_option( 'arshid6social_marketplace_require_verified', false ) ) {
			$verification = ARSHID6SOCIAL()->component( 'verification' );
			if ( $verification && ! $verification->is_verified( get_current_user_id() ) ) {
				return '<div class="arshid6social-notice arshid6social-notice-warning">' .
					esc_html__( 'Only verified users can post listings.', '6arshid-social-community-main' ) .
					'</div>';
			}
		}

		$categories = Components\Marketplace\Marketplace_Listings::get_category_select_options();
		$max_photos = (int) get_option( 'arshid6social_marketplace_max_photos', 10 );
		$max_mb     = (int) get_option( 'arshid6social_marketplace_max_photo_size_mb', 5 );

		return ARSHID6SOCIAL()->template()->get_template(
			'marketplace/listing-form.php',
			array(
				'categories' => $categories,
				'max_photos' => $max_photos,
				'max_mb'     => $max_mb,
			),
			true
		);
	}

	// -------------------------------------------------------------------------
	// Listing detail page (internal — routed from marketplace shortcode)
	// -------------------------------------------------------------------------

	/**
	 * Renders a single listing's detail page.
	 * Routed by marketplace() when ?action=view&id={uid}.
	 */
	private function listing_detail(): string {
		global $wpdb;

		$uid = sanitize_key( wp_unslash( $_GET['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $uid ) {
			return '<p class="arshid6social-notice">' . esc_html__( 'Listing not found.', '6arshid-social-community-main' ) . '</p>';
		}

		$current_user_id = get_current_user_id();

		// Look up by uid first; fall back to numeric id for old rows
		if ( ctype_digit( $uid ) ) {
			$listing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT * FROM {$wpdb->prefix}arshid6social_listings WHERE id = %d",
				(int) $uid
			) );
		} else {
			$listing = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT * FROM {$wpdb->prefix}arshid6social_listings WHERE uid = %s",
				$uid
			) );
		}

		if ( ! $listing ) {
			return '<p class="arshid6social-notice">' . esc_html__( 'Listing not found.', '6arshid-social-community-main' ) . '</p>';
		}

		// Status gate — guests/other users can only see active listings
		$is_owner = ( $current_user_id && (int) $listing->seller_id === $current_user_id );
		$is_admin = current_user_can( 'manage_options' );
		if ( ! in_array( $listing->status, array( 'active', 'sold' ), true ) && ! $is_owner && ! $is_admin ) {
			return '<p class="arshid6social-notice">' . esc_html__( 'This listing is not available.', '6arshid-social-community-main' ) . '</p>';
		}

		// Increment view counter (skip for owner)
		if ( ! $is_owner ) {
			$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"UPDATE {$wpdb->prefix}arshid6social_listings SET views = views + 1 WHERE id = %d",
				(int) $listing->id
			) );
		}

		$photos  = Components\Marketplace\Marketplace_Listings::get_photos( (int) $listing->id );
		$seller  = get_userdata( (int) $listing->seller_id );
		$cat     = $listing->category_id
			? $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT name, icon FROM {$wpdb->prefix}arshid6social_categories WHERE id = %d",
				$listing->category_id
			) )
			: null;

		$base_url = get_permalink( (int) get_option( 'arshid6social_page_marketplace', 0 ) )
			?: home_url( '/' . get_option( 'arshid6social_marketplace_slug', 'marketplace' ) . '/' );

		return ARSHID6SOCIAL()->template()->get_template(
			'marketplace/listing-single.php',
			array(
				'listing'         => $listing,
				'photos'          => $photos,
				'seller'          => $seller,
				'category'        => $cat,
				'is_owner'        => $is_owner,
				'is_admin'        => $is_admin,
				'current_user_id' => $current_user_id,
				'base_url'        => $base_url,
			),
			true
		);
	}

	// -------------------------------------------------------------------------
	// [arshid6social_my_listings]
	// -------------------------------------------------------------------------

	/** Renders the current user's listings dashboard. */
	public function my_listings( $atts ): string {
		if ( ! get_option( 'arshid6social_marketplace_enabled', false ) || ! is_user_logged_in() ) {
			return '';
		}
		// Implemented in Step 3.
		return '<p class="arshid6social-notice">' . esc_html__( 'My listings coming soon.', '6arshid-social-community-main' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// [arshid6social_saved_listings]
	// -------------------------------------------------------------------------

	/** Renders the current user's saved/favorited listings. */
	public function saved_listings( $atts ): string {
		if ( ! get_option( 'arshid6social_marketplace_enabled', false ) || ! is_user_logged_in() ) {
			return '';
		}
		// Implemented in Step 7.
		return '<p class="arshid6social-notice">' . esc_html__( 'Saved listings coming soon.', '6arshid-social-community-main' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// [arshid6social_seller_listings user_id="0"]
	// -------------------------------------------------------------------------

	/** Renders listings by a specific seller (for seller profile mini-page). */
	public function seller_listings( $atts ): string {
		if ( ! get_option( 'arshid6social_marketplace_enabled', false ) ) {
			return '';
		}
		$atts    = shortcode_atts( array( 'user_id' => 0 ), $atts, 'arshid6social_seller_listings' );
		$user_id = absint( $atts['user_id'] ) ?: get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		// Implemented in Step 8.
		return '<p class="arshid6social-notice">' . esc_html__( 'Seller listings coming soon.', '6arshid-social-community-main' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// [arshid6social_ads placement="sidebar" limit="0"]
	// -------------------------------------------------------------------------

	/**
	 * Renders active ads for the given placement context.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function ads( $atts ): string {
		$atts = shortcode_atts(
			array(
				'placement' => 'sidebar',
				'limit'     => 0,
			),
			$atts,
			'arshid6social_ads'
		);

		return Components\Ads\Ads::render_sidebar_ads( absint( $atts['limit'] ) );
	}

	// -------------------------------------------------------------------------
	// [arshid6social_forgot_password]
	// -------------------------------------------------------------------------

	/**
	 * Renders a custom forgot-password form (no wp-login.php dependency).
	 */
	public function forgot_password(): string {
		if ( is_user_logged_in() ) {
			return '';
		}

		$reset_id  = (int) get_option( 'arshid6social_page_reset_password', 0 );
		$reset_url = $reset_id ? get_permalink( $reset_id ) : home_url( '/reset-password/' );
		$login_id  = (int) get_option( 'arshid6social_page_login', 0 );
		$login_url = $login_id ? get_permalink( $login_id ) : wp_login_url();

		$message = '';
		$error   = '';

		if ( isset( $_POST['arshid6social_forgot_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! check_ajax_referer( 'arshid6social_forgot_password', 'arshid6social_forgot_nonce', false ) ) {
				$error = __( 'Security check failed.', '6arshid-social-community-main' );
			} else {
				$user_login = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );

				if ( empty( $user_login ) ) {
					$error = __( 'Please enter your email or username.', '6arshid-social-community-main' );
				} else {
					$user = strpos( $user_login, '@' ) !== false
						? get_user_by( 'email', $user_login )
						: get_user_by( 'login', $user_login );

					if ( ! $user || ! $user->exists() ) {
						$message = __( 'If an account with that information exists, a reset link has been sent.', '6arshid-social-community-main' );
					} else {
						$key = get_password_reset_key( $user );

						if ( is_wp_error( $key ) ) {
							$error = __( 'Could not generate reset link. Please try again.', '6arshid-social-community-main' );
						} else {
							$url = add_query_arg(
								array(
									'key'   => rawurlencode( $key ),
									'login' => rawurlencode( $user->user_login ),
								),
								$reset_url
							);

							$subject = sprintf(
								/* translators: %s: site name */
								__( '[%s] Password Reset', '6arshid-social-community-main' ),
								get_bloginfo( 'name' )
							);

							$body = sprintf(
								/* translators: 1: user email, 2: reset link URL */
								__( "Someone requested a password reset for the account with email %1\$s.\n\nIf this was a mistake, ignore this email.\n\nTo reset your password, visit:\n\n%2\$s\n\nThis link expires in 24 hours.", '6arshid-social-community-main' ),
								$user->user_email,
								$url
							);

							wp_mail( $user->user_email, $subject, $body );
							$message = __( 'If an account with that information exists, a reset link has been sent.', '6arshid-social-community-main' );
						}
					}
				}
			}
		}

		$saved_login = isset( $_POST['user_login'] )
			? esc_attr( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) )
			: '';

		ob_start();
		$this->splash_wrap_start(
			__( 'Forgot password?', '6arshid-social-community-main' ),
			__( 'Enter your email or username and we\'ll send you a reset link.', '6arshid-social-community-main' )
		);
		?>
		<?php if ( $message ) : ?>
			<div class="arshid6social-notice arshid6social-notice-success"><?php echo esc_html( $message ); ?></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="arshid6social-notice arshid6social-notice-error"><?php echo esc_html( $error ); ?></div>
		<?php endif; ?>

		<?php if ( ! $message ) : ?>
		<form id="arshid6social-forgot-form" class="a6scsplash__inner-form" method="post">
			<?php wp_nonce_field( 'arshid6social_forgot_password', 'arshid6social_forgot_nonce' ); ?>
			<div class="a6scsplash__field">
				<label for="arshid6social-forgot-login"><?php esc_html_e( 'Email or Username', '6arshid-social-community-main' ); ?></label>
				<input type="text" id="arshid6social-forgot-login" name="user_login"
					autocomplete="username"
					value="<?php echo $saved_login; ?>"> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<button type="submit" class="a6scsplash__submit-btn"><?php esc_html_e( 'Send Reset Link', '6arshid-social-community-main' ); ?></button>
		</form>
		<?php endif; ?>

		<p class="a6scsplash__forgot" style="text-align:center;margin-top:1rem;">
			<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( '← Back to Sign in', '6arshid-social-community-main' ); ?></a>
		</p>
		<?php
		$this->splash_wrap_end();
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [arshid6social_reset_password]
	// -------------------------------------------------------------------------

	/**
	 * Renders a custom reset-password form (no wp-login.php dependency).
	 * Reads ?key=...&login=... from the URL (set by the forgot-password email).
	 */
	public function reset_password(): string {
		if ( is_user_logged_in() ) {
			return '';
		}

		$login_id   = (int) get_option( 'arshid6social_page_login', 0 );
		$login_url  = $login_id ? get_permalink( $login_id ) : wp_login_url();
		$forgot_id  = (int) get_option( 'arshid6social_page_forgot_password', 0 );
		$forgot_url = $forgot_id ? get_permalink( $forgot_id ) : '';

		// Key and login come from GET (initial load) or POST hidden fields (form submit).
		$key   = isset( $_POST['reset_key'] )   ? sanitize_text_field( wp_unslash( $_POST['reset_key'] ) )
			   : ( isset( $_GET['key'] )         ? sanitize_text_field( wp_unslash( $_GET['key'] ) )   : '' );
		$login = isset( $_POST['reset_login'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_login'] ) )
			   : ( isset( $_GET['login'] )       ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '' );

		$error   = '';
		$message = '';
		$user    = null;

		if ( $key && $login ) {
			$user = check_password_reset_key( $key, $login );
			if ( is_wp_error( $user ) ) {
				$error = __( 'This password reset link is invalid or has expired. Please request a new one.', '6arshid-social-community-main' );
				$user  = null;
			}
		} else {
			$error = __( 'Invalid password reset link.', '6arshid-social-community-main' );
		}

		if ( $user && isset( $_POST['arshid6social_reset_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! check_ajax_referer( 'arshid6social_reset_password', 'arshid6social_reset_nonce', false ) ) {
				$error = __( 'Security check failed.', '6arshid-social-community-main' );
			} else {
				$pass1 = wp_unslash( $_POST['password1'] ?? '' );
				$pass2 = wp_unslash( $_POST['password2'] ?? '' );

				if ( strlen( $pass1 ) < 8 ) {
					$error = __( 'Password must be at least 8 characters.', '6arshid-social-community-main' );
				} elseif ( $pass1 !== $pass2 ) {
					$error = __( 'Passwords do not match.', '6arshid-social-community-main' );
				} else {
					reset_password( $user, $pass1 );
					$message = __( 'Your password has been reset. You can now log in.', '6arshid-social-community-main' );
					$user    = null;
				}
			}
		}

		ob_start();
		$this->splash_wrap_start( __( 'Set new password', '6arshid-social-community-main' ) );
		?>
		<?php if ( $message ) : ?>
			<div class="arshid6social-notice arshid6social-notice-success"><?php echo esc_html( $message ); ?></div>
			<p class="a6scsplash__forgot" style="text-align:center;margin-top:1rem;">
				<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in →', '6arshid-social-community-main' ); ?></a>
			</p>
		<?php elseif ( $error && ! $user ) : ?>
			<div class="arshid6social-notice arshid6social-notice-error"><?php echo esc_html( $error ); ?></div>
			<?php if ( $forgot_url ) : ?>
				<p class="a6scsplash__forgot" style="text-align:center;margin-top:1rem;">
					<a href="<?php echo esc_url( $forgot_url ); ?>"><?php esc_html_e( 'Request a new reset link', '6arshid-social-community-main' ); ?></a>
				</p>
			<?php endif; ?>
		<?php elseif ( $user ) : ?>
			<?php if ( $error ) : ?>
				<div class="arshid6social-notice arshid6social-notice-error"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>
			<form id="arshid6social-reset-form" class="a6scsplash__inner-form" method="post">
				<?php wp_nonce_field( 'arshid6social_reset_password', 'arshid6social_reset_nonce' ); ?>
				<input type="hidden" name="reset_key"   value="<?php echo esc_attr( $key ); ?>">
				<input type="hidden" name="reset_login" value="<?php echo esc_attr( $login ); ?>">

				<div class="a6scsplash__field">
					<label for="arshid6social-reset-pass1"><?php esc_html_e( 'New Password', '6arshid-social-community-main' ); ?></label>
					<input type="password" id="arshid6social-reset-pass1" name="password1"
						autocomplete="new-password" minlength="8">
				</div>

				<div class="a6scsplash__field">
					<label for="arshid6social-reset-pass2"><?php esc_html_e( 'Confirm Password', '6arshid-social-community-main' ); ?></label>
					<input type="password" id="arshid6social-reset-pass2" name="password2"
						autocomplete="new-password" minlength="8">
				</div>

				<button type="submit" class="a6scsplash__submit-btn"><?php esc_html_e( 'Set New Password', '6arshid-social-community-main' ); ?></button>
			</form>
		<?php endif; ?>
		<?php
		$this->splash_wrap_end();
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [arshid6social_home]
	// -------------------------------------------------------------------------

	/**
	 * Renders the Social-style social network homepage splash: fullscreen looping video background,
	 * glassmorphic login card, and Instrument Serif typography.
	 * Logged-in users are redirected to the activity feed by Plugin::handle_auth_page_redirects().
	 */
	public function home( $atts ): string {
		if ( is_user_logged_in() ) {
			return '';
		}

		$register_id  = (int) get_option( 'arshid6social_page_register', 0 );
		$register_url = $register_id ? get_permalink( $register_id ) : '';
		$forgot_id    = (int) get_option( 'arshid6social_page_forgot_password', 0 );
		$forgot_url   = $forgot_id ? get_permalink( $forgot_id ) : wp_lostpassword_url();
		$redirect     = home_url( '/activity/' );
		$site_name    = get_bloginfo( 'name' );
		$home_logo_id  = (int) get_option( 'arshid6social_logo_desktop', 0 );
		$home_logo_url = $home_logo_id ? wp_get_attachment_image_url( $home_logo_id, 'full' ) : '';
		if ( ! $home_logo_url && has_custom_logo() ) {
			$home_logo_url = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
		}

		$bg_url  = $this->splash_video_url();
		$bg_type = $this->splash_bg_type();

		ob_start();
		?>
		<?php $this->enqueue_splash_fonts(); ?>
		<div class="a6scsplash">

			<!-- Fullscreen background (video or image) -->
			<?php if ( $bg_type === 'image' ) : ?>
			<img class="a6scsplash__video" src="<?php echo esc_url( $bg_url ); ?>" alt="" aria-hidden="true">
			<?php else : ?>
			<video class="a6scsplash__video" autoplay loop muted playsinline>
				<source src="<?php echo esc_url( $bg_url ); ?>" type="video/mp4">
			</video>
			<?php endif; ?>

			<!-- Dark overlay for readability -->
			<div class="a6scsplash__overlay" aria-hidden="true"></div>

			<!-- Glassmorphic nav bar -->
			<nav class="a6scsplash__nav animate-fade-rise">
				<span class="a6scsplash__nav-logo">
					<?php if ( $home_logo_url ) : ?>
						<img src="<?php echo esc_url( $home_logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" class="a6scsplash__nav-logo-img">
					<?php else : ?>
						<?php echo esc_html( $site_name ); ?>
					<?php endif; ?>
				</span>
				<a class="a6scsplash__nav-btn a6scsplash-glass" href="<?php echo esc_url( $register_url ?: '#' ); ?>">
					<?php esc_html_e( 'Create account', '6arshid-social-community-main' ); ?>
				</a>
			</nav>

			<!-- Centered hero content -->
			<div class="a6scsplash__hero">

				<h1 class="a6scsplash__headline animate-fade-rise">
					<?php esc_html_e( 'Where ', '6arshid-social-community-main' ); ?>
					<em><?php esc_html_e( 'your community', '6arshid-social-community-main' ); ?></em>
					<?php esc_html_e( ' comes alive.', '6arshid-social-community-main' ); ?>
				</h1>

				<p class="a6scsplash__sub animate-fade-rise-delay">
					<?php esc_html_e( 'Connect, share, and grow with people who matter.', '6arshid-social-community-main' ); ?>
				</p>

				<!-- Login card -->
				<div class="a6scsplash__card a6scsplash-glass animate-fade-rise-delay-2">
					<?php
					wp_login_form( array(
						'redirect'       => $redirect,
						'label_username' => __( 'Username or Email', '6arshid-social-community-main' ),
						'label_password' => __( 'Password', '6arshid-social-community-main' ),
						'label_remember' => __( 'Remember Me', '6arshid-social-community-main' ),
						'label_log_in'   => __( 'Log In', '6arshid-social-community-main' ),
						'id_username'    => 'arshid6social-home-username',
						'id_password'    => 'arshid6social-home-password',
						'id_remember'    => 'arshid6social-home-remember',
						'id_submit'      => 'arshid6social-home-submit',
						'form_id'        => 'arshid6social-home-form',
					) );
					?>
					<p class="a6scsplash__forgot">
						<a href="<?php echo esc_url( $forgot_url ); ?>"><?php esc_html_e( 'Forgot password?', '6arshid-social-community-main' ); ?></a>
					</p>
					<?php if ( $register_url ) : ?>
						<div class="a6scsplash__register-row">
							<span><?php esc_html_e( "Don't have an account?", '6arshid-social-community-main' ); ?></span>
							<a class="a6scsplash__register-btn a6scsplash-glass" href="<?php echo esc_url( $register_url ); ?>">
								<?php esc_html_e( 'Create account', '6arshid-social-community-main' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
