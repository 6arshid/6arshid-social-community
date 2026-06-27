<?php
namespace Arshid6Social\Admin;

/**
 * Admin Activity Items page.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Activity
 *
 * Displays all activity items with search, status filters (All / Spam / Hidden),
 * bulk actions, per-row quick actions, and full Screen Options support
 * (items per page + column visibility).
 */
final class Admin_Activity {

	private static ?Admin_Activity $instance = null;

	/** Screen ID assigned by WordPress for this page. */
	private string $screen_id = 'arshid6social-dashboard_page_arshid6social-activity';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function hooks(): void {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// Register columns for Screen Options column-visibility checkboxes.
		add_filter( 'manage_' . $this->screen_id . '_columns', array( $this, 'get_columns' ) );

		// Save per-page screen option.
		add_filter( 'set_screen_option_arshid6social_activity_per_page', array( $this, 'save_per_page' ), 10, 3 );
		add_filter( 'set-screen-option', array( $this, 'save_per_page_legacy' ), 10, 3 );
	}

	// ── Screen Options ────────────────────────────────────────────────────────

	public function setup_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Activity items per page', '6arshid social community' ),
				'default' => 20,
				'option'  => 'arshid6social_activity_per_page',
			)
		);
	}

	/** @return array<string,string> */
	public function get_columns(): array {
		return array(
			'author'    => __( 'Author', '6arshid social community' ),
			'type'      => __( 'Type', '6arshid social community' ),
			'component' => __( 'Component', '6arshid social community' ),
			'content'   => __( 'Content', '6arshid social community' ),
			'flags'     => __( 'Flags', '6arshid social community' ),
			'date'      => __( 'Date', '6arshid social community' ),
		);
	}

	public function save_per_page( $status, string $option, $value ): int {
		return (int) $value;
	}

	public function save_per_page_legacy( $status, string $option, $value ) {
		if ( 'arshid6social_activity_per_page' === $option ) {
			return (int) $value;
		}
		return $status;
	}

	// ── Action handler ────────────────────────────────────────────────────────

	public function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || 'arshid6social-activity' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			return;
		}

		// Single-row action.
		if ( ! empty( $_GET['action'] ) && ! empty( $_GET['activity_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$action      = sanitize_key( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$activity_id = absint( $_GET['activity_id'] ); // phpcs:ignore WordPress.Security.NonceVerification

			if ( ! check_admin_referer( 'arshid6social_act_' . $action . '_' . $activity_id ) ) {
				wp_die( esc_html__( 'Security check failed.', '6arshid social community' ) );
			}

			$this->process_single_action( $action, $activity_id );

			wp_safe_redirect( remove_query_arg( array( 'action', 'activity_id', '_wpnonce' ) ) );
			exit;
		}

		// Bulk action.
		if ( ! empty( $_POST['action'] ) && ! empty( $_POST['activity_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! check_admin_referer( 'arshid6social_act_bulk', '_wpnonce_bulk' ) ) {
				wp_die( esc_html__( 'Security check failed.', '6arshid social community' ) );
			}

			$action       = sanitize_key( wp_unslash( $_POST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$activity_ids = array_map( 'absint', (array) $_POST['activity_ids'] ); // phpcs:ignore WordPress.Security.NonceVerification

			foreach ( $activity_ids as $id ) {
				$this->process_single_action( $action, $id );
			}

			wp_safe_redirect( remove_query_arg( array( 'action', 'activity_ids', '_wpnonce_bulk' ) ) );
			exit;
		}
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '6arshid social community' ) );
		}

		global $wpdb;

		// ── Screen Options values ─────────────────────────────────────────────
		$per_page = (int) get_user_option( 'arshid6social_activity_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$screen         = get_current_screen();
		$hidden_columns = $screen ? get_hidden_columns( $screen ) : array();
		$col_class      = fn( string $col ) => in_array( $col, $hidden_columns, true ) ? ' hidden' : '';

		// ── Request params ────────────────────────────────────────────────────
		$search     = isset( $_GET['s'] )      ? sanitize_text_field( wp_unslash( $_GET['s'] ) )  : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$tab        = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) )    : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$paged      = isset( $_GET['paged'] )  ? max( 1, absint( $_GET['paged'] ) )               : 1;  // phpcs:ignore WordPress.Security.NonceVerification

		// ── Build WHERE ───────────────────────────────────────────────────────
		$where  = 'WHERE 1=1';
		$params = array();

		if ( 'spam' === $tab ) {
			$where .= ' AND a.is_spam = 1';
		} elseif ( 'hidden' === $tab ) {
			$where .= ' AND a.hide_sitewide = 1 AND a.is_spam = 0';
		} else {
			$where .= ' AND a.is_spam = 0';
		}

		if ( $search ) {
			$where   .= ' AND (a.content LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		// ── Count per tab ─────────────────────────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$all_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity WHERE is_spam = 0" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$spam_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity WHERE is_spam = 1" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$hidden_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity WHERE hide_sitewide = 1 AND is_spam = 0" );

		// ── Total for pagination ──────────────────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_activity a
			 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id $where",
			...$params
		) );
		$total_pages = (int) ceil( $total / $per_page );
		$offset      = ( $paged - 1 ) * $per_page;

		// ── Fetch rows ────────────────────────────────────────────────────────
		$select = "SELECT a.id, a.user_id, a.component, a.type, a.content, a.action,
		                  a.date_recorded, a.hide_sitewide, a.is_spam, a.privacy,
		                  u.display_name AS author_name, u.user_email AS author_email
		           FROM {$wpdb->prefix}sn_activity a
		           LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
		           $where
		           ORDER BY a.date_recorded DESC
		           LIMIT %d OFFSET %d";

		$row_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $select, ...$row_params ) );

		$current_url = admin_url( 'admin.php?page=arshid6social-activity' );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Activity Items', '6arshid social community' ); ?></h1>
			<hr class="wp-header-end">

			<?php // ── Status tabs ──────────────────────────────────────────── ?>
			<ul class="subsubsub">
				<?php
				$tabs = array(
					''       => array( __( 'All', '6arshid social community' ),    $all_count ),
					'spam'   => array( __( 'Spam', '6arshid social community' ),   $spam_count ),
					'hidden' => array( __( 'Hidden', '6arshid social community' ), $hidden_count ),
				);
				$tab_list = array();
				foreach ( $tabs as $key => list( $label, $count ) ) {
					$is_active  = ( $tab === $key );
					$url        = $key ? add_query_arg( 'status', $key, $current_url ) : $current_url;
					$class      = $is_active ? ' class="current"' : '';
					$tab_list[] = sprintf(
						'<li><a href="%s"%s>%s <span class="count">(%s)</span></a>',
						esc_url( $url ),
						$class,
						esc_html( $label ),
						esc_html( number_format_i18n( $count ) )
					);
				}
				echo implode( ' | </li>', $tab_list ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</ul>

			<?php // ── Search box ───────────────────────────────────────────── ?>
			<form method="get" style="margin-top:8px;">
				<input type="hidden" name="page" value="arshid6social-activity" />
				<?php if ( $tab ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $tab ); ?>" />
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="arshid6social-act-search"><?php esc_html_e( 'Search activity', '6arshid social community' ); ?></label>
					<input type="search" id="arshid6social-act-search" name="s"
						value="<?php echo esc_attr( $search ); ?>"
						placeholder="<?php esc_attr_e( 'Search by content, author…', '6arshid social community' ); ?>" />
					<?php submit_button( __( 'Search Activity', '6arshid social community' ), 'secondary', '', false ); ?>
				</p>
			</form>

			<?php // ── Bulk action form ─────────────────────────────────────── ?>
			<form method="post" id="arshid6social-act-bulk-form">
				<?php wp_nonce_field( 'arshid6social_act_bulk', '_wpnonce_bulk' ); ?>

				<?php $this->render_tablenav( 'top', $total, $total_pages, $paged, $tab ); ?>

				<table class="wp-list-table widefat fixed striped posts">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="arshid6social-act-check-all" />
							</td>
							<th class="manage-column column-author column-primary"><?php esc_html_e( 'Author', '6arshid social community' ); ?></th>
							<th class="manage-column column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>"><?php esc_html_e( 'Type', '6arshid social community' ); ?></th>
							<th class="manage-column column-component<?php echo esc_attr( $col_class( 'component' ) ); ?>"><?php esc_html_e( 'Component', '6arshid social community' ); ?></th>
							<th class="manage-column column-content<?php echo esc_attr( $col_class( 'content' ) ); ?>"><?php esc_html_e( 'Content', '6arshid social community' ); ?></th>
							<th class="manage-column column-flags<?php echo esc_attr( $col_class( 'flags' ) ); ?>"><?php esc_html_e( 'Flags', '6arshid social community' ); ?></th>
							<th class="manage-column column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>"><?php esc_html_e( 'Date', '6arshid social community' ); ?></th>
						</tr>
					</thead>

					<tbody>
						<?php if ( empty( $items ) ) : ?>
							<tr>
								<td colspan="7"><?php esc_html_e( 'No activity items found.', '6arshid social community' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$spam_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => $item->is_spam ? 'unspam' : 'spam', 'activity_id' => $item->id ),
										$current_url
									),
									'arshid6social_act_' . ( $item->is_spam ? 'unspam' : 'spam' ) . '_' . $item->id
								);
								$hide_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => $item->hide_sitewide ? 'show' : 'hide', 'activity_id' => $item->id ),
										$current_url
									),
									'arshid6social_act_' . ( $item->hide_sitewide ? 'show' : 'hide' ) . '_' . $item->id
								);
								$delete_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => 'delete', 'activity_id' => $item->id ),
										$current_url
									),
									'arshid6social_act_delete_' . $item->id
								);

								$content_plain = wp_strip_all_tags( $item->content );
								$content_short = mb_strlen( $content_plain ) > 120
									? mb_substr( $content_plain, 0, 120 ) . '…'
									: $content_plain;

								$type_label = ucwords( str_replace( '_', ' ', $item->type ) );
								$comp_label = ucwords( str_replace( '_', ' ', $item->component ) );
								?>
								<tr>
									<th class="check-column" scope="row">
										<input type="checkbox" name="activity_ids[]" value="<?php echo esc_attr( $item->id ); ?>" />
									</th>
									<td class="author column-author column-primary has-row-actions">
										<?php if ( $item->author_name ) : ?>
											<?php echo get_avatar( (int) $item->user_id, 32 ); ?>
											<strong>
												<a href="<?php echo esc_url( add_query_arg( 's', $item->author_email, admin_url( 'admin.php?page=arshid6social-members' ) ) ); ?>">
													<?php echo esc_html( $item->author_name ); ?>
												</a>
											</strong>
										<?php else : ?>
											<span style="color:#999;"><?php esc_html_e( 'System', '6arshid social community' ); ?></span>
										<?php endif; ?>
										<div class="row-actions">
											<span>
												<a href="<?php echo esc_url( $spam_url ); ?>">
													<?php echo $item->is_spam ? esc_html__( 'Not Spam', '6arshid social community' ) : esc_html__( 'Spam', '6arshid social community' ); ?>
												</a>
											</span>
											|
											<span>
												<a href="<?php echo esc_url( $hide_url ); ?>">
													<?php echo $item->hide_sitewide ? esc_html__( 'Show', '6arshid social community' ) : esc_html__( 'Hide', '6arshid social community' ); ?>
												</a>
											</span>
											|
											<span class="delete">
												<a href="<?php echo esc_url( $delete_url ); ?>"
													class="submitdelete"
													onclick="return confirm('<?php esc_attr_e( 'Delete this activity item permanently?', '6arshid social community' ); ?>')">
													<?php esc_html_e( 'Delete', '6arshid social community' ); ?>
												</a>
											</span>
										</div>
									</td>
									<td class="type column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>">
										<?php echo esc_html( $type_label ); ?>
									</td>
									<td class="component column-component<?php echo esc_attr( $col_class( 'component' ) ); ?>">
										<?php echo esc_html( $comp_label ); ?>
									</td>
									<td class="content column-content<?php echo esc_attr( $col_class( 'content' ) ); ?>">
										<?php if ( $content_short ) : ?>
											<span title="<?php echo esc_attr( $content_plain ); ?>">
												<?php echo esc_html( $content_short ); ?>
											</span>
										<?php else : ?>
											<em style="color:#999;"><?php echo esc_html( wp_strip_all_tags( $item->action ) ); ?></em>
										<?php endif; ?>
									</td>
									<td class="flags column-flags<?php echo esc_attr( $col_class( 'flags' ) ); ?>">
										<?php if ( $item->is_spam ) : ?>
											<span style="display:inline-block;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:600;color:#fff;background:#d63638;margin-bottom:3px;">
												<?php esc_html_e( 'Spam', '6arshid social community' ); ?>
											</span>
										<?php endif; ?>
										<?php if ( $item->hide_sitewide ) : ?>
											<span style="display:inline-block;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:600;color:#fff;background:#8c8f94;">
												<?php esc_html_e( 'Hidden', '6arshid social community' ); ?>
											</span>
										<?php endif; ?>
										<?php if ( ! $item->is_spam && ! $item->hide_sitewide ) : ?>
											—
										<?php endif; ?>
									</td>
									<td class="date column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>">
										<abbr title="<?php echo esc_attr( $item->date_recorded ); ?>">
											<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $item->date_recorded ) ) ); ?>
										</abbr>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>

					<tfoot>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" />
							</td>
							<th class="manage-column column-author column-primary"><?php esc_html_e( 'Author', '6arshid social community' ); ?></th>
							<th class="manage-column column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>"><?php esc_html_e( 'Type', '6arshid social community' ); ?></th>
							<th class="manage-column column-component<?php echo esc_attr( $col_class( 'component' ) ); ?>"><?php esc_html_e( 'Component', '6arshid social community' ); ?></th>
							<th class="manage-column column-content<?php echo esc_attr( $col_class( 'content' ) ); ?>"><?php esc_html_e( 'Content', '6arshid social community' ); ?></th>
							<th class="manage-column column-flags<?php echo esc_attr( $col_class( 'flags' ) ); ?>"><?php esc_html_e( 'Flags', '6arshid social community' ); ?></th>
							<th class="manage-column column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>"><?php esc_html_e( 'Date', '6arshid social community' ); ?></th>
						</tr>
					</tfoot>
				</table>

				<?php $this->render_tablenav( 'bottom', $total, $total_pages, $paged, $tab ); ?>
			</form>
		</div>

		<?php
		$js_act  = '(function(){';
		$js_act .= 'var txtSelectAction=' . wp_json_encode( __( 'Please select a bulk action.', '6arshid social community' ) ) . ';';
		$js_act .= 'var txtSelectItem=' . wp_json_encode( __( 'Please select at least one item.', '6arshid social community' ) ) . ';';
		$js_act .= 'var txtConfirmDelete=' . wp_json_encode( __( 'Delete selected activity items permanently?', '6arshid social community' ) ) . ';';
		$js_act .= <<<'ENDJS'
