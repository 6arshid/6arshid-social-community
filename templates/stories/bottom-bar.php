<?php
/**
 * Stories bottom bar — fixed horizontal strip at the bottom of every plugin page.
 *
 * Available variables:
 *   $stories    array
 *   $viewer_id  int
 *   $stories_obj \Arshid6Social\Components\Stories\Stories
 *
 * @package Arshid6Social
 */
defined( 'ABSPATH' ) || exit;

if ( empty( $stories ) && ! is_user_logged_in() ) {
	return;
}
?>
<div class="sn-stories-bottom-bar" id="sn-stories-bottom-bar" aria-label="<?php esc_attr_e( 'Stories', '6arshid-social-community-main' ); ?>">
	<div class="sn-stories-bottom-bar__inner" role="list">

		<?php if ( is_user_logged_in() ) : ?>
		<div class="sn-story-bubble sn-story-bubble--own" role="listitem">
			<button class="sn-story-bubble__avatar-btn" id="sn-add-story-btn-bar"
			        aria-label="<?php esc_attr_e( 'Add Story', '6arshid-social-community-main' ); ?>">
				<span class="sn-story-bubble__ring sn-story-bubble__ring--add"></span>
				<img class="sn-story-bubble__avatar"
				     src="<?php echo esc_url( get_avatar_url( $viewer_id, array( 'size' => 48 ) ) ); ?>"
				     alt="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>"
				     width="48" height="48">
				<span class="sn-story-bubble__add-icon" aria-hidden="true">+</span>
			</button>
			<span class="sn-story-bubble__name"><?php esc_html_e( 'Your Story', '6arshid-social-community-main' ); ?></span>
		</div>
		<?php endif; ?>

		<?php foreach ( $stories as $story ) :
			$story_user_id = (int) $story->user_id;
			$is_own        = $story_user_id === $viewer_id;
			$has_unseen    = (int) $story->unseen_count > 0;
			$ring_class    = $has_unseen ? 'sn-story-bubble__ring--unseen' : 'sn-story-bubble__ring--seen';
		?>
		<div class="sn-story-bubble<?php echo $is_own ? ' sn-story-bubble--own' : ''; ?>"
		     role="listitem"
		     data-story-id="<?php echo esc_attr( $story->id ); ?>"
		     data-user-id="<?php echo esc_attr( $story_user_id ); ?>">
			<button class="sn-story-bubble__avatar-btn sn-open-story"
			        data-story-id="<?php echo esc_attr( $story->id ); ?>"
			        aria-label="<?php echo esc_attr( sprintf( /* translators: %s: user display name */ __( '%s\'s story', '6arshid-social-community-main' ), $story->display_name ) ); ?>">
				<span class="sn-story-bubble__ring <?php echo esc_attr( $ring_class ); ?>"></span>
				<img class="sn-story-bubble__avatar"
				     src="<?php echo esc_url( get_avatar_url( $story_user_id, array( 'size' => 48 ) ) ); ?>"
				     alt="<?php echo esc_attr( $story->display_name ); ?>"
				     width="48" height="48">
			</button>
			<span class="sn-story-bubble__name"><?php echo esc_html( $story->display_name ); ?></span>
		</div>
		<?php endforeach; ?>

	</div>
</div>
