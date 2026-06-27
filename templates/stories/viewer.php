<?php
/**
 * Story viewer overlay — full-screen lightbox rendered once per page.
 * JavaScript (stories.js) populates and controls it.
 *
 * @package Arshid6Social
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="sn-story-viewer" id="sn-story-viewer" role="dialog" aria-modal="true"
     aria-label="<?php esc_attr_e( 'Story Viewer', '6arshid-social-community-main' ); ?>" hidden>

	<!-- Progress bar row -->
	<div class="sn-story-viewer__progress" id="sn-story-progress" aria-hidden="true"></div>

	<!-- Header -->
	<header class="sn-story-viewer__header">
		<a class="sn-story-viewer__user-link" id="sn-story-user-link" href="#">
			<img class="sn-story-viewer__avatar" id="sn-story-avatar" src="" alt="" width="40" height="40">
			<div class="sn-story-viewer__meta">
				<span class="sn-story-viewer__name" id="sn-story-name"></span>
				<span class="sn-story-viewer__time" id="sn-story-time"></span>
			</div>
		</a>
		<div class="sn-story-viewer__header-actions">
			<!-- Mute / Options menu -->
			<button class="sn-story-viewer__btn sn-story-mute-btn"
			        id="sn-story-mute-btn"
			        aria-label="<?php esc_attr_e( 'Mute stories', '6arshid-social-community-main' ); ?>"
			        title="<?php esc_attr_e( 'Mute', '6arshid-social-community-main' ); ?>"
			        hidden>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/>
					<path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/>
					<line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
				</svg>
			</button>
			<!-- Report -->
			<button class="sn-story-viewer__btn sn-story-report-btn"
			        id="sn-story-report-btn"
			        aria-label="<?php esc_attr_e( 'Report story', '6arshid-social-community-main' ); ?>"
			        title="<?php esc_attr_e( 'Report', '6arshid-social-community-main' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
				</svg>
			</button>
			<!-- Delete (own story) -->
			<button class="sn-story-viewer__btn sn-story-delete-btn"
			        id="sn-story-delete-btn"
			        aria-label="<?php esc_attr_e( 'Delete story', '6arshid-social-community-main' ); ?>"
			        title="<?php esc_attr_e( 'Delete', '6arshid-social-community-main' ); ?>"
			        hidden>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
					<path d="M10 11v6"/><path d="M14 11v6"/>
				</svg>
			</button>
			<!-- Add to Highlight (own story) -->
			<button class="sn-story-viewer__btn sn-story-highlight-btn"
			        id="sn-story-highlight-btn"
			        aria-label="<?php esc_attr_e( 'Add to Highlights', '6arshid-social-community-main' ); ?>"
			        title="<?php esc_attr_e( 'Add to Highlights', '6arshid-social-community-main' ); ?>"
			        hidden>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
				</svg>
			</button>
			<!-- Close -->
			<button class="sn-story-viewer__btn sn-story-viewer__close"
			        id="sn-story-viewer-close"
			        aria-label="<?php esc_attr_e( 'Close', '6arshid-social-community-main' ); ?>">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
				</svg>
			</button>
		</div>
	</header>

	<!-- Navigation arrows -->
	<button class="sn-story-viewer__nav sn-story-viewer__nav--prev" id="sn-story-prev"
	        aria-label="<?php esc_attr_e( 'Previous', '6arshid-social-community-main' ); ?>">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<polyline points="15 18 9 12 15 6"/>
		</svg>
	</button>

	<!-- Media / text content area -->
	<div class="sn-story-viewer__content" id="sn-story-content">
		<div class="sn-story-viewer__media-wrap" id="sn-story-media-wrap">
			<img class="sn-story-viewer__media-img" id="sn-story-media-img" src="" alt="" hidden>
			<video class="sn-story-viewer__media-video" id="sn-story-media-video"
			       playsinline muted hidden></video>
			<div class="sn-story-viewer__text-card" id="sn-story-text-card" hidden></div>
		</div>
	</div>

	<button class="sn-story-viewer__nav sn-story-viewer__nav--next" id="sn-story-next"
	        aria-label="<?php esc_attr_e( 'Next', '6arshid-social-community-main' ); ?>">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
			<polyline points="9 18 15 12 9 6"/>
		</svg>
	</button>

	<!-- Footer: viewers count (owner) + reaction tray + reply input -->
	<footer class="sn-story-viewer__footer">
		<button class="sn-story-viewer__viewers-btn" id="sn-story-viewers-btn" hidden
		        aria-label="<?php esc_attr_e( 'View viewers', '6arshid-social-community-main' ); ?>">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
			</svg>
			<span class="sn-story-viewer__viewers-count" id="sn-story-viewers-count">0</span>
		</button>

		<!-- Emoji reaction row -->
		<div class="sn-story-viewer__reactions" id="sn-story-reactions" role="toolbar"
		     aria-label="<?php esc_attr_e( 'React to story', '6arshid-social-community-main' ); ?>">
			<?php foreach ( array( '❤️', '😂', '😮', '😢', '😡', '👏', '🔥', '🙏' ) as $emoji ) : ?>
			<button class="sn-story-viewer__reaction-btn sn-react-story"
			        data-reaction="<?php echo esc_attr( $emoji ); ?>"
			        aria-label="<?php echo esc_attr( $emoji ); ?>">
				<?php echo esc_html( $emoji ); ?>
			</button>
			<?php endforeach; ?>
		</div>

		<!-- Reply input -->
		<?php if ( is_user_logged_in() && get_option( 'arshid6social_messages_story_enabled', false ) ) : ?>
		<div class="sn-story-viewer__reply" id="sn-story-reply-wrap">
			<input type="text" class="sn-story-viewer__reply-input" id="sn-story-reply-input"
			       placeholder="<?php esc_attr_e( 'Reply to story…', '6arshid-social-community-main' ); ?>"
			       maxlength="500" autocomplete="off">
			<button class="sn-story-viewer__reply-send" id="sn-story-reply-send"
			        aria-label="<?php esc_attr_e( 'Send reply', '6arshid-social-community-main' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
				</svg>
			</button>
		</div>
		<?php endif; ?>
	</footer>

	<!-- Viewers panel (sliding) -->
	<div class="sn-story-viewer__viewers-panel" id="sn-story-viewers-panel" hidden aria-live="polite"></div>
</div><!-- /.sn-story-viewer -->
