<?php
/**
 * Member notifications page template.
 *
 * Variables:
 *   $profile_user  \WP_User
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$notif_component = ARSHID6SOCIAL()->component( 'notifications' );
if ( ! $notif_component ) {
	return;
}
$notif_prefs = $notif_component->get_user_prefs( $profile_user->ID );
$notif_types = \Arshid6Social\Components\Notifications\Notifications::TYPES;
?>
<div id="arshid6social-notifications-page">

	<!-- Header bar -->
	<div class="arshid6social-notif-page-header">
		<h2 class="arshid6social-notif-page-title">
			<?php esc_html_e( 'Notifications', '6arshid-social-community' ); ?>
			<span id="arshid6social-notif-page-unread-badge" class="arshid6social-badge arshid6social-badge--primary" hidden></span>
		</h2>
		<div class="arshid6social-notif-page-actions">
			<label class="arshid6social-notif-filter-label">
				<input type="checkbox" id="arshid6social-notif-unread-only" />
				<?php esc_html_e( 'Unread only', '6arshid-social-community' ); ?>
			</label>
			<button id="arshid6social-notif-mark-all" class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm">
				<?php esc_html_e( 'Mark all read', '6arshid-social-community' ); ?>
			</button>
			<button id="arshid6social-notif-settings-toggle" class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-notif-settings-toggle"
				aria-expanded="false" aria-controls="arshid6social-notif-settings-panel">
				⚙ <?php esc_html_e( 'Customize', '6arshid-social-community' ); ?>
			</button>
		</div>
	</div>

	<!-- Collapsible preferences panel -->
	<div id="arshid6social-notif-settings-panel" class="arshid6social-notif-settings-panel" hidden>
		<div class="arshid6social-card" style="margin-block-end:1.5rem;">
			<div class="arshid6social-card__header" style="display:flex;align-items:center;justify-content:space-between;">
				<span>⚙ <?php esc_html_e( 'Notification Preferences', '6arshid-social-community' ); ?></span>
				<button type="button" class="arshid6social-notif-settings-toggle arshid6social-link-btn" style="font-size:1.2rem;" aria-label="<?php esc_attr_e( 'Close', '6arshid-social-community' ); ?>">✕</button>
			</div>
			<div class="arshid6social-card__body">
				<form id="arshid6social-notif-prefs-form">

					<p class="arshid6social-settings-desc" style="margin-block-end:1rem;">
						<?php esc_html_e( 'Choose which events send you a notification.', '6arshid-social-community' ); ?>
					</p>

					<div class="arshid6social-notif-prefs-grid">
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

					<hr style="margin-block:1.25rem;border:none;border-top:1px solid var(--arshid6social-border);" />

					<div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-end;">
						<label class="arshid6social-notif-pref-row" style="flex:none;">
							<input type="checkbox" name="arshid6social_email_notifications" value="1"
								<?php checked( ! empty( $notif_prefs['email_notifications'] ) ); ?> />
							<span>✉ <?php esc_html_e( 'Email notifications', '6arshid-social-community' ); ?></span>
						</label>

						<div>
							<label style="font-size:.8rem;font-weight:600;display:block;margin-block-end:.25rem;">
								<?php esc_html_e( 'Email frequency', '6arshid-social-community' ); ?>
							</label>
							<select name="arshid6social_email_digest" class="arshid6social-select" style="max-width:200px;">
								<option value="none"   <?php selected( $notif_prefs['email_digest'], 'none' ); ?>><?php esc_html_e( 'Never', '6arshid-social-community' ); ?></option>
								<option value="daily"  <?php selected( $notif_prefs['email_digest'], 'daily' ); ?>><?php esc_html_e( 'Daily digest', '6arshid-social-community' ); ?></option>
								<option value="weekly" <?php selected( $notif_prefs['email_digest'], 'weekly' ); ?>><?php esc_html_e( 'Weekly digest', '6arshid-social-community' ); ?></option>
							</select>
						</div>
					</div>

					<div class="arshid6social-settings-actions" style="margin-block-start:1.25rem;">
						<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-notif-prefs-save-btn">
							<?php esc_html_e( 'Save Preferences', '6arshid-social-community' ); ?>
						</button>
						<span class="arshid6social-notif-prefs-saved-msg" hidden aria-live="polite">
							&#10003; <?php esc_html_e( 'Saved!', '6arshid-social-community' ); ?>
						</span>
					</div>

				</form>
			</div>
		</div>
	</div>

	<!-- Notification list -->
	<div id="arshid6social-notif-list" class="arshid6social-notif-list" role="list">
		<div class="arshid6social-notif-skeleton">
			<div class="arshid6social-skeleton" style="height:72px;margin-bottom:8px;border-radius:12px;"></div>
			<div class="arshid6social-skeleton" style="height:72px;margin-bottom:8px;border-radius:12px;"></div>
			<div class="arshid6social-skeleton" style="height:72px;border-radius:12px;"></div>
		</div>
	</div>

	<!-- Infinite scroll sentinel -->
	<div id="arshid6social-notif-sentinel" style="height:1px;margin-top:1.5rem;"></div>
	<div id="arshid6social-notif-load-more-wrap" style="text-align:center;padding:1rem;" hidden>
		<div class="arshid6social-notif-skeleton">
			<div class="arshid6social-skeleton" style="height:72px;margin-bottom:8px;border-radius:12px;"></div>
			<div class="arshid6social-skeleton" style="height:72px;border-radius:12px;"></div>
		</div>
	</div>
</div>
