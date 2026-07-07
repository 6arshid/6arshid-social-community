<?php
namespace Arshid6Social\Admin;

/**
 * Verification admin queue page.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Verification
 *
 * Displays the verification request queue with status tabs, per-row actions,
 * and full Screen Options support (items per page + column visibility).
 */
class Admin_Verification {

	private static ?Admin_Verification $instance = null;

	/** Screen ID assigned by WordPress for this page. */
	private string $screen_id = 'arshid6social-dashboard_page_arshid6social-verification';

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
		add_filter( 'set_screen_option_arshid6social_verification_per_page', array( $this, 'save_per_page' ), 10, 3 );
		add_filter( 'set-screen-option', array( $this, 'save_per_page_legacy' ), 10, 3 );
	}

	// ── Screen Options ────────────────────────────────────────────────────────

	public function setup_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Requests per page', '6arshid-social-community' ),
				'default' => 20,
				'option'  => 'arshid6social_verification_per_page',
			)
		);
	}

	/** @return array<string,string> */
	public function get_columns(): array {
		return array(
			'user'      => __( 'User', '6arshid-social-community' ),
			'type'      => __( 'Type', '6arshid-social-community' ),
			'full_name' => __( 'Full Name', '6arshid-social-community' ),
			'category'  => __( 'Category', '6arshid-social-community' ),
			'links'     => __( 'Links', '6arshid-social-community' ),
			'doc'       => __( 'Doc', '6arshid-social-community' ),
			'submitted' => __( 'Submitted', '6arshid-social-community' ),
			'actions'   => __( 'Actions', '6arshid-social-community' ),
		);
	}

	public function save_per_page( $status, string $option, $value ): int {
		return (int) $value;
	}

	public function save_per_page_legacy( $status, string $option, $value ) {
		if ( 'arshid6social_verification_per_page' === $option ) {
			return (int) $value;
		}
		return $status;
	}

	// ── Action handler ────────────────────────────────────────────────────────

	public function handle_actions(): void {
		if ( ! isset( $_GET['arshid6social_verify_action'], $_GET['request_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			return;
		}

		check_admin_referer( 'arshid6social_verify_action_' . absint( $_GET['request_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$action       = sanitize_key( $_GET['arshid6social_verify_action'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$request_id   = absint( $_GET['request_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$verification = ARSHID6SOCIAL()->component( 'verification' );

		if ( ! $verification ) {
			return;
		}

		switch ( $action ) {
			case 'approve':
				$type = sanitize_key( $_GET['type'] ?? 'general' ); // phpcs:ignore WordPress.Security.NonceVerification
				$verification->approve_request( $request_id, $type );
				$msg = 'approved';
				break;
			case 'reject':
				$reason = sanitize_text_field( wp_unslash( $_GET['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
				$verification->reject_request( $request_id, $reason );
				$msg = 'rejected';
				break;
			case 'more_info':
				$message = sanitize_textarea_field( wp_unslash( $_GET['message'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
				$verification->request_more_info( $request_id, $message );
				$msg = 'more_info';
				break;
			case 'revoke':
				global $wpdb;
				$req = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					"SELECT user_id FROM {$wpdb->prefix}sn_verification_requests WHERE id = %d",
					$request_id
				) );
				if ( $req ) {
					$verification->revoke( (int) $req->user_id );
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->prefix . 'sn_verification_requests',
						array( 'status' => 'revoked', 'reviewer_id' => get_current_user_id(), 'decided_at' => current_time( 'mysql' ) ),
						array( 'id' => $request_id ),
						array( '%s', '%d', '%s' ),
						array( '%d' )
					);
				}
				$msg = 'revoked';
				break;
			default:
				return;
		}

		$redirect_status = match ( $msg ) {
			'approved' => 'approved',
			'revoked'  => 'revoked',
			default    => 'pending',
		};

		wp_safe_redirect( admin_url( 'admin.php?page=arshid6social-verification&status=' . $redirect_status . '&arshid6social_msg=' . $msg ) );
		exit;
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render(): void {
		global $wpdb;

		// ── Screen Options values ─────────────────────────────────────────────
		$per_page = (int) get_user_option( 'arshid6social_verification_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$screen         = get_current_screen();
		$hidden_columns = $screen ? get_hidden_columns( $screen ) : array();
		$col_class      = fn( string $col ) => in_array( $col, $hidden_columns, true ) ? ' hidden' : '';

		// phpcs:disable WordPress.Security.NonceVerification
		$status = sanitize_key( $_GET['status'] ?? 'pending' );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable

		$offset = ( $paged - 1 ) * $per_page;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT r.*, u.user_login, u.display_name, u.user_email
			 FROM {$wpdb->prefix}sn_verification_requests r
			 JOIN {$wpdb->users} u ON u.ID = r.user_id
			 WHERE r.status = %s
			 ORDER BY r.created_at DESC
			 LIMIT %d OFFSET %d",
			$status, $per_page, $offset
		) ) ?: array();

		$total = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}sn_verification_requests WHERE status = %s",
			$status
		) );

		$total_pages = (int) ceil( $total / $per_page );

		// ── Tab counts ────────────────────────────────────────────────────────
		$tab_counts = array();
		foreach ( array( 'pending', 'approved', 'rejected', 'revoked' ) as $s ) {
			$tab_counts[ $s ] = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) FROM {$wpdb->prefix}sn_verification_requests WHERE status = %s",
				$s
			) );
		}

		// ── Flash messages ────────────────────────────────────────────────────
		if ( isset( $_GET['arshid6social_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$messages = array(
				'approved'  => __( 'Request approved and badge granted.', '6arshid-social-community' ),
				'rejected'  => __( 'Request rejected.', '6arshid-social-community' ),
				'more_info' => __( 'More info requested from user.', '6arshid-social-community' ),
				'revoked'   => __( 'Verification badge revoked.', '6arshid-social-community' ),
			);
			$msg_key = sanitize_key( $_GET['arshid6social_msg'] ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $messages[ $msg_key ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $msg_key ] ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Verification Queue', '6arshid-social-community' ); ?></h1>
			<hr class="wp-header-end">

			<?php // ── Status tabs ──────────────────────────────────────────── ?>
			<ul class="subsubsub">
				<?php
				$tab_labels = array(
					'pending'  => __( 'Pending', '6arshid-social-community' ),
					'approved' => __( 'Approved', '6arshid-social-community' ),
					'rejected' => __( 'Rejected', '6arshid-social-community' ),
					'revoked'  => __( 'Revoked', '6arshid-social-community' ),
				);
				$tab_list = array();
				foreach ( $tab_labels as $key => $label ) {
					$url      = admin_url( 'admin.php?page=arshid6social-verification&status=' . $key );
					$is_active = ( $key === $status );
					$count    = $tab_counts[ $key ] ?? 0;
					$tab_list[] = sprintf(
						'<li><a href="%s"%s>%s <span class="count">(%s)</span></a>',
						esc_url( $url ),
						$is_active ? ' class="current"' : '',
						esc_html( $label ),
						esc_html( number_format_i18n( $count ) )
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
								'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=arshid6social-verification&status=' . $status ) ),
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

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="manage-column column-user column-primary"><?php esc_html_e( 'User', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>"><?php esc_html_e( 'Type', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-full_name<?php echo esc_attr( $col_class( 'full_name' ) ); ?>"><?php esc_html_e( 'Full Name', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-category<?php echo esc_attr( $col_class( 'category' ) ); ?>"><?php esc_html_e( 'Category', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-links<?php echo esc_attr( $col_class( 'links' ) ); ?>"><?php esc_html_e( 'Links', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-doc<?php echo esc_attr( $col_class( 'doc' ) ); ?>"><?php esc_html_e( 'Doc', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-submitted<?php echo esc_attr( $col_class( 'submitted' ) ); ?>"><?php esc_html_e( 'Submitted', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>"><?php esc_html_e( 'Actions', '6arshid-social-community' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No requests found.', '6arshid-social-community' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$types_conf = ARSHID6SOCIAL()->component( 'verification' ) ? ARSHID6SOCIAL()->component( 'verification' )->get_types() : array();
							$type_label = $types_conf[ $row->type ]['label'] ?? $row->type;
							$fields     = json_decode( $row->fields_json ?? '{}', true );
							$doc_paths  = json_decode( $row->document_paths ?? '[]', true );
							?>
							<tr>
								<td class="user column-user column-primary">
									<strong><?php echo esc_html( $row->display_name ); ?></strong><br>
									<a href="<?php echo esc_url( 'mailto:' . $row->user_email ); ?>"><?php echo esc_html( $row->user_email ); ?></a>
								</td>
								<td class="type column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>"><?php echo esc_html( $type_label ); ?></td>
								<td class="full_name column-full_name<?php echo esc_attr( $col_class( 'full_name' ) ); ?>"><?php echo esc_html( $fields['full_name'] ?? '' ); ?></td>
								<td class="category column-category<?php echo esc_attr( $col_class( 'category' ) ); ?>"><?php echo esc_html( $fields['category'] ?? '' ); ?></td>
								<td class="links column-links<?php echo esc_attr( $col_class( 'links' ) ); ?>">
									<?php if ( ! empty( $fields['links'] ) ) : ?>
										<?php foreach ( explode( "\n", $fields['links'] ) as $link ) : ?>
											<?php $link = trim( $link ); ?>
											<?php if ( $link ) : ?>
												<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
													<?php echo esc_html( wp_trim_words( $link, 5 ) ); ?>
												</a><br>
											<?php endif; ?>
										<?php endforeach; ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td class="doc column-doc<?php echo esc_attr( $col_class( 'doc' ) ); ?>">
									<?php if ( ! empty( $doc_paths ) ) : ?>
										<?php foreach ( $doc_paths as $idx => $path ) : ?>
											<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=arshid6social_serve_verification_doc&request_id=' . $row->id . '&idx=' . $idx . '&nonce=' . wp_create_nonce( 'arshid6social_ajax_nonce' ) ) ); ?>" target="_blank">
												<?php
												/* translators: %d: document number */
												echo esc_html( sprintf( __( 'Doc %d', '6arshid-social-community' ), $idx + 1 ) ); ?>
											</a><br>
										<?php endforeach; ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td class="submitted column-submitted<?php echo esc_attr( $col_class( 'submitted' ) ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row->created_at ) ) ); ?>
								</td>
								<td class="actions column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>">
									<?php if ( 'pending' === $row->status ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url(
											admin_url( 'admin.php?page=arshid6social-verification&arshid6social_verify_action=approve&request_id=' . $row->id . '&type=' . esc_attr( $row->type ) ),
											'arshid6social_verify_action_' . $row->id
										) ); ?>" class="button button-primary button-small">
											<?php esc_html_e( 'Approve', '6arshid-social-community' ); ?>
										</a>
										<a href="<?php echo esc_url( wp_nonce_url(
											admin_url( 'admin.php?page=arshid6social-verification&arshid6social_verify_action=reject&request_id=' . $row->id ),
											'arshid6social_verify_action_' . $row->id
										) ); ?>" class="button button-small"
											onclick="return confirm('<?php esc_attr_e( 'Reject this request?', '6arshid-social-community' ); ?>')">
											<?php esc_html_e( 'Reject', '6arshid-social-community' ); ?>
										</a>
									<?php elseif ( 'approved' === $row->status ) : ?>
										<span class="dashicons dashicons-yes-alt" style="color:#16a34a;vertical-align:middle;"></span>
										<a href="<?php echo esc_url( wp_nonce_url(
											admin_url( 'admin.php?page=arshid6social-verification&arshid6social_verify_action=revoke&request_id=' . $row->id ),
											'arshid6social_verify_action_' . $row->id
										) ); ?>" class="button button-small" style="color:#b91c1c;border-color:#fca5a5;"
											onclick="return confirm('<?php esc_attr_e( 'Revoke this verification badge?', '6arshid-social-community' ); ?>')">
											<?php esc_html_e( 'Revoke Badge', '6arshid-social-community' ); ?>
										</a>
									<?php elseif ( 'rejected' === $row->status ) : ?>
										<span class="dashicons dashicons-dismiss" style="color:#dc2626"></span>
									<?php elseif ( 'revoked' === $row->status ) : ?>
										<span class="dashicons dashicons-remove" style="color:#92400e"></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>

				<tfoot>
					<tr>
						<th class="manage-column column-user column-primary"><?php esc_html_e( 'User', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-type<?php echo esc_attr( $col_class( 'type' ) ); ?>"><?php esc_html_e( 'Type', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-full_name<?php echo esc_attr( $col_class( 'full_name' ) ); ?>"><?php esc_html_e( 'Full Name', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-category<?php echo esc_attr( $col_class( 'category' ) ); ?>"><?php esc_html_e( 'Category', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-links<?php echo esc_attr( $col_class( 'links' ) ); ?>"><?php esc_html_e( 'Links', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-doc<?php echo esc_attr( $col_class( 'doc' ) ); ?>"><?php esc_html_e( 'Doc', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-submitted<?php echo esc_attr( $col_class( 'submitted' ) ); ?>"><?php esc_html_e( 'Submitted', '6arshid-social-community' ); ?></th>
						<th class="manage-column column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>"><?php esc_html_e( 'Actions', '6arshid-social-community' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<?php // ── Bottom pagination ────────────────────────────────────── ?>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
							'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=arshid6social-verification&status=' . $status ) ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
