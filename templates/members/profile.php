<?php
/**
 * Member profile template.
 *
 * Variables injected by Template_Loader:
 *   $profile_user  \WP_User  The member whose profile is being viewed.
 *   $component     Members   The Members component instance.
 *   $active_tab    string    Active tab key.
 *
 * Theme can override by placing this file at:
 *   {theme}/social-network/members/profile.php
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$xprofile   = $component->xprofile;
$avatar     = $component->avatar;
$cover_url  = $avatar->get_cover_url( $profile_user->ID );
$avatar_url = $avatar->get_avatar_url( $profile_user->ID, 120 );
$is_self    = is_user_logged_in() && get_current_user_id() === $profile_user->ID;

$_profile_suspended = (bool) get_user_meta( $profile_user->ID, 'arshid6social_suspended', true );
$_viewer_is_admin   = current_user_can( 'arshid6social_manage_reports' );

// Block access: blocked users and suspended profiles are restricted.
if ( ! $is_self && is_user_logged_in() ) {
	$_friends_comp = ARSHID6SOCIAL()->component( 'friends' );
	if ( $_friends_comp && $_friends_comp->is_blocked( get_current_user_id(), $profile_user->ID ) ) {
		echo '<div class="arshid6social-wrap"><div class="arshid6social-container" style="padding-block:4rem;text-align:center;">';
		echo '<p>' . esc_html__( 'This profile is not available.', 'social-network-6' ) . '</p>';
		echo '</div></div>';
		return;
	}
}

// Suspended profile: show notice to everyone (admins get an unsuspend button).
if ( $_profile_suspended ) {
	$_suspend_reason = get_user_meta( $profile_user->ID, 'arshid6social_suspended_reason', true );
	echo '<div class="arshid6social-wrap"><div class="arshid6social-container" style="padding-block:4rem;">';
	echo '<div class="arshid6social-suspended-notice">';
	echo '<div class="arshid6social-suspended-notice__icon">&#128683;</div>';
	if ( $is_self ) {
		echo '<h2 class="arshid6social-suspended-notice__title">' . esc_html__( 'Your Account is Suspended', 'social-network-6' ) . '</h2>';
		echo '<p class="arshid6social-suspended-notice__msg">' . esc_html__( 'Your account has been suspended. Please contact an administrator for more information.', 'social-network-6' ) . '</p>';
		if ( $_suspend_reason && 'auto_threshold' !== $_suspend_reason ) {
			echo '<p class="arshid6social-suspended-notice__msg" style="font-weight:600;">' . esc_html( $_suspend_reason ) . '</p>';
		}
	} else {
		echo '<h2 class="arshid6social-suspended-notice__title">' . esc_html__( 'Account Suspended', 'social-network-6' ) . '</h2>';
		echo '<p class="arshid6social-suspended-notice__msg">' . esc_html__( 'This account has been suspended and its content is not available.', 'social-network-6' ) . '</p>';
	}
	echo '<div class="arshid6social-suspended-notice__actions">';
	echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="arshid6social-btn arshid6social-btn-primary">' . esc_html__( 'Back to Home', 'social-network-6' ) . '</a>';
	if ( $_viewer_is_admin ) {
		$_unsuspend_url = add_query_arg(
			array(
				'page'          => 'arshid6social-members',
				'arshid6social_action'   => 'unsuspend',
				'user_id'       => $profile_user->ID,
				'_wpnonce'      => wp_create_nonce( 'arshid6social_unsuspend_' . $profile_user->ID ),
			),
			admin_url( 'admin.php' )
		);
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=arshid6social-members' ) ) . '" class="arshid6social-btn arshid6social-btn-secondary">' . esc_html__( 'Manage in Admin', 'social-network-6' ) . '</a>';
	}
	echo '</div>';
	echo '</div>';
	echo '</div></div>';
	return;
}

$tabs = apply_filters(
	'arshid6social_profile_tabs',
	array(
		'activity'    => __( 'Activity', 'social-network-6' ),
		'friends'     => __( 'Friends', 'social-network-6' ),
		'groups'      => __( 'Groups', 'social-network-6' ),
		'about'       => __( 'About', 'social-network-6' ),
	),
	$profile_user
);

if ( $is_self ) {
	$_notif_comp  = ARSHID6SOCIAL()->component( 'notifications' );
	$unread_count = $_notif_comp ? $_notif_comp->get_unread_count( get_current_user_id() ) : 0;
	$notif_label  = __( 'Notifications', 'social-network-6' );
	if ( $unread_count ) {
		$notif_label .= ' <span class="arshid6social-badge arshid6social-badge--primary arshid6social-badge--sm">' . esc_html( $unread_count ) . '</span>';
	}
	$tabs['notifications'] = $notif_label;
	$tabs['settings']      = __( 'Settings', 'social-network-6' );
}

$profile_url = home_url( '/members/' . $profile_user->user_nicename . '/' );
?>
<div class="arshid6social-wrap" id="arshid6social-profile-page">
	<div class="arshid6social-container" style="padding-block: 2rem;">

		<?php do_action( 'arshid6social_before_profile', $profile_user ); ?>

		<div class="arshid6social-card">
			<!-- Cover photo -->
			<div class="arshid6social-profile-cover"
				<?php if ( $cover_url ) : ?>
					style="background-image:url('<?php echo esc_url( $cover_url ); ?>');"
				<?php endif; ?>
				role="img"
				aria-label="<?php
					printf(
						/* translators: %s: member name */
						esc_attr__( '%s cover photo', 'social-network-6' ),
						esc_attr( $profile_user->display_name )
					);
				?>"
			>
				<?php if ( $is_self ) : ?>
					<button class="arshid6social-cover-edit-btn"
						onclick="document.getElementById('arshid6social-cover-input').click()"
						title="<?php esc_attr_e( 'Edit Cover', 'social-network-6' ); ?>"
						aria-label="<?php esc_attr_e( 'Edit Cover', 'social-network-6' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
					</button>
					<form id="arshid6social-cover-form" hidden
						action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="arshid6social_upload_cover" />
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'arshid6social_upload_cover' ) ); ?>" />
						<input type="file" id="arshid6social-cover-input" name="cover" accept="image/*" style="display:none" />
					</form>
				<?php endif; ?>
			</div>

			<!-- Profile header -->
			<div class="arshid6social-profile-header">
				<div class="arshid6social-avatar-wrap">
					<img id="arshid6social-avatar-preview"
						class="arshid6social-avatar arshid6social-avatar--xl"
						src="<?php echo esc_url( $avatar_url ); ?>"
						alt="<?php echo esc_attr( $profile_user->display_name ); ?>"
						width="120" height="120"
					/>
					<?php if ( $component->is_user_online( $profile_user->ID ) ) : ?>
						<span class="arshid6social-online-badge" aria-label="<?php esc_attr_e( 'Online', 'social-network-6' ); ?>"></span>
					<?php endif; ?>
				</div>

				<div class="arshid6social-profile-header__info">
					<h1 class="arshid6social-profile-name">
						<?php echo esc_html( $profile_user->display_name ); ?>
						<?php if ( $_profile_suspended ) : ?>
							<span class="arshid6social-badge arshid6social-badge--suspended arshid6social-badge--sm" style="vertical-align:middle;font-size:.65rem;">
								<?php esc_html_e( 'Suspended', 'social-network-6' ); ?>
							</span>
						<?php endif; ?>
						<?php
						$_verif = arshid6social_verification();
						if ( $_verif && $_verif->is_verified( $profile_user->ID ) ) :
							echo wp_kses_post( $_verif->get_badge_html( $profile_user->ID ) );
						elseif ( ! $_verif && get_user_meta( $profile_user->ID, 'arshid6social_verified', true ) ) :
					?>
							<span class="arshid6social-verified-badge" title="<?php esc_attr_e( 'Verified Member', 'social-network-6' ); ?>" aria-label="<?php esc_attr_e( 'Verified', 'social-network-6' ); ?>">&#10003;</span>
						<?php endif; ?>
					</h1>
					<div class="arshid6social-profile-stats">
						<div>
							<strong><?php echo esc_html( number_format_i18n( $component->get_friend_count( $profile_user->ID ) ) ); ?></strong>
							<?php esc_html_e( 'Friends', 'social-network-6' ); ?>
						</div>
					</div>
				</div>

				<!-- Action buttons -->
				<div class="arshid6social-profile-actions">
					<?php if ( $is_self ) : ?>
						<a href="<?php echo esc_url( $profile_url . 'settings/' ); ?>" class="arshid6social-btn arshid6social-btn--secondary">
							<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
							<?php esc_html_e( 'Edit Profile', 'social-network-6' ); ?>
						</a>
						<form id="arshid6social-avatar-form" hidden enctype="multipart/form-data"
							action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post">
							<input type="hidden" name="action" value="arshid6social_upload_avatar" />
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'arshid6social_upload_avatar' ) ); ?>" />
							<input type="file" id="arshid6social-avatar-input" name="avatar" accept="image/*" style="display:none" />
						</form>
						<button class="arshid6social-btn arshid6social-btn--secondary" onclick="document.getElementById('arshid6social-avatar-input').click()">
							<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
							<?php esc_html_e( 'Change Photo', 'social-network-6' ); ?>
						</button>
					<?php elseif ( is_user_logged_in() ) :
						$friends_comp   = ARSHID6SOCIAL()->component( 'friends' );
						$friend_status  = $friends_comp
							? $friends_comp->get_friendship_status( get_current_user_id(), $profile_user->ID )
							: 'not_friends';
						$friend_labels  = array(
							'not_friends'      => __( 'Add Friend', 'social-network-6' ),
							'pending_sent'     => __( 'Cancel Request', 'social-network-6' ),
							'pending_received' => __( 'Accept Request', 'social-network-6' ),
							'friends'          => __( 'Friends ✓', 'social-network-6' ),
						);
					?>
						<button class="arshid6social-btn arshid6social-btn--primary arshid6social-friend-btn"
							data-user-id="<?php echo esc_attr( $profile_user->ID ); ?>"
							data-status="<?php echo esc_attr( $friend_status ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
							<?php echo esc_html( $friend_labels[ $friend_status ] ?? __( 'Add Friend', 'social-network-6' ) ); ?>
						</button>
						<a href="<?php echo esc_url( home_url( '/messages/compose/?to=' . \Arshid6Social\Components\Messages\Messages::get_user_uid( $profile_user->ID ) ) ); ?>"
							class="arshid6social-btn arshid6social-btn--secondary">
							<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
							<?php esc_html_e( 'Send Message', 'social-network-6' ); ?>
						</a>
						<?php if ( arshid6social_blocking() ) :
							global $wpdb;
							$current_blocked = (bool) $wpdb->get_var( $wpdb->prepare(
								"SELECT id FROM {$wpdb->prefix}sn_blocks WHERE blocker_id = %d AND blocked_id = %d",
								get_current_user_id(), $profile_user->ID
							) );
						?>
						<?php if ( $current_blocked ) : ?>
							<button class="arshid6social-btn arshid6social-btn--danger sn--blocked"
								data-unblock-user-id="<?php echo esc_attr( $profile_user->ID ); ?>">
								<?php esc_html_e( 'Blocked', 'social-network-6' ); ?>
							</button>
						<?php else : ?>
							<button class="arshid6social-btn arshid6social-btn--secondary"
								data-block-user-id="<?php echo esc_attr( $profile_user->ID ); ?>">
								<?php esc_html_e( 'Block', 'social-network-6' ); ?>
							</button>
						<?php endif; ?>
						<?php endif; ?>
						<span class="arshid6social-profile-actions__sep" aria-hidden="true"></span>
						<button class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-report-btn arshid6social-profile-report-btn"
							data-item-id="<?php echo esc_attr( $profile_user->ID ); ?>"
							data-item-type="profile"
							title="<?php esc_attr_e( 'Report this profile', 'social-network-6' ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
							<?php esc_html_e( 'Report', 'social-network-6' ); ?>
						</button>
					<?php else : ?>
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="arshid6social-btn arshid6social-btn--primary">
							<?php esc_html_e( 'Connect', 'social-network-6' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Tabs -->
			<nav class="arshid6social-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Profile sections', 'social-network-6' ); ?>">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a class="arshid6social-tab-link <?php echo ( $active_tab === $tab_key ) ? 'is-active' : ''; ?>"
						href="<?php echo esc_url( $profile_url . $tab_key . '/' ); ?>"
						role="tab"
						<?php echo ( $active_tab === $tab_key ) ? 'aria-selected="true"' : 'aria-selected="false"'; ?>>
						<?php echo wp_kses( $tab_label, array( 'span' => array( 'class' => array() ) ) ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>

		<!-- Tab content -->
		<?php if ( 'settings' === $active_tab ) : ?>

		<div class="arshid6social-settings-page-wrap" style="margin-block-start:1.5rem;">
			<?php if ( $is_self ) : ?>
				<?php
				\Arshid6Social\Template_Loader::instance()->get_template(
					'members/settings.php',
					array( 'profile_user' => $profile_user )
				);
				?>
			<?php endif; ?>
		</div>

		<?php else : ?>

		<div class="arshid6social-profile-content" style="margin-block-start:1.5rem;">

			<!-- About compact (always visible) -->
			<?php $about_bio = $xprofile->get_field_value( $profile_user->ID, 'bio' ); ?>
			<div class="arshid6social-card arshid6social-card--about" id="arshid6social-about-card">
				<div class="arshid6social-about-row">
					<span class="arshid6social-about-label"><?php esc_html_e( 'About', 'social-network-6' ); ?></span>
					<span class="arshid6social-about-bio" id="arshid6social-bio-display">
						<?php if ( $about_bio ) : ?>
							<?php echo wp_kses_post( $about_bio ); ?>
						<?php else : ?>
							<span class="arshid6social-text-muted"><?php esc_html_e( 'No bio yet.', 'social-network-6' ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( $is_self ) : ?>
						<button type="button" class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-about-edit-btn" id="arshid6social-bio-edit-btn" aria-label="<?php esc_attr_e( 'Edit bio', 'social-network-6' ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
						</button>
					<?php endif; ?>
				</div>
				<?php if ( $is_self ) : ?>
				<div id="arshid6social-bio-edit-form" hidden style="padding:.65rem 1rem; border-top:1px solid var(--arshid6social-border);">
					<textarea id="arshid6social-bio-textarea" class="arshid6social-textarea" rows="3" style="width:100%;box-sizing:border-box;resize:vertical;"><?php echo esc_textarea( $about_bio ); ?></textarea>
					<div style="display:flex;gap:.5rem;margin-block-start:.5rem;">
						<button type="button" class="arshid6social-btn arshid6social-btn--primary arshid6social-btn--sm" id="arshid6social-bio-save-btn"><?php esc_html_e( 'Save', 'social-network-6' ); ?></button>
						<button type="button" class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm" id="arshid6social-bio-cancel-btn"><?php esc_html_e( 'Cancel', 'social-network-6' ); ?></button>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<!-- Stories compact (activity tab only) -->
			<?php if ( 'activity' === $active_tab && get_option( 'arshid6social_stories_enabled', false ) ) :
				$_stories_comp = ARSHID6SOCIAL()->component( 'stories' );
				if ( $_stories_comp ) :
					$_viewer_id = get_current_user_id();
					$_profile_stories = array_values( array_filter(
						$_stories_comp->get_tray( $_viewer_id ),
						fn( $s ) => (int) $s->user_id === $profile_user->ID
					) );
					if ( $is_self || ! empty( $_profile_stories ) ) :
			?>
			<div class="arshid6social-card arshid6social-sidebar-stories">
				<div class="arshid6social-sidebar-stories__header">
					<span><?php esc_html_e( 'Stories', 'social-network-6' ); ?></span>
				</div>
				<div class="arshid6social-sidebar-stories__body">
					<?php
					\Arshid6Social\Template_Loader::instance()->get_template(
						'stories/tray.php',
						array(
							'stories'     => $_profile_stories,
							'viewer_id'   => $_viewer_id,
							'stories_obj' => $_stories_comp,
						)
					);
					defined( 'ARSHID6SOCIAL_STORIES_TRAY_INLINE' ) || define( 'ARSHID6SOCIAL_STORIES_TRAY_INLINE', true );
					?>
				</div>
			</div>
			<?php
					endif;
				endif;
			endif;
			?>

			<!-- Tab content (full width, single column) -->
			<?php
			switch ( $active_tab ) {
				case 'activity':
					?>
					<div id="arshid6social-activity-feed"
						data-user-id="<?php echo esc_attr( $profile_user->ID ); ?>"
						data-scope="personal"
						role="feed"
						aria-label="<?php esc_attr_e( 'Activity feed', 'social-network-6' ); ?>">
						<div class="arshid6social-skeleton" style="height:80px;margin-bottom:12px;"></div>
						<div class="arshid6social-skeleton" style="height:80px;margin-bottom:12px;"></div>
					</div>
					<div id="arshid6social-activity-sentinel" style="height:1px;"></div>
					<?php
					break;

				case 'friends':
					?>
					<div id="arshid6social-friends-tab"
						data-user-id="<?php echo esc_attr( $profile_user->ID ); ?>"
						data-is-self="<?php echo esc_attr( $is_self ? '1' : '0' ); ?>">
						<div id="arshid6social-friends-grid" class="arshid6social-members-grid">
							<div class="arshid6social-skeleton" style="height:160px;border-radius:12px;"></div>
							<div class="arshid6social-skeleton" style="height:160px;border-radius:12px;"></div>
							<div class="arshid6social-skeleton" style="height:160px;border-radius:12px;"></div>
						</div>
						<div id="arshid6social-friends-load-more-wrap" hidden style="text-align:center;margin-top:1rem;">
							<button id="arshid6social-friends-load-more" class="arshid6social-btn arshid6social-btn--secondary">
								<?php esc_html_e( 'Load more', 'social-network-6' ); ?>
							</button>
						</div>
					</div>
					<?php
					break;

				case 'about':
					$groups = $xprofile->get_groups();
					foreach ( $groups as $group ) :
						if ( empty( $group['fields'] ) ) continue;
						?>
						<div class="arshid6social-card" style="margin-block-end:1rem;">
							<div class="arshid6social-card__header"><?php echo esc_html( $group['name'] ); ?></div>
							<div class="arshid6social-card__body">
								<dl style="display:grid;gap:.75rem;">
									<?php foreach ( $group['fields'] as $field ) :
										$value = $xprofile->get_field_value( $profile_user->ID, (int) $field['id'] );
										if ( ! $value ) continue;
										?>
										<div>
											<dt style="font-weight:600;font-size:.875rem;"><?php echo esc_html( $field['name'] ); ?></dt>
											<dd style="margin:0;color:var(--arshid6social-text-muted);"><?php echo wp_kses_post( $value ); ?></dd>
										</div>
									<?php endforeach; ?>
								</dl>
							</div>
						</div>
					<?php endforeach;
					break;

				case 'notifications':
					if ( $is_self ) {
						\Arshid6Social\Template_Loader::instance()->get_template(
							'members/notifications.php',
							array( 'profile_user' => $profile_user )
						);
					}
					break;

				default:
					do_action( 'arshid6social_profile_tab_content', $active_tab, $profile_user, $component );
					break;
			}
			?>
		</div>

		<?php endif; ?>

		<?php do_action( 'arshid6social_after_profile', $profile_user ); ?>
	</div>
</div>

<?php if ( is_user_logged_in() && ! $is_self ) : ?>
<?php arshid6social_report_modal(); ?>
<?php endif; ?>
