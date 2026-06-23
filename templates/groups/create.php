<?php
/**
 * Create group form template.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( home_url( '/groups/create/' ) ) );
	exit;
}
?>

<div class="arshid6social-wrap" id="arshid6social-create-group-page">
<div class="arshid6social-container" style="padding-block:2rem;">

	<div class="arshid6social-create-group-header">
		<a href="<?php echo esc_url( home_url( '/groups/' ) ); ?>" class="arshid6social-breadcrumb-back">
			&larr; <?php esc_html_e( 'Back to Groups', 'social-network-6' ); ?>
		</a>
		<h1 class="arshid6social-page-title"><?php esc_html_e( 'Create a Group', 'social-network-6' ); ?></h1>
	</div>

	<div class="arshid6social-create-group-layout">

		<!-- Main form -->
		<div class="arshid6social-card" style="padding:2rem;">
			<form class="arshid6social-create-group-form" method="post" novalidate>
				<?php wp_nonce_field( 'arshid6social_create_group', 'arshid6social_group_nonce' ); ?>

				<div class="arshid6social-form-group">
					<label class="arshid6social-label" for="arshid6social-group-name">
						<?php esc_html_e( 'Group Name', 'social-network-6' ); ?> <span aria-hidden="true" style="color:var(--arshid6social-danger);">*</span>
					</label>
					<input type="text" id="arshid6social-group-name" name="name" class="arshid6social-input"
						required maxlength="100" autocomplete="off"
						placeholder="<?php esc_attr_e( 'Enter a name for your group…', 'social-network-6' ); ?>" />
				</div>

				<div class="arshid6social-form-group">
					<label class="arshid6social-label" for="arshid6social-group-desc">
						<?php esc_html_e( 'Description', 'social-network-6' ); ?>
					</label>
					<textarea id="arshid6social-group-desc" name="description" class="arshid6social-input"
						rows="4" maxlength="1000" style="height:auto;"
						placeholder="<?php esc_attr_e( 'What is this group about?', 'social-network-6' ); ?>"></textarea>
				</div>

				<div class="arshid6social-form-group">
					<label class="arshid6social-label" for="arshid6social-group-status">
						<?php esc_html_e( 'Privacy', 'social-network-6' ); ?>
					</label>
					<select id="arshid6social-group-status" name="status" class="arshid6social-input">
						<option value="public"><?php esc_html_e( 'Public — anyone can join and see posts', 'social-network-6' ); ?></option>
						<option value="private"><?php esc_html_e( 'Private — anyone can request, but only members see posts', 'social-network-6' ); ?></option>
						<option value="hidden"><?php esc_html_e( 'Hidden — invite only, not listed in directory', 'social-network-6' ); ?></option>
					</select>
				</div>

				<div id="arshid6social-create-group-error" class="arshid6social-notice arshid6social-notice--error" hidden></div>

				<div class="arshid6social-form-actions">
					<a href="<?php echo esc_url( home_url( '/groups/' ) ); ?>" class="arshid6social-btn arshid6social-btn-secondary">
						<?php esc_html_e( 'Cancel', 'social-network-6' ); ?>
					</a>
					<button type="submit" class="arshid6social-btn arshid6social-btn-primary">
						<?php esc_html_e( 'Create Group', 'social-network-6' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Tips sidebar -->
		<aside class="arshid6social-card arshid6social-create-group-tips" style="padding:1.5rem;">
			<h3 style="margin:0 0 1rem;font-size:1rem;font-weight:600;"><?php esc_html_e( 'Privacy Settings', 'social-network-6' ); ?></h3>
			<ul style="margin:0;padding:0 0 0 1.25rem;display:flex;flex-direction:column;gap:.75rem;color:var(--arshid6social-text-muted);font-size:.875rem;">
				<li>
					<strong style="color:var(--arshid6social-text);"><?php esc_html_e( 'Public', 'social-network-6' ); ?></strong><br>
					<?php esc_html_e( 'Anyone can find, join, and read posts.', 'social-network-6' ); ?>
				</li>
				<li>
					<strong style="color:var(--arshid6social-text);"><?php esc_html_e( 'Private', 'social-network-6' ); ?></strong><br>
					<?php esc_html_e( 'Anyone can find it and request to join, but posts are only visible to members.', 'social-network-6' ); ?>
				</li>
				<li>
					<strong style="color:var(--arshid6social-text);"><?php esc_html_e( 'Hidden', 'social-network-6' ); ?></strong><br>
					<?php esc_html_e( 'Not listed in the directory. Invite-only access.', 'social-network-6' ); ?>
				</li>
			</ul>
		</aside>

	</div><!-- .arshid6social-create-group-layout -->

</div>
</div>

<?php
$create_group_js = '( function () {
	const form = document.querySelector( ".arshid6social-create-group-form" );
	if ( ! form ) return;

	const i18n = {
		creating:     ' . wp_json_encode( __( 'Creating…', 'social-network-6' ) ) . ',
		createGroup:  ' . wp_json_encode( __( 'Create Group', 'social-network-6' ) ) . ',
		couldNotCreate: ' . wp_json_encode( __( 'Could not create group.', 'social-network-6' ) ) . ',
		networkError: ' . wp_json_encode( __( 'Network error. Please try again.', 'social-network-6' ) ) . ',
		ajaxUrl:      ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',
		groupsUrl:    ' . wp_json_encode( home_url( '/groups/' ) ) . ',
	};

	form.addEventListener( "submit", async ( e ) => {
		e.preventDefault();
		const btn     = form.querySelector( "[type=\"submit\"]" );
		const errBox  = document.getElementById( "arshid6social-create-group-error" );
		const cfg     = window.ARSHID6SOCIALConfig || {};
		const body    = new FormData();
		body.append( "action", "arshid6social_create_group" );
		body.append( "nonce", cfg.ajaxNonce || "" );
		body.append( "name", form.querySelector( "[name=\"name\"]" ).value );
		body.append( "description", form.querySelector( "[name=\"description\"]" ).value );
		body.append( "status", form.querySelector( "[name=\"status\"]" ).value );

		btn.disabled = true;
		btn.textContent = i18n.creating;
		if ( errBox ) errBox.hidden = true;

		try {
			const res  = await fetch( cfg.ajaxUrl || i18n.ajaxUrl, { method: "POST", body } );
			const data = await res.json();
			const slug = data.data?.group?.slug || data.data?.slug;

			if ( data.success && slug ) {
				window.location.href = i18n.groupsUrl + slug + "/";
			} else if ( data.success ) {
				window.location.href = i18n.groupsUrl;
			} else {
				if ( errBox ) {
					errBox.textContent = data.data?.message || i18n.couldNotCreate;
					errBox.hidden = false;
				}
				btn.disabled = false;
				btn.textContent = i18n.createGroup;
			}
		} catch ( err ) {
			if ( errBox ) {
				errBox.textContent = i18n.networkError;
				errBox.hidden = false;
			}
			btn.disabled = false;
			btn.textContent = i18n.createGroup;
		}
	} );
} )();';
wp_add_inline_script( 'arshid6social-main', $create_group_js );
?>
