<?php
/**
 * Global helper functions for 6Arshid Social Community.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns true if either user has blocked the other (bidirectional).
 * Single source of truth — used everywhere: activity queries, REST callbacks, messaging.
 *
 * @param int $user_a User A ID.
 * @param int $user_b User B ID.
 * @return bool
 */
function arshid6social_is_blocked( int $user_a, int $user_b ): bool {
	if ( ! $user_a || ! $user_b || $user_a === $user_b ) {
		return false;
	}
	$friends = ARSHID6SOCIAL()->component( 'friends' );
	if ( $friends ) {
		return $friends->is_blocked( $user_a, $user_b );
	}
	// Fallback: direct DB query if Friends component not loaded.
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT id FROM {$wpdb->prefix}sn_blocks
		 WHERE (blocker_id = %d AND blocked_id = %d)
		    OR (blocker_id = %d AND blocked_id = %d)",
		$user_a, $user_b, $user_b, $user_a
	) );
}

/**
 * Returns the Stories component or null when disabled.
 *
 * @return \Arshid6Social\Components\Stories\Stories|null
 */
function arshid6social_stories(): ?object {
	return ARSHID6SOCIAL()->component( 'stories' );
}

/**
 * Returns the Blocking component or null when disabled.
 *
 * @return \Arshid6Social\Components\Blocking\Blocking|null
 */
function arshid6social_blocking(): ?object {
	return ARSHID6SOCIAL()->component( 'blocking' );
}

/**
 * Returns the Verification component or null when disabled.
 *
 * @return \Arshid6Social\Components\Verification\Verification|null
 */
function arshid6social_verification(): ?object {
	return ARSHID6SOCIAL()->component( 'verification' );
}

/**
 * Returns the Marketplace component or null when disabled.
 *
 * @return \Arshid6Social\Components\Marketplace\Marketplace|null
 */
function arshid6social_marketplace(): ?object {
	return ARSHID6SOCIAL()->component( 'marketplace' );
}

/**
 * Returns true if user is site-wide blocked/banned by an admin.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function arshid6social_is_site_blocked( int $user_id ): bool {
	return (bool) get_user_meta( $user_id, 'arshid6social_site_blocked', true );
}

/**
 * Returns the verified badge HTML for a user, or empty string if not verified.
 *
 * @param int  $user_id User ID.
 * @param bool $echo    Whether to echo.
 * @return string
 */
/**
 * Returns the Bootstrap Icon SVG for a given page, or empty string if none is set.
 *
 * @param int $page_id  WordPress page ID (defaults to current post).
 * @param int $size     SVG width/height in pixels.
 * @return string       Safe inline SVG, or empty string.
 */
function arshid6social_page_icon( int $page_id = 0, int $size = 20 ): string {
	if ( ! $page_id ) {
		$page_id = (int) get_the_ID();
	}
	$icon = (string) get_post_meta( $page_id, \Arshid6Social\Admin\Admin_Page_Icons::META_KEY, true );
	if ( ! $icon ) {
		return '';
	}
	$picker = \Arshid6Social\Admin\Admin_Page_Icons::instance();
	return $picker->get_icon_svg( $icon, $size );
}

