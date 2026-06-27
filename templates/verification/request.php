<?php
/**
 * Verification request form — shortcode [sn_verification_request].
 *
 * @package Arshid6Social
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	echo '<p>' . esc_html__( 'You must be logged in to request verification.', '6arshid-social-community-main' ) . '</p>';
	return;
}

$verification = ARSHID6SOCIAL()->component( 'verification' );
$user_id      = isset( $user_id ) ? (int) $user_id : get_current_user_id();
$is_verified  = $verification && $verification->is_verified( $user_id );
$pending      = $verification ? $verification->get_pending_request( $user_id ) : null;
$types        = $verification ? $verification->get_types() : array();
$require_doc  = (bool) get_option( 'arshid6social_verification_require_doc', false );
?>
<div class="sn-verification-request" id="sn-verification-request">

	<?php if ( $is_verified ) : ?>
	<div class="sn-verification-request__verified sn-alert sn-alert--success">
		<?php echo wp_kses_post( $verification->get_badge_html( $user_id ) ); ?>
		<span><?php esc_html_e( 'Your account is verified.', '6arshid-social-community-main' ); ?></span>
	</div>

	<?php elseif ( $pending && 'more_info' === $pending->status ) : ?>
	<div class="sn-verification-request__more-info sn-alert sn-alert--warning">
		<strong><?php esc_html_e( 'Additional information required', '6arshid-social-community-main' ); ?></strong>
		<?php if ( ! empty( $pending->reason ) ) : ?>
		<p><?php echo esc_html( $pending->reason ); ?></p>
		<?php endif; ?>
	</div>

	<form class="sn-verification-request__form" id="sn-verification-form"
	      enctype="multipart/form-data" novalidate data-mode="resubmit">
		<?php wp_nonce_field( 'arshid6social_ajax_nonce', 'nonce' ); ?>

		<?php
		$saved_fields = json_decode( $pending->fields_json ?? '{}', true );
		?>

		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-name">
				<?php esc_html_e( 'Full Legal Name', '6arshid-social-community-main' ); ?> <span aria-hidden="true">*</span>
			</label>
			<input type="text" id="sn-verify-name" name="full_name"
			       class="sn-form-field__input"
			       value="<?php echo esc_attr( $saved_fields['full_name'] ?? '' ); ?>"
			       maxlength="100" required>
		</div>

		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-category">
				<?php esc_html_e( 'Category', '6arshid-social-community-main' ); ?>
			</label>
			<input type="text" id="sn-verify-category" name="category"
			       class="sn-form-field__input"
			       value="<?php echo esc_attr( $saved_fields['category'] ?? '' ); ?>"
			       placeholder="<?php esc_attr_e( 'e.g. Journalist, Athlete, Business…', '6arshid-social-community-main' ); ?>"
			       maxlength="100">
		</div>

		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-links">
				<?php esc_html_e( 'Supporting Links', '6arshid-social-community-main' ); ?>
			</label>
			<textarea id="sn-verify-links" name="links" class="sn-form-field__textarea" rows="3"
			          placeholder="<?php esc_attr_e( 'News articles, official pages, Wikipedia, etc. (one per line)', '6arshid-social-community-main' ); ?>"
			          maxlength="1000"><?php echo esc_textarea( $saved_fields['links'] ?? '' ); ?></textarea>
		</div>

		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-doc">
				<?php esc_html_e( 'Identity Document', '6arshid-social-community-main' ); ?>
				<?php if ( $require_doc ) : ?>
					<span aria-hidden="true">*</span>
				<?php else : ?>
					<span class="sn-form-field__hint"><?php esc_html_e( 'Optional', '6arshid-social-community-main' ); ?></span>
				<?php endif; ?>
				<span class="sn-form-field__hint">
					<?php esc_html_e( 'PDF or image. Stored securely and not publicly accessible.', '6arshid-social-community-main' ); ?>
				</span>
			</label>
			<input type="file" id="sn-verify-doc" name="document"
			       class="sn-form-field__file"
			       accept="image/jpeg,image/png,application/pdf"
			       <?php echo $require_doc ? 'required' : ''; ?>>
		</div>

		<div class="sn-verification-request__feedback" id="sn-verify-feedback" role="alert" hidden></div>

		<button type="submit" class="sn-btn sn-btn--primary" id="sn-verify-submit">
			<?php esc_html_e( 'Submit Additional Information', '6arshid-social-community-main' ); ?>
		</button>
	</form>

	<?php elseif ( $pending ) : ?>
	<div class="sn-verification-request__pending sn-alert sn-alert--info">
		<strong><?php esc_html_e( 'Request pending', '6arshid-social-community-main' ); ?></strong>
		<p><?php esc_html_e( 'Your verification request is under review. We\'ll notify you when a decision is made.', '6arshid-social-community-main' ); ?></p>
		<p class="sn-verification-request__submitted">
			<?php
			echo esc_html( sprintf(
				/* translators: %s: date */
				__( 'Submitted: %s', '6arshid-social-community-main' ),
				wp_date( get_option( 'date_format' ), strtotime( $pending->created_at ) )
			) );
			?>
		</p>
	</div>

	<?php else : ?>
	<form class="sn-verification-request__form" id="sn-verification-form"
	      enctype="multipart/form-data" novalidate>
		<?php wp_nonce_field( 'arshid6social_ajax_nonce', 'nonce' ); ?>

		<p class="sn-verification-request__intro">
			<?php esc_html_e( 'Request account verification. Complete the form below and we\'ll review your application.', '6arshid-social-community-main' ); ?>
		</p>

		<!-- Verification type -->
		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-type">
				<?php esc_html_e( 'Verification Type', '6arshid-social-community-main' ); ?> <span aria-hidden="true">*</span>
			</label>
			<select id="sn-verify-type" name="type" class="sn-form-field__select" required>
				<option value=""><?php esc_html_e( '— Select —', '6arshid-social-community-main' ); ?></option>
				<?php foreach ( $types as $key => $type ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( $type['label'] ); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>

		<!-- Full name -->
		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-name">
				<?php esc_html_e( 'Full Legal Name', '6arshid-social-community-main' ); ?> <span aria-hidden="true">*</span>
			</label>
			<input type="text" id="sn-verify-name" name="full_name"
			       class="sn-form-field__input"
			       placeholder="<?php esc_attr_e( 'As it appears on your ID', '6arshid-social-community-main' ); ?>"
			       maxlength="100" required>
		</div>

		<!-- Category / niche -->
		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-category">
				<?php esc_html_e( 'Category', '6arshid-social-community-main' ); ?>
			</label>
			<input type="text" id="sn-verify-category" name="category"
			       class="sn-form-field__input"
			       placeholder="<?php esc_attr_e( 'e.g. Journalist, Athlete, Business…', '6arshid-social-community-main' ); ?>"
			       maxlength="100">
		</div>

		<!-- Supporting links -->
		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-links">
				<?php esc_html_e( 'Supporting Links', '6arshid-social-community-main' ); ?>
			</label>
			<textarea id="sn-verify-links" name="links" class="sn-form-field__textarea" rows="3"
			          placeholder="<?php esc_attr_e( 'News articles, official pages, Wikipedia, etc. (one per line)', '6arshid-social-community-main' ); ?>"
			          maxlength="1000"></textarea>
		</div>

		<!-- Document upload -->
		<div class="sn-form-field">
			<label class="sn-form-field__label" for="sn-verify-doc">
				<?php esc_html_e( 'Identity Document', '6arshid-social-community-main' ); ?>
				<?php if ( $require_doc ) : ?>
					<span aria-hidden="true">*</span>
				<?php else : ?>
					<span class="sn-form-field__hint"><?php esc_html_e( 'Optional', '6arshid-social-community-main' ); ?></span>
				<?php endif; ?>
				<span class="sn-form-field__hint">
					<?php esc_html_e( 'PDF or image. Stored securely and not publicly accessible.', '6arshid-social-community-main' ); ?>
				</span>
			</label>
			<input type="file" id="sn-verify-doc" name="document"
			       class="sn-form-field__file"
			       accept="image/jpeg,image/png,application/pdf"
			       <?php echo $require_doc ? 'required' : ''; ?>>
		</div>

		<!-- Error / success -->
		<div class="sn-verification-request__feedback" id="sn-verify-feedback" role="alert" hidden></div>

		<button type="submit" class="sn-btn sn-btn--primary" id="sn-verify-submit">
			<?php esc_html_e( 'Submit Request', '6arshid-social-community-main' ); ?>
		</button>
	</form>
	<?php endif; ?>
</div><!-- /.sn-verification-request -->
