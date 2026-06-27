<?php
namespace Arshid6Social\Admin;

/**
 * Admin Ads manager — create, edit, delete ads and view click/impression stats.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Ads
 */
final class Admin_Ads {

	private static ?Admin_Ads $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function hooks(): void {
		add_action( 'admin_init',                       array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts',            array( $this, 'enqueue_page_styles' ) );
		add_action( 'wp_ajax_arshid6social_delete_ad',        array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_arshid6social_toggle_ad_status', array( $this, 'ajax_toggle_status' ) );
	}

	public function enqueue_page_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'arshid6social-ads' ) ) {
			return;
		}
		wp_add_inline_style( 'arshid6social-admin', '.arshid6social-status-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600}.arshid6social-status-active{background:#d4edda;color:#155724}.arshid6social-status-inactive{background:#f8d7da;color:#721c24}.arshid6social-ads-table td{vertical-align:middle}' );
	}

	// ── Save (add / edit) ─────────────────────────────────────────────────────

	public function handle_save(): void {
		if ( empty( $_POST['arshid6social_ads_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['arshid6social_ads_nonce'] ) ), 'arshid6social_save_ad' ) ) {
			wp_die( esc_html__( 'Nonce check failed.', '6arshid-social-community-main' ) );
		}
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'Forbidden.', '6arshid-social-community-main' ) );
		}

		$ad_id      = absint( $_POST['ad_id'] ?? 0 );
		$title      = sanitize_text_field( wp_unslash( $_POST['ad_title'] ?? '' ) );
		$ad_type    = sanitize_key( $_POST['ad_type'] ?? 'image' );
		$file_url   = esc_url_raw( wp_unslash( $_POST['ad_file_url'] ?? '' ) );
		$click_url  = esc_url_raw( wp_unslash( $_POST['ad_click_url'] ?? '' ) );
		$js_code    = sanitize_textarea_field( wp_unslash( $_POST['ad_js_code'] ?? '' ) );
		$placement  = sanitize_key( $_POST['ad_placement'] ?? 'both' );
		$every_n    = max( 1, absint( $_POST['ad_every_n_posts'] ?? 5 ) );
		$status     = in_array( $_POST['ad_status'] ?? 'active', array( 'active', 'inactive' ), true )
			? sanitize_key( $_POST['ad_status'] )
			: 'active';
		$start_date = sanitize_text_field( wp_unslash( $_POST['ad_start_date'] ?? '' ) ) ?: null;
		$end_date   = sanitize_text_field( wp_unslash( $_POST['ad_end_date'] ?? '' ) ) ?: null;

		global $wpdb;
		$table = $wpdb->prefix . 'sn_ads';
		$data  = array(
			'title'         => $title,
			'ad_type'       => $ad_type,
			'file_url'      => $file_url,
			'click_url'     => $click_url,
			'js_code'       => $js_code,
			'placement'     => $placement,
			'every_n_posts' => $every_n,
			'status'        => $status,
			'start_date'    => $start_date,
			'end_date'      => $end_date,
		);

		if ( $ad_id ) {
			$wpdb->update( $table, $data, array( 'id' => $ad_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$data['date_created'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'arshid6social-ads', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── AJAX: delete ──────────────────────────────────────────────────────────

	public function ajax_delete(): void {
		check_ajax_referer( 'arshid6social_admin_ads_nonce', 'nonce' );
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( __( 'Forbidden', '6arshid-social-community-main' ) );
		}
		$ad_id = absint( $_POST['ad_id'] ?? 0 );
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'sn_ads', array( 'id' => $ad_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_send_json_success();
	}

	// ── AJAX: toggle status ───────────────────────────────────────────────────

	public function ajax_toggle_status(): void {
		check_ajax_referer( 'arshid6social_admin_ads_nonce', 'nonce' );
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( __( 'Forbidden', '6arshid-social-community-main' ) );
		}
		$ad_id = absint( $_POST['ad_id'] ?? 0 );
		global $wpdb;
		$current    = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT status FROM {$wpdb->prefix}sn_ads WHERE id = %d",
			$ad_id
		) );
		$new_status = ( 'active' === $current ) ? 'inactive' : 'active';
		$wpdb->update( $wpdb->prefix . 'sn_ads', array( 'status' => $new_status ), array( 'id' => $ad_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_send_json_success( array(
			'status'       => $new_status,
			'status_label' => 'active' === $new_status ? __( 'Active', '6arshid-social-community-main' ) : __( 'Inactive', '6arshid-social-community-main' ),
			'label'        => 'active' === $new_status ? __( 'Deactivate', '6arshid-social-community-main' ) : __( 'Activate', '6arshid-social-community-main' ),
		) );
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '6arshid-social-community-main' ) );
		}

		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$ad_id  = absint( $_GET['ad_id'] ?? 0 );

		if ( 'add' === $action || 'edit' === $action ) {
			$this->render_form( $ad_id );
		} else {
			$this->render_list();
		}
	}

	// ── List view ─────────────────────────────────────────────────────────────

	private function render_list(): void {
		global $wpdb;
		$ads     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}sn_ads ORDER BY id DESC",
			ARRAY_A
		) ?: array();
		$saved   = ! empty( $_GET['saved'] );
		$add_url = add_query_arg( array( 'page' => 'arshid6social-ads', 'action' => 'add' ), admin_url( 'admin.php' ) );
		$nonce   = wp_create_nonce( 'arshid6social_admin_ads_nonce' );
		$ajax    = admin_url( 'admin-ajax.php' );
		?>
		<div class="wrap arshid6social-admin-ads">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ads Manager', '6arshid-social-community-main' ); ?></h1>
			<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( '+ Add New Ad', '6arshid-social-community-main' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ad saved.', '6arshid-social-community-main' ); ?></p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped arshid6social-ads-table">
				<thead>
					<tr>
						<th style="width:40px">ID</th>
						<th><?php esc_html_e( 'Title', '6arshid-social-community-main' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Type', '6arshid-social-community-main' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Placement', '6arshid-social-community-main' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Every N', '6arshid-social-community-main' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Impressions', '6arshid-social-community-main' ); ?></th>
						<th style="width:60px"><?php esc_html_e( 'Clicks', '6arshid-social-community-main' ); ?></th>
						<th style="width:60px">CTR</th>
						<th style="width:80px"><?php esc_html_e( 'Status', '6arshid-social-community-main' ); ?></th>
						<th style="width:180px"><?php esc_html_e( 'Actions', '6arshid-social-community-main' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $ads ) : ?>
					<tr><td colspan="10"><?php esc_html_e( 'No ads yet. Click "+ Add New Ad" to create one.', '6arshid-social-community-main' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $ads as $ad ) :
						$edit_url   = add_query_arg( array( 'page' => 'arshid6social-ads', 'action' => 'edit', 'ad_id' => $ad['id'] ), admin_url( 'admin.php' ) );
						$ctr        = $ad['impressions'] > 0 ? round( ( $ad['clicks'] / $ad['impressions'] ) * 100, 2 ) : 0;
						$is_active  = 'active' === $ad['status'];
						$toggle_lbl = $is_active ? __( 'Deactivate', '6arshid-social-community-main' ) : __( 'Activate', '6arshid-social-community-main' );
						$status_lbl = $is_active ? __( 'Active', '6arshid-social-community-main' ) : __( 'Inactive', '6arshid-social-community-main' );
					?>
					<tr id="arshid6social-ad-row-<?php echo (int) $ad['id']; ?>">
						<td><?php echo (int) $ad['id']; ?></td>
						<td><strong><?php echo esc_html( $ad['title'] ); ?></strong></td>
						<td><?php echo esc_html( strtoupper( $ad['ad_type'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $ad['placement'] ) ); ?></td>
						<td><?php echo (int) $ad['every_n_posts']; ?></td>
						<td><?php echo number_format( (int) $ad['impressions'] ); ?></td>
						<td><?php echo number_format( (int) $ad['clicks'] ); ?></td>
						<td><?php echo esc_html( $ctr ); ?>%</td>
						<td>
							<span id="arshid6social-ad-status-badge-<?php echo (int) $ad['id']; ?>"
								class="arshid6social-status-badge arshid6social-status-<?php echo esc_attr( $ad['status'] ); ?>">
								<?php echo esc_html( $status_lbl ); ?>
							</span>
						</td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', '6arshid-social-community-main' ); ?></a>
							<button type="button"
								class="button button-small arshid6social-ad-toggle <?php echo $is_active ? 'button-secondary' : 'button-primary'; ?>"
								data-id="<?php echo (int) $ad['id']; ?>">
								<?php echo esc_html( $toggle_lbl ); ?>
							</button>
							<button type="button"
								class="button button-small arshid6social-ad-del"
								data-id="<?php echo (int) $ad['id']; ?>"
								style="color:#b32d2e">
								<?php esc_html_e( 'Delete', '6arshid-social-community-main' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

		</div>

		<?php
		$js_list  = '(function(){';
		$js_list .= 'var nonce=' . wp_json_encode( $nonce ) . ';';
		$js_list .= 'var ajaxUrl=' . wp_json_encode( $ajax ) . ';';
		$js_list .= 'var confirmTxt=' . wp_json_encode( __( 'Delete this ad?', '6arshid-social-community-main' ) ) . ';';
		$js_list .= <<<'ENDJS'
document.querySelectorAll('.arshid6social-ad-del').forEach(function(btn){
	btn.addEventListener('click',function(){
		if(!confirm(confirmTxt))return;
		var id=btn.dataset.id;
		var fd=new FormData();
		fd.append('action','arshid6social_delete_ad');
		fd.append('nonce',nonce);
		fd.append('ad_id',id);
		fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
			if(r.success){var row=document.getElementById('arshid6social-ad-row-'+id);if(row)row.remove();}
			else{alert(r.data||'Error');}
		});
	});
});
document.querySelectorAll('.arshid6social-ad-toggle').forEach(function(btn){
	btn.addEventListener('click',function(){
		var id=btn.dataset.id;
		var fd=new FormData();
		fd.append('action','arshid6social_toggle_ad_status');
		fd.append('nonce',nonce);
		fd.append('ad_id',id);
		fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
			if(r.success){
				btn.textContent=r.data.label;
				btn.classList.toggle('button-primary',r.data.status==='active');
				btn.classList.toggle('button-secondary',r.data.status!=='active');
				var badge=document.getElementById('arshid6social-ad-status-badge-'+id);
				if(badge){badge.textContent=r.data.status_label;badge.className='arshid6social-status-badge arshid6social-status-'+r.data.status;}
			}
		});
	});
});
})();
ENDJS;
		wp_add_inline_script( 'arshid6social-admin', $js_list );
	}

	// ── Form view (add / edit) ────────────────────────────────────────────────

	private function render_form( int $ad_id ): void {
		global $wpdb;
		$ad = array(
			'id'            => 0,
			'title'         => '',
			'ad_type'       => 'image',
			'file_url'      => '',
			'click_url'     => '',
			'js_code'       => '',
			'placement'     => 'both',
			'every_n_posts' => 5,
			'status'        => 'active',
			'start_date'    => '',
			'end_date'      => '',
		);

		if ( $ad_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT * FROM {$wpdb->prefix}sn_ads WHERE id = %d",
				$ad_id
			), ARRAY_A );
			if ( $row ) {
				$ad = array_merge( $ad, $row );
			}
		}

		$list_url   = add_query_arg( array( 'page' => 'arshid6social-ads' ), admin_url( 'admin.php' ) );
		$page_title = $ad_id ? __( 'Edit Ad', '6arshid-social-community-main' ) : __( 'Add New Ad', '6arshid-social-community-main' );
		$ajax_url   = admin_url( 'admin-ajax.php' );
		$nonce      = wp_create_nonce( 'arshid6social_admin_ads_nonce' );
		?>
		<div class="wrap arshid6social-admin-ads">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to list', '6arshid-social-community-main' ); ?></a>
			<hr>

			<form method="post" action="" style="max-width:760px">
				<?php wp_nonce_field( 'arshid6social_save_ad', 'arshid6social_ads_nonce' ); ?>
				<input type="hidden" name="ad_id"       value="<?php echo (int) $ad['id']; ?>">
				<input type="hidden" name="ad_file_url" id="arshid6social-ad-file-url" value="<?php echo esc_url( $ad['file_url'] ); ?>">

				<table class="form-table">
					<tr>
						<th><label for="arshid6social-ad-title"><?php esc_html_e( 'Title', '6arshid-social-community-main' ); ?></label></th>
						<td><input type="text" id="arshid6social-ad-title" name="ad_title" value="<?php echo esc_attr( $ad['title'] ); ?>" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="arshid6social-ad-type"><?php esc_html_e( 'Ad Type', '6arshid-social-community-main' ); ?></label></th>
						<td>
							<select id="arshid6social-ad-type" name="ad_type">
								<option value="image" <?php selected( $ad['ad_type'], 'image' ); ?>><?php esc_html_e( 'Image', '6arshid-social-community-main' ); ?></option>
								<option value="video" <?php selected( $ad['ad_type'], 'video' ); ?>><?php esc_html_e( 'Video', '6arshid-social-community-main' ); ?></option>
								<option value="html"  <?php selected( $ad['ad_type'], 'html' ); ?>><?php esc_html_e( 'HTML / JS Code', '6arshid-social-community-main' ); ?></option>
							</select>
						</td>
					</tr>

					<tr class="arshid6social-ad-field-file">
						<th><?php esc_html_e( 'Media File', '6arshid-social-community-main' ); ?></th>
						<td>
							<button type="button" id="arshid6social-ad-upload-btn" class="button">
								<?php esc_html_e( 'Choose file', '6arshid-social-community-main' ); ?>
							</button>
							<span id="arshid6social-ad-upload-status" style="margin-left:8px;color:#666;font-size:13px"></span>
							<div id="arshid6social-ad-file-preview" style="margin-top:8px">
								<?php if ( $ad['file_url'] ) :
									$is_video = (bool) preg_match( '/\.(mp4|webm|ogg|ogv)$/i', $ad['file_url'] );
								?>
									<?php if ( $is_video ) : ?>
										<video src="<?php echo esc_url( $ad['file_url'] ); ?>" controls style="max-width:100%;max-height:180px"></video>
									<?php else : ?>
										<img src="<?php echo esc_url( $ad['file_url'] ); ?>" style="max-width:100%;max-height:180px" alt="">
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</td>
					</tr>

					<tr class="arshid6social-ad-field-click">
						<th><label for="arshid6social-ad-click-url"><?php esc_html_e( 'Destination URL (on click)', '6arshid-social-community-main' ); ?></label></th>
						<td><input type="url" id="arshid6social-ad-click-url" name="ad_click_url" value="<?php echo esc_url( $ad['click_url'] ); ?>" class="regular-text" placeholder="https://"></td>
					</tr>

					<tr class="arshid6social-ad-field-code">
						<th><label for="arshid6social-ad-js-code"><?php esc_html_e( 'HTML / JS Code', '6arshid-social-community-main' ); ?></label></th>
						<td>
							<textarea id="arshid6social-ad-js-code" name="ad_js_code" rows="8" class="large-text code"><?php echo esc_textarea( $ad['js_code'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Paste raw HTML or JavaScript (e.g. an ad network snippet).', '6arshid-social-community-main' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><label for="arshid6social-ad-placement"><?php esc_html_e( 'Placement', '6arshid-social-community-main' ); ?></label></th>
						<td>
							<select id="arshid6social-ad-placement" name="ad_placement">
								<option value="both"    <?php selected( $ad['placement'], 'both' ); ?>><?php esc_html_e( 'Feed + Sidebar', '6arshid-social-community-main' ); ?></option>
								<option value="feed"    <?php selected( $ad['placement'], 'feed' ); ?>><?php esc_html_e( 'Feed only', '6arshid-social-community-main' ); ?></option>
								<option value="sidebar" <?php selected( $ad['placement'], 'sidebar' ); ?>><?php esc_html_e( 'Sidebar only', '6arshid-social-community-main' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th><label for="arshid6social-ad-every-n"><?php esc_html_e( 'Show in feed every N posts', '6arshid-social-community-main' ); ?></label></th>
						<td>
							<input type="number" id="arshid6social-ad-every-n" name="ad_every_n_posts" value="<?php echo (int) $ad['every_n_posts']; ?>" min="1" max="100" class="small-text">
							<p class="description"><?php esc_html_e( 'Ad appears after every N posts in the feed. Ignored for sidebar-only ads.', '6arshid-social-community-main' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><label for="arshid6social-ad-status"><?php esc_html_e( 'Status', '6arshid-social-community-main' ); ?></label></th>
						<td>
							<select id="arshid6social-ad-status" name="ad_status">
								<option value="active"   <?php selected( $ad['status'], 'active' ); ?>><?php esc_html_e( 'Active', '6arshid-social-community-main' ); ?></option>
								<option value="inactive" <?php selected( $ad['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', '6arshid-social-community-main' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Date range', '6arshid-social-community-main' ); ?></th>
						<td>
							<label><?php esc_html_e( 'From', '6arshid-social-community-main' ); ?>
								<input type="date" name="ad_start_date" value="<?php echo esc_attr( $ad['start_date'] ?? '' ); ?>">
							</label>
							&nbsp;&nbsp;
							<label><?php esc_html_e( 'To', '6arshid-social-community-main' ); ?>
								<input type="date" name="ad_end_date" value="<?php echo esc_attr( $ad['end_date'] ?? '' ); ?>">
							</label>
							<p class="description"><?php esc_html_e( 'Leave blank for no date restriction.', '6arshid-social-community-main' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( $ad_id ? __( 'Update Ad', '6arshid-social-community-main' ) : __( 'Create Ad', '6arshid-social-community-main' ) ); ?>
			</form>
		</div>

		<?php
		$js_form  = '(function(){';
		$js_form .= 'var ajaxUrl=' . wp_json_encode( $ajax_url ) . ';';
		$js_form .= 'var nonce=' . wp_json_encode( $nonce ) . ';';
		$js_form .= 'var uploadErr=' . wp_json_encode( __( 'Upload failed. Please try again.', '6arshid-social-community-main' ) ) . ';';
		$js_form .= 'var uploadingTxt=' . wp_json_encode( __( 'Uploading…', '6arshid-social-community-main' ) ) . ';';
		$js_form .= <<<'ENDJS'
var typeSelect=document.getElementById('arshid6social-ad-type');
function applyType(){
	var t=typeSelect.value;
	var showFile=(t==='image'||t==='video');
	document.querySelectorAll('.arshid6social-ad-field-file,.arshid6social-ad-field-click').forEach(function(r){r.style.display=showFile?'':'none';});
	var codeRow=document.querySelector('.arshid6social-ad-field-code');
	if(codeRow)codeRow.style.display=(t==='html')?'':'none';
}
typeSelect.addEventListener('change',applyType);applyType();
var uploadBtn=document.getElementById('arshid6social-ad-upload-btn');
var urlField=document.getElementById('arshid6social-ad-file-url');
var preview=document.getElementById('arshid6social-ad-file-preview');
var statusSpan=document.getElementById('arshid6social-ad-upload-status');
var fileInput=document.createElement('input');
fileInput.type='file';fileInput.accept='image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg';
fileInput.style.display='none';document.body.appendChild(fileInput);
uploadBtn.addEventListener('click',function(e){e.preventDefault();fileInput.click();});
fileInput.addEventListener('change',function(){
	var file=fileInput.files[0];if(!file)return;
	uploadBtn.disabled=true;statusSpan.textContent=uploadingTxt;
	var fd=new FormData();fd.append('action','arshid6social_upload_ad_media');fd.append('nonce',nonce);fd.append('file',file);
	fetch(ajaxUrl,{method:'POST',body:fd})
		.then(function(r){return r.json();})
		.then(function(r){
			if(r&&r.success){
				var url=r.data.url;urlField.value=url;statusSpan.textContent='✓ '+file.name;statusSpan.style.color='#2ea44f';
				var isVideo=/\.(mp4|webm|ogg|ogv)$/i.test(url);
				preview.innerHTML=isVideo?'<video src="'+url+'" controls style="max-width:100%;max-height:180px"></video>':'<img src="'+url+'" style="max-width:100%;max-height:180px" alt="">';
			}else{statusSpan.textContent='';statusSpan.style.color='';alert((r&&r.data)?r.data:uploadErr);}
		})
		.catch(function(){statusSpan.textContent='';alert(uploadErr);})
		.finally(function(){uploadBtn.disabled=false;fileInput.value='';});
});
})();
ENDJS;
		wp_add_inline_script( 'arshid6social-admin', $js_form );
	}
}
