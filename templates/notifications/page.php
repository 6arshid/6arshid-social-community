<?php
/**
 * Standalone notifications page — rendered by [arshid6social_notifications] shortcode.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$notif_component = ARSHID6SOCIAL()->component( 'notifications' );
$notif_prefs     = $notif_component->get_user_prefs( get_current_user_id() );
$notif_types     = \Arshid6Social\Components\Notifications\Notifications::TYPES;
$unread_count    = $notif_component->get_unread_count( get_current_user_id() );

$dark_mode = sanitize_key( get_option( 'arshid6social_dark_mode', 'off' ) );
$is_dark   = ( 'on' === $dark_mode );
$is_auto   = ( 'auto' === $dark_mode );

$dark_css = '
#arshid6social-notifications-page .arshid6social-notif-card { color: #f1f5f9 !important; background: transparent !important; border: none !important; box-shadow: none !important; }
#arshid6social-notifications-page .arshid6social-notif-card.is-unread { background: rgba(59,130,246,.15) !important; border-inline-start: none !important; }
#arshid6social-notifications-page .arshid6social-notif-card:hover { background: #243447 !important; box-shadow: none !important; }
#arshid6social-notifications-page .arshid6social-notif-card * { color: inherit; }
#arshid6social-notifications-page .arshid6social-notif-time { color: #94a3b8 !important; }
#arshid6social-notifications-page .arshid6social-notif-time.is-new { color: #60a5fa !important; }
#arshid6social-notifications-page .arshid6social-notif-type-badge { border-color: #1e293b !important; }
#arshid6social-notifications-page .arshid6social-notif-dots-btn { background: #334155 !important; color: #f1f5f9 !important; }
#arshid6social-notifications-page .arshid6social-notif-delete { background: #334155 !important; color: #94a3b8 !important; }
#arshid6social-notifications-page .arshid6social-notif-see-more-btn { background: #334155 !important; }
#arshid6social-notifications-page,
#arshid6social-notifications-page .arshid6social-notif-page-title,
#arshid6social-notifications-page .arshid6social-notif-section-header { color: #f1f5f9 !important; }
#arshid6social-notifications-page .arshid6social-notif-section-header { text-transform: none !important; letter-spacing: normal !important; font-size: 1rem !important; }
#arshid6social-notifications-page .arshid6social-notif-page-header { border-block-end-color: #334155 !important; }
#arshid6social-notifications-page .arshid6social-notif-desc { color: #f1f5f9 !important; }
';
?>
<?php if ( $is_dark ) : ?>
<style><?php echo $dark_css; // phpcs:ignore WordPress.Security.EscapeOutput ?></style>
<?php elseif ( $is_auto ) : ?>
<style>@media (prefers-color-scheme: dark) { <?php echo $dark_css; // phpcs:ignore WordPress.Security.EscapeOutput ?> }</style>
<?php endif; ?>
<div id="arshid6social-notifications-page" class="arshid6social-notif-standalone-page<?php echo $is_dark ? ' arshid6social-is-dark' : ''; ?>">

	<!-- Header bar -->
	<div class="arshid6social-notif-page-header">
		<div class="arshid6social-notif-header-top">
			<h2 class="arshid6social-notif-page-title">
				<?php esc_html_e( 'Notifications', '6arshid social community' ); ?>
			</h2>
			<button id="arshid6social-notif-settings-toggle" class="arshid6social-notif-dots-btn arshid6social-notif-settings-toggle"
				aria-expanded="false" aria-controls="arshid6social-notif-settings-panel"
				title="<?php esc_attr_e( 'Customize', '6arshid social community' ); ?>">&#x22EF;</button>
		</div>
		<div class="arshid6social-notif-tabs-row">
			<div class="arshid6social-notif-tabs" role="tablist">
				<button class="arshid6social-notif-tab is-active" data-filter="all" role="tab" aria-selected="true">
					<?php esc_html_e( 'All', '6arshid social community' ); ?>
				</button>
				<button class="arshid6social-notif-tab" data-filter="unread" role="tab" aria-selected="false">
					<?php esc_html_e( 'Unread', '6arshid social community' ); ?>
					<span id="arshid6social-notif-page-unread-badge" class="arshid6social-notif-tab-badge"
						<?php echo $unread_count ? '' : 'hidden'; ?>>
						<?php echo $unread_count ? esc_html( $unread_count ) : ''; ?>
					</span>
				</button>
			</div>
			<button id="arshid6social-notif-mark-all" class="arshid6social-btn arshid6social-btn--primary arshid6social-btn--sm">
				<?php esc_html_e( 'Mark all read', '6arshid social community' ); ?>
			</button>
		</div>
		<input type="checkbox" id="arshid6social-notif-unread-only" hidden aria-hidden="true" />
	</div>

	<!-- Collapsible preferences panel -->
	<div id="arshid6social-notif-settings-panel" class="arshid6social-notif-settings-panel" hidden>
		<div class="arshid6social-card" style="margin-block-end:1.5rem;">
			<div class="arshid6social-card__header" style="display:flex;align-items:center;justify-content:space-between;">
				<span>⚙ <?php esc_html_e( 'Notification Preferences', '6arshid social community' ); ?></span>
				<button type="button" class="arshid6social-notif-settings-toggle arshid6social-link-btn" style="font-size:1.2rem;"
					aria-label="<?php esc_attr_e( 'Close', '6arshid social community' ); ?>">✕</button>
			</div>
			<div class="arshid6social-card__body">
				<form id="arshid6social-notif-prefs-form">

					<p class="arshid6social-settings-desc" style="margin-block-end:1rem;">
						<?php esc_html_e( 'Choose which events send you a notification.', '6arshid social community' ); ?>
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
							<span>✉ <?php esc_html_e( 'Email notifications', '6arshid social community' ); ?></span>
						</label>

						<div>
							<label style="font-size:.8rem;font-weight:600;display:block;margin-block-end:.25rem;">
								<?php esc_html_e( 'Email frequency', '6arshid social community' ); ?>
							</label>
							<select name="arshid6social_email_digest" class="arshid6social-select" style="max-width:200px;">
								<option value="none"   <?php selected( $notif_prefs['email_digest'], 'none' ); ?>><?php esc_html_e( 'Never', '6arshid social community' ); ?></option>
								<option value="daily"  <?php selected( $notif_prefs['email_digest'], 'daily' ); ?>><?php esc_html_e( 'Daily digest', '6arshid social community' ); ?></option>
								<option value="weekly" <?php selected( $notif_prefs['email_digest'], 'weekly' ); ?>><?php esc_html_e( 'Weekly digest', '6arshid social community' ); ?></option>
							</select>
						</div>
					</div>

					<div class="arshid6social-settings-actions" style="margin-block-start:1.25rem;">
						<button type="submit" class="arshid6social-btn arshid6social-btn--primary" id="arshid6social-notif-prefs-save-btn">
							<?php esc_html_e( 'Save Preferences', '6arshid social community' ); ?>
						</button>
						<span class="arshid6social-notif-prefs-saved-msg" hidden aria-live="polite">
							&#10003; <?php esc_html_e( 'Saved!', '6arshid social community' ); ?>
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

	<!-- Load more button (fallback when IntersectionObserver not available) -->
	<div id="arshid6social-notif-load-more-wrap" style="margin-top:0.5rem;" hidden>
		<button id="arshid6social-notif-load-more" class="arshid6social-notif-see-more-btn">
			<?php esc_html_e( 'See previous notifications', '6arshid social community' ); ?>
		</button>
	</div>
</div>
