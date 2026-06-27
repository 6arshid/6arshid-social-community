<?php
/**
 * Member settings template — personal preferences.
 *
 * Variables injected by Template_Loader:
 *   $profile_user  \WP_User  The member whose settings are shown.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

// Notification preferences.
$notif_component = ARSHID6SOCIAL()->component( 'notifications' );
$notif_prefs     = $notif_component ? $notif_component->get_user_prefs( $profile_user->ID ) : array();
$notif_types     = \Arshid6Social\Components\Notifications\Notifications::TYPES;
?>
<?php $settings_bio = ARSHID6SOCIAL()->component( 'members' )->xprofile->get_field_value( $profile_user->ID, 'bio' ); ?>
<div class="arshid6social-card arshid6social-user-settings-card" id="arshid6social-bio-settings-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'About / Bio', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-bio-settings-form">
			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label" for="arshid6social-bio-settings-textarea"><?php esc_html_e( 'Bio', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'Tell others a bit about yourself.', '6arshid-social-community-main' ); ?></p>
				<textarea id="arshid6social-bio-settings-textarea" name="arshid6social_bio" class="arshid6social-textarea" rows="4" style="width:100%;box-sizing:border-box;resize:vertical;"><?php echo esc_textarea( $settings_bio ); ?></textarea>
			</div>
			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-bio-settings-save-btn">
					<?php esc_html_e( 'Save Bio', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-bio-settings-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community-main' ); ?>
				</span>
			</div>
		</form>
	</div>
</div>

<?php
$theme_mode = get_user_meta( $profile_user->ID, 'arshid6social_theme_mode', true ) ?: 'system';
?>
<div class="arshid6social-card arshid6social-user-settings-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Appearance', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-appearance-settings-form">
			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label"><?php esc_html_e( 'Theme', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'Choose your preferred color theme.', '6arshid-social-community-main' ); ?></p>
				<div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-block-start:.5rem;">
					<label class="arshid6social-radio-option <?php echo $theme_mode === 'light' ? 'is-selected' : ''; ?>" style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_theme_mode" value="light" <?php checked( $theme_mode, 'light' ); ?> />
						<span><?php esc_html_e( 'Light', '6arshid-social-community-main' ); ?></span>
					</label>
					<label class="arshid6social-radio-option <?php echo $theme_mode === 'dark' ? 'is-selected' : ''; ?>" style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_theme_mode" value="dark" <?php checked( $theme_mode, 'dark' ); ?> />
						<span><?php esc_html_e( 'Dark', '6arshid-social-community-main' ); ?></span>
					</label>
					<label class="arshid6social-radio-option <?php echo $theme_mode === 'dim' ? 'is-selected' : ''; ?>" style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_theme_mode" value="dim" <?php checked( $theme_mode, 'dim' ); ?> />
						<span><?php esc_html_e( 'Dim', '6arshid-social-community-main' ); ?></span>
					</label>
					<label class="arshid6social-radio-option <?php echo $theme_mode === 'system' ? 'is-selected' : ''; ?>" style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_theme_mode" value="system" <?php checked( $theme_mode, 'system' ); ?> />
						<span><?php esc_html_e( 'System', '6arshid-social-community-main' ); ?></span>
					</label>
				</div>
			</div>
			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-appearance-save-btn">
					<?php esc_html_e( 'Save Appearance', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-appearance-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community-main' ); ?>
				</span>
			</div>
		</form>
	</div>
</div>

<?php
$reaction_style    = get_user_meta( $profile_user->ID, 'arshid6social_reaction_style', true ) ?: 'emoji';
$activity_feed_tab = get_user_meta( $profile_user->ID, 'arshid6social_activity_feed_tab', true ) ?: 'all';
?>
<div class="arshid6social-card arshid6social-user-settings-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Preferences', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-user-settings-form">

			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label"><?php esc_html_e( 'Reaction Style', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'Choose how you react to posts.', '6arshid-social-community-main' ); ?></p>
				<div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-block-start:.5rem;">
					<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_reaction_style" value="emoji" <?php checked( $reaction_style, 'emoji' ); ?> />
						<span>&#128515; <?php esc_html_e( 'Emoji reactions', '6arshid-social-community-main' ); ?></span>
					</label>
					<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_reaction_style" value="heart" <?php checked( $reaction_style, 'heart' ); ?> />
						<span>&#10084;&#65039; <?php esc_html_e( 'Simple heart', '6arshid-social-community-main' ); ?></span>
					</label>
				</div>
			</div>

			<div class="arshid6social-settings-field" style="margin-block-start:1.25rem;">
				<label class="arshid6social-settings-label"><?php esc_html_e( 'Default Activity Feed', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'Choose which feed tab opens by default on the activity page.', '6arshid-social-community-main' ); ?></p>
				<div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-block-start:.5rem;">
					<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_activity_feed_tab" value="all" <?php checked( $activity_feed_tab, 'all' ); ?> />
						<span><?php esc_html_e( 'All — show everyone\'s posts', '6arshid-social-community-main' ); ?></span>
					</label>
					<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="arshid6social_activity_feed_tab" value="follow" <?php checked( $activity_feed_tab, 'follow' ); ?> />
						<span><?php esc_html_e( 'Follow — show posts from people & hashtags you follow', '6arshid-social-community-main' ); ?></span>
					</label>
				</div>
			</div>

			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-settings-save-btn">
					<?php esc_html_e( 'Save Preferences', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-settings-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community-main' ); ?>
				</span>
			</div>

		</form>
	</div>
</div>

<!-- Notification Preferences card -->
<div class="arshid6social-card arshid6social-user-settings-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Notification Preferences', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-notif-prefs-form">

			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label"><?php esc_html_e( 'Notify me when…', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'Choose which events send you a notification.', '6arshid-social-community-main' ); ?></p>

				<div class="arshid6social-notif-prefs-list">
					<?php foreach ( $notif_types as $action => $info ) : ?>
						<label class="arshid6social-notif-pref-row">
							<input type="checkbox"
								name="arshid6social_notify_<?php echo esc_attr( $action ); ?>"
								value="1"
								<?php checked( ! empty( $notif_prefs[ $action ] ) ); ?>
							/>
							<span class="arshid6social-notif-pref-icon" style="background:<?php echo esc_attr( $info['color'] ); ?>;">
								<?php echo esc_html( $info['icon'] ); ?>
							</span>
							<span><?php echo esc_html( $info['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label"><?php esc_html_e( 'Email Notifications', '6arshid-social-community-main' ); ?></label>

				<label class="arshid6social-notif-pref-row" style="margin-block-end:.75rem;">
					<input type="checkbox" name="arshid6social_email_notifications" value="1"
						<?php checked( ! empty( $notif_prefs['email_notifications'] ) ); ?> />
					<span><?php esc_html_e( 'Receive email notifications', '6arshid-social-community-main' ); ?></span>
				</label>

				<div class="arshid6social-settings-field" style="margin-block-end:0;">
					<label class="arshid6social-settings-label" style="font-size:.8rem;font-weight:500;">
						<?php esc_html_e( 'Email frequency', '6arshid-social-community-main' ); ?>
					</label>
					<select name="arshid6social_email_digest" class="arshid6social-select">
						<option value="none"   <?php selected( $notif_prefs['email_digest'], 'none' ); ?>><?php esc_html_e( 'Never',         '6arshid-social-community-main' ); ?></option>
						<option value="daily"  <?php selected( $notif_prefs['email_digest'], 'daily' ); ?>><?php esc_html_e( 'Daily digest',  '6arshid-social-community-main' ); ?></option>
						<option value="weekly" <?php selected( $notif_prefs['email_digest'], 'weekly' ); ?>><?php esc_html_e( 'Weekly digest', '6arshid-social-community-main' ); ?></option>
					</select>
				</div>
			</div>

			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-notif-prefs-save-btn">
					<?php esc_html_e( 'Save Notification Preferences', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-notif-prefs-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community-main' ); ?>
				</span>
			</div>

		</form>
	</div>
</div>

<?php if ( get_option( 'arshid6social_verification_enabled', false ) && arshid6social_verification() ) : ?>
<div class="arshid6social-card arshid6social-user-settings-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Account Verification', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<?php
		\Arshid6Social\Template_Loader::instance()->get_template(
			'verification/request.php',
			array(
				'verification' => arshid6social_verification(),
				'user_id'      => $profile_user->ID,
				'types'        => arshid6social_verification()->get_types(),
				'pending'      => arshid6social_verification()->get_pending_request( $profile_user->ID ),
				'is_verified'  => arshid6social_verification()->is_verified( $profile_user->ID ),
			)
		);
		?>
	</div>
</div>
<?php endif; ?>

<?php if ( get_current_user_id() === $profile_user->ID ) : ?>
<div class="arshid6social-card arshid6social-user-settings-card" id="arshid6social-change-name-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Display Name', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-change-name-form" autocomplete="off">
			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label" for="arshid6social-display-name-input"><?php esc_html_e( 'Name', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'This is how your name appears on your profile and in posts.', '6arshid-social-community-main' ); ?></p>
				<input type="text" id="arshid6social-display-name-input" name="display_name" class="arshid6social-input"
					value="<?php echo esc_attr( $profile_user->display_name ); ?>"
					maxlength="100" style="width:100%;box-sizing:border-box;" />
			</div>
			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-change-name-btn">
					<?php esc_html_e( 'Save Name', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-change-name-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community-main' ); ?>
				</span>
			</div>
		</form>
	</div>
</div>

<?php
$friends_privacy = get_user_meta( $profile_user->ID, 'arshid6social_friends_list_privacy', true ) ?: 'private';
?>
<div class="arshid6social-card arshid6social-user-settings-card" id="arshid6social-friends-privacy-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Friends List Privacy', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-friends-privacy-form">
			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label"><?php esc_html_e( 'Who can see your friends list?', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'Control whether other users can view the list of people you are friends with.', '6arshid-social-community-main' ); ?></p>
				<div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-block-start:.5rem;">
					<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="friends_privacy" value="public" <?php checked( $friends_privacy, 'public' ); ?> />
						<span><?php esc_html_e( 'Public — anyone can see my friends list', '6arshid-social-community-main' ); ?></span>
					</label>
					<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
						<input type="radio" name="friends_privacy" value="private" <?php checked( $friends_privacy, 'private' ); ?> />
						<span><?php esc_html_e( 'Private — only I can see my friends list', '6arshid-social-community-main' ); ?></span>
					</label>
				</div>
			</div>
			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-friends-privacy-save-btn">
					<?php esc_html_e( 'Save Privacy', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-friends-privacy-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community-main' ); ?>
				</span>
			</div>
		</form>
	</div>
</div>

<div class="arshid6social-card arshid6social-user-settings-card" id="arshid6social-change-username-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Change Username', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-change-username-form" autocomplete="off">
			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label" for="arshid6social-new-username"><?php esc_html_e( 'New Username', '6arshid-social-community-main' ); ?></label>
				<p class="arshid6social-settings-desc"><?php esc_html_e( 'Changing your username will also change your profile URL. You will be redirected after saving.', '6arshid-social-community-main' ); ?></p>
				<div style="position:relative;">
					<input type="text" id="arshid6social-new-username" name="new_username" class="arshid6social-input"
						value="<?php echo esc_attr( $profile_user->user_login ); ?>"
						autocomplete="off" minlength="3" maxlength="60"
						style="width:100%;box-sizing:border-box;padding-inline-end:2.5rem;" />
					<span id="arshid6social-username-check-icon" aria-live="polite" style="position:absolute;inset-inline-end:.75rem;top:50%;transform:translateY(-50%);font-size:1.1rem;"></span>
				</div>
				<p id="arshid6social-username-check-msg" aria-live="polite" style="margin-block-start:.4rem;font-size:.82rem;min-height:1.2em;"></p>
			</div>
			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-change-username-btn" disabled>
					<?php esc_html_e( 'Save Username', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-change-username-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Username changed!', '6arshid-social-community-main' ); ?>
				</span>
			</div>
		</form>
	</div>
</div>

<div class="arshid6social-card arshid6social-user-settings-card" id="arshid6social-change-password-card">
	<div class="arshid6social-card__header"><?php esc_html_e( 'Change Password', '6arshid-social-community-main' ); ?></div>
	<div class="arshid6social-card__body">
		<form id="arshid6social-change-password-form" autocomplete="off">
			<div class="arshid6social-settings-field">
				<label class="arshid6social-settings-label" for="arshid6social-current-password"><?php esc_html_e( 'Current Password', '6arshid-social-community-main' ); ?></label>
				<input type="password" id="arshid6social-current-password" name="current_password" class="arshid6social-input" autocomplete="current-password" style="width:100%;box-sizing:border-box;" />
			</div>
			<div class="arshid6social-settings-field" style="margin-block-start:1rem;">
				<label class="arshid6social-settings-label" for="arshid6social-new-password"><?php esc_html_e( 'New Password', '6arshid-social-community-main' ); ?></label>
				<input type="password" id="arshid6social-new-password" name="new_password" class="arshid6social-input" autocomplete="new-password" style="width:100%;box-sizing:border-box;" />
			</div>
			<div class="arshid6social-settings-field" style="margin-block-start:1rem;">
				<label class="arshid6social-settings-label" for="arshid6social-confirm-password"><?php esc_html_e( 'Confirm New Password', '6arshid-social-community-main' ); ?></label>
				<input type="password" id="arshid6social-confirm-password" name="confirm_password" class="arshid6social-input" autocomplete="new-password" style="width:100%;box-sizing:border-box;" />
			</div>
			<div class="arshid6social-settings-actions">
				<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-change-password-btn">
					<?php esc_html_e( 'Change Password', '6arshid-social-community-main' ); ?>
				</button>
				<span class="arshid6social-change-password-saved-msg" hidden aria-live="polite">
					&#10003; <?php esc_html_e( 'Password changed!', '6arshid-social-community-main' ); ?>
				</span>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( arshid6social_blocking() ) : ?>
<div class="arshid6social-card arshid6social-user-settings-card">
	<div class="arshid6social-card__body" style="padding-block-start:.5rem;">
		<?php echo arshid6social_blocking()->shortcode_block_list( array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
</div>
<?php endif; ?>

<?php
/**
 * Fires after all built-in settings cards are rendered.
 * Used by optional components (e.g. Monetization) to append their own cards.
 *
 * @param \WP_User $profile_user The user whose settings are displayed.
 */
do_action( 'arshid6social_member_settings_after', $profile_user );
?>
