<?php
/**
 * Single group view template.
 *
 * Variables available:
 *  @var array  $group        Formatted group array from Groups::format_group()
 *  @var object $component    Groups component instance
 *  @var string $current_tab Active tab slug
 *
 * @package Arshid6Social
 */

defined( 'ABSPATH' ) || exit;

$group_id   = absint( $group['id'] );
$is_member  = (bool) ( $group['isMember'] ?? false );
$is_admin   = (bool) ( $group['isAdmin'] ?? false );

// Check group suspension: show notice to non-admins.
$_group_suspended  = (bool) ( $group['isSuspended'] ?? false );
$_viewer_is_admin  = current_user_can( 'arshid6social_manage_reports' );

if ( $_group_suspended ) {
	echo '<div class="arshid6social-wrap"><div class="arshid6social-container" style="padding-block:4rem;">';
	echo '<div class="arshid6social-suspended-notice">';
	echo '<div class="arshid6social-suspended-notice__icon">&#128683;</div>';
	echo '<h2 class="arshid6social-suspended-notice__title">' . esc_html__( 'Group Suspended', '6arshid social community' ) . '</h2>';
	echo '<p class="arshid6social-suspended-notice__msg">' . esc_html__( 'This group has been suspended and its content is not available.', '6arshid social community' ) . '</p>';
	echo '<div class="arshid6social-suspended-notice__actions">';
	echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="arshid6social-btn arshid6social-btn-primary">' . esc_html__( 'Back to Home', '6arshid social community' ) . '</a>';
	if ( $_viewer_is_admin ) {
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=arshid6social-moderation&moderation_view=groups' ) ) . '" class="arshid6social-btn arshid6social-btn-secondary">' . esc_html__( 'Manage in Admin', '6arshid social community' ) . '</a>';
	}
	echo '</div>';
	echo '</div>';
	echo '</div></div>';
	return;
}
$is_public  = 'public' === ( $group['status'] ?? 'public' );
$cover_url  = esc_url( $group['coverUrl'] ?? '' );
$avatar_url = esc_url( $group['avatarUrl'] ?? '' );
$nonce_join = wp_create_nonce( 'arshid6social_join_group_' . $group_id );

$privacy_labels = array(
	'public'  => __( 'Public', '6arshid social community' ),
	'private' => __( 'Private', '6arshid social community' ),
	'hidden'  => __( 'Hidden', '6arshid social community' ),
);

$tabs = apply_filters(
	'arshid6social_group_tabs',
	array(
		'activity' => __( 'Activity', '6arshid social community' ),
		'members'  => __( 'Members', '6arshid social community' ),
	),
	$group
);

if ( $is_admin ) {
	$tabs['manage'] = __( 'Manage', '6arshid social community' );
}

$current_tab = isset( $current_tab ) ? sanitize_key( $current_tab ) : 'activity';
if ( ! array_key_exists( $current_tab, $tabs ) ) {
	$current_tab = 'activity';
}
?>

