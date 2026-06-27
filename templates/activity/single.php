<?php
/**
 * Single activity post — injected via the_content filter.
 *
 * Variables:
 *  @var array $activity  Formatted activity item from format_activity().
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$activity = $activity ?? array();
if ( empty( $activity['id'] ) ) {
	return;
}
?>
<div class="arshid6social-wrap arshid6social-single-activity-page">

	<nav class="arshid6social-breadcrumb">
		<a href="<?php echo esc_url( home_url( '/activity/' ) ); ?>"><?php esc_html_e( 'Activity', '6arshid-social-community' ); ?></a>
		<span aria-hidden="true"> › </span>
		<span><?php esc_html_e( 'Post', '6arshid-social-community' ); ?> #<?php echo esc_html( $activity['id'] ); ?></span>
	</nav>

	<div id="arshid6social-single-activity-wrap"
		data-activity='<?php echo wp_json_encode( $activity, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>'
		data-user-id="<?php echo esc_attr( $activity['userId'] ?? '' ); ?>">
		<div class="arshid6social-activity-feed" role="main" aria-label="<?php esc_attr_e( 'Activity post', '6arshid-social-community' ); ?>"></div>
	</div>

	<div id="arshid6social-single-more-wrap" class="arshid6social-single-more-from">
		<h3 class="arshid6social-single-more-title">
			<?php
			printf(
				/* translators: %s: user display name */
				esc_html__( 'More from %s', '6arshid-social-community' ),
				'<strong>' . esc_html( $activity['userName'] ?? '' ) . '</strong>'
			);
			?>
		</h3>
		<div id="arshid6social-single-more-feed" class="arshid6social-activity-feed"></div>
	</div>

</div>
