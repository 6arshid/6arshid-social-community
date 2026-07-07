/**
 * 6Arshid Social Community — Verification
 *
 * Handles:
 *  - Verification request form AJAX submission (with optional file upload)
 *  - Resubmission for more_info requests
 *  - Admin: approve / reject / more-info actions via fetch (supplement to PHP GET links)
 */
( function () {
	'use strict';

	const nonce   = window.ARSHID6SOCIALVerification?.nonce   || '';
	const ajaxUrl = window.ARSHID6SOCIALVerification?.ajaxUrl || window.ajaxurl || '';
	const i18n    = window.ARSHID6SOCIALVerification?.i18n    || {};

	/* -- Verification request form ---------------------------- */
	const form = document.getElementById( 'sn-verification-form' );
	if ( form ) {
		form.addEventListener( 'submit', async e => {
			e.preventDefault();
			const isResubmit = form.dataset.mode === 'resubmit';
			await submitVerificationForm( form, isResubmit );
		} );
	}

	async function submitVerificationForm( form, isResubmit ) {
		const feedback = document.getElementById( 'sn-verify-feedback' );
		const submit   = document.getElementById( 'sn-verify-submit' );

		clearFeedback( feedback );

		const full_name = document.getElementById( 'sn-verify-name' )?.value?.trim();

		if ( ! isResubmit ) {
			const type = document.getElementById( 'sn-verify-type' )?.value?.trim();
			if ( ! type ) {
				showFeedback( feedback, i18n.selectType || 'Please select a verification type.', 'error' );
				return;
			}
		}

		if ( ! full_name ) {
			showFeedback( feedback, i18n.nameRequired || 'Please enter your full name.', 'error' );
			return;
		}

		if ( submit ) submit.disabled = true;

		const action = isResubmit
			? 'arshid6social_resubmit_verification_request'
			: 'arshid6social_submit_verification_request';

		const data = new FormData();
		data.append( 'action',    action );
		data.append( 'nonce',     form.querySelector( '[name="nonce"]' )?.value || nonce );
		data.append( 'full_name', full_name );
		data.append( 'category',  document.getElementById( 'sn-verify-category' )?.value?.trim() || '' );
		data.append( 'links',     document.getElementById( 'sn-verify-links' )?.value?.trim()    || '' );

		if ( ! isResubmit ) {
			data.append( 'type', document.getElementById( 'sn-verify-type' )?.value?.trim() || '' );
		}

		const docInput = document.getElementById( 'sn-verify-doc' );
		if ( docInput?.files?.length ) {
			data.append( 'document', docInput.files[0] );
		}

		const res = await fetch( ajaxUrl, { method: 'POST', body: data } ).then( r => r.json() );
		if ( submit ) submit.disabled = false;

		const successMsg = isResubmit
			? ( i18n.resubmitSubmitted || 'Your additional information has been submitted.' )
			: ( i18n.requestSubmitted  || 'Your request has been submitted. We\'ll review it and notify you.' );

		if ( res.success ) {
			form.innerHTML =
				'<div class="sn-alert sn-alert--success" role="alert">' + successMsg + '</div>';
		} else {
			showFeedback( feedback, res.data?.message || ( i18n.submitError || 'Could not submit request.' ), 'error' );
		}
	}

	function showFeedback( el, msg, type ) {
		if ( ! el ) return;
		el.textContent  = msg;
		el.className    = 'sn-verification-request__feedback is-' + type;
		el.hidden       = false;
		el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	function clearFeedback( el ) {
		if ( el ) { el.hidden = true; el.textContent = ''; }
	}

	/* -- Badge tooltip (accessibility) ------------------------ */
	document.querySelectorAll( '.sn-verified-badge[title]' ).forEach( badge => {
		badge.setAttribute( 'role', 'img' );
		badge.setAttribute( 'aria-label', badge.getAttribute( 'title' ) );
	} );
} )();
