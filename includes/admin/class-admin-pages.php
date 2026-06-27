<?php
namespace Arshid6Social\Admin;

/**
 * Admin "Pages & Shortcodes" screen.
 *
 * Shows all auto-created pages with their status, URL, and copy-able shortcodes.
 * Also allows re-creating a page if it was accidentally deleted.
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Pages
 */
class Admin_Pages {

	public function __construct() {
		add_action( 'admin_enqueue_scripts',               array( $this, 'enqueue_page_styles' ) );
		add_action( 'wp_ajax_arshid6social_recreate_page', array( $this, 'ajax_recreate_page' ) );
		add_action( 'wp_ajax_arshid6social_assign_page',   array( $this, 'ajax_assign_page' ) );
	}

	public function enqueue_page_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'arshid6social-pages' ) ) {
			return;
		}
		wp_add_inline_style( 'arshid6social-admin', '.arshid6social-pages-table{width:100%;border-collapse:collapse;margin-block-start:1.5rem}.arshid6social-pages-table th,.arshid6social-pages-table td{padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;text-align:left}.arshid6social-pages-table th{background:#f8fafc;font-weight:600;font-size:.8125rem;text-transform:uppercase;letter-spacing:.04em;color:#475569}.arshid6social-pages-table tr:last-child td{border-bottom:none}.arshid6social-shortcode-box{display:flex;align-items:center;gap:.5rem}.arshid6social-shortcode-code{font-family:monospace;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:4px;padding:.25rem .625rem;font-size:.875rem;color:#1e40af}.arshid6social-copy-btn{cursor:pointer;background:#e0e7ff;border:none;border-radius:4px;padding:.25rem .625rem;font-size:.8125rem;color:#3730a3}.arshid6social-copy-btn:hover{background:#c7d2fe}.arshid6social-status-ok{color:#16a34a;font-weight:600}.arshid6social-status-missing{color:#dc2626;font-weight:600}.arshid6social-recreate-btn{background:#fef9c3;border:1px solid #fde047;border-radius:4px;padding:.25rem .75rem;cursor:pointer;font-size:.8125rem;color:#713f12}.arshid6social-sc-ref{margin-block-start:2rem}.arshid6social-sc-ref table{width:100%;border-collapse:collapse}.arshid6social-sc-ref th,.arshid6social-sc-ref td{padding:.625rem 1rem;border-bottom:1px solid #e2e8f0;text-align:left;font-size:.875rem}.arshid6social-sc-ref th{background:#f8fafc;font-weight:600;color:#475569}.arshid6social-sc-ref code{font-family:monospace;background:#f1f5f9;padding:.2rem .5rem;border-radius:3px;color:#1e40af}.arshid6social-assign-select{font-size:.875rem;border-radius:4px;border:1px solid #cbd5e1;padding:.3rem .5rem}.arshid6social-assign-view{font-size:1rem;text-decoration:none;opacity:.7}.arshid6social-assign-view:hover{opacity:1}' );
	}

	/**
	 * Renders the Pages & Shortcodes admin screen.
	 */
	public function render(): void {
		$pages = $this->get_page_definitions();
		?>
		<div class="wrap arshid6social-admin-pages">
			<h1><?php esc_html_e( 'Social Network — Pages & Shortcodes', '6arshid-social-community-main' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'These pages were created automatically when the plugin was activated. Each page uses a shortcode to display the social network content. You can copy the shortcode and paste it into any page or widget.', '6arshid-social-community-main' ); ?>
			</p>

			<?php $this->render_pages_table( $pages ); ?>
			<?php $this->render_shortcode_reference(); ?>
		</div>

		<?php
		$nonce_recreate = wp_create_nonce( 'arshid6social_recreate_page' );
		$nonce_assign   = wp_create_nonce( 'arshid6social_assign_page' );
		$js_pages  = '(function(){';
		$js_pages .= 'var nonceRecreate=' . wp_json_encode( $nonce_recreate ) . ';';
		$js_pages .= 'var nonceAssign=' . wp_json_encode( $nonce_assign ) . ';';
		$js_pages .= 'var txtRecreate=' . wp_json_encode( __( 'Re-create this page?', '6arshid-social-community-main' ) ) . ';';
		$js_pages .= 'var txtSaved=' . wp_json_encode( '✓ ' . __( 'Saved', '6arshid-social-community-main' ) ) . ';';
		$js_pages .= 'var txtError=' . wp_json_encode( __( 'Error.', '6arshid-social-community-main' ) ) . ';';
		$js_pages .= <<<'ENDJS'
document.querySelectorAll('.arshid6social-copy-btn').forEach(function(btn){
	btn.addEventListener('click',function(){
		var code=btn.previousElementSibling.textContent;
		navigator.clipboard.writeText(code).then(function(){
			var orig=btn.textContent;btn.textContent='✓ Copied!';
			setTimeout(function(){btn.textContent=orig;},1800);
		});
	});
});
document.querySelectorAll('.arshid6social-recreate-btn').forEach(function(btn){
	btn.addEventListener('click',async function(){
		if(!confirm(txtRecreate))return;
		btn.disabled=true;
		var body=new FormData();
		body.append('action','arshid6social_recreate_page');
		body.append('nonce',nonceRecreate);
		body.append('page_key',btn.dataset.pageKey);
		var res=await fetch(ajaxurl,{method:'POST',body:body});
		var data=await res.json();
		if(data.success){location.reload();}else{alert(data.data&&data.data.message?data.data.message:'Error.');btn.disabled=false;}
	});
});
document.querySelectorAll('.arshid6social-assign-save').forEach(function(btn){
	btn.addEventListener('click',async function(){
		var wrap=btn.closest('.arshid6social-assign-page-wrap');
		var select=wrap.querySelector('.arshid6social-assign-select');
		var msgEl=wrap.querySelector('.arshid6social-assign-msg');
		var pageKey=btn.dataset.pageKey;var pageId=select.value;
		btn.disabled=true;msgEl.style.display='none';
		var body=new FormData();
		body.append('action','arshid6social_assign_page');
		body.append('nonce',nonceAssign);
		body.append('page_key',pageKey);
		body.append('page_id',pageId);
		try{
			var res=await fetch(ajaxurl,{method:'POST',body:body});
			var data=await res.json();
			if(data.success){msgEl.textContent=txtSaved;msgEl.style.color='#16a34a';msgEl.style.display='inline';setTimeout(function(){location.reload();},900);}
			else{msgEl.textContent=(data.data&&data.data.message)?data.data.message:txtError;msgEl.style.color='#dc2626';msgEl.style.display='inline';}
		}catch{msgEl.textContent=txtError;msgEl.style.color='#dc2626';msgEl.style.display='inline';}
		finally{btn.disabled=false;}
	});
});
})();
ENDJS;
		wp_add_inline_script( 'arshid6social-admin', $js_pages );
	}

	/**
	 * Renders the main pages status table.
	 */
	private function render_pages_table( array $pages ): void {
		$all_wp_pages = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
		?>
		<table class="arshid6social-pages-table widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Status', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Assigned Page', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Actions', '6arshid-social-community-main' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $key => $page ) : ?>
					<?php
					$page_id  = (int) get_option( $page['option'], 0 );
					$wp_page  = $page_id ? get_post( $page_id ) : null;
					$is_ok    = $wp_page && 'publish' === $wp_page->post_status;
					$page_url = $is_ok ? get_permalink( $wp_page->ID ) : '';
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $page['title'] ); ?></strong>
							<?php if ( $page['description'] ) : ?>
								<br><span style="color:#64748b;font-size:.8125rem;"><?php echo esc_html( $page['description'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_ok ) : ?>
								<span class="arshid6social-status-ok">&#10003; <?php esc_html_e( 'Active', '6arshid-social-community-main' ); ?></span>
							<?php else : ?>
								<span class="arshid6social-status-missing">&#10007; <?php esc_html_e( 'Missing', '6arshid-social-community-main' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<div class="arshid6social-assign-page-wrap" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
								<select class="arshid6social-assign-select" data-page-key="<?php echo esc_attr( $key ); ?>" data-option="<?php echo esc_attr( $page['option'] ); ?>" style="max-width:260px;">
									<option value="0"><?php esc_html_e( '— Not assigned —', '6arshid-social-community-main' ); ?></option>
									<?php foreach ( $all_wp_pages as $wp_p ) : ?>
										<option value="<?php echo esc_attr( $wp_p->ID ); ?>" <?php selected( $page_id, $wp_p->ID ); ?>>
											<?php echo esc_html( $wp_p->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button arshid6social-assign-save" data-page-key="<?php echo esc_attr( $key ); ?>">
									<?php esc_html_e( 'Save', '6arshid-social-community-main' ); ?>
								</button>
								<?php if ( $is_ok ) : ?>
									<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" class="arshid6social-assign-view" title="<?php esc_attr_e( 'View page', '6arshid-social-community-main' ); ?>">&#128279;</a>
								<?php endif; ?>
								<span class="arshid6social-assign-msg" style="font-size:.8125rem;color:#16a34a;display:none;"></span>
							</div>
						</td>
						<td>
							<div class="arshid6social-shortcode-box">
								<span class="arshid6social-shortcode-code"><?php echo esc_html( $page['shortcode'] ); ?></span>
								<button type="button" class="arshid6social-copy-btn"><?php esc_html_e( 'Copy', '6arshid-social-community-main' ); ?></button>
							</div>
						</td>
						<td>
							<?php if ( ! $is_ok ) : ?>
								<button type="button" class="arshid6social-recreate-btn" data-page-key="<?php echo esc_attr( $key ); ?>">
									<?php esc_html_e( 'Re-create Page', '6arshid-social-community-main' ); ?>
								</button>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the full shortcode reference section.
	 */
	private function render_shortcode_reference(): void {
		$shortcodes = array(
			array(
				'code'    => '[arshid6social_members]',
				'attrs'   => 'per_page="12" show_search="true" type="newest|active|alphabetical"',
				'desc'    => __( 'Displays the member directory with search bar and pagination.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_members per_page="20" type="active"]',
			),
			array(
				'code'    => '[arshid6social_activity]',
				'attrs'   => 'per_page="10" show_composer="true" scope="site|friends|self"',
				'desc'    => __( 'Displays an activity feed with infinite scroll. Logged-in users see a post composer.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_activity scope="friends" show_composer="true"]',
			),
			array(
				'code'    => '[arshid6social_groups]',
				'attrs'   => 'per_page="9" show_search="true" show_create="true" status="public|all"',
				'desc'    => __( 'Displays the group directory. Logged-in users see a "Create Group" button.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_groups per_page="12" status="public"]',
			),
			array(
				'code'    => '[arshid6social_messages]',
				'attrs'   => '—',
				'desc'    => __( 'Displays the private messages inbox. Redirects guests to login.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_messages]',
			),
			array(
				'code'    => '[arshid6social_notifications]',
				'attrs'   => '—',
				'desc'    => __( 'Displays the current user\'s notifications feed with mark-all-read and customizable preferences. Redirects guests to login.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_notifications]',
			),
			array(
				'code'    => '[arshid6social_profile]',
				'attrs'   => 'id="0" slug=""',
				'desc'    => __( 'Displays a user profile. Uses the current logged-in user if no id or slug is given.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_profile id="5"]',
			),
			array(
				'code'    => '[arshid6social_login_form]',
				'attrs'   => 'redirect=""',
				'desc'    => __( 'Displays a styled login form. Hidden when the user is already logged in.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_login_form redirect="/members/"]',
			),
			array(
				'code'    => '[arshid6social_register_form]',
				'attrs'   => '—',
				'desc'    => __( 'Displays a registration form. Respects the "Allow Registration" setting.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_register_form]',
			),
			array(
				'code'    => '[arshid6social_forgot_password]',
				'attrs'   => '—',
				'desc'    => __( 'Custom forgot-password form. Sends a reset email linking to the Reset Password page — no wp-login.php.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_forgot_password]',
			),
			array(
				'code'    => '[arshid6social_reset_password]',
				'attrs'   => '—',
				'desc'    => __( 'Custom reset-password form. Reads the key and login from the email link and sets a new password — no wp-login.php.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_reset_password]',
			),
			array(
				'code'    => '[arshid6social_bookmarks]',
				'attrs'   => 'per_page="20"',
				'desc'    => __( 'Displays the current user\'s saved posts and bookmarked marketplace listings.', '6arshid-social-community-main' ),
				'example' => '[arshid6social_bookmarks per_page="20"]',
			),
		);
		?>
		<div class="arshid6social-sc-ref">
			<h2><?php esc_html_e( 'Shortcode Reference', '6arshid-social-community-main' ); ?></h2>
			<p class="description"><?php esc_html_e( 'All shortcodes can be placed in any page, post, or widget. Gutenberg users can also use the dedicated blocks (search for "Social Network" in the block inserter).', '6arshid-social-community-main' ); ?></p>

			<table class="widefat">
				<thead>
					<tr>
						<th style="width:180px;"><?php esc_html_e( 'Shortcode', '6arshid-social-community-main' ); ?></th>
						<th><?php esc_html_e( 'Available Attributes', '6arshid-social-community-main' ); ?></th>
						<th><?php esc_html_e( 'Description', '6arshid-social-community-main' ); ?></th>
						<th><?php esc_html_e( 'Example', '6arshid-social-community-main' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $shortcodes as $sc ) : ?>
						<tr>
							<td>
								<div class="arshid6social-shortcode-box">
									<code><?php echo esc_html( $sc['code'] ); ?></code>
									<button type="button" class="arshid6social-copy-btn"><?php esc_html_e( 'Copy', '6arshid-social-community-main' ); ?></button>
								</div>
							</td>
							<td><code style="font-size:.8125rem;color:#475569;"><?php echo esc_html( $sc['attrs'] ); ?></code></td>
							<td><?php echo esc_html( $sc['desc'] ); ?></td>
							<td>
								<div class="arshid6social-shortcode-box">
									<code><?php echo esc_html( $sc['example'] ); ?></code>
									<button type="button" class="arshid6social-copy-btn"><?php esc_html_e( 'Copy', '6arshid-social-community-main' ); ?></button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX: Re-creates a single missing page.
	 */
	public function ajax_recreate_page(): void {
		if ( ! check_ajax_referer( 'arshid6social_recreate_page', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		$page_key = sanitize_key( $_POST['page_key'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$pages    = $this->get_page_definitions();

		if ( ! isset( $pages[ $page_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown page key.', '6arshid-social-community-main' ) ) );
		}

		$page    = $pages[ $page_key ];
		$page_id = wp_insert_post(
			array(
				'post_title'     => $page['title'],
				'post_name'      => $page['slug'],
				'post_content'   => $page['shortcode'],
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( is_wp_error( $page_id ) ) {
			wp_send_json_error( array( 'message' => $page_id->get_error_message() ) );
		}

		update_option( $page['option'], $page_id );
		wp_send_json_success( array( 'page_id' => $page_id ) );
	}

	/**
	 * AJAX: Assigns an existing WordPress page to a plugin page slot.
	 */
	public function ajax_assign_page(): void {
		if ( ! check_ajax_referer( 'arshid6social_assign_page', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		$page_key = sanitize_key( $_POST['page_key'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$page_id  = absint( $_POST['page_id'] ?? 0 );         // phpcs:ignore WordPress.Security.NonceVerification
		$pages    = $this->get_page_definitions();

		if ( ! isset( $pages[ $page_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown page key.', '6arshid-social-community-main' ) ) );
		}

		if ( $page_id && ! get_post( $page_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Page not found.', '6arshid-social-community-main' ) ) );
		}

		update_option( $pages[ $page_key ]['option'], $page_id );
		wp_send_json_success( array( 'page_id' => $page_id ) );
	}

	/**
	 * Returns the canonical page definition list.
	 *
	 * Components can append their own pages via the arshid6social_page_definitions filter.
	 *
	 * @return array<string, array>
	 */
	private function get_page_definitions(): array {
		$pages = array(
			'members'  => array(
				'title'       => __( 'Members', '6arshid-social-community-main' ),
				'slug'        => 'members',
				'shortcode'   => '[arshid6social_members]',
				'option'      => 'arshid6social_page_members',
				'description' => __( 'Member directory with search and filters', '6arshid-social-community-main' ),
			),
			'activity' => array(
				'title'       => __( 'Activity', '6arshid-social-community-main' ),
				'slug'        => 'activity',
				'shortcode'   => '[arshid6social_activity]',
				'option'      => 'arshid6social_page_activity',
				'description' => __( 'Site-wide activity feed', '6arshid-social-community-main' ),
			),
			'groups'   => array(
				'title'       => __( 'Groups', '6arshid-social-community-main' ),
				'slug'        => 'groups',
				'shortcode'   => '[arshid6social_groups]',
				'option'      => 'arshid6social_page_groups',
				'description' => __( 'Group directory with join buttons', '6arshid-social-community-main' ),
			),
			'messages' => array(
				'title'       => __( 'Messages', '6arshid-social-community-main' ),
				'slug'        => 'messages',
				'shortcode'   => '[arshid6social_messages]',
				'option'      => 'arshid6social_page_messages',
				'description' => __( 'Private messaging inbox', '6arshid-social-community-main' ),
			),
			'notifications' => array(
				'title'       => __( 'Notifications', '6arshid-social-community-main' ),
				'slug'        => 'notifications',
				'shortcode'   => '[arshid6social_notifications]',
				'option'      => 'arshid6social_page_notifications',
				'description' => __( 'Personal notifications feed with customize options', '6arshid-social-community-main' ),
			),
			'register' => array(
				'title'       => __( 'Register', '6arshid-social-community-main' ),
				'slug'        => 'register',
				'shortcode'   => '[arshid6social_register_form]',
				'option'      => 'arshid6social_page_register',
				'description' => __( 'Member registration form', '6arshid-social-community-main' ),
			),
			'login' => array(
				'title'       => __( 'Login', '6arshid-social-community-main' ),
				'slug'        => 'login',
				'shortcode'   => '[arshid6social_login_form]',
				'option'      => 'arshid6social_page_login',
				'description' => __( 'Member login form', '6arshid-social-community-main' ),
			),
			'forgot-password' => array(
				'title'       => __( 'Forgot Password', '6arshid-social-community-main' ),
				'slug'        => 'forgot-password',
				'shortcode'   => '[arshid6social_forgot_password]',
				'option'      => 'arshid6social_page_forgot_password',
				'description' => __( 'Password reset request form (no wp-login.php)', '6arshid-social-community-main' ),
			),
			'reset-password' => array(
				'title'       => __( 'Reset Password', '6arshid-social-community-main' ),
				'slug'        => 'reset-password',
				'shortcode'   => '[arshid6social_reset_password]',
				'option'      => 'arshid6social_page_reset_password',
				'description' => __( 'Set new password via email link (no wp-login.php)', '6arshid-social-community-main' ),
			),
		);

		/**
		 * Filters the list of auto-managed plugin pages.
		 *
		 * Each entry is keyed by a short slug and has: title, slug, shortcode, option, description.
		 * Use this hook to register additional component pages (e.g. Marketplace).
		 *
		 * @param array<string, array> $pages
		 */
		return apply_filters( 'arshid6social_page_definitions', $pages );
	}
}
