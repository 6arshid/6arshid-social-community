<?php
/**
 * Stories tray — horizontal scrollable row displayed above the activity feed.
 *
 * Available variables:
 *   $stories    array   Tray rows from Stories::get_tray()
 *   $viewer_id  int     Current viewer user ID
 *   $stories_obj \Arshid6Social\Components\Stories\Stories
 *
 * @package Arshid6Social
 */
defined( 'ABSPATH' ) || exit;

if ( empty( $stories ) && ! is_user_logged_in() ) {
	return;
}
?>
<div class="sn-stories-tray" id="sn-stories-tray" aria-label="<?php esc_attr_e( 'Stories', '6arshid social community' ); ?>">
	<div class="sn-stories-tray__inner" role="list">

		<?php if ( is_user_logged_in() ) : ?>
		<div class="sn-story-bubble sn-story-bubble--own" role="listitem" data-user-id="<?php echo esc_attr( $viewer_id ); ?>">
			<button class="sn-story-bubble__avatar-btn" id="sn-add-story-btn"
			        aria-label="<?php esc_attr_e( 'Add Story', '6arshid social community' ); ?>">
				<span class="sn-story-bubble__ring sn-story-bubble__ring--add"></span>
				<img class="sn-story-bubble__avatar"
				     src="<?php echo esc_url( get_avatar_url( $viewer_id, array( 'size' => 56 ) ) ); ?>"
				     alt="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>"
				     width="56" height="56">
				<span class="sn-story-bubble__add-icon" aria-hidden="true">+</span>
			</button>
			<span class="sn-story-bubble__name"><?php esc_html_e( 'Your Story', '6arshid social community' ); ?></span>
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
			        aria-label="<?php echo esc_attr( sprintf( /* translators: %s: user display name */ __( '%s\'s story', '6arshid social community' ), $story->display_name ) ); ?>">
				<span class="sn-story-bubble__ring <?php echo esc_attr( $ring_class ); ?>"></span>
				<img class="sn-story-bubble__avatar"
				     src="<?php echo esc_url( get_avatar_url( $story_user_id, array( 'size' => 56 ) ) ); ?>"
				     alt="<?php echo esc_attr( $story->display_name ); ?>"
				     width="56" height="56">
			</button>
			<span class="sn-story-bubble__name"><?php echo esc_html( $story->display_name ); ?></span>
		</div>
		<?php endforeach; ?>

	</div><!-- /.sn-stories-tray__inner -->
</div><!-- /.sn-stories-tray -->

<?php if ( ! defined( 'ARSHID6SOCIAL_STORIES_OVERLAYS_RENDERED' ) ) :
	define( 'ARSHID6SOCIAL_STORIES_OVERLAYS_RENDERED', true );
	require __DIR__ . '/viewer.php';
	require __DIR__ . '/creator.php';
endif; ?>
