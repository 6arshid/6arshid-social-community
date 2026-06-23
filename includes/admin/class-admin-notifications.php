<?php
namespace Arshid6Social\Admin;

/**
 * Admin Notifications management page.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Notifications
 *
 * Provides a WP Admin page for:
 *  - Global notification type on/off switches
 *  - Site-wide notification statistics
 *  - Recent notifications log with bulk delete
 */
class Admin_Notifications {

	private static ?Admin_Notifications $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_arshid6social_admin_delete_notifications', array( $this, 'ajax_bulk_delete' ) );
		add_action( 'wp_ajax_arshid6social_admin_save_notif_settings',  array( $this, 'ajax_save_settings' ) );
	}

	/**
	 * Renders the full admin notifications page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', '6arshid social community' ) );
		}

		global $wpdb;

		// Statistics.
		$total_notifs  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_notifications" ); // phpcs:ignore
		$unread_notifs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_notifications WHERE is_new = 1" ); // phpcs:ignore
		$users_count   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}sn_notifications WHERE is_new = 1" ); // phpcs:ignore

		// Per-type counts.
		$type_counts = $wpdb->get_results( // phpcs:ignore
			"SELECT component_action, COUNT(*) as cnt FROM {$wpdb->prefix}sn_notifications GROUP BY component_action ORDER BY cnt DESC",
			ARRAY_A
		) ?: array();

		// Global enabled types (stored as comma-separated or JSON option).
		$disabled_types = get_option( 'arshid6social_disabled_notification_types', array() );
		if ( ! is_array( $disabled_types ) ) {
			$disabled_types = array();
		}

		$all_types = \Arshid6Social\Components\Notifications\Notifications::TYPES;

		// Recent 50 notifications for the log.
		$recent = $wpdb->get_results( // phpcs:ignore
			"SELECT n.*, u.display_name as recipient_name, s.display_name as sender_name
			 FROM {$wpdb->prefix}sn_notifications n
			 LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id
			 LEFT JOIN {$wpdb->users} s ON s.ID = n.item_id
			 ORDER BY n.date_notified DESC
			 LIMIT 50"
		) ?: array();

		$nonce = wp_create_nonce( 'arshid6social_admin_notif_nonce' );
		?>
		<div class="wrap arshid6social-admin-notif">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Notifications', '6arshid social community' ); ?>
			</h1>

			<!-- ── Stat cards ── -->
			<div class="arshid6social-admin-notif-stats">
				<div class="arshid6social-stat-card">
					<span class="dashicons dashicons-bell" style="font-size:2rem;color:#2563eb;"></span>
					<strong><?php echo esc_html( number_format_i18n( $total_notifs ) ); ?></strong>
					<p><?php esc_html_e( 'Total Notifications', '6arshid social community' ); ?></p>
				</div>
				<div class="arshid6social-stat-card">
					<span style="font-size:2rem;">🔴</span>
					<strong><?php echo esc_html( number_format_i18n( $unread_notifs ) ); ?></strong>
					<p><?php esc_html_e( 'Unread', '6arshid social community' ); ?></p>
				</div>
				<div class="arshid6social-stat-card">
					<span class="dashicons dashicons-admin-users" style="font-size:2rem;color:#16a34a;"></span>
					<strong><?php echo esc_html( number_format_i18n( $users_count ) ); ?></strong>
					<p><?php esc_html_e( 'Members with unread', '6arshid social community' ); ?></p>
				</div>
			</div>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:2rem;align-items:start;">

				<!-- ── Global type toggles ── -->
				<div class="postbox">
					<div class="postbox-header">
						<h2><?php esc_html_e( 'Notification Types', '6arshid social community' ); ?></h2>
					</div>
					<div class="inside">
						<p class="description" style="margin-bottom:1rem;">
							<?php esc_html_e( 'Disable a type to stop generating those notifications site-wide. Existing notifications are not deleted.', '6arshid social community' ); ?>
						</p>
						<form id="arshid6social-admin-notif-types-form">
							<table class="widefat striped" style="border:none;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Type', '6arshid social community' ); ?></th>
										<th style="text-align:center;"><?php esc_html_e( 'Total sent', '6arshid social community' ); ?></th>
										<th style="text-align:center;"><?php esc_html_e( 'Enabled', '6arshid social community' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$counts_by_type = array_column( $type_counts, 'cnt', 'component_action' );
									foreach ( $all_types as $action => $info ) :
										$count   = $counts_by_type[ $action ] ?? 0;
										$enabled = ! in_array( $action, $disabled_types, true );
										?>
										<tr>
											<td>
												<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr( $info['color'] ); ?>;font-size:.75rem;margin-right:.5rem;"><?php echo esc_html( $info['icon'] ); ?></span>
												<?php echo esc_html( $info['label'] ); ?>
											</td>
											<td style="text-align:center;"><?php echo esc_html( number_format_i18n( (int) $count ) ); ?></td>
											<td style="text-align:center;">
												<label class="arshid6social-admin-toggle">
													<input type="checkbox" name="enabled_types[]"
														value="<?php echo esc_attr( $action ); ?>"
														<?php checked( $enabled ); ?> />
													<span class="arshid6social-admin-toggle-slider"></span>
												</label>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>

							<p style="margin-top:1rem;">
								<button type="submit" class="button button-primary">
									<?php esc_html_e( 'Save Type Settings', '6arshid social community' ); ?>
								</button>
								<span id="arshid6social-admin-types-saved" style="display:none;margin-left:.75rem;color:#16a34a;">
									&#10003; <?php esc_html_e( 'Saved!', '6arshid social community' ); ?>
								</span>
							</p>
						</form>
					</div>
				</div>

				<!-- ── Email & digest settings ── -->
				<div class="postbox">
					<div class="postbox-header">
						<h2><?php esc_html_e( 'Email Settings', '6arshid social community' ); ?></h2>
					</div>
					<div class="inside">
						<form method="post" action="options.php">
							<?php settings_fields( 'arshid6social_notifications' ); ?>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php esc_html_e( 'Email Notifications', '6arshid social community' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="arshid6social_email_notifications" value="1"
												<?php checked( get_option( 'arshid6social_email_notifications', true ) ); ?> />
											<?php esc_html_e( 'Send email notifications to members.', '6arshid social community' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Default Email Digest', '6arshid social community' ); ?></th>
									<td>
										<select name="arshid6social_email_digest">
											<option value="none"   <?php selected( get_option( 'arshid6social_email_digest', 'daily' ), 'none' ); ?>><?php esc_html_e( 'Never', '6arshid social community' ); ?></option>
											<option value="daily"  <?php selected( get_option( 'arshid6social_email_digest', 'daily' ), 'daily' ); ?>><?php esc_html_e( 'Daily digest', '6arshid social community' ); ?></option>
											<option value="weekly" <?php selected( get_option( 'arshid6social_email_digest', 'daily' ), 'weekly' ); ?>><?php esc_html_e( 'Weekly digest', '6arshid social community' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Members can override this per their own profile settings.', '6arshid social community' ); ?></p>
									</td>
								</tr>
							</table>
							<?php submit_button( __( 'Save Email Settings', '6arshid social community' ) ); ?>
						</form>
					</div>
				</div>

			</div><!-- end grid -->

			<!-- ── Recent notifications log ── -->
			<div class="postbox" style="margin-top:2rem;">
				<div class="postbox-header" style="display:flex;align-items:center;justify-content:space-between;">
					<h2><?php esc_html_e( 'Recent Notifications', '6arshid social community' ); ?></h2>
					<div style="padding:.75rem 1rem;">
						<button id="arshid6social-admin-delete-all" class="button button-link-delete">
							<?php esc_html_e( 'Delete all notifications', '6arshid social community' ); ?>
						</button>
					</div>
				</div>
				<div class="inside" style="padding:0;">
					<?php if ( empty( $recent ) ) : ?>
						<p style="padding:1.5rem;"><?php esc_html_e( 'No notifications found.', '6arshid social community' ); ?></p>
					<?php else : ?>
					<table class="wp-list-table widefat fixed striped" id="arshid6social-notif-log">
						<thead>
							<tr>
								<th style="width:36px;"><input type="checkbox" id="arshid6social-log-select-all" /></th>
								<th><?php esc_html_e( 'Recipient', '6arshid social community' ); ?></th>
								<th><?php esc_html_e( 'From', '6arshid social community' ); ?></th>
								<th><?php esc_html_e( 'Type', '6arshid social community' ); ?></th>
								<th><?php esc_html_e( 'Status', '6arshid social community' ); ?></th>
								<th><?php esc_html_e( 'Date', '6arshid social community' ); ?></th>
								<th><?php esc_html_e( 'Actions', '6arshid social community' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent as $notif ) :
								$type_info = $all_types[ $notif->component_action ] ?? array( 'label' => $notif->component_action, 'icon' => '🔔', 'color' => '#6b7280' );
							?>
							<tr data-id="<?php echo esc_attr( $notif->id ); ?>">
								<td><input type="checkbox" class="arshid6social-log-cb" value="<?php echo esc_attr( $notif->id ); ?>" /></td>
								<td><?php echo esc_html( $notif->recipient_name ?: '—' ); ?></td>
								<td><?php echo esc_html( $notif->sender_name   ?: '—' ); ?></td>
								<td>
									<span style="display:inline-flex;align-items:center;gap:.375rem;">
										<span style="width:22px;height:22px;border-radius:50%;background:<?php echo esc_attr( $type_info['color'] ); ?>;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;"><?php echo esc_html( $type_info['icon'] ); ?></span>
										<?php echo esc_html( $type_info['label'] ); ?>
									</span>
								</td>
								<td>
									<?php if ( $notif->is_new ) : ?>
										<span style="color:#dc2626;font-weight:600;"><?php esc_html_e( 'Unread', '6arshid social community' ); ?></span>
									<?php else : ?>
										<span style="color:#16a34a;"><?php esc_html_e( 'Read', '6arshid social community' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $notif->date_notified ) ) ); ?></td>
								<td>
									<button class="button button-small arshid6social-admin-del-notif" data-id="<?php echo esc_attr( $notif->id ); ?>">
										<?php esc_html_e( 'Delete', '6arshid social community' ); ?>
									</button>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div style="padding:.75rem 1rem;border-top:1px solid #ddd;display:flex;align-items:center;gap:.75rem;">
						<button id="arshid6social-admin-delete-selected" class="button button-link-delete" disabled>
							<?php esc_html_e( 'Delete selected', '6arshid social community' ); ?>
						</button>
						<span id="arshid6social-admin-log-msg" style="color:#16a34a;display:none;"></span>
					</div>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- .wrap -->

		<?php
		$notif_css = '
		.arshid6social-admin-notif-stats { display:flex; gap:1rem; flex-wrap:wrap; margin-top:1.5rem; }
		.arshid6social-admin-notif-stats .arshid6social-stat-card { flex:1; min-width:160px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:1rem 1.25rem; display:flex; flex-direction:column; align-items:center; gap:.375rem; text-align:center; }
		.arshid6social-admin-notif-stats .arshid6social-stat-card strong { font-size:1.75rem; font-weight:700; color:#111; }
		.arshid6social-admin-notif-stats .arshid6social-stat-card p { margin:0; color:#6b7280; font-size:.8125rem; }
		.arshid6social-admin-toggle { position:relative; display:inline-flex; width:40px; height:22px; }
		.arshid6social-admin-toggle input { opacity:0; width:0; height:0; }
		.arshid6social-admin-toggle-slider { position:absolute; inset:0; border-radius:22px; background:#d1d5db; cursor:pointer; transition:.2s; }
		.arshid6social-admin-toggle input:checked + .arshid6social-admin-toggle-slider { background:#2563eb; }
		.arshid6social-admin-toggle-slider::before { content:""; position:absolute; width:16px; height:16px; border-radius:50%; background:#fff; top:3px; left:3px; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
		.arshid6social-admin-toggle input:checked + .arshid6social-admin-toggle-slider::before { transform:translateX(18px); }
		';
		wp_add_inline_style( 'arshid6social-admin', $notif_css );

		$notif_js = '(function() {
			const nonce = ' . wp_json_encode( $nonce ) . ';

			// Type toggles form.
			document.getElementById("arshid6social-admin-notif-types-form")?.addEventListener("submit", async function(e) {
				e.preventDefault();
				const checked = [...this.querySelectorAll("input[name=\'enabled_types[]\']:checked")].map(c => c.value);
				const body = new FormData();
				body.append("action", "arshid6social_admin_save_notif_settings");
				body.append("nonce", nonce);
				body.append("enabled_types", JSON.stringify(checked));
				const res = await fetch(ajaxurl, {method:"POST", body}).then(r => r.json());
				if (res.success) {
					const msg = document.getElementById("arshid6social-admin-types-saved");
					msg.style.display = "inline";
					setTimeout(() => { msg.style.display = "none"; }, 2500);
				}
			});

			// Select-all.
			document.getElementById("arshid6social-log-select-all")?.addEventListener("change", function() {
				document.querySelectorAll(".arshid6social-log-cb").forEach(cb => cb.checked = this.checked);
				updateBulkBtn();
			});
			document.addEventListener("change", e => { if (e.target.classList.contains("arshid6social-log-cb")) updateBulkBtn(); });

			function updateBulkBtn() {
				const any = [...document.querySelectorAll(".arshid6social-log-cb")].some(c => c.checked);
				document.getElementById("arshid6social-admin-delete-selected").disabled = !any;
			}

			async function deleteNotifRows(ids) {
				const body = new FormData();
				body.append("action", "arshid6social_admin_delete_notifications");
				body.append("nonce", nonce);
				ids.forEach(id => body.append("ids[]", id));
				return fetch(ajaxurl, {method:"POST", body}).then(r => r.json());
			}

			// Delete individual.
			document.addEventListener("click", async function(e) {
				const btn = e.target.closest(".arshid6social-admin-del-notif");
				if (!btn) return;
				if (!confirm(' . wp_json_encode( __( 'Delete this notification?', '6arshid social community' ) ) . ')) return;
				const id = btn.dataset.id;
				const res = await deleteNotifRows([id]);
				if (res.success) btn.closest("tr").remove();
			});

			// Delete selected.
			document.getElementById("arshid6social-admin-delete-selected")?.addEventListener("click", async function() {
				const ids = [...document.querySelectorAll(".arshid6social-log-cb:checked")].map(c => c.value);
				if (!ids.length || !confirm(' . wp_json_encode( __( 'Delete selected notifications?', '6arshid social community' ) ) . ')) return;
				const res = await deleteNotifRows(ids);
				if (res.success) {
					ids.forEach(id => document.querySelector(`tr[data-id="${id}"]`)?.remove());
					updateBulkBtn();
				}
			});

			// Delete all.
			document.getElementById("arshid6social-admin-delete-all")?.addEventListener("click", async function() {
				if (!confirm(' . wp_json_encode( __( 'Delete ALL notifications? This cannot be undone.', '6arshid social community' ) ) . ')) return;
				const body = new FormData();
				body.append("action", "arshid6social_admin_delete_notifications");
				body.append("nonce", nonce);
				body.append("delete_all", "1");
				const res = await fetch(ajaxurl, {method:"POST", body}).then(r => r.json());
				if (res.success) {
					document.querySelectorAll("#arshid6social-notif-log tbody tr").forEach(r => r.remove());
					document.getElementById("arshid6social-admin-log-msg").style.display = "inline";
					document.getElementById("arshid6social-admin-log-msg").textContent = ' . wp_json_encode( __( 'All notifications deleted.', '6arshid social community' ) ) . ';
				}
			});
		})();';
		wp_add_inline_script( 'arshid6social-admin', $notif_js );
		?>
		<?php
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_bulk_delete(): void {
		if ( ! check_ajax_referer( 'arshid6social_admin_notif_nonce', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( null, 403 );
		}

		global $wpdb;

		if ( ! empty( $_POST['delete_all'] ) ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}sn_notifications" ); // phpcs:ignore
		} else {
			$ids = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ); // phpcs:ignore
			if ( $ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$wpdb->query( $wpdb->prepare( // phpcs:ignore
					"DELETE FROM {$wpdb->prefix}sn_notifications WHERE id IN ($placeholders)", // phpcs:ignore
					$ids
				) );
			}
		}

		wp_send_json_success();
	}

	public function ajax_save_settings(): void {
		if ( ! check_ajax_referer( 'arshid6social_admin_notif_nonce', 'nonce', false ) || ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_send_json_error( null, 403 );
		}

		$all_types    = array_keys( \Arshid6Social\Components\Notifications\Notifications::TYPES );
		$enabled_raw  = sanitize_text_field( wp_unslash( $_POST['enabled_types'] ?? '[]' ) ); // phpcs:ignore
		$enabled      = json_decode( $enabled_raw, true ) ?: array();
		$enabled      = array_intersect( $enabled, $all_types );
		$disabled     = array_values( array_diff( $all_types, $enabled ) );

		update_option( 'arshid6social_disabled_notification_types', $disabled );
		wp_send_json_success();
	}
}