<div class="arshid6social-wrap" id="arshid6social-group-page">
<div class="arshid6social-container" style="padding-block:2rem;">

	<!-- Group header card -->
	<div class="arshid6social-card" id="arshid6social-group-header-card">

		<!-- Cover -->
		<div class="arshid6social-profile-cover"
			<?php if ( $cover_url ) : ?>style="background-image:url('<?php echo esc_url( $cover_url ); ?>');"<?php endif; ?>
			role="img"
			aria-label="<?php
			/* translators: %s: group name */
			printf( esc_attr__( '%s cover photo', '6arshid social community' ), esc_attr( $group['name'] ) ); ?>">
			<?php if ( $is_admin ) : ?>
				<button type="button" class="arshid6social-btn arshid6social-btn-sm arshid6social-btn-secondary arshid6social-cover-edit-btn"
					data-action="arshid6social-upload-group-cover"
					data-group-id="<?php echo esc_attr( $group_id ); ?>">
					<?php esc_html_e( 'Edit Cover', '6arshid social community' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<!-- Group info row -->
		<div class="arshid6social-profile-header">

			<!-- Group avatar -->
			<div class="arshid6social-avatar-wrap arshid6social-group-avatar-wrap">
				<?php if ( $avatar_url ) : ?>
					<img src="<?php echo esc_url( $avatar_url ); ?>"
						alt="<?php echo esc_attr( $group['name'] ); ?>"
						class="arshid6social-avatar arshid6social-avatar--lg arshid6social-group-avatar"
						id="arshid6social-group-avatar-img"
						width="80" height="80" />
				<?php else : ?>
					<div class="arshid6social-avatar arshid6social-avatar--lg arshid6social-avatar-initial arshid6social-group-avatar"
						id="arshid6social-group-avatar-img"
						aria-label="<?php echo esc_attr( $group['name'] ); ?>">
						<?php echo esc_html( mb_strtoupper( mb_substr( $group['name'], 0, 1 ) ) ); ?>
					</div>
				<?php endif; ?>
				<?php if ( $is_admin ) : ?>
					<button type="button" class="arshid6social-group-avatar-edit-btn"
						title="<?php esc_attr_e( 'Change group photo', '6arshid social community' ); ?>"
						data-group-id="<?php echo esc_attr( $group_id ); ?>">
						&#9998;
					</button>
				<?php endif; ?>
			</div>

			<!-- Name + stats -->
			<div style="flex:1;min-width:0;">
				<h1 class="arshid6social-profile-name" style="margin-bottom:.25rem;">
					<?php echo esc_html( $group['name'] ); ?>
				</h1>
				<div class="arshid6social-profile-meta">
					<span>
						<?php
						printf(
							/* translators: %s: member count */
							esc_html( _n( '%s member', '%s members', $group['memberCount'] ?? 0, '6arshid social community' ) ),
							esc_html( number_format_i18n( $group['memberCount'] ?? 0 ) )
						);
						?>
					</span>
					<span>&middot;</span>
					<span class="arshid6social-status-badge arshid6social-status-<?php echo esc_attr( $group['status'] ?? 'public' ); ?>">
						<?php echo esc_html( $privacy_labels[ $group['status'] ] ?? ucfirst( $group['status'] ) ); ?>
					</span>
				</div>
			</div>

			<!-- Action buttons -->
			<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
				<?php if ( is_user_logged_in() ) : ?>
					<?php if ( $is_member ) : ?>
						<button type="button" class="arshid6social-btn arshid6social-btn-secondary arshid6social-group-leave-btn"
							data-group-id="<?php echo esc_attr( $group_id ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_leave_group_' . $group_id ) ); ?>">
							<?php esc_html_e( 'Leave Group', '6arshid social community' ); ?>
						</button>
					<?php elseif ( $is_public ) : ?>
						<button type="button" class="arshid6social-btn arshid6social-btn-primary arshid6social-group-join-btn"
							data-group-id="<?php echo esc_attr( $group_id ); ?>"
							data-nonce="<?php echo esc_attr( $nonce_join ); ?>">
							<?php esc_html_e( 'Join Group', '6arshid social community' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="arshid6social-btn arshid6social-btn-primary arshid6social-group-join-btn"
							data-group-id="<?php echo esc_attr( $group_id ); ?>"
							data-nonce="<?php echo esc_attr( $nonce_join ); ?>">
							<?php esc_html_e( 'Request to Join', '6arshid social community' ); ?>
						</button>
					<?php endif; ?>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url( $group['url'] ?? '' ) ); ?>" class="arshid6social-btn arshid6social-btn-primary">
						<?php esc_html_e( 'Join Group', '6arshid social community' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( is_user_logged_in() && ! $is_admin ) : ?>
					<button class="arshid6social-btn arshid6social-btn-ghost arshid6social-report-btn"
						data-item-id="<?php echo esc_attr( $group_id ); ?>"
						data-item-type="group"
						title="<?php esc_attr_e( 'Report this group', '6arshid social community' ); ?>">
						&#9873; <?php esc_html_e( 'Report', '6arshid social community' ); ?>
					</button>
				<?php endif; ?>
			</div>

		</div><!-- .arshid6social-profile-header -->

		<!-- Tabs -->
		<nav class="arshid6social-tabs" aria-label="<?php esc_attr_e( 'Group sections', '6arshid social community' ); ?>" role="tablist">
			<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
				<?php $tab_url = home_url( '/groups/' . $group['slug'] . '/' . $tab_slug . '/' ); ?>
				<a href="<?php echo esc_url( $tab_url ); ?>"
					class="arshid6social-tab-link <?php echo ( $current_tab === $tab_slug ) ? 'is-active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo ( $current_tab === $tab_slug ) ? 'true' : 'false'; ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

	</div><!-- .arshid6social-card -->

	<!-- Content grid: sidebar + main -->
	<div class="arshid6social-grid arshid6social-grid--sidebar" style="margin-block-start:1.5rem;">

		<!-- Sidebar: About -->
		<div>
			<div class="arshid6social-card arshid6social-card--about">
				<div class="arshid6social-card__header"><?php esc_html_e( 'About', '6arshid social community' ); ?></div>
				<div class="arshid6social-card__body">
					<?php if ( ! empty( $group['description'] ) ) : ?>
						<p style="margin:0 0 1rem;color:var(--arshid6social-text);"><?php echo wp_kses_post( $group['description'] ); ?></p>
					<?php endif; ?>
					<dl class="arshid6social-group-about-dl">
						<div>
							<dt><?php esc_html_e( 'Members', '6arshid social community' ); ?></dt>
							<dd><?php echo esc_html( number_format_i18n( $group['memberCount'] ?? 0 ) ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Privacy', '6arshid social community' ); ?></dt>
							<dd><?php echo esc_html( $privacy_labels[ $group['status'] ] ?? ucfirst( $group['status'] ) ); ?></dd>
						</div>
					</dl>
				</div>
			</div>
		</div>

		<!-- Main tab content -->
		<div>

			<?php if ( 'activity' === $current_tab ) : ?>

				<div class="arshid6social-activity-block" data-group-id="<?php echo esc_attr( $group_id ); ?>" data-per-page="10">
					<?php if ( $is_member && is_user_logged_in() ) : ?>
						<form class="arshid6social-activity-form arshid6social-card"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_activity' ) ); ?>">
							<?php wp_nonce_field( 'arshid6social_activity', 'arshid6social_nonce' ); ?>
							<input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" />
							<textarea name="content" class="arshid6social-activity-composer"
								placeholder="<?php esc_attr_e( 'Post in this group…', '6arshid social community' ); ?>"
								rows="3" maxlength="5000" required></textarea>
							<div class="arshid6social-activity-form-footer">
								<button type="submit" class="arshid6social-btn arshid6social-btn-primary">
									<?php esc_html_e( 'Post', '6arshid social community' ); ?>
								</button>
							</div>
						</form>
					<?php elseif ( ! is_user_logged_in() ) : ?>
						<div class="arshid6social-card" style="padding:1.25rem;text-align:center;color:var(--arshid6social-text-muted);">
							<a href="<?php echo esc_url( wp_login_url( $group['url'] ?? '' ) ); ?>" class="arshid6social-btn arshid6social-btn-primary" style="margin-top:.5rem;">
								<?php esc_html_e( 'Log in to post', '6arshid social community' ); ?>
							</a>
						</div>
					<?php endif; ?>
					<div class="arshid6social-activity-feed" aria-label="<?php esc_attr_e( 'Group activity', '6arshid social community' ); ?>"></div>
					<div class="arshid6social-load-more-sentinel" style="height:1px;"></div>
				</div>

			<?php elseif ( 'members' === $current_tab ) : ?>

				<div class="arshid6social-group-members-grid">
					<?php
					global $wpdb;
					$table = $wpdb->prefix . 'sn_groups_members';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT user_id, is_admin FROM `{$table}` WHERE group_id = %d AND is_confirmed = 1 AND is_banned = 0 ORDER BY is_admin DESC, date_modified ASC LIMIT 100",
							$group_id
						)
					);

					if ( empty( $rows ) ) :
						?>
						<p class="arshid6social-text-muted" style="color:var(--arshid6social-text-muted);"><?php esc_html_e( 'No members yet.', '6arshid social community' ); ?></p>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$member = get_userdata( $row->user_id );
							if ( ! $member ) continue;
							$member_url    = home_url( '/members/' . $member->user_nicename . '/' );
							$member_avatar = get_avatar_url( $member->ID, array( 'size' => 60 ) );
						?>
						<div class="arshid6social-member-card arshid6social-card">
							<a href="<?php echo esc_url( $member_url ); ?>">
								<img src="<?php echo esc_url( $member_avatar ); ?>"
									alt="<?php echo esc_attr( $member->display_name ); ?>"
									class="arshid6social-avatar arshid6social-avatar-md"
									width="60" height="60" />
							</a>
							<div class="arshid6social-member-card-body">
								<a class="arshid6social-member-name" href="<?php echo esc_url( $member_url ); ?>">
									<?php echo esc_html( $member->display_name ); ?>
								</a>
								<?php if ( $row->is_admin ) : ?>
									<span class="arshid6social-status-badge arshid6social-status-active"><?php esc_html_e( 'Admin', '6arshid social community' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

			<?php endif; ?>

			<?php do_action( 'arshid6social_group_tab_content', $current_tab, $group ); ?>

			<?php if ( 'manage' === $current_tab && $is_admin ) : ?>

				<!-- Edit group form -->
				<div class="arshid6social-card" style="padding:2rem;margin-bottom:1.5rem;">
					<h2 style="margin:0 0 1.25rem;font-size:1.1rem;font-weight:600;"><?php esc_html_e( 'Edit Group', '6arshid social community' ); ?></h2>
					<form class="arshid6social-edit-group-form" novalidate>
						<input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" />

						<div class="arshid6social-form-group">
							<label class="arshid6social-label" for="arshid6social-edit-group-name">
								<?php esc_html_e( 'Group Name', '6arshid social community' ); ?> <span aria-hidden="true" style="color:var(--arshid6social-danger);">*</span>
							</label>
							<input type="text" id="arshid6social-edit-group-name" name="name" class="arshid6social-input"
								required maxlength="100"
								value="<?php echo esc_attr( $group['name'] ); ?>" />
						</div>

						<div class="arshid6social-form-group">
							<label class="arshid6social-label" for="arshid6social-edit-group-desc">
								<?php esc_html_e( 'Description', '6arshid social community' ); ?>
							</label>
							<textarea id="arshid6social-edit-group-desc" name="description" class="arshid6social-input"
								rows="4" maxlength="1000" style="height:auto;"><?php echo esc_textarea( wp_strip_all_tags( $group['description'] ) ); ?></textarea>
						</div>

						<div class="arshid6social-form-group">
							<label class="arshid6social-label" for="arshid6social-edit-group-status">
								<?php esc_html_e( 'Privacy', '6arshid social community' ); ?>
							</label>
							<select id="arshid6social-edit-group-status" name="status" class="arshid6social-input">
								<option value="public"  <?php selected( $group['status'], 'public' ); ?>><?php esc_html_e( 'Public — anyone can join and see posts', '6arshid social community' ); ?></option>
								<option value="private" <?php selected( $group['status'], 'private' ); ?>><?php esc_html_e( 'Private — anyone can request, but only members see posts', '6arshid social community' ); ?></option>
								<option value="hidden"  <?php selected( $group['status'], 'hidden' ); ?>><?php esc_html_e( 'Hidden — invite only, not listed in directory', '6arshid social community' ); ?></option>
							</select>
						</div>

						<div id="arshid6social-edit-group-msg" class="arshid6social-notice" hidden></div>

						<div class="arshid6social-form-actions">
							<button type="submit" class="arshid6social-btn arshid6social-btn-primary">
								<?php esc_html_e( 'Save Changes', '6arshid social community' ); ?>
							</button>
						</div>
					</form>
				</div>

				<!-- Danger zone: delete group -->
				<div class="arshid6social-card" style="padding:2rem;border:1px solid var(--arshid6social-danger,#dc2626);">
					<h2 style="margin:0 0 .5rem;font-size:1.1rem;font-weight:600;color:var(--arshid6social-danger,#dc2626);"><?php esc_html_e( 'Delete Group', '6arshid social community' ); ?></h2>
					<p style="margin:0 0 1rem;color:var(--arshid6social-text-muted);"><?php esc_html_e( 'Permanently delete this group and all its data. This cannot be undone.', '6arshid social community' ); ?></p>
					<button type="button" class="arshid6social-btn arshid6social-btn-danger arshid6social-group-delete-btn"
						data-group-id="<?php echo esc_attr( $group_id ); ?>"
						data-group-name="<?php echo esc_attr( $group['name'] ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'arshid6social_ajax_nonce' ) ); ?>"
						data-redirect="<?php echo esc_attr( home_url( '/groups/' ) ); ?>">
						<?php esc_html_e( 'Delete Group', '6arshid social community' ); ?>
					</button>
				</div>

				<script>
				( function () {
					// Edit group form.
					const editForm = document.querySelector( '.arshid6social-edit-group-form' );
					const msgBox   = document.getElementById( 'arshid6social-edit-group-msg' );
					const cfg      = window.ARSHID6SOCIALConfig || {};

					if ( editForm ) {
						editForm.addEventListener( 'submit', async ( e ) => {
							e.preventDefault();
							const btn = editForm.querySelector( '[type="submit"]' );
							btn.disabled = true;
							btn.textContent = '<?php echo esc_js( __( 'Saving…', '6arshid social community' ) ); ?>';
							if ( msgBox ) { msgBox.hidden = true; msgBox.className = 'arshid6social-notice'; }

							const body = new FormData();
							body.append( 'action',      'arshid6social_update_group' );
							body.append( 'nonce',       cfg.ajaxNonce || '' );
							body.append( 'group_id',    editForm.querySelector( '[name="group_id"]' ).value );
							body.append( 'name',        editForm.querySelector( '[name="name"]' ).value );
							body.append( 'description', editForm.querySelector( '[name="description"]' ).value );
							body.append( 'status',      editForm.querySelector( '[name="status"]' ).value );

							try {
								const res  = await fetch( cfg.ajaxUrl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body } );
								const data = await res.json();
								if ( data.success ) {
									if ( msgBox ) {
										msgBox.textContent = data.data?.message || '<?php echo esc_js( __( 'Saved.', '6arshid social community' ) ); ?>';
										msgBox.classList.add( 'arshid6social-notice--success' );
										msgBox.hidden = false;
									}
								} else {
									if ( msgBox ) {
										msgBox.textContent = data.data?.message || '<?php echo esc_js( __( 'Could not save.', '6arshid social community' ) ); ?>';
										msgBox.classList.add( 'arshid6social-notice--error' );
										msgBox.hidden = false;
									}
								}
							} catch {
								if ( msgBox ) {
									msgBox.textContent = '<?php echo esc_js( __( 'Network error. Please try again.', '6arshid social community' ) ); ?>';
									msgBox.classList.add( 'arshid6social-notice--error' );
									msgBox.hidden = false;
								}
							} finally {
								btn.disabled = false;
								btn.textContent = '<?php echo esc_js( __( 'Save Changes', '6arshid social community' ) ); ?>';
							}
						} );
					}

					// Delete group button.
					const delBtn = document.querySelector( '.arshid6social-group-delete-btn' );
					if ( delBtn ) {
						delBtn.addEventListener( 'click', async () => {
							const name = delBtn.dataset.groupName || '';
							if ( ! window.confirm( '<?php echo esc_js( __( 'Are you sure you want to delete this group? This cannot be undone.', '6arshid social community' ) ); ?>' ) ) {
								return;
							}
							delBtn.disabled = true;
							delBtn.textContent = '<?php echo esc_js( __( 'Deleting…', '6arshid social community' ) ); ?>';

							const body = new FormData();
							body.append( 'action',   'arshid6social_delete_group' );
							body.append( 'nonce',    delBtn.dataset.nonce );
							body.append( 'group_id', delBtn.dataset.groupId );

							try {
								const res  = await fetch( cfg.ajaxUrl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body } );
								const data = await res.json();
								if ( data.success ) {
									window.location.href = delBtn.dataset.redirect || '<?php echo esc_js( home_url( '/groups/' ) ); ?>';
								} else {
									alert( data.data?.message || '<?php echo esc_js( __( 'Could not delete group.', '6arshid social community' ) ); ?>' );
									delBtn.disabled = false;
									delBtn.textContent = '<?php echo esc_js( __( 'Delete Group', '6arshid social community' ) ); ?>';
								}
							} catch {
								alert( '<?php echo esc_js( __( 'Network error. Please try again.', '6arshid social community' ) ); ?>' );
								delBtn.disabled = false;
								delBtn.textContent = '<?php echo esc_js( __( 'Delete Group', '6arshid social community' ) ); ?>';
							}
						} );
					}
				} )();
				</script>

			<?php endif; ?>

		</div><!-- main content -->

	</div><!-- .arshid6social-grid -->

</div><!-- .arshid6social-container -->
</div><!-- .arshid6social-wrap -->

<?php if ( is_user_logged_in() && ! $is_admin ) : ?>
<?php arshid6social_report_modal(); ?>
<?php endif; ?>
