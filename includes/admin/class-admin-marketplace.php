<?php
namespace Arshid6Social\Admin;

/**
 * Admin Marketplace page — lists all marketplace listings.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Marketplace
 *
 * Renders the marketplace listings list table with search, status filters,
 * bulk actions, per-row quick actions, and full Screen Options support
 * (items per page + column visibility).
 */
final class Admin_Marketplace {

	private static ?Admin_Marketplace $instance = null;

	/** Screen ID assigned by WordPress for this page. */
	private string $screen_id = 'arshid6social-dashboard_page_arshid6social-marketplace';

	/** All available columns (id => label). */
	private array $all_columns = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->all_columns = array(
			'seller'         => '',
			'category'       => '',
			'price'          => '',
			'item_condition' => '',
			'location'       => '',
			'status'         => '',
			'date'           => '',
		);
	}

	private function hooks(): void {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// Register columns for Screen Options panel (column-visibility checkboxes).
		add_filter( 'manage_' . $this->screen_id . '_columns', array( $this, 'get_columns' ) );

		// Save per-page screen option.
		add_filter( 'set_screen_option_arshid6social_mkt_per_page', array( $this, 'save_per_page' ), 10, 3 );
		add_filter( 'set-screen-option', array( $this, 'save_per_page_legacy' ), 10, 3 );
	}

	// ── Screen Options ────────────────────────────────────────────────────────

	/**
	 * Called on load-{hook} — registers the per-page Screen Option.
	 * Column checkboxes are registered automatically via the columns filter.
	 */
	public function setup_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Listings per page', '6arshid-social-community' ),
				'default' => 20,
				'option'  => 'arshid6social_mkt_per_page',
			)
		);
	}

	/**
	 * Returns all available columns (used by WP to build Screen Options checkboxes).
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'title'          => __( 'Title', '6arshid-social-community' ),
			'seller'         => __( 'Seller', '6arshid-social-community' ),
			'category'       => __( 'Category', '6arshid-social-community' ),
			'price'          => __( 'Price', '6arshid-social-community' ),
			'item_condition' => __( 'Condition', '6arshid-social-community' ),
			'location'       => __( 'Location', '6arshid-social-community' ),
			'status'         => __( 'Status', '6arshid-social-community' ),
			'date'           => __( 'Date', '6arshid-social-community' ),
		);
	}

	/** Saves the per-page value (WP 5.4.2+). */
	public function save_per_page( $status, string $option, $value ): int {
		return (int) $value;
	}

	/** Saves the per-page value (WP < 5.4.2 compatibility). */
	public function save_per_page_legacy( $status, string $option, $value ) {
		if ( 'arshid6social_mkt_per_page' === $option ) {
			return (int) $value;
		}
		return $status;
	}

	// ── Action handler ────────────────────────────────────────────────────────

	/**
	 * Processes bulk actions and single-row actions before output.
	 */
	public function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || 'arshid6social-marketplace' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			return;
		}

		// Single-row action.
		if ( ! empty( $_GET['action'] ) && ! empty( $_GET['listing_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$action     = sanitize_key( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$listing_id = absint( $_GET['listing_id'] ); // phpcs:ignore WordPress.Security.NonceVerification

			if ( ! check_admin_referer( 'arshid6social_mkt_' . $action . '_' . $listing_id ) ) {
				wp_die( esc_html__( 'Security check failed.', '6arshid-social-community' ) );
			}

			$this->process_single_action( $action, $listing_id );

			wp_safe_redirect( remove_query_arg( array( 'action', 'listing_id', '_wpnonce' ) ) );
			exit;
		}

		// Bulk action.
		if ( ! empty( $_POST['action'] ) && ! empty( $_POST['listing_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! check_admin_referer( 'arshid6social_mkt_bulk', '_wpnonce_bulk' ) ) {
				wp_die( esc_html__( 'Security check failed.', '6arshid-social-community' ) );
			}

			$action      = sanitize_key( wp_unslash( $_POST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$listing_ids = array_map( 'absint', (array) $_POST['listing_ids'] ); // phpcs:ignore WordPress.Security.NonceVerification

			foreach ( $listing_ids as $id ) {
				$this->process_single_action( $action, $id );
			}

			wp_safe_redirect( remove_query_arg( array( 'action', 'listing_ids', '_wpnonce_bulk' ) ) );
			exit;
		}
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'arshid6social_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '6arshid-social-community' ) );
		}

		global $wpdb;

		// ── Screen Options values ─────────────────────────────────────────────
		$per_page = (int) get_user_option( 'arshid6social_mkt_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$screen         = get_current_screen();
		$hidden_columns = $screen ? get_hidden_columns( $screen ) : array();

		// ── Request params ────────────────────────────────────────────────────
		$search        = isset( $_GET['s'] )      ? sanitize_text_field( wp_unslash( $_GET['s'] ) )   : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) )     : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$paged         = isset( $_GET['paged'] )  ? max( 1, absint( $_GET['paged'] ) )                : 1;  // phpcs:ignore WordPress.Security.NonceVerification

		// ── Build WHERE ───────────────────────────────────────────────────────
		$where  = 'WHERE 1=1';
		$params = array();

		if ( $status_filter && in_array( $status_filter, array( 'active', 'draft', 'archived', 'expired' ), true ) ) {
			$where   .= ' AND l.status = %s';
			$params[] = $status_filter;
		}

		if ( $search ) {
			$where   .= ' AND (l.title LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		// ── Count per status ──────────────────────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$status_counts_raw = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}arshid6social_listings GROUP BY status"
		);
		$counts = array( 'all' => 0 );
		foreach ( $status_counts_raw as $row ) {
			$counts[ $row->status ] = (int) $row->cnt;
			$counts['all']         += (int) $row->cnt;
		}

		// ── Total for pagination ──────────────────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT COUNT(*) FROM {$wpdb->prefix}arshid6social_listings l
			 LEFT JOIN {$wpdb->users} u ON u.ID = l.seller_id
			 $where",
			...$params
		) );
		$total_pages = (int) ceil( $total / $per_page );
		$offset      = ( $paged - 1 ) * $per_page;

		// ── Fetch rows ────────────────────────────────────────────────────────
		$select = "SELECT l.id, l.title, l.status, l.price, l.is_free, l.item_condition,
		                  l.location_city, l.location_country, l.created_at, l.category_id,
		                  u.display_name AS seller_name, u.user_email AS seller_email,
		                  c.name AS category_name
		           FROM {$wpdb->prefix}arshid6social_listings l
		           LEFT JOIN {$wpdb->users} u ON u.ID = l.seller_id
		           LEFT JOIN {$wpdb->prefix}arshid6social_categories c ON c.id = l.category_id
		           $where
		           ORDER BY l.created_at DESC
		           LIMIT %d OFFSET %d";

		$row_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$listings = $wpdb->get_results( $wpdb->prepare( $select, ...$row_params ) );

		// ── Helpers ───────────────────────────────────────────────────────────
		$status_labels = array(
			'active'   => __( 'Active', '6arshid-social-community' ),
			'draft'    => __( 'Draft', '6arshid-social-community' ),
			'archived' => __( 'Archived', '6arshid-social-community' ),
			'expired'  => __( 'Expired', '6arshid-social-community' ),
		);

		$current_url = admin_url( 'admin.php?page=arshid6social-marketplace' );

		// Helper: return 'hidden' class string if column should be hidden.
		$col_class = fn( string $col ) => in_array( $col, $hidden_columns, true ) ? ' hidden' : '';

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Products Archive', '6arshid-social-community' ); ?></h1>
			<hr class="wp-header-end">

			<?php // ── Status tabs ──────────────────────────────────────────── ?>
			<ul class="subsubsub">
				<?php
				$tab_items = array( '' => __( 'All', '6arshid-social-community' ) ) + $status_labels;
				$tab_list  = array();
				foreach ( $tab_items as $tab_key => $tab_label ) {
					$count     = ( '' === $tab_key ) ? $counts['all'] : ( $counts[ $tab_key ] ?? 0 );
					$is_active = ( $status_filter === $tab_key );
					$url       = $tab_key ? add_query_arg( 'status', $tab_key, $current_url ) : $current_url;
					$class     = $is_active ? ' class="current"' : '';
					$tab_list[] = sprintf(
						'<li><a href="%s"%s>%s <span class="count">(%s)</span></a>',
						esc_url( $url ),
						$class,
						esc_html( $tab_label ),
						esc_html( number_format_i18n( $count ) )
					);
				}
				echo implode( ' | </li>', $tab_list ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</ul>

			<?php // ── Search box ───────────────────────────────────────────── ?>
			<form method="get" style="margin-top:8px;">
				<input type="hidden" name="page" value="arshid6social-marketplace" />
				<?php if ( $status_filter ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="arshid6social-mkt-search"><?php esc_html_e( 'Search listings', '6arshid-social-community' ); ?></label>
					<input type="search" id="arshid6social-mkt-search" name="s" value="<?php echo esc_attr( $search ); ?>"
						placeholder="<?php esc_attr_e( 'Search by title, seller…', '6arshid-social-community' ); ?>" />
					<?php submit_button( __( 'Search Listings', '6arshid-social-community' ), 'secondary', '', false ); ?>
				</p>
			</form>

			<?php // ── Bulk action form ─────────────────────────────────────── ?>
			<form method="post" id="arshid6social-mkt-bulk-form">
				<?php wp_nonce_field( 'arshid6social_mkt_bulk', '_wpnonce_bulk' ); ?>

				<?php $this->render_tablenav( 'top', $total, $total_pages, $paged ); ?>

				<table class="wp-list-table widefat fixed striped posts">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="arshid6social-mkt-check-all" />
							</td>
							<th class="manage-column column-title column-primary"><?php esc_html_e( 'Title', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-seller<?php echo esc_attr( $col_class( 'seller' ) ); ?>"><?php esc_html_e( 'Seller', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-category<?php echo esc_attr( $col_class( 'category' ) ); ?>"><?php esc_html_e( 'Category', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-price<?php echo esc_attr( $col_class( 'price' ) ); ?>"><?php esc_html_e( 'Price', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-item_condition<?php echo esc_attr( $col_class( 'item_condition' ) ); ?>"><?php esc_html_e( 'Condition', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-location<?php echo esc_attr( $col_class( 'location' ) ); ?>"><?php esc_html_e( 'Location', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-status<?php echo esc_attr( $col_class( 'status' ) ); ?>"><?php esc_html_e( 'Status', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>"><?php esc_html_e( 'Date', '6arshid-social-community' ); ?></th>
						</tr>
					</thead>

					<tbody>
						<?php if ( empty( $listings ) ) : ?>
							<tr>
								<td colspan="9"><?php esc_html_e( 'No listings found.', '6arshid-social-community' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $listings as $listing ) : ?>
								<?php
								$delete_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => 'delete', 'listing_id' => $listing->id ),
										$current_url
									),
									'arshid6social_mkt_delete_' . $listing->id
								);
								$archive_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => 'set_archived', 'listing_id' => $listing->id ),
										$current_url
									),
									'arshid6social_mkt_set_archived_' . $listing->id
								);
								$activate_url = wp_nonce_url(
									add_query_arg(
										array( 'action' => 'set_active', 'listing_id' => $listing->id ),
										$current_url
									),
									'arshid6social_mkt_set_active_' . $listing->id
								);

								$status_color = match ( $listing->status ) {
									'active'   => '#00a32a',
									'draft'    => '#9ea3a8',
									'expired'  => '#d63638',
									'archived' => '#8c8f94',
									default    => '#9ea3a8',
								};

								$location = trim( implode( ', ', array_filter( array( $listing->location_city, strtoupper( $listing->location_country ) ) ) ) );
								$price    = $listing->is_free
									? __( 'Free', '6arshid-social-community' )
									: \Arshid6Social\Components\Marketplace\Marketplace::format_price( $listing->price );
								?>
								<tr>
									<th class="check-column" scope="row">
										<input type="checkbox" name="listing_ids[]" value="<?php echo esc_attr( $listing->id ); ?>" />
									</th>
									<td class="title column-title column-primary has-row-actions">
										<strong><?php echo esc_html( $listing->title ?: __( '(no title)', '6arshid-social-community' ) ); ?></strong>
										<div class="row-actions">
											<?php if ( 'active' !== $listing->status ) : ?>
												<span class="activate">
													<a href="<?php echo esc_url( $activate_url ); ?>"><?php esc_html_e( 'Activate', '6arshid-social-community' ); ?></a> |
												</span>
											<?php endif; ?>
											<?php if ( 'archived' !== $listing->status ) : ?>
												<span class="trash">
													<a href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'Archive', '6arshid-social-community' ); ?></a> |
												</span>
											<?php endif; ?>
											<span class="delete">
												<a href="<?php echo esc_url( $delete_url ); ?>"
													class="submitdelete"
													onclick="return confirm('<?php esc_attr_e( 'Delete this listing permanently?', '6arshid-social-community' ); ?>')">
													<?php esc_html_e( 'Delete', '6arshid-social-community' ); ?>
												</a>
											</span>
										</div>
									</td>
									<td class="seller column-seller<?php echo esc_attr( $col_class( 'seller' ) ); ?>">
										<?php if ( $listing->seller_name ) : ?>
											<a href="<?php echo esc_url( add_query_arg( 's', $listing->seller_email, admin_url( 'admin.php?page=arshid6social-members' ) ) ); ?>">
												<?php echo esc_html( $listing->seller_name ); ?>
											</a>
										<?php else : ?>
											<span style="color:#999;"><?php esc_html_e( 'Unknown', '6arshid-social-community' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="category column-category<?php echo esc_attr( $col_class( 'category' ) ); ?>"><?php echo esc_html( $listing->category_name ?: '—' ); ?></td>
									<td class="price column-price<?php echo esc_attr( $col_class( 'price' ) ); ?>"><?php echo esc_html( $price ); ?></td>
									<td class="item_condition column-item_condition<?php echo esc_attr( $col_class( 'item_condition' ) ); ?>"><?php echo esc_html( ucfirst( $listing->item_condition ) ); ?></td>
									<td class="location column-location<?php echo esc_attr( $col_class( 'location' ) ); ?>"><?php echo esc_html( $location ?: '—' ); ?></td>
									<td class="status column-status<?php echo esc_attr( $col_class( 'status' ) ); ?>">
										<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;color:#fff;background:<?php echo esc_attr( $status_color ); ?>;">
											<?php echo esc_html( $status_labels[ $listing->status ] ?? ucfirst( $listing->status ) ); ?>
										</span>
									</td>
									<td class="date column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>">
										<abbr title="<?php echo esc_attr( $listing->created_at ); ?>">
											<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $listing->created_at ) ) ); ?>
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
							<th class="manage-column column-title column-primary"><?php esc_html_e( 'Title', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-seller<?php echo esc_attr( $col_class( 'seller' ) ); ?>"><?php esc_html_e( 'Seller', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-category<?php echo esc_attr( $col_class( 'category' ) ); ?>"><?php esc_html_e( 'Category', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-price<?php echo esc_attr( $col_class( 'price' ) ); ?>"><?php esc_html_e( 'Price', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-item_condition<?php echo esc_attr( $col_class( 'item_condition' ) ); ?>"><?php esc_html_e( 'Condition', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-location<?php echo esc_attr( $col_class( 'location' ) ); ?>"><?php esc_html_e( 'Location', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-status<?php echo esc_attr( $col_class( 'status' ) ); ?>"><?php esc_html_e( 'Status', '6arshid-social-community' ); ?></th>
							<th class="manage-column column-date<?php echo esc_attr( $col_class( 'date' ) ); ?>"><?php esc_html_e( 'Date', '6arshid-social-community' ); ?></th>
						</tr>
					</tfoot>
				</table>

				<?php $this->render_tablenav( 'bottom', $total, $total_pages, $paged ); ?>
			</form>
		</div>

		<?php
		$mkt_admin_js = '(function(){
			var checkAll = document.getElementById("arshid6social-mkt-check-all");
			if ( checkAll ) {
				checkAll.addEventListener("change", function(){
					document.querySelectorAll("#arshid6social-mkt-bulk-form input[name=\'listing_ids[]\']").forEach(function(cb){
						cb.checked = this.checked;
					}, this);
				});
			}

			window.arshid6social_mkt_confirm_bulk = function(btn) {
				var form   = document.getElementById("arshid6social-mkt-bulk-form");
				var select = form.querySelector("select[name=\'action\']");
				var action = select ? select.value : "";
				if ( ! action ) {
					alert(' . wp_json_encode( __( 'Please select a bulk action.', '6arshid-social-community' ) ) . ');
					return false;
				}
				var checked = form.querySelectorAll("input[name=\'listing_ids[]\']:checked");
				if ( ! checked.length ) {
					alert(' . wp_json_encode( __( 'Please select at least one listing.', '6arshid-social-community' ) ) . ');
					return false;
				}
				if ( action === "delete" ) {
					return confirm(' . wp_json_encode( __( 'Delete selected listings permanently?', '6arshid-social-community' ) ) . ');
				}
				return true;
			};
		})();';
		wp_add_inline_script( 'arshid6social-admin', $mkt_admin_js );
		?>
		<?php
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Outputs a tablenav bar (bulk selects + pagination).
	 *
	 * @param string $position 'top' or 'bottom'.
	 * @param int    $total       Total item count.
	 * @param int    $total_pages Number of pages.
	 * @param int    $paged       Current page.
	 */
	private function render_tablenav( string $position, int $total, int $total_pages, int $paged ): void {
		?>
		<div class="tablenav <?php echo esc_attr( $position ); ?>">
			<div class="alignleft actions bulkactions">
				<label class="screen-reader-text" for="bulk-action-selector-<?php echo esc_attr( $position ); ?>">
					<?php esc_html_e( 'Select bulk action', '6arshid-social-community' ); ?>
				</label>
				<select name="action" id="bulk-action-selector-<?php echo esc_attr( $position ); ?>">
					<option value=""><?php esc_html_e( 'Bulk actions', '6arshid-social-community' ); ?></option>
					<option value="set_active"><?php esc_html_e( 'Set Active', '6arshid-social-community' ); ?></option>
					<option value="set_archived"><?php esc_html_e( 'Set Archived', '6arshid-social-community' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', '6arshid-social-community' ); ?></option>
				</select>
				<input type="submit" class="button action"
					value="<?php esc_attr_e( 'Apply', '6arshid-social-community' ); ?>"
					onclick="return arshid6social_mkt_confirm_bulk(this);" />
			</div>
			<div class="tablenav-pages<?php echo 1 === $total_pages ? ' one-page' : ''; ?>">
				<span class="displaying-num">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of items */
							_n( '%s item', '%s items', $total, '6arshid-social-community' ),
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
		<?php
	}

	/**
	 * Executes a single action on one listing.
	 *
	 * @param string $action     'delete' | 'set_active' | 'set_archived'.
	 * @param int    $listing_id Listing ID.
	 */
	private function process_single_action( string $action, int $listing_id ): void {
		global $wpdb;

		if ( ! $listing_id ) {
			return;
		}

		if ( 'delete' === $action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( "{$wpdb->prefix}arshid6social_listing_media", array( 'listing_id' => $listing_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( "{$wpdb->prefix}arshid6social_listings", array( 'id' => $listing_id ), array( '%d' ) );
			return;
		}

		$new_status = match ( $action ) {
			'set_active'   => 'active',
			'set_archived' => 'archived',
			default        => null,
		};

		if ( $new_status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				"{$wpdb->prefix}arshid6social_listings",
				array( 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $listing_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}
}