var checkAll=document.getElementById('arshid6social-act-check-all');
if(checkAll){checkAll.addEventListener('change',function(){document.querySelectorAll('#arshid6social-act-bulk-form input[name="activity_ids[]"]').forEach(function(cb){cb.checked=this.checked;},this);});}
window.arshid6social_act_confirm_bulk=function(){
	var form=document.getElementById('arshid6social-act-bulk-form');
	var select=form.querySelector('select[name="action"]');
	var action=select?select.value:'';
	if(!action){alert(txtSelectAction);return false;}
	var checked=form.querySelectorAll('input[name="activity_ids[]"]:checked');
	if(!checked.length){alert(txtSelectItem);return false;}
	if(action==='delete'){return confirm(txtConfirmDelete);}
	return true;
};
})();
ENDJS;
		wp_add_inline_script( 'arshid6social-admin', $js_act );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function render_tablenav( string $position, int $total, int $total_pages, int $paged, string $tab ): void {
		?>
		<div class="tablenav <?php echo esc_attr( $position ); ?>">
			<div class="alignleft actions bulkactions">
				<label class="screen-reader-text" for="bulk-action-selector-<?php echo esc_attr( $position ); ?>">
					<?php esc_html_e( 'Select bulk action', '6arshid social community' ); ?>
				</label>
				<select name="action" id="bulk-action-selector-<?php echo esc_attr( $position ); ?>">
					<option value=""><?php esc_html_e( 'Bulk actions', '6arshid social community' ); ?></option>
					<option value="spam"><?php esc_html_e( 'Mark as Spam', '6arshid social community' ); ?></option>
					<option value="unspam"><?php esc_html_e( 'Not Spam', '6arshid social community' ); ?></option>
					<option value="hide"><?php esc_html_e( 'Hide', '6arshid social community' ); ?></option>
					<option value="show"><?php esc_html_e( 'Show', '6arshid social community' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', '6arshid social community' ); ?></option>
				</select>
				<input type="submit" class="button action"
					value="<?php esc_attr_e( 'Apply', '6arshid social community' ); ?>"
					onclick="return arshid6social_act_confirm_bulk();" />
			</div>
			<div class="tablenav-pages<?php echo 1 === $total_pages ? ' one-page' : ''; ?>">
				<span class="displaying-num">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of items */
							_n( '%s item', '%s items', $total, '6arshid social community' ),
							number_format_i18n( $total )
						)
					);
					?>
				</span>
				<?php if ( $total_pages > 1 ) : ?>
					<?php
					$page_url = $tab
						? add_query_arg( 'status', $tab, admin_url( 'admin.php?page=arshid6social-activity' ) )
						: admin_url( 'admin.php?page=arshid6social-activity' );
					echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
						array(
							'base'      => add_query_arg( 'paged', '%#%', $page_url ),
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
		<?php
	}

	/**
	 * Executes a single action on one activity item.
	 *
	 * @param string $action      'delete' | 'spam' | 'unspam' | 'hide' | 'show'.
	 * @param int    $activity_id Activity item ID.
	 */
	private function process_single_action( string $action, int $activity_id ): void {
		global $wpdb;

		if ( ! $activity_id ) {
			return;
		}

		if ( 'delete' === $action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( "{$wpdb->prefix}sn_activity_meta",      array( 'activity_id' => $activity_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( "{$wpdb->prefix}sn_activity_reactions",  array( 'activity_id' => $activity_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( "{$wpdb->prefix}sn_activity_media",      array( 'activity_id' => $activity_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( "{$wpdb->prefix}sn_activity",            array( 'id'          => $activity_id ), array( '%d' ) );
			return;
		}

		$updates = match ( $action ) {
			'spam'   => array( 'is_spam'        => 1 ),
			'unspam' => array( 'is_spam'        => 0 ),
			'hide'   => array( 'hide_sitewide'  => 1 ),
			'show'   => array( 'hide_sitewide'  => 0 ),
			default  => null,
		};

		if ( $updates ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				"{$wpdb->prefix}sn_activity",
				$updates,
				array( 'id' => $activity_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}
}
