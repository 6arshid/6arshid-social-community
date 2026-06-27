<?php
/**
 * Groups directory template.
 * Never calls get_header/get_footer — rendered inside a WP page via shortcode.
 *
 * Available variables:
 *  $groups              array   Pre-fetched groups (may be empty for JS-loaded grids)
 *  $show_search         bool
 *  $show_create_button  bool
 *  $block_mode          bool
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$show_search        = isset( $show_search ) ? (bool) $show_search : true;
$show_create_button = isset( $show_create_button ) ? (bool) $show_create_button : true;
$groups             = isset( $groups ) ? (array) $groups : array();
$nonce              = wp_create_nonce( 'arshid6social_ajax_nonce' );
?>
<div class="arshid6social-directory-wrap" id="arshid6social-groups-page" data-nonce="<?php echo esc_attr( $nonce ); ?>">

	<div class="arshid6social-toolbar">
		<?php if ( $show_search ) : ?>
		<div class="arshid6social-search-wrap">
			<input type="search"
				class="arshid6social-search-input arshid6social-group-search"
				placeholder="<?php esc_attr_e( 'Search groups…', '6arshid-social-community' ); ?>"
				aria-label="<?php esc_attr_e( 'Search groups', '6arshid-social-community' ); ?>"
			/>
		</div>
		<?php endif; ?>

		<?php if ( $show_create_button && is_user_logged_in() ) : ?>
		<div class="arshid6social-toolbar-actions">
			<a href="<?php echo esc_url( home_url( '/groups/create/' ) ); ?>"
				class="arshid6social-btn arshid6social-btn-primary">
				+ <?php esc_html_e( 'Create Group', '6arshid-social-community' ); ?>
			</a>
		</div>
		<?php endif; ?>
	</div>

	<div class="arshid6social-group-grid" id="arshid6social-group-grid" role="list"
		aria-label="<?php esc_attr_e( 'Groups directory', '6arshid-social-community' ); ?>">

		<?php if ( ! empty( $groups ) ) : ?>
			<?php foreach ( $groups as $group ) : ?>
			<div class="arshid6social-group-card arshid6social-card" role="listitem">
				<div class="arshid6social-group-card-cover"
					<?php if ( ! empty( $group['coverUrl'] ) ) : ?>
					style="background-image:url('<?php echo esc_url( $group['coverUrl'] ); ?>')"
					<?php endif; ?>>
				</div>
				<div class="arshid6social-group-card-body">
					<?php if ( ! empty( $group['avatarUrl'] ) ) : ?>
					<img class="arshid6social-avatar arshid6social-avatar-md"
						src="<?php echo esc_url( $group['avatarUrl'] ); ?>"
						alt="<?php echo esc_attr( $group['name'] ); ?>"
						width="48" height="48" loading="lazy"
					/>
					<?php else : ?>
					<div class="arshid6social-avatar arshid6social-avatar-md arshid6social-avatar-initial"
						aria-label="<?php echo esc_attr( $group['name'] ); ?>">
						<?php echo esc_html( mb_strtoupper( mb_substr( $group['name'], 0, 1 ) ) ); ?>
					</div>
					<?php endif; ?>
					<div class="arshid6social-group-card-info">
						<a class="arshid6social-group-card-name" href="<?php echo esc_url( $group['url'] ?? home_url( '/groups/' . $group['slug'] . '/' ) ); ?>">
							<?php echo esc_html( $group['name'] ); ?>
						</a>
						<span class="arshid6social-group-card-meta">
							<span class="arshid6social-badge arshid6social-badge-<?php echo esc_attr( $group['status'] ); ?>">
								<?php echo esc_html( ucfirst( $group['status'] ) ); ?>
							</span>
							<?php
							printf(
								/* translators: %s: member count */
								esc_html__( '%s members', '6arshid-social-community' ),
								esc_html( number_format_i18n( $group['memberCount'] ?? 0 ) )
							);
							?>
						</span>
						<?php if ( is_user_logged_in() && empty( $group['isMember'] ) ) : ?>
						<button class="arshid6social-btn arshid6social-btn-secondary arshid6social-btn-sm arshid6social-group-join-btn"
							data-group-id="<?php echo esc_attr( $group['id'] ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_join_group_' . $group['id'] ) ); ?>">
							<?php esc_html_e( 'Join', '6arshid-social-community' ); ?>
						</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php endforeach; ?>

		<?php else : ?>
			<?php
			// Show skeleton loaders; JS replaces them once loaded.
			for ( $i = 0; $i < 6; $i++ ) :
				?>
				<div class="arshid6social-group-card arshid6social-card arshid6social-skeleton-item" role="listitem" aria-hidden="true">
					<div class="arshid6social-group-card-cover arshid6social-skeleton"></div>
					<div class="arshid6social-group-card-body">
						<div class="arshid6social-skeleton" style="width:48px;height:48px;border-radius:50%;flex-shrink:0;"></div>
						<div style="flex:1;">
							<div class="arshid6social-skeleton" style="height:14px;width:60%;margin-block-end:.5rem;"></div>
							<div class="arshid6social-skeleton" style="height:12px;width:40%;"></div>
						</div>
					</div>
				</div>
			<?php endfor; ?>
		<?php endif; ?>

	</div>

	<div class="arshid6social-pagination" id="arshid6social-group-pagination"
		role="navigation" aria-label="<?php esc_attr_e( 'Groups pagination', '6arshid-social-community' ); ?>">
	</div>
</div>
