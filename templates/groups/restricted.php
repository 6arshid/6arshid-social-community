<?php
/**
 * Shown when a non-member tries to access a private/hidden group.
 *
 * Variables available:
 *  @var array $group Formatted group array from Groups::format_group()
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="arshid6social-wrap">
<main class="arshid6social-layout">
	<div class="arshid6social-card" style="text-align:center;padding:3rem 2rem;max-width:480px;margin:4rem auto;">
		<span style="font-size:3rem;">🔒</span>
		<h1 style="margin-block-start:.75rem;"><?php echo esc_html( $group['name'] ); ?></h1>
		<p style="color:var(--arshid6social-text-muted);">
			<?php esc_html_e( 'This is a private group. You must be a member to view its contents.', '6arshid-social-community-main' ); ?>
		</p>

		<?php if ( is_user_logged_in() ) : ?>
			<?php $group_id = absint( $group['id'] ); ?>
			<button type="button" class="arshid6social-btn arshid6social-btn-primary arshid6social-group-join-btn"
				data-group-id="<?php echo esc_attr( $group_id ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_join_group_' . $group_id ) ); ?>">
				<?php esc_html_e( 'Request to Join', '6arshid-social-community-main' ); ?>
			</button>
		<?php else : ?>
			<a class="arshid6social-btn arshid6social-btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
				<?php esc_html_e( 'Log in to join', '6arshid-social-community-main' ); ?>
			</a>
		<?php endif; ?>

		<p style="margin-block-start:1.5rem;">
			<a href="<?php echo esc_url( home_url( '/groups/' ) ); ?>">&larr; <?php esc_html_e( 'Back to Groups', '6arshid-social-community-main' ); ?></a>
		</p>
	</div>
</main>
</div>
