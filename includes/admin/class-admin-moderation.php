<?php
namespace Arshid6Social\Admin;

/**
 * Admin moderation queue.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Moderation
 *
 * Displays reported content and allows admins to approve, delete, or dismiss reports.
 * Also supports suspending users with a reason directly from the report queue.
 * Includes full Screen Options support (items per page + column visibility).
 */
final class Admin_Moderation {

	private static ?Admin_Moderation $instance = null;

	/** Screen ID assigned by WordPress for this page. */
	private string $screen_id = 'arshid6social-dashboard_page_arshid6social-moderation';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function hooks(): void {
		add_action( 'wp_ajax_arshid6social_resolve_report',          array( $this, 'ajax_resolve_report' ) );
		add_action( 'wp_ajax_arshid6social_admin_suspend_from_report', array( $this, 'ajax_suspend_from_report' ) );

		// Register columns for Screen Options column-visibility checkboxes.
		add_filter( 'manage_' . $this->screen_id . '_columns', array( $this, 'get_columns' ) );

		// Save per-page screen option.
		add_filter( 'set_screen_option_arshid6social_moderation_per_page', array( $this, 'save_per_page' ), 10, 3 );
		add_filter( 'set-screen-option', array( $this, 'save_per_page_legacy' ), 10, 3 );
	}

	// ── Screen Options ────────────────────────────────────────────────────────

