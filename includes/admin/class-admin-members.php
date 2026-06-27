<?php
namespace Arshid6Social\Admin;

/**
 * Admin member management page.
 *
 * @package Arshid6Social\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Members
 *
 * Displays the member list with search, filters, quick actions (suspend, delete profile),
 * and full Screen Options support (items per page + column visibility).
 */
final class Admin_Members {

	private static ?Admin_Members $instance = null;

	/** Screen ID assigned by WordPress for this page. */
	private string $screen_id = 'arshid6social-dashboard_page_arshid6social-members';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function hooks(): void {
		add_action( 'wp_ajax_arshid6social_admin_suspend_user',       array( $this, 'ajax_suspend_user' ) );
		add_action( 'wp_ajax_arshid6social_admin_delete_member_data', array( $this, 'ajax_delete_member_data' ) );

		// Register columns for Screen Options column-visibility checkboxes.
		add_filter( 'manage_' . $this->screen_id . '_columns', array( $this, 'get_columns' ) );

		// Save per-page screen option.
		add_filter( 'set_screen_option_arshid6social_members_per_page', array( $this, 'save_per_page' ), 10, 3 );
		add_filter( 'set-screen-option', array( $this, 'save_per_page_legacy' ), 10, 3 );
	}

	// ── Screen Options ────────────────────────────────────────────────────────

