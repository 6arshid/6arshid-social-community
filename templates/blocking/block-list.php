<?php
/**
 * Block list shortcode template.
 *
 * Available variables:
 *   $blocks   array   Block rows (with blocked_id, date_created, reason)
 *   $page     int     Current page
 *   $has_more bool    Whether more pages exist
 *   $user_id  int     Current user ID
 *
 * @package Arshid6Social
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="sn-block-list" id="sn-block-list" data-page="<?php echo esc_attr( $page ); ?>">
	<h3 class="sn-block-list__title"><?php esc_html_e( 'Blocked Users', '6arshid-social-community' ); ?></h3>

	<?php if ( empty( $blocks ) ) : ?>
	<p class="sn-block-list__empty"><?php esc_html_e( 'You have not blocked anyone.', '6arshid-social-community' ); ?></p>
	<?php else : ?>

	<ul class="sn-block-list__items" role="list">
	<?php foreach ( $blocks as $block ) :
		$blocked_user = get_userdata( (int) $block->blocked_id );
		if ( ! $blocked_user ) {
			continue;
		}
	?>
	<li class="sn-block-list__item" data-user-id="<?php echo esc_attr( $block->blocked_id ); ?>">
		<a class="sn-block-list__avatar-link"
		   href="<?php echo esc_url( home_url( '/members/' . $blocked_user->user_nicename . '/' ) ); ?>">
			<?php echo get_avatar( $blocked_user->ID, 40, '', esc_attr( $blocked_user->display_name ) ); ?>
		</a>
		<div class="sn-block-list__info">
			<a class="sn-block-list__name"
			   href="<?php echo esc_url( home_url( '/members/' . $blocked_user->user_nicename . '/' ) ); ?>">
				<?php echo esc_html( $blocked_user->display_name ); ?>
			</a>
			<span class="sn-block-list__date">
				<?php
				echo esc_html( sprintf(
					/* translators: %s: human-readable date */
					__( 'Blocked %s', '6arshid-social-community' ),
					human_time_diff( strtotime( $block->date_created ) ) . ' ' . __( 'ago', '6arshid-social-community' )
				) );
				?>
			</span>
			<?php if ( ! empty( $block->reason ) && get_option( 'arshid6social_blocking_show_reason', true ) ) : ?>
			<span class="sn-block-list__reason">
				<?php echo esc_html( $block->reason ); ?>
			</span>
			<?php endif; ?>
		</div>
		<button class="sn-btn sn-btn--danger-outline sn-unblock-btn"
		        data-user-id="<?php echo esc_attr( $block->blocked_id ); ?>"
		        data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_ajax_nonce' ) ); ?>">
			<?php esc_html_e( 'Unblock', '6arshid-social-community' ); ?>
		</button>
	</li>
	<?php endforeach; ?>
	</ul>

	<?php if ( $has_more || $page > 1 ) : ?>
	<div class="sn-block-list__pagination">
		<?php if ( $page > 1 ) : ?>
		<a class="sn-btn sn-btn--secondary"
		   href="<?php echo esc_url( add_query_arg( 'block_page', $page - 1 ) ); ?>">
			<?php esc_html_e( '← Previous', '6arshid-social-community' ); ?>
		</a>
		<?php endif; ?>
		<?php if ( $has_more ) : ?>
		<a class="sn-btn sn-btn--secondary"
		   href="<?php echo esc_url( add_query_arg( 'block_page', $page + 1 ) ); ?>">
			<?php esc_html_e( 'Next →', '6arshid-social-community' ); ?>
		</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php endif; ?>
</div><!-- /.sn-block-list -->