	public function setup_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Reports per page', '6arshid-social-community-main' ),
				'default' => 20,
				'option'  => 'arshid6social_moderation_per_page',
			)
		);
	}

	/** @return array<string,string> */
	public function get_columns(): array {
		return array(
			'report_id' => __( 'ID', '6arshid-social-community-main' ),
			'reporter'  => __( 'Reporter', '6arshid-social-community-main' ),
			'reported'  => __( 'Reported', '6arshid-social-community-main' ),
			'type'      => __( 'Type', '6arshid-social-community-main' ),
			'reason'    => __( 'Reason', '6arshid-social-community-main' ),
			'notes'     => __( 'Notes / Attachment', '6arshid-social-community-main' ),
			'date'      => __( 'Date', '6arshid-social-community-main' ),
			'actions'   => __( 'Actions', '6arshid-social-community-main' ),
		);
	}

	public function save_per_page( $status, string $option, $value ): int {
		return (int) $value;
	}

	public function save_per_page_legacy( $status, string $option, $value ) {
		if ( 'arshid6social_moderation_per_page' === $option ) {
			return (int) $value;
		}
		return $status;
	}

	// ── Groups tab ────────────────────────────────────────────────────────────

	private function render_groups_tab(): void {
		global $wpdb;

		$suspend_reasons = $this->get_suspend_reasons();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$groups = $wpdb->get_results(
			"SELECT id, name, slug, is_suspended, suspend_reason, creator_id, date_created
			 FROM {$wpdb->prefix}sn_groups
			 ORDER BY is_suspended DESC, date_created DESC
			 LIMIT 100"
		);
		?>
		<h2><?php esc_html_e( 'Groups — Suspension Management', '6arshid-social-community-main' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Suspend a group to hide it and all its content from non-admin users.', '6arshid-social-community-main' ); ?></p>

		<table class="wp-list-table widefat fixed striped" style="margin-top:1rem;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Group', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Creator', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Status', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Reason', '6arshid-social-community-main' ); ?></th>
					<th><?php esc_html_e( 'Actions', '6arshid-social-community-main' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $groups as $group ) : ?>
					<?php
					$creator      = get_userdata( (int) $group->creator_id );
					$is_suspended = (bool) $group->is_suspended;
					$group_url    = home_url( '/groups/' . $group->slug . '/' );
					?>
					<tr id="arshid6social-group-row-<?php echo esc_attr( $group->id ); ?>">
						<td>
							<a href="<?php echo esc_url( $group_url ); ?>" target="_blank">
								<?php echo esc_html( $group->name ); ?>
							</a>
						</td>
						<td>
							<?php if ( $creator ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $creator->ID ) ); ?>">
									<?php echo esc_html( $creator->display_name ); ?>
								</a>
							<?php else : ?>
								<em>—</em>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_suspended ) : ?>
								<span class="arshid6social-badge arshid6social-badge--suspended"><?php esc_html_e( 'Suspended', '6arshid-social-community-main' ); ?></span>
							<?php else : ?>
								<span class="arshid6social-badge arshid6social-badge--active"><?php esc_html_e( 'Active', '6arshid-social-community-main' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<small id="arshid6social-group-reason-<?php echo esc_attr( $group->id ); ?>" style="color:#555;">
								<?php echo $group->suspend_reason ? esc_html( $group->suspend_reason ) : '—'; ?>
							</small>
						</td>
						<td>
							<button class="button button-small arshid6social-suspend-group-btn <?php echo $is_suspended ? 'button-secondary' : 'button-link-delete'; ?>"
								data-group-id="<?php echo esc_attr( $group->id ); ?>"
								data-group-name="<?php echo esc_attr( $group->name ); ?>"
								data-suspended="<?php echo esc_attr( $is_suspended ? '1' : '0' ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_suspend_group_' . $group->id ) ); ?>">
								<?php echo $is_suspended ? esc_html__( 'Unsuspend Group', '6arshid-social-community-main' ) : esc_html__( 'Suspend Group', '6arshid-social-community-main' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $groups ) ) : ?>
					<tr><td colspan="5" style="text-align:center;padding:2rem;">
						<?php esc_html_e( 'No groups found.', '6arshid-social-community-main' ); ?>
					</td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Group suspend modal -->
		<div id="arshid6social-group-suspend-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;">
			<div style="background:#fff;border-radius:6px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
				<h2 style="margin-top:0;" id="arshid6social-gsm-title"><?php esc_html_e( 'Suspend Group', '6arshid-social-community-main' ); ?></h2>
				<p id="arshid6social-gsm-desc" style="color:#555;margin-bottom:16px;"></p>
				<label style="display:block;margin-bottom:6px;font-weight:600;"><?php esc_html_e( 'Reason', '6arshid-social-community-main' ); ?></label>
				<select id="arshid6social-gsm-reason" style="width:100%;margin-bottom:16px;">
					<option value=""><?php esc_html_e( '— Select a reason —', '6arshid-social-community-main' ); ?></option>
					<?php foreach ( $suspend_reasons as $r ) : ?>
						<option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?></option>
					<?php endforeach; ?>
					<option value="__custom__"><?php esc_html_e( 'Other (type below)', '6arshid-social-community-main' ); ?></option>
				</select>
				<input type="text" id="arshid6social-gsm-custom" placeholder="<?php esc_attr_e( 'Custom reason…', '6arshid-social-community-main' ); ?>"
					style="width:100%;display:none;margin-bottom:16px;" />
				<div style="display:flex;gap:8px;justify-content:flex-end;">
					<button class="button" id="arshid6social-gsm-cancel"><?php esc_html_e( 'Cancel', '6arshid-social-community-main' ); ?></button>
					<button class="button button-primary" id="arshid6social-gsm-confirm"><?php esc_html_e( 'Confirm', '6arshid-social-community-main' ); ?></button>
				</div>
				<input type="hidden" id="arshid6social-gsm-group-id" value="" />
				<input type="hidden" id="arshid6social-gsm-nonce" value="" />
				<input type="hidden" id="arshid6social-gsm-suspended" value="" />
			</div>
		</div>
		<?php
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'arshid6social_manage_reports' ) ) {
			wp_die( esc_html__( 'Permission denied.', '6arshid-social-community-main' ) );
		}

		global $wpdb;

		// ── Screen Options values ─────────────────────────────────────────────
		$per_page = (int) get_user_option( 'arshid6social_moderation_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$screen         = get_current_screen();
		$hidden_columns = $screen ? get_hidden_columns( $screen ) : array();
		$col_class      = fn( string $col ) => in_array( $col, $hidden_columns, true ) ? ' hidden' : '';

		// ── Request params ────────────────────────────────────────────────────
		$view   = isset( $_GET['moderation_view'] ) ? sanitize_key( wp_unslash( $_GET['moderation_view'] ) ) : 'reports'; // phpcs:ignore WordPress.Security.NonceVerification
		$status = isset( $_GET['report_status'] )   ? sanitize_key( wp_unslash( $_GET['report_status'] ) )  : 'pending'; // phpcs:ignore WordPress.Security.NonceVerification
		$paged  = isset( $_GET['paged'] )           ? max( 1, absint( $_GET['paged'] ) )                    : 1;         // phpcs:ignore WordPress.Security.NonceVerification

		$allowed_statuses = array( 'pending', 'resolved', 'dismissed' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'pending';
		}

		$offset = ( $paged - 1 ) * $per_page;

		$reports = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sn_reports WHERE status = %s ORDER BY date_reported DESC LIMIT %d OFFSET %d",
				$status,
				$per_page,
				$offset
			)
		);

		$counts = array();
		foreach ( $allowed_statuses as $s ) {
			$counts[ $s ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_reports WHERE status = %s", $s ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$total       = $counts[ $status ];
		$total_pages = (int) ceil( $total / $per_page );

		$suspend_reasons = $this->get_suspend_reasons();
		?>
		<div class="wrap" id="arshid6social-moderation-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Reports & Moderation', '6arshid-social-community-main' ); ?></h1>
			<hr class="wp-header-end">

			<nav class="nav-tab-wrapper" style="margin-bottom:1rem;">
				<a href="<?php echo esc_url( add_query_arg( 'moderation_view', 'reports' ) ); ?>"
					class="nav-tab <?php echo 'reports' === $view ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Reports', '6arshid-social-community-main' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'moderation_view', 'groups' ) ); ?>"
					class="nav-tab <?php echo 'groups' === $view ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Groups', '6arshid-social-community-main' ); ?>
				</a>
			</nav>

			<?php if ( 'groups' === $view ) : ?>
				<?php $this->render_groups_tab(); ?>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php // ── Status tabs ──────────────────────────────────────────── ?>
			<ul class="subsubsub">
				<?php
				$tab_list = array();
				foreach ( $allowed_statuses as $s ) {
					$url      = add_query_arg( 'report_status', $s );
					$is_active = ( $status === $s );
					$tab_list[] = sprintf(
						'<li><a href="%s"%s>%s <span class="count">(%s)</span></a>',
						esc_url( $url ),
						$is_active ? ' class="current"' : '',
						esc_html( ucfirst( $s ) ),
						esc_html( number_format_i18n( $counts[ $s ] ) )
					);
				}
				echo implode( ' | </li>', $tab_list ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</ul>

			<?php // ── Top tablenav ─────────────────────────────────────────── ?>
			<div class="tablenav top">
				<div class="tablenav-pages<?php echo 1 === $total_pages ? ' one-page' : ''; ?>">
					<span class="displaying-num">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of items */
								_n( '%s item', '%s items', $total, '6arshid-social-community-main' ),
								number_format_i18n( $total )
							)
						);
						?>
					</span>
					<?php if ( $total_pages > 1 ) : ?>
						<?php
						echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						);
						?>
					<?php endif; ?>
				</div>
				<br class="clear" />
			</div>

			<table class="wp-list-table widefat fixed striped" style="margin-top:.5rem;">
				<thead>
					<tr>
						<th class="manage-column column-report_id<?php echo esc_attr( $col_class( 'report_id' ) ); ?>" style="width:40px;"><?php esc_html_e( 'ID', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-reporter column-primary<?php echo esc_attr( $col_class( 'reporter' ) ); ?>"><?php esc_html_e( 'Reporter', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-reported<?php echo esc_attr( $col_class( 'reported' ) ); ?>"><?php esc_html_e( 'Reported', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>"><?php esc_html_e( 'Type', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-reason<?php echo esc_attr( $col_class( 'reason' ) ); ?>"><?php esc_html_e( 'Reason', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-notes<?php echo esc_attr( $col_class( 'notes' ) ); ?>"><?php esc_html_e( 'Notes / Attachment', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>"><?php esc_html_e( 'Date', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>"><?php esc_html_e( 'Actions', '6arshid-social-community-main' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $reports as $report ) : ?>
						<?php
						$reporter         = get_userdata( (int) $report->reporter_id );
						$report_date      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report->date_reported ) );
						$item_link        = $this->get_item_link( (int) $report->item_id, $report->item_type );
						$reported_user_id = ( 'profile' === $report->item_type ) ? (int) $report->item_id : 0;
						$reported_user    = $reported_user_id ? get_userdata( $reported_user_id ) : null;
						$is_suspended     = $reported_user_id ? (bool) get_user_meta( $reported_user_id, 'arshid6social_suspended', true ) : false;
						?>
						<tr id="arshid6social-report-<?php echo esc_attr( $report->id ); ?>">
							<td class="report_id column-report_id<?php echo esc_attr( $col_class( 'report_id' ) ); ?>"><?php echo esc_html( $report->id ); ?></td>
							<td class="reporter column-reporter column-primary<?php echo esc_attr( $col_class( 'reporter' ) ); ?>">
								<?php if ( $reporter ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $reporter->ID ) ); ?>">
										<?php echo esc_html( $reporter->display_name ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Deleted user', '6arshid-social-community-main' ); ?></em>
								<?php endif; ?>
							</td>
							<td class="reported column-reported<?php echo esc_attr( $col_class( 'reported' ) ); ?>">
								<?php if ( $item_link ) : ?>
									<a href="<?php echo esc_url( $item_link['url'] ); ?>" target="_blank">
										<?php echo esc_html( $item_link['label'] ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( sprintf( '#%d', $report->item_id ) ); ?>
								<?php endif; ?>
							</td>
							<td class="type column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>">
								<span class="arshid6social-badge arshid6social-badge--<?php echo esc_attr( $report->item_type ); ?>">
									<?php echo esc_html( ucfirst( $report->item_type ) ); ?>
								</span>
							</td>
							<td class="reason column-reason<?php echo esc_attr( $col_class( 'reason' ) ); ?>"><?php echo esc_html( $report->reason ); ?></td>
							<td class="notes column-notes<?php echo esc_attr( $col_class( 'notes' ) ); ?>">
								<?php if ( $report->notes ) : ?>
									<p style="margin:0 0 4px;font-size:12px;color:#555;"><?php echo esc_html( wp_trim_words( $report->notes, 20 ) ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $report->attachment_url ) ) : ?>
									<a href="<?php echo esc_url( $report->attachment_url ); ?>" target="_blank" style="font-size:12px;">
										<img src="<?php echo esc_url( $report->attachment_url ); ?>"
											alt="<?php esc_attr_e( 'Report attachment', '6arshid-social-community-main' ); ?>"
											style="max-width:80px;max-height:60px;border-radius:3px;border:1px solid #ddd;cursor:pointer;" />
									</a>
								<?php elseif ( ! $report->notes ) : ?>
									<em style="color:#999;font-size:12px;"><?php esc_html_e( '—', '6arshid-social-community-main' ); ?></em>
								<?php endif; ?>
							</td>
							<td class="date column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>">
								<small><?php echo esc_html( $report_date ); ?></small>
							</td>
							<td class="actions column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>">
								<?php if ( 'pending' === $status ) : ?>
									<button class="button button-small arshid6social-resolve-report"
										data-report-id="<?php echo esc_attr( $report->id ); ?>"
										data-action-type="resolved"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_resolve_report_' . $report->id ) ); ?>">
										<?php esc_html_e( 'Resolve', '6arshid-social-community-main' ); ?>
									</button>
									<button class="button button-small arshid6social-resolve-report"
										data-report-id="<?php echo esc_attr( $report->id ); ?>"
										data-action-type="dismissed"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_resolve_report_' . $report->id ) ); ?>">
										<?php esc_html_e( 'Dismiss', '6arshid-social-community-main' ); ?>
									</button>
									<?php if ( $reported_user ) : ?>
										<button class="button button-small arshid6social-suspend-from-report <?php echo $is_suspended ? 'button-secondary' : 'button-link-delete'; ?>"
											data-user-id="<?php echo esc_attr( $reported_user_id ); ?>"
											data-report-id="<?php echo esc_attr( $report->id ); ?>"
											data-suspended="<?php echo esc_attr( $is_suspended ? '1' : '0' ); ?>"
											data-user-name="<?php echo esc_attr( $reported_user->display_name ); ?>"
											data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_suspend_report_' . $report->id ) ); ?>">
											<?php echo $is_suspended ? esc_html__( 'Unsuspend', '6arshid-social-community-main' ) : esc_html__( 'Suspend User', '6arshid-social-community-main' ); ?>
										</button>
									<?php endif; ?>
								<?php else : ?>
									<em><?php echo esc_html( ucfirst( $status ) ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php if ( empty( $reports ) ) : ?>
						<tr><td colspan="8" style="text-align:center;padding:2rem;">
							<?php esc_html_e( 'No reports found.', '6arshid-social-community-main' ); ?>
						</td></tr>
					<?php endif; ?>
				</tbody>

				<tfoot>
					<tr>
						<th class="manage-column column-report_id<?php echo esc_attr( $col_class( 'report_id' ) ); ?>"><?php esc_html_e( 'ID', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-reporter column-primary<?php echo esc_attr( $col_class( 'reporter' ) ); ?>"><?php esc_html_e( 'Reporter', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-reported<?php echo esc_attr( $col_class( 'reported' ) ); ?>"><?php esc_html_e( 'Reported', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>"><?php esc_html_e( 'Type', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-reason<?php echo esc_attr( $col_class( 'reason' ) ); ?>"><?php esc_html_e( 'Reason', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-notes<?php echo esc_attr( $col_class( 'notes' ) ); ?>"><?php esc_html_e( 'Notes / Attachment', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>"><?php esc_html_e( 'Date', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>"><?php esc_html_e( 'Actions', '6arshid-social-community-main' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<?php // ── Bottom tablenav ──────────────────────────────────────── ?>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- Suspend from report modal -->
		<div id="arshid6social-suspend-report-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;">
			<div style="background:#fff;border-radius:6px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
				<h2 style="margin-top:0;" id="arshid6social-srm-title"><?php esc_html_e( 'Suspend User', '6arshid-social-community-main' ); ?></h2>
				<p id="arshid6social-srm-desc" style="color:#555;margin-bottom:16px;"></p>
				<label style="display:block;margin-bottom:6px;font-weight:600;"><?php esc_html_e( 'Reason', '6arshid-social-community-main' ); ?></label>
				<select id="arshid6social-srm-reason" style="width:100%;margin-bottom:16px;">
					<option value=""><?php esc_html_e( '— Select a reason —', '6arshid-social-community-main' ); ?></option>
					<?php foreach ( $suspend_reasons as $r ) : ?>
						<option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?></option>
					<?php endforeach; ?>
					<option value="__custom__"><?php esc_html_e( 'Other (type below)', '6arshid-social-community-main' ); ?></option>
				</select>
				<input type="text" id="arshid6social-srm-custom" placeholder="<?php esc_attr_e( 'Custom reason…', '6arshid-social-community-main' ); ?>"
					style="width:100%;display:none;margin-bottom:16px;" />
				<div style="display:flex;gap:8px;justify-content:flex-end;">
					<button class="button" id="arshid6social-srm-cancel"><?php esc_html_e( 'Cancel', '6arshid-social-community-main' ); ?></button>
					<button class="button button-primary" id="arshid6social-srm-confirm"><?php esc_html_e( 'Confirm Suspension', '6arshid-social-community-main' ); ?></button>
				</div>
				<input type="hidden" id="arshid6social-srm-user-id" value="" />
				<input type="hidden" id="arshid6social-srm-report-id" value="" />
				<input type="hidden" id="arshid6social-srm-nonce" value="" />
				<input type="hidden" id="arshid6social-srm-suspended" value="" />
			</div>
		</div>
		<?php
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Returns a label and URL for the reported item.
	 *
	 * @param int    $item_id   Item ID.
	 * @param string $item_type Item type.
	 * @return array{url:string,label:string}|null
	 */
	private function get_item_link( int $item_id, string $item_type ): ?array {
		switch ( $item_type ) {
			case 'profile':
				$user = get_userdata( $item_id );
				if ( ! $user ) {
					return null;
				}
				return array(
					'url'   => home_url( '/members/' . $user->user_nicename . '/' ),
					'label' => $user->display_name,
				);

			case 'group':
				global $wpdb;
				$slug = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->prefix}sn_groups WHERE id = %d", $item_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}sn_groups WHERE id = %d", $item_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( ! $slug ) {
					return null;
				}
				return array(
					'url'   => home_url( '/groups/' . $slug . '/' ),
					/* translators: %d: group ID */
					'label' => $name ?: sprintf( __( 'Group #%d', '6arshid-social-community-main' ), $item_id ),
				);

			case 'activity':
				return array(
					'url'   => home_url( '/activity/' ),
					/* translators: %d: activity ID */
					'label' => sprintf( __( 'Activity #%d', '6arshid-social-community-main' ), $item_id ),
				);

			default:
				return null;
		}
	}

	/**
	 * Returns the list of suspension reasons from settings.
	 *
	 * @return string[]
	 */
	private function get_suspend_reasons(): array {
		$default = "Spam activity\nHarassment\nHate speech or discrimination\nInappropriate content\nMultiple violations\nViolation of community guidelines\nOther";
		$raw     = get_option( 'arshid6social_suspend_reasons', $default );
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public function ajax_resolve_report(): void {
		$report_id   = absint( $_POST['report_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$action_type = sanitize_key( $_POST['action_type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! check_ajax_referer( 'arshid6social_resolve_report_' . $report_id, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! current_user_can( 'arshid6social_manage_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community-main' ) ), 403 );
		}

		$allowed = array( 'resolved', 'dismissed' );
		if ( ! in_array( $action_type, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', '6arshid-social-community-main' ) ), 400 );
		}

		global $wpdb;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sn_reports',
			array(
				'status'        => $action_type,
				'date_resolved' => current_time( 'mysql' ),
				'resolved_by'   => get_current_user_id(),
			),
			array( 'id' => $report_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Database error.', '6arshid-social-community-main' ) ), 500 );
		}

		\Arshid6Social\Components\Moderation\Moderation::log_action(
			get_current_user_id(),
			'report_' . $action_type,
			'report',
			$report_id
		);

		wp_send_json_success( array( 'message' => __( 'Report updated.', '6arshid-social-community-main' ) ) );
	}

	public function ajax_suspend_from_report(): void {
		$report_id = absint( $_POST['report_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id   = absint( $_POST['user_id'] ?? 0 );   // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! check_ajax_referer( 'arshid6social_suspend_report_' . $report_id, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! current_user_can( 'arshid6social_manage_reports' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', '6arshid-social-community-main' ) ), 400 );
		}

		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$currently_suspended = (bool) get_user_meta( $user_id, 'arshid6social_suspended', true );
		$new_state           = ! $currently_suspended;

		update_user_meta( $user_id, 'arshid6social_suspended', $new_state );

		if ( $new_state && $reason ) {
			update_user_meta( $user_id, 'arshid6social_suspended_reason', $reason );
		} elseif ( ! $new_state ) {
			delete_user_meta( $user_id, 'arshid6social_suspended_reason' );
		}

		do_action( 'arshid6social_user_suspension_changed', $user_id, $new_state );

		\Arshid6Social\Components\Moderation\Moderation::log_action(
			get_current_user_id(),
			$new_state ? 'user_suspended' : 'user_unsuspended',
			'user',
			$user_id,
			array( 'reason' => $reason, 'report_id' => $report_id )
		);

		wp_send_json_success(
			array(
				'suspended' => $new_state,
				'label'     => $new_state
					? __( 'Unsuspend', '6arshid-social-community-main' )
					: __( 'Suspend User', '6arshid-social-community-main' ),
			)
		);
	}
}