	/**
	 * Called on load-{hook} — registers the per-page Screen Option.
	 */
	public function setup_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Members per page', '6arshid-social-community-main' ),
				'default' => 20,
				'option'  => 'arshid6social_members_per_page',
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
			'member'     => __( 'Member', '6arshid-social-community-main' ),
			'email'      => __( 'Email', '6arshid-social-community-main' ),
			'registered' => __( 'Registered', '6arshid-social-community-main' ),
			'status'     => __( 'Status', '6arshid-social-community-main' ),
			'actions'    => __( 'Actions', '6arshid-social-community-main' ),
		);
	}

	/** Saves the per-page value (WP 5.4.2+). */
	public function save_per_page( $status, string $option, $value ): int {
		return (int) $value;
	}

	/** Saves the per-page value (WP < 5.4.2 compatibility). */
	public function save_per_page_legacy( $status, string $option, $value ) {
		if ( 'arshid6social_members_per_page' === $option ) {
			return (int) $value;
		}
		return $status;
	}

	// ── Render ────────────────────────────────────────────────────────────────

	/**
	 * Renders the admin members page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			wp_die( esc_html__( 'Permission denied.', '6arshid-social-community-main' ) );
		}

		// ── Screen Options values ─────────────────────────────────────────────
		$per_page = (int) get_user_option( 'arshid6social_members_per_page' );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$screen         = get_current_screen();
		$hidden_columns = $screen ? get_hidden_columns( $screen ) : array();

		// Helper: returns ' hidden' class if column should be hidden.
		$col_class = fn( string $col ) => in_array( $col, $hidden_columns, true ) ? ' hidden' : '';

		// ── Request params ────────────────────────────────────────────────────
		$search  = isset( $_GET['s'] )     ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) )              : 1;  // phpcs:ignore WordPress.Security.NonceVerification

		$args = array(
			'number' => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
			'search' => $search ? '*' . $search . '*' : '',
		);

		$users       = get_users( $args );
		$total_users = (int) ( new \WP_User_Query( array_merge( $args, array( 'count_total' => true, 'number' => 0, 'offset' => 0 ) ) ) )->get_total();
		$total_pages = (int) ceil( $total_users / $per_page );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Members', '6arshid-social-community-main' ); ?></h1>
			<hr class="wp-header-end">

			<?php // ── Search box ───────────────────────────────────────────── ?>
			<form method="get">
				<input type="hidden" name="page" value="arshid6social-members" />
				<p class="search-box">
					<label class="screen-reader-text" for="arshid6social-member-search"><?php esc_html_e( 'Search members', '6arshid-social-community-main' ); ?></label>
					<input type="search" id="arshid6social-member-search" name="s"
						value="<?php echo esc_attr( $search ); ?>"
						placeholder="<?php esc_attr_e( 'Search members…', '6arshid-social-community-main' ); ?>" />
					<?php submit_button( __( 'Search Members', '6arshid-social-community-main' ), 'secondary', '', false ); ?>
				</p>
			</form>

			<?php // ── Top tablenav ─────────────────────────────────────────── ?>
			<div class="tablenav top">
				<div class="tablenav-pages<?php echo 1 === $total_pages ? ' one-page' : ''; ?>">
					<span class="displaying-num">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of members */
								_n( '%s member', '%s members', $total_users, '6arshid-social-community-main' ),
								number_format_i18n( $total_users )
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

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="manage-column column-member column-primary"><?php esc_html_e( 'Member', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-email<?php echo esc_attr( $col_class( 'email' ) ); ?>"><?php esc_html_e( 'Email', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-registered<?php echo esc_attr( $col_class( 'registered' ) ); ?>"><?php esc_html_e( 'Registered', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-status<?php echo esc_attr( $col_class( 'status' ) ); ?>"><?php esc_html_e( 'Status', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>"><?php esc_html_e( 'Actions', '6arshid-social-community-main' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $user ) : ?>
						<?php $suspended = (bool) get_user_meta( $user->ID, 'arshid6social_suspended', true ); ?>
						<tr>
							<td class="member column-member column-primary has-row-actions">
								<?php echo get_avatar( $user->ID, 32 ); ?>
								<strong><?php echo esc_html( $user->display_name ); ?></strong>
								<div class="row-actions">
									<span>
										<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php esc_html_e( 'Edit', '6arshid-social-community-main' ); ?></a>
									</span>
								</div>
							</td>
							<td class="email column-email<?php echo esc_attr( $col_class( 'email' ) ); ?>"><?php echo esc_html( $user->user_email ); ?></td>
							<td class="registered column-registered<?php echo esc_attr( $col_class( 'registered' ) ); ?>">
								<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?>
							</td>
							<td class="status column-status<?php echo esc_attr( $col_class( 'status' ) ); ?>">
								<?php if ( $suspended ) : ?>
									<span class="arshid6social-badge arshid6social-badge--suspended"><?php esc_html_e( 'Suspended', '6arshid-social-community-main' ); ?></span>
									<?php
									$susp_reason = get_user_meta( $user->ID, 'arshid6social_suspended_reason', true );
									if ( $susp_reason && 'auto_threshold' !== $susp_reason ) :
									?>
										<br><small style="color:#555;"><?php echo esc_html( $susp_reason ); ?></small>
									<?php elseif ( 'auto_threshold' === $susp_reason ) : ?>
										<br><small style="color:#888;"><?php esc_html_e( 'Auto (report threshold)', '6arshid-social-community-main' ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span class="arshid6social-badge arshid6social-badge--active"><?php esc_html_e( 'Active', '6arshid-social-community-main' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="actions column-actions<?php echo esc_attr( $col_class( 'actions' ) ); ?>">
								<button class="button button-small arshid6social-admin-suspend-btn"
									data-user-id="<?php echo esc_attr( $user->ID ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_suspend_' . $user->ID ) ); ?>"
									data-suspended="<?php echo esc_attr( $suspended ? '1' : '0' ); ?>"
									data-user-name="<?php echo esc_attr( $user->display_name ); ?>">
									<?php echo $suspended ? esc_html__( 'Unsuspend', '6arshid-social-community-main' ) : esc_html__( 'Suspend', '6arshid-social-community-main' ); ?>
								</button>
								<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', '6arshid-social-community-main' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No members found.', '6arshid-social-community-main' ); ?></td></tr>
					<?php endif; ?>
				</tbody>

				<tfoot>
					<tr>
						<th class="manage-column column-member column-primary"><?php esc_html_e( 'Member', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-email<?php echo esc_attr( $col_class( 'email' ) ); ?>"><?php esc_html_e( 'Email', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-registered<?php echo esc_attr( $col_class( 'registered' ) ); ?>"><?php esc_html_e( 'Registered', '6arshid-social-community-main' ); ?></th>
						<th class="manage-column column-status<?php echo esc_attr( $col_class( 'status' ) ); ?>"><?php esc_html_e( 'Status', '6arshid-social-community-main' ); ?></th>
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

		<?php // ── Suspend modal ──────────────────────────────────────────────── ?>
		<?php
		$default_suspend_reasons = "Spam activity\nHarassment\nHate speech or discrimination\nInappropriate content\nMultiple violations\nViolation of community guidelines\nOther";
		$suspend_reasons         = array_values( array_filter( array_map( 'trim', explode( "\n", get_option( 'arshid6social_suspend_reasons', $default_suspend_reasons ) ) ) ) );
		?>
		<div id="arshid6social-suspend-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center;">
			<div style="background:#fff;border-radius:6px;padding:24px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
				<h2 style="margin-top:0;" id="arshid6social-sm-title"><?php esc_html_e( 'Suspend User', '6arshid-social-community-main' ); ?></h2>
				<p id="arshid6social-sm-desc" style="color:#555;margin-bottom:16px;"></p>
				<label style="display:block;margin-bottom:6px;font-weight:600;"><?php esc_html_e( 'Reason', '6arshid-social-community-main' ); ?></label>
				<select id="arshid6social-sm-reason" style="width:100%;margin-bottom:16px;">
					<option value=""><?php esc_html_e( '— Select a reason —', '6arshid-social-community-main' ); ?></option>
					<?php foreach ( $suspend_reasons as $r ) : ?>
						<option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?></option>
					<?php endforeach; ?>
					<option value="__custom__"><?php esc_html_e( 'Other (type below)', '6arshid-social-community-main' ); ?></option>
				</select>
				<input type="text" id="arshid6social-sm-custom" placeholder="<?php esc_attr_e( 'Custom reason…', '6arshid-social-community-main' ); ?>"
					style="width:100%;display:none;margin-bottom:16px;" />
				<div style="display:flex;gap:8px;justify-content:flex-end;">
					<button class="button" id="arshid6social-sm-cancel"><?php esc_html_e( 'Cancel', '6arshid-social-community-main' ); ?></button>
					<button class="button button-primary" id="arshid6social-sm-confirm"><?php esc_html_e( 'Confirm', '6arshid-social-community-main' ); ?></button>
				</div>
				<input type="hidden" id="arshid6social-sm-user-id" value="" />
				<input type="hidden" id="arshid6social-sm-nonce" value="" />
				<input type="hidden" id="arshid6social-sm-suspended" value="" />
			</div>
		</div>
		<?php
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	/**
	 * AJAX: Toggles a user's suspended status (accepts optional suspension reason).
	 */
	public function ajax_suspend_user(): void {
		$user_id = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! check_ajax_referer( 'arshid6social_suspend_' . $user_id, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', '6arshid-social-community-main' ) ), 400 );
		}

		$currently_suspended = (bool) get_user_meta( $user_id, 'arshid6social_suspended', true );
		$new_state           = ! $currently_suspended;
		$reason              = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		update_user_meta( $user_id, 'arshid6social_suspended', $new_state );

		if ( $new_state && $reason ) {
			update_user_meta( $user_id, 'arshid6social_suspended_reason', $reason );
		} elseif ( ! $new_state ) {
			delete_user_meta( $user_id, 'arshid6social_suspended_reason' );
		}

		clean_user_cache( $user_id );

		if ( $new_state ) {
			self::delete_user_standard_attachments( $user_id );
			delete_transient( 'arshid6social_sweep_done' );
		}

		do_action( 'arshid6social_user_suspension_changed', $user_id, $new_state );

		\Arshid6Social\Components\Moderation\Moderation::log_action(
			get_current_user_id(),
			$new_state ? 'user_suspended' : 'user_unsuspended',
			'user',
			$user_id,
			array( 'reason' => $reason )
		);

		wp_send_json_success(
			array(
				'suspended' => $new_state,
				'reason'    => $reason,
				'label'     => $new_state
					? __( 'Unsuspend', '6arshid-social-community-main' )
					: __( 'Suspend', '6arshid-social-community-main' ),
			)
		);
	}

	/**
	 * Permanently deletes all WP Media Library attachments that live in the standard
	 * uploads/YYYY/MM/ path for the given user. Files already inside social-network/
	 * are skipped because those are served through the PHP endpoint and checked there.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	private static function delete_user_standard_attachments( int $user_id ): void {
		global $wpdb;

		$upload_dir  = wp_upload_dir();
		$upload_base = $upload_dir['basedir'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, pm.meta_value AS rel_path
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm
			      ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
			 WHERE p.post_type = 'attachment'
			   AND p.post_author = %d
			   AND pm.meta_value NOT LIKE %s",
			$user_id,
			$wpdb->esc_like( 'social-network/' ) . '%'
		) );

		if ( ! $rows ) {
			return;
		}

		foreach ( $rows as $row ) {
			$att_id   = (int) $row->ID;
			$rel_path = (string) $row->rel_path;
			$abs_path = $upload_base . '/' . ltrim( $rel_path, '/' );

			if ( file_exists( $abs_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $abs_path );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$meta_raw = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta}
				 WHERE post_id = %d AND meta_key = '_wp_attachment_metadata' LIMIT 1",
				$att_id
			) );
			if ( $meta_raw ) {
				$meta = maybe_unserialize( $meta_raw );
				if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
					$dir = dirname( $abs_path );
					foreach ( $meta['sizes'] as $size_data ) {
						if ( ! empty( $size_data['file'] ) ) {
							$thumb = $dir . '/' . basename( $size_data['file'] );
							if ( file_exists( $thumb ) ) {
								// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
								@unlink( $thumb );
							}
						}
					}
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->posts, array( 'ID' => $att_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $att_id ), array( '%d' ) );
		}
	}

	/**
	 * AJAX: Deletes all social network data for a user (GDPR erasure).
	 */
	public function ajax_delete_member_data(): void {
		$user_id = absint( $_POST['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! check_ajax_referer( 'arshid6social_delete_data_' . $user_id, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', '6arshid-social-community-main' ) ), 403 );
		}

		if ( ! current_user_can( 'arshid6social_manage_members' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', '6arshid-social-community-main' ) ), 403 );
		}

		do_action( 'arshid6social_before_delete_member_data', $user_id );

		\Arshid6Social\Components\Moderation\Moderation::purge_user_data( $user_id );

		do_action( 'arshid6social_deleted_member_data', $user_id );

		wp_send_json_success( array( 'message' => __( 'Member data deleted.', '6arshid-social-community-main' ) ) );
	}
}
