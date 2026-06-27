<?php
/**
 * Hashtag archive page.
 *
 * Variables available:
 *  @var string $hashtag  Raw tag string (no #)
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$hashtag     = isset( $hashtag ) ? sanitize_text_field( $hashtag ) : '';
$display_tag = '#' . $hashtag;
?>

<div class="arshid6social-wrap">
<main id="arshid6social-hashtag-page" class="arshid6social-layout">

	<header class="arshid6social-card" style="margin-block-end:1.5rem;">
		<h1 style="margin:0;"><?php echo esc_html( $display_tag ); ?></h1>
		<p style="color:var(--arshid6social-text-muted);margin:.25rem 0 0;">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: hashtag with # symbol */
					__( 'All activity tagged with %s', '6arshid-social-community' ),
					$display_tag
				)
			);
			?>
		</p>
	</header>

	<div class="arshid6social-activity-block"
		data-hashtag="<?php echo esc_attr( $hashtag ); ?>"
		data-per-page="10">
		<div class="arshid6social-activity-feed" aria-label="<?php
		/* translators: %s: hashtag */
		echo esc_attr( sprintf( __( 'Activity tagged %s', '6arshid-social-community' ), $display_tag ) ); ?>"></div>
		<div class="arshid6social-load-more-sentinel" style="height:1px;"></div>
	</div>

</main>
</div>