function arshid6social_verified_badge( int $user_id, bool $echo = false ): string {
	$verification = arshid6social_verification();
	if ( ! $verification ) {
		return '';
	}
	$badge = $verification->get_badge_html( $user_id );
	if ( $echo ) {
		echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	return $badge;
}

/**
 * Checks a rate limit via transients.
 *
 * @param string $key     Unique key prefix (e.g. 'arshid6social_rl_stories').
 * @param int    $user_id User ID.
 * @param int    $max     Max calls per hour.
 * @return bool True if under limit.
 */
function arshid6social_check_rate_limit( string $key, int $user_id, int $max ): bool {
	// Ensure the transient key is always prefixed with the plugin slug.
	$prefixed_key = str_starts_with( $key, 'ARSHID6SOCIAL_' ) ? $key : 'ARSHID6SOCIAL_' . $key;
	$tk           = $prefixed_key . '_' . $user_id;
	$count        = (int) get_transient( $tk );
	if ( $count >= $max ) {
		return false;
	}
	set_transient( $tk, $count + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * Determines whether the current user is allowed to view a given activity item.
 *
 * Rules:
 *  - Public activities are visible to everyone.
 *  - Private activities are visible only to the author or admins.
 *  - Friends-only activities are visible to the author, their friends, or admins.
 *  - Paid (PPV) activities follow the same rules as private for REST permission checks.
 *
 * @param int $activity_id Activity ID.
 * @return bool True if current user may view the activity.
 */
function arshid6social_current_user_can_view_activity( int $activity_id ): bool {
	if ( ! $activity_id ) {
		return false;
	}

	$component = ARSHID6SOCIAL()->component( 'activity' );
	if ( $component ) {
		$activity = $component->get_by_id( $activity_id );
	} else {
		global $wpdb;
		$activity = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, user_id, privacy FROM {$wpdb->prefix}sn_activity WHERE id = %d LIMIT 1",
			$activity_id
		) );
	}

	if ( ! $activity ) {
		return false;
	}

	$privacy         = isset( $activity->privacy ) ? (string) $activity->privacy : 'public';
	$activity_author = (int) ( $activity->user_id ?? 0 );
	$current_user    = get_current_user_id();

	// Public is visible to all.
	if ( 'public' === $privacy ) {
		return true;
	}

	// All non-public requires login.
	if ( ! $current_user ) {
		return false;
	}

	// Admins and the author always have access.
	if ( $current_user === $activity_author || current_user_can( 'arshid6social_manage_activity' ) ) {
		return true;
	}

	// Friends-only: check friendship.
	if ( 'friends' === $privacy ) {
		$friends = ARSHID6SOCIAL()->component( 'friends' );
		if ( $friends && method_exists( $friends, 'are_friends' ) ) {
			return $friends->are_friends( $current_user, $activity_author );
		}
	}

	return false;
}

/**
 * Outputs the report modal HTML used on profile and group pages.
 * Requires ARSHID6SOCIALConfig.reportReasons to be populated via wp_localize_script.
 */
function arshid6social_report_modal(): void {
	?>
	<div id="arshid6social-report-modal" class="arshid6social-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="arshid6social-rm-title" hidden>
		<div class="arshid6social-modal-box">
			<button class="arshid6social-modal-close" id="arshid6social-rm-close" aria-label="<?php esc_attr_e( 'Close', '6arshid-social-community' ); ?>">&times;</button>
			<h2 class="arshid6social-modal-title" id="arshid6social-rm-title"><?php esc_html_e( 'Report', '6arshid-social-community' ); ?></h2>
			<p class="arshid6social-modal-desc"><?php esc_html_e( 'Why are you reporting this?', '6arshid-social-community' ); ?></p>

			<div id="arshid6social-rm-reasons" class="arshid6social-report-reasons"></div>

			<label class="arshid6social-report-label" for="arshid6social-rm-notes"><?php esc_html_e( 'Additional details (optional)', '6arshid-social-community' ); ?></label>
			<textarea id="arshid6social-rm-notes" class="arshid6social-report-notes" rows="3"
				placeholder="<?php esc_attr_e( 'Tell us more about this report…', '6arshid-social-community' ); ?>"></textarea>

			<div id="arshid6social-rm-attachment-wrap" hidden>
				<label class="arshid6social-report-label" for="arshid6social-rm-file"><?php esc_html_e( 'Attach a screenshot (optional)', '6arshid-social-community' ); ?></label>
				<input type="file" id="arshid6social-rm-file" accept="image/*" class="arshid6social-report-file" />
			</div>

			<div id="arshid6social-rm-feedback" class="arshid6social-report-feedback" hidden></div>

			<div class="arshid6social-modal-actions">
				<button class="arshid6social-btn arshid6social-btn--secondary" id="arshid6social-rm-cancel"><?php esc_html_e( 'Cancel', '6arshid-social-community' ); ?></button>
				<button class="arshid6social-btn arshid6social-btn--danger" id="arshid6social-rm-submit" disabled><?php esc_html_e( 'Submit Report', '6arshid-social-community' ); ?></button>
			</div>

			<input type="hidden" id="arshid6social-rm-item-id" value="" />
			<input type="hidden" id="arshid6social-rm-item-type" value="" />
			<input type="hidden" id="arshid6social-rm-reason" value="" />
		</div>
	</div>
	<?php
}
