<?php
/**
 * Story creator modal — shown when user clicks "Add Story".
 *
 * @package Arshid6Social
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	return;
}
?>
<div class="sn-story-creator" id="sn-story-creator" role="dialog" aria-modal="true"
     aria-label="<?php esc_attr_e( 'Create Story', '6arshid-social-community' ); ?>" hidden>

<div class="sn-story-creator__panel">
	<header class="sn-story-creator__header">
		<h2 class="sn-story-creator__title"><?php esc_html_e( 'Create Story', '6arshid-social-community' ); ?></h2>
		<button class="sn-story-creator__close" id="sn-story-creator-close"
		        aria-label="<?php esc_attr_e( 'Close', '6arshid-social-community' ); ?>">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
			</svg>
		</button>
	</header>

	<!-- Type selector -->
	<div class="sn-story-creator__type-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Story type', '6arshid-social-community' ); ?>">
		<button role="tab" aria-selected="true" class="sn-story-tab sn-story-tab--active" data-type="text">
			<?php esc_html_e( 'Text', '6arshid-social-community' ); ?>
		</button>
		<button role="tab" aria-selected="false" class="sn-story-tab" data-type="image">
			<?php esc_html_e( 'Photo', '6arshid-social-community' ); ?>
		</button>
		<?php if ( get_option( 'arshid6social_stories_allow_video', true ) ) : ?>
		<button role="tab" aria-selected="false" class="sn-story-tab" data-type="video">
			<?php esc_html_e( 'Video', '6arshid-social-community' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<!-- Preview canvas -->
	<div class="sn-story-creator__preview" id="sn-story-preview">
		<!-- Text card panel -->
		<div class="sn-story-creator__text-panel" id="sn-creator-text-panel">
			<div class="sn-story-creator__text-card" id="sn-creator-text-card"
			     style="background: #2563eb;">
				<textarea class="sn-story-creator__text-input" id="sn-creator-text"
				          placeholder="<?php esc_attr_e( 'Write something…', '6arshid-social-community' ); ?>"
				          maxlength="280" rows="3"></textarea>
			</div>
			<div class="sn-story-creator__bg-colors" role="group"
			     aria-label="<?php esc_attr_e( 'Background colour', '6arshid-social-community' ); ?>">
				<?php
				$bg_colors = array( '#2563eb', '#16a34a', '#dc2626', '#9333ea', '#f97316', '#0f172a', '#ffffff' );
				foreach ( $bg_colors as $color ) :
				?>
				<button class="sn-story-creator__bg-swatch<?php echo '#2563eb' === $color ? ' sn-story-creator__bg-swatch--active' : ''; ?>"
				        style="background:<?php echo esc_attr( $color ); ?>;"
				        data-color="<?php echo esc_attr( $color ); ?>"
				        aria-label="<?php echo esc_attr( $color ); ?>"></button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Media upload panel -->
		<div class="sn-story-creator__media-panel" id="sn-creator-media-panel" hidden>
			<label class="sn-story-creator__upload-area" id="sn-creator-upload-area"
			       for="sn-creator-file-input">
				<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
					<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
					<polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
				</svg>
				<span><?php esc_html_e( 'Click or drag to upload', '6arshid-social-community' ); ?></span>
				<input type="file" id="sn-creator-file-input" accept="image/*,video/*" class="sn-visually-hidden">
			</label>
			<div class="sn-story-creator__media-preview-wrap" id="sn-creator-media-preview" hidden>
				<img id="sn-creator-preview-img" src="" alt="" hidden>
				<video id="sn-creator-preview-video" src="" controls hidden></video>
				<button class="sn-story-creator__remove-media" id="sn-creator-remove-media"
				        aria-label="<?php esc_attr_e( 'Remove media', '6arshid-social-community' ); ?>">✕</button>
			</div>
		</div>
	</div>

	<!-- Privacy selector -->
	<div class="sn-story-creator__privacy">
		<label class="sn-story-creator__label" for="sn-creator-privacy">
			<?php esc_html_e( 'Audience', '6arshid-social-community' ); ?>
		</label>
		<select id="sn-creator-privacy" name="privacy" class="sn-story-creator__select">
			<option value="public"><?php esc_html_e( 'Public', '6arshid-social-community' ); ?></option>
			<option value="friends"><?php esc_html_e( 'Friends', '6arshid-social-community' ); ?></option>
			<option value="followers"><?php esc_html_e( 'Followers', '6arshid-social-community' ); ?></option>
			<option value="close_friends"><?php esc_html_e( 'Close Friends', '6arshid-social-community' ); ?></option>
		</select>
	</div>

	<!-- Duration (text/image only) -->
	<div class="sn-story-creator__duration" id="sn-creator-duration-wrap">
		<label class="sn-story-creator__label" for="sn-creator-duration">
			<?php esc_html_e( 'Duration (seconds)', '6arshid-social-community' ); ?>
		</label>
		<input type="range" id="sn-creator-duration" name="duration"
		       min="3" max="15" value="5" class="sn-story-creator__range">
		<span id="sn-creator-duration-val">5s</span>
	</div>

	<!-- Actions -->
	<div class="sn-story-creator__actions">
		<button class="sn-btn sn-btn--secondary" id="sn-story-creator-cancel">
			<?php esc_html_e( 'Cancel', '6arshid-social-community' ); ?>
		</button>
		<button class="sn-btn sn-btn--primary" id="sn-story-creator-submit">
			<?php esc_html_e( 'Share Story', '6arshid-social-community' ); ?>
		</button>
	</div>

	<div class="sn-story-creator__error" id="sn-creator-error" role="alert" hidden></div>
</div><!-- /.sn-story-creator__panel -->
</div>
