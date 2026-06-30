<?php
/**
 * Saved Posts page template — rendered by [arshid6social_bookmarks] shortcode.
 *
 * @package Arshid6Social\Engagement
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	echo '<p class="arshid6social-notice">' . esc_html__( 'Please log in to view your saved posts.', '6arshid-social-community' ) . '</p>';
	return;
}
?>
<div class="arshid6social-wrap arshid6social-bookmarks-page">
	<div class="arshid6social-activity-feed" id="arshid6social-bookmarks-feed"></div>
	<div id="arshid6social-bookmarks-sentinel" style="height:1px"></div>
</div>
