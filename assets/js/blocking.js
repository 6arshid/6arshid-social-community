/**
 * 6Arshid Social Community \u2014 Blocking
 *
 * Handles:
 *  - Block / Unblock user AJAX (from profile / activity menus)
 *  - Block list page: inline unblock with confirmation
 *  - Block modal: optional reason textarea
 */
( function () {
	'use strict';

	const nonce   = window.ARSHID6SOCIALBlocking?.nonce   || '';
	const ajaxUrl = window.ARSHID6SOCIALBlocking?.ajaxUrl || window.ajaxurl || '';
	const i18n    = window.ARSHID6SOCIALBlocking?.i18n    || {};

	function ajax( action, data ) {
		const form = new FormData();
		form.append( 'action', action );
		form.append( 'nonce', nonce );
		Object.entries( data || {} ).forEach( ( [ k, v ] ) => form.append( k, v ) );
		return fetch( ajaxUrl, { method: 'POST', body: form } ).then( r => r.json() );
	}

	/* -- Block with optional reason --------------------------- */
	document.addEventListener( 'click', async e => {
		// Trigger: any element with data-block-user-id attribute.
		const trigger = e.target.closest( '[data-block-user-id]' );
		if ( trigger ) {
			e.preventDefault();
			const userId = trigger.dataset.blockUserId;
			if ( ! userId ) return;

			if ( ! confirm( i18n.confirmBlock || 'Block this user?' ) ) return;

			let reason = '';

			trigger.disabled = true;
			let res;
			try {
				res = await ajax( 'arshid6social_block_with_reason', { user_id: userId, reason } );
			} catch ( err ) {
				trigger.disabled = false;
				alert( i18n.blockError || 'Block failed. Please try again.' );
				return;
			}
			trigger.disabled = false;

			if ( res.success ) {
				trigger.textContent = i18n.blocked || 'Blocked';
				trigger.classList.add( 'sn--blocked', 'arshid6social-btn--danger' );
				trigger.classList.remove( 'arshid6social-btn--secondary' );
				trigger.setAttribute( 'data-unblock-user-id', userId );
				trigger.removeAttribute( 'data-block-user-id' );
				document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:user-blocked', { detail: { userId } } ) );
			} else {
				const dbErr = res.data?.db_error ? '\n\nDB: ' + res.data.db_error : '';
				alert( ( res.data?.message || i18n.blockError || 'Block failed.' ) + dbErr );
			}
			return;
		}

		// Unblock via data attribute.
		const unblockTrigger = e.target.closest( '[data-unblock-user-id]' );
		if ( unblockTrigger && ! unblockTrigger.closest( '.sn-block-list' ) ) {
			e.preventDefault();
			const userId = unblockTrigger.dataset.unblockUserId;
			unblockTrigger.disabled = true;
			const res = await ajax( 'arshid6social_unblock_user', { user_id: userId } );
			unblockTrigger.disabled = false;
			if ( res.success ) {
				unblockTrigger.textContent = i18n.block || 'Block';
				unblockTrigger.classList.remove( 'sn--blocked', 'arshid6social-btn--danger' );
				unblockTrigger.classList.add( 'arshid6social-btn--secondary' );
				unblockTrigger.setAttribute( 'data-block-user-id', userId );
				unblockTrigger.removeAttribute( 'data-unblock-user-id' );
				document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:user-unblocked', { detail: { userId } } ) );
			}
		}
	} );

	/* -- Block list page --------------------------------------- */
	document.addEventListener( 'click', async e => {
		const btn = e.target.closest( '.sn-unblock-btn' );
		if ( ! btn ) return;
		e.preventDefault();

		if ( ! confirm( i18n.confirmUnblock || 'Unblock this user?' ) ) return;

		const userId = btn.dataset.userId;
		btn.disabled = true;

		const form = new FormData();
		form.append( 'action', 'arshid6social_unblock_user' );
		form.append( 'nonce', btn.dataset.nonce || nonce );
		form.append( 'user_id', userId );

		const res = await fetch( ajaxUrl, { method: 'POST', body: form } ).then( r => r.json() );
		btn.disabled = false;

		if ( res.success ) {
			// Remove the list item with a fade.
			const item = btn.closest( '.sn-block-list__item' );
			if ( item ) {
				item.style.transition = 'opacity .3s';
				item.style.opacity = '0';
				setTimeout( () => {
					item.remove();
					// Show empty state if no items remain.
					const list = document.querySelector( '.sn-block-list__items' );
					if ( list && ! list.children.length ) {
						const empty = document.createElement( 'p' );
						empty.className = 'sn-block-list__empty';
						empty.textContent = i18n.emptyList || 'You have not blocked anyone.';
						list.replaceWith( empty );
					}
				}, 300 );
			}
		}
	} );
} )();
