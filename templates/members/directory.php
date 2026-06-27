<?php
/**
 * Member directory template.
 * Rendered both from shortcode (inside WP page) and from rewrite-rule handler.
 * Never calls get_header/get_footer — that is the caller's responsibility.
 *
 * Available variables (set by Template_Loader):
 *  $show_search  bool
 *  $block_mode   bool  (true when called from shortcode/block — no outer wrap)
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$show_search = isset( $show_search ) ? (bool) $show_search : true;
$nonce       = wp_create_nonce( 'arshid6social_ajax_nonce' );
?>
<div class="arshid6social-directory-wrap" id="arshid6social-members-page">

	<?php if ( $show_search ) : ?>
	<div class="arshid6social-toolbar">
		<div class="arshid6social-search-wrap">
			<input type="search"
				class="arshid6social-search-input arshid6social-member-search"
				placeholder="<?php esc_attr_e( 'Search members…', '6arshid social community' ); ?>"
				aria-label="<?php esc_attr_e( 'Search members', '6arshid social community' ); ?>"
			/>
		</div>
		<div class="arshid6social-filter-wrap">
			<select class="arshid6social-sort-select" data-type="member">
				<option value="newest"><?php esc_html_e( 'Newest', '6arshid social community' ); ?></option>
				<option value="active"><?php esc_html_e( 'Recently Active', '6arshid social community' ); ?></option>
				<option value="alphabetical"><?php esc_html_e( 'Alphabetical', '6arshid social community' ); ?></option>
			</select>
		</div>
	</div>
	<?php endif; ?>

	<div id="arshid6social-member-directory"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-pagination-type="<?php echo esc_attr( get_option( 'arshid6social_members_pagination_type', 'pagination' ) ); ?>">
		<div class="arshid6social-member-grid" role="list" aria-label="<?php esc_attr_e( 'Member directory', '6arshid social community' ); ?>">
			<?php for ( $i = 0; $i < 8; $i++ ) : ?>
				<div class="arshid6social-member-card arshid6social-skeleton-item" role="listitem" aria-hidden="true">
					<div class="arshid6social-skeleton" style="width:80px;height:80px;border-radius:50%;margin:0 auto .75rem;"></div>
					<div class="arshid6social-skeleton" style="height:14px;width:60%;margin:0 auto .5rem;"></div>
					<div class="arshid6social-skeleton" style="height:12px;width:40%;margin:0 auto;"></div>
				</div>
			<?php endfor; ?>
		</div>
		<div class="arshid6social-members-load-more-sentinel" style="height:1px;"></div>
		<div class="arshid6social-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Member directory pagination', '6arshid social community' ); ?>"></div>
	</div>
</div>
