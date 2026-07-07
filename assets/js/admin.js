/**
 * 6Arshid Social Community \u2014 Admin bundle.
 *
 * Handles: member suspend/delete, report resolution, settings colour preview.
 * No jQuery dependency.
 */
/* global ARSHID6SOCIALAdminConfig */

( function () {
	'use strict';

	const cfg = window.ARSHID6SOCIALAdminConfig || {};

	// ---------------------------------------------------------------------------
	// Utility: AJAX helper
	// ---------------------------------------------------------------------------

	/**
	 * Fires an admin-ajax.php request with FormData.
	 *
	 * @param {string} action WP AJAX action name.
	 * @param {Object} data   Key-value pairs to add to the FormData body.
	 * @returns {Promise<Object>} Parsed JSON response.
	 */
	async function doAjax( action, data ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.entries( data ).forEach( ( [ k, v ] ) => body.append( k, v ) );

		const res = await fetch( cfg.ajaxUrl, { method: 'POST', body } );
		return res.json();
	}

	// ---------------------------------------------------------------------------
	// Toast notice
	// ---------------------------------------------------------------------------

	function showNotice( message, type = 'success' ) {
		const container = document.getElementById( 'arshid6social-admin-notices' ) || createNoticeContainer();
		const el = document.createElement( 'div' );
		el.className = `notice notice-${ type } is-dismissible arshid6social-admin-toast`;
		el.innerHTML = `<p>${ message }</p>`;
		container.appendChild( el );
		setTimeout( () => el.remove(), 4000 );
	}

	function createNoticeContainer() {
		const el = document.createElement( 'div' );
		el.id = 'arshid6social-admin-notices';
		el.style.cssText = 'position:fixed;top:60px;right:20px;z-index:9999;width:320px;';
		document.body.appendChild( el );
		return el;
	}

	// ---------------------------------------------------------------------------
	// Member management: Suspend / Unsuspend with reason modal
	// ---------------------------------------------------------------------------

	const suspendModal = document.getElementById( 'arshid6social-suspend-modal' );
	let   pendingSuspend = null;

	function openSuspendModal( userId, nonce, isSuspended, userName, onConfirm ) {
		if ( ! suspendModal ) {
			// Fallback: no modal present — do direct toggle (unsuspend path or reports page).
			onConfirm( '', nonce );
			return;
		}

		pendingSuspend = { userId, nonce, isSuspended, onConfirm };

		const title   = suspendModal.querySelector( '#arshid6social-sm-title' );
		const desc    = suspendModal.querySelector( '#arshid6social-sm-desc' );
		const reasonEl = suspendModal.querySelector( '#arshid6social-sm-reason' );
		const customEl = suspendModal.querySelector( '#arshid6social-sm-custom' );

		if ( isSuspended ) {
			if ( title ) title.textContent = ( cfg.i18n?.unsuspendConfirm || 'Unsuspend %s?' ).replace( '%s', userName );
			if ( desc )  desc.textContent  = '';
			if ( reasonEl ) reasonEl.style.display = 'none';
			if ( customEl ) customEl.style.display = 'none';
		} else {
			if ( title ) title.textContent = ( cfg.i18n?.suspendConfirm || 'Suspend %s?' ).replace( '%s', userName );
			if ( desc )  desc.textContent  = cfg.i18n?.selectReason || 'Please select a reason.';
			if ( reasonEl ) { reasonEl.value = ''; reasonEl.style.display = ''; }
			if ( customEl ) { customEl.value = ''; customEl.style.display = 'none'; }
		}

		suspendModal.style.display = 'flex';
	}

	function closeSuspendModal() {
		if ( suspendModal ) suspendModal.style.display = 'none';
		pendingSuspend = null;
	}

	if ( suspendModal ) {
		const reasonEl = suspendModal.querySelector( '#arshid6social-sm-reason' );
		const customEl = suspendModal.querySelector( '#arshid6social-sm-custom' );
		const cancelBtn = suspendModal.querySelector( '#arshid6social-sm-cancel' );
		const confirmBtn = suspendModal.querySelector( '#arshid6social-sm-confirm' );

		if ( reasonEl ) {
			reasonEl.addEventListener( 'change', () => {
				if ( customEl ) customEl.style.display = reasonEl.value === '__custom__' ? '' : 'none';
			} );
		}

		if ( cancelBtn ) cancelBtn.addEventListener( 'click', closeSuspendModal );
		suspendModal.addEventListener( 'click', ( e ) => { if ( e.target === suspendModal ) closeSuspendModal(); } );

		if ( confirmBtn ) {
			confirmBtn.addEventListener( 'click', () => {
				if ( ! pendingSuspend ) return;
				const { userId, nonce, isSuspended, onConfirm } = pendingSuspend;

				let reason = '';
				if ( ! isSuspended && reasonEl ) {
					reason = reasonEl.value === '__custom__'
						? ( customEl ? customEl.value.trim() : '' )
						: reasonEl.value;
					if ( ! reason ) {
						showNotice( cfg.i18n?.selectReason || 'Please select a reason.', 'error' );
						return;
					}
				}

				closeSuspendModal();
				onConfirm( reason, nonce );
			} );
		}
	}

	// Handle .arshid6social-admin-suspend-btn (Members page).
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '.arshid6social-admin-suspend-btn' );
		if ( ! btn ) return;
		e.preventDefault();

		const userId      = btn.dataset.userId;
		const nonce       = btn.dataset.nonce;
		const isSuspended = btn.dataset.suspended === '1';
		const userName    = btn.dataset.userName || 'this user';

		openSuspendModal( userId, nonce, isSuspended, userName, async ( reason ) => {
			btn.disabled = true;
			const res = await doAjax( 'arshid6social_admin_suspend_user', { user_id: userId, nonce, reason } );
			btn.disabled = false;

			if ( res.success ) {
				const row         = btn.closest( 'tr' );
				const statusBadge = row?.querySelector( '.arshid6social-badge--suspended, .arshid6social-badge--active' );
				const newState    = res.data?.suspended;

				btn.dataset.suspended = newState ? '1' : '0';
				btn.textContent       = res.data?.label || ( newState ? 'Unsuspend' : 'Suspend' );

				if ( statusBadge ) {
					statusBadge.textContent = newState ? 'Suspended' : 'Active';
					statusBadge.className   = newState ? 'arshid6social-badge arshid6social-badge--suspended' : 'arshid6social-badge arshid6social-badge--active';
					// Update or remove reason line.
					const reasonLine = statusBadge.parentElement?.querySelector( 'small' );
					if ( newState && reason ) {
						if ( reasonLine ) {
							reasonLine.textContent = reason;
						} else {
							const small = document.createElement( 'small' );
							small.style.color = '#555';
							small.textContent = reason;
							statusBadge.insertAdjacentElement( 'afterend', document.createElement( 'br' ) );
							statusBadge.parentElement.appendChild( small );
						}
					} else if ( reasonLine ) {
						reasonLine.previousSibling?.remove();
						reasonLine.remove();
					}
				}

				showNotice( newState ? ( cfg.i18n?.suspended || 'User suspended.' ) : ( cfg.i18n?.unsuspended || 'User unsuspended.' ) );
			} else {
				showNotice( res.data?.message || cfg.i18n?.error || 'Error.', 'error' );
			}
		} );
	} );

	// ---------------------------------------------------------------------------
	// Member management: Delete member data (GDPR erasure)
	// ---------------------------------------------------------------------------

	document.addEventListener( 'click', async ( e ) => {
		const btn = e.target.closest( '[data-action="arshid6social-delete-data"]' );
		if ( ! btn ) return;
		e.preventDefault();

		if ( ! confirm( cfg.i18n?.confirm || 'Are you sure? This cannot be undone.' ) ) return;

		const userId = btn.dataset.userId;
		const nonce  = btn.dataset.nonce;

		btn.disabled = true;
		const res = await doAjax( 'arshid6social_delete_member_data', { user_id: userId, user_nonce: nonce } );

		if ( res.success ) {
			btn.closest( 'tr' )?.remove();
			showNotice( cfg.i18n?.deleted || 'Member data deleted.' );
		} else {
			btn.disabled = false;
			showNotice( res.data?.message || cfg.i18n?.error || 'Error.', 'error' );
		}
	} );

	// ---------------------------------------------------------------------------
	// Moderation: Resolve / Dismiss report
	// ---------------------------------------------------------------------------

	document.addEventListener( 'click', async ( e ) => {
		const btn = e.target.closest( '.arshid6social-resolve-report' );
		if ( ! btn ) return;
		e.preventDefault();

		const reportId   = btn.dataset.reportId;
		const actionType = btn.dataset.actionType || 'resolved';
		const nonce      = btn.dataset.nonce;

		btn.disabled = true;
		const res = await doAjax( 'arshid6social_resolve_report', { report_id: reportId, action_type: actionType, nonce } );

		if ( res.success ) {
			btn.closest( 'tr' )?.remove();
			showNotice( cfg.i18n?.approved || 'Report resolved.' );
		} else {
			btn.disabled = false;
			showNotice( res.data?.message || cfg.i18n?.error || 'Error.', 'error' );
		}
	} );

	// ---------------------------------------------------------------------------
	// Moderation: Suspend user from report row (uses the reports-page modal)
	// ---------------------------------------------------------------------------

	const reportSuspendModal = document.getElementById( 'arshid6social-suspend-report-modal' );

	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '.arshid6social-suspend-from-report' );
		if ( ! btn || ! reportSuspendModal ) return;
		e.preventDefault();

		const userId      = btn.dataset.userId;
		const reportId    = btn.dataset.reportId;
		const nonce       = btn.dataset.nonce;
		const isSuspended = btn.dataset.suspended === '1';
		const userName    = btn.dataset.userName || 'this user';

		const title   = reportSuspendModal.querySelector( '#arshid6social-srm-title' );
		const desc    = reportSuspendModal.querySelector( '#arshid6social-srm-desc' );
		const reasonEl = reportSuspendModal.querySelector( '#arshid6social-srm-reason' );
		const customEl = reportSuspendModal.querySelector( '#arshid6social-srm-custom' );

		if ( isSuspended ) {
			if ( title ) title.textContent = ( cfg.i18n?.unsuspendConfirm || 'Unsuspend %s?' ).replace( '%s', userName );
			if ( desc )  desc.textContent  = '';
			if ( reasonEl ) reasonEl.style.display = 'none';
			if ( customEl ) customEl.style.display = 'none';
		} else {
			if ( title ) title.textContent = ( cfg.i18n?.suspendConfirm || 'Suspend %s?' ).replace( '%s', userName );
			if ( desc )  desc.textContent  = '';
			if ( reasonEl ) { reasonEl.value = ''; reasonEl.style.display = ''; }
			if ( customEl ) { customEl.value = ''; customEl.style.display = 'none'; }
		}

		reportSuspendModal.querySelector( '#arshid6social-srm-user-id' ).value   = userId;
		reportSuspendModal.querySelector( '#arshid6social-srm-report-id' ).value  = reportId;
		reportSuspendModal.querySelector( '#arshid6social-srm-nonce' ).value      = nonce;
		reportSuspendModal.querySelector( '#arshid6social-srm-suspended' ).value  = isSuspended ? '1' : '0';
		reportSuspendModal.style.display = 'flex';

		// Store reference to the triggering button for UI update.
		reportSuspendModal._triggerBtn = btn;
	} );

	if ( reportSuspendModal ) {
		const reasonEl  = reportSuspendModal.querySelector( '#arshid6social-srm-reason' );
		const customEl  = reportSuspendModal.querySelector( '#arshid6social-srm-custom' );
		const cancelBtn = reportSuspendModal.querySelector( '#arshid6social-srm-cancel' );
		const confirmBtn = reportSuspendModal.querySelector( '#arshid6social-srm-confirm' );

		if ( reasonEl ) {
			reasonEl.addEventListener( 'change', () => {
				if ( customEl ) customEl.style.display = reasonEl.value === '__custom__' ? '' : 'none';
			} );
		}

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', () => { reportSuspendModal.style.display = 'none'; } );
		}

		reportSuspendModal.addEventListener( 'click', ( e ) => {
			if ( e.target === reportSuspendModal ) reportSuspendModal.style.display = 'none';
		} );

		if ( confirmBtn ) {
			confirmBtn.addEventListener( 'click', async () => {
				const userId      = reportSuspendModal.querySelector( '#arshid6social-srm-user-id' ).value;
				const reportId    = reportSuspendModal.querySelector( '#arshid6social-srm-report-id' ).value;
				const nonce       = reportSuspendModal.querySelector( '#arshid6social-srm-nonce' ).value;
				const isSuspended = reportSuspendModal.querySelector( '#arshid6social-srm-suspended' ).value === '1';

				let reason = '';
				if ( ! isSuspended && reasonEl ) {
					reason = reasonEl.value === '__custom__'
						? ( customEl ? customEl.value.trim() : '' )
						: reasonEl.value;
					if ( ! reason ) {
						showNotice( cfg.i18n?.selectReason || 'Please select a reason.', 'error' );
						return;
					}
				}

				confirmBtn.disabled = true;
				const res = await doAjax( 'arshid6social_admin_suspend_from_report', {
					user_id: userId, report_id: reportId, nonce, reason
				} );
				confirmBtn.disabled = false;
				reportSuspendModal.style.display = 'none';

				if ( res.success ) {
					const btn = reportSuspendModal._triggerBtn;
					if ( btn ) {
						const newState = res.data?.suspended;
						btn.dataset.suspended = newState ? '1' : '0';
						btn.textContent       = res.data?.label || ( newState ? 'Unsuspend' : 'Suspend User' );
						btn.className         = btn.className.replace( /button-link-delete|button-secondary/g, '' ).trim()
							+ ' ' + ( newState ? 'button-secondary' : 'button-link-delete' );
					}
					showNotice( res.data?.suspended ? ( cfg.i18n?.suspended || 'User suspended.' ) : ( cfg.i18n?.unsuspended || 'User unsuspended.' ) );
				} else {
					showNotice( res.data?.message || cfg.i18n?.error || 'Error.', 'error' );
				}
			} );
		}
	}

	// ---------------------------------------------------------------------------
	// Moderation: Suspend/Unsuspend group
	// ---------------------------------------------------------------------------

	const groupSuspendModal = document.getElementById( 'arshid6social-group-suspend-modal' );

	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '.arshid6social-suspend-group-btn' );
		if ( ! btn || ! groupSuspendModal ) return;
		e.preventDefault();

		const groupId     = btn.dataset.groupId;
		const groupName   = btn.dataset.groupName || 'this group';
		const nonce       = btn.dataset.nonce;
		const isSuspended = btn.dataset.suspended === '1';

		const title   = groupSuspendModal.querySelector( '#arshid6social-gsm-title' );
		const desc    = groupSuspendModal.querySelector( '#arshid6social-gsm-desc' );
		const reasonEl = groupSuspendModal.querySelector( '#arshid6social-gsm-reason' );
		const customEl = groupSuspendModal.querySelector( '#arshid6social-gsm-custom' );

		if ( isSuspended ) {
			if ( title ) title.textContent = ( cfg.i18n?.unsuspendConfirm || 'Unsuspend %s?' ).replace( '%s', groupName );
			if ( desc )  desc.textContent  = '';
			if ( reasonEl ) reasonEl.style.display = 'none';
			if ( customEl ) customEl.style.display = 'none';
		} else {
			if ( title ) title.textContent = ( cfg.i18n?.suspendConfirm || 'Suspend %s?' ).replace( '%s', groupName );
			if ( desc )  desc.textContent  = '';
			if ( reasonEl ) { reasonEl.value = ''; reasonEl.style.display = ''; }
			if ( customEl ) { customEl.value = ''; customEl.style.display = 'none'; }
		}

		groupSuspendModal.querySelector( '#arshid6social-gsm-group-id' ).value  = groupId;
		groupSuspendModal.querySelector( '#arshid6social-gsm-nonce' ).value      = nonce;
		groupSuspendModal.querySelector( '#arshid6social-gsm-suspended' ).value  = isSuspended ? '1' : '0';
		groupSuspendModal.style.display = 'flex';
		groupSuspendModal._triggerBtn   = btn;
	} );

	if ( groupSuspendModal ) {
		const reasonEl  = groupSuspendModal.querySelector( '#arshid6social-gsm-reason' );
		const customEl  = groupSuspendModal.querySelector( '#arshid6social-gsm-custom' );
		const cancelBtn = groupSuspendModal.querySelector( '#arshid6social-gsm-cancel' );
		const confirmBtn = groupSuspendModal.querySelector( '#arshid6social-gsm-confirm' );

		if ( reasonEl ) {
			reasonEl.addEventListener( 'change', () => {
				if ( customEl ) customEl.style.display = reasonEl.value === '__custom__' ? '' : 'none';
			} );
		}

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', () => { groupSuspendModal.style.display = 'none'; } );
		}
		groupSuspendModal.addEventListener( 'click', ( e ) => {
			if ( e.target === groupSuspendModal ) groupSuspendModal.style.display = 'none';
		} );

		if ( confirmBtn ) {
			confirmBtn.addEventListener( 'click', async () => {
				const groupId     = groupSuspendModal.querySelector( '#arshid6social-gsm-group-id' ).value;
				const nonce       = groupSuspendModal.querySelector( '#arshid6social-gsm-nonce' ).value;
				const isSuspended = groupSuspendModal.querySelector( '#arshid6social-gsm-suspended' ).value === '1';

				let reason = '';
				if ( ! isSuspended && reasonEl ) {
					reason = reasonEl.value === '__custom__'
						? ( customEl ? customEl.value.trim() : '' )
						: reasonEl.value;
					if ( ! reason ) {
						showNotice( cfg.i18n?.selectReason || 'Please select a reason.', 'error' );
						return;
					}
				}

				confirmBtn.disabled = true;
				const res = await doAjax( 'arshid6social_admin_suspend_group', { group_id: groupId, nonce, reason } );
				confirmBtn.disabled = false;
				groupSuspendModal.style.display = 'none';

				if ( res.success ) {
					const btn = groupSuspendModal._triggerBtn;
					if ( btn ) {
						const newState = res.data?.suspended;
						btn.dataset.suspended = newState ? '1' : '0';
						btn.textContent       = res.data?.label || ( newState ? 'Unsuspend Group' : 'Suspend Group' );
						btn.className = btn.className.replace( /button-link-delete|button-secondary/g, '' ).trim()
							+ ' ' + ( newState ? 'button-secondary' : 'button-link-delete' );

						// Update status cell and reason cell.
						const row = btn.closest( 'tr' );
						const badge = row?.querySelector( '.arshid6social-badge--suspended, .arshid6social-badge--active' );
						if ( badge ) {
							badge.textContent = newState ? 'Suspended' : 'Active';
							badge.className   = newState ? 'arshid6social-badge arshid6social-badge--suspended' : 'arshid6social-badge arshid6social-badge--active';
						}
						const reasonCell = row?.querySelector( `#arshid6social-group-reason-${ groupId }` );
						if ( reasonCell ) reasonCell.textContent = ( newState && reason ) ? reason : '—';
					}
					showNotice( res.data?.suspended ? ( cfg.i18n?.suspended || 'Group suspended.' ) : ( cfg.i18n?.unsuspended || 'Group unsuspended.' ) );
				} else {
					showNotice( res.data?.message || cfg.i18n?.error || 'Error.', 'error' );
				}
			} );
		}
	}

	// ---------------------------------------------------------------------------
	// Settings: Live colour preview
	// ---------------------------------------------------------------------------

	const colorInput = document.getElementById( 'arshid6social_primary_color' );
	if ( colorInput ) {
		const preview = document.createElement( 'span' );
		preview.id = 'arshid6social-color-preview';
		preview.style.cssText = `
			display:inline-block;width:24px;height:24px;border-radius:50%;
			border:2px solid #ccc;vertical-align:middle;margin-inline-start:8px;
			background:${ colorInput.value };
		`;
		colorInput.insertAdjacentElement( 'afterend', preview );

		colorInput.addEventListener( 'input', () => {
			preview.style.background = colorInput.value;
		} );
	}

	// ---------------------------------------------------------------------------
	// Settings: Tab navigation via URL hash (fallback for browsers without CSS :target)
	// ---------------------------------------------------------------------------

	function activateTab( hash ) {
		if ( ! hash ) return;
		const tabs    = document.querySelectorAll( '.arshid6social-tab-link' );
		const panels  = document.querySelectorAll( '.arshid6social-tab-panel' );
		const tabName = hash.replace( '#', '' );

		tabs.forEach( t => t.classList.toggle( 'is-active', t.dataset.tab === tabName ) );
		panels.forEach( p => p.classList.toggle( 'is-active', p.id === tabName ) );
	}

	document.querySelectorAll( '.arshid6social-tab-link' ).forEach( link => {
		link.addEventListener( 'click', () => activateTab( link.hash ) );
	} );

	if ( window.location.hash ) {
		activateTab( window.location.hash );
	}
} )();
