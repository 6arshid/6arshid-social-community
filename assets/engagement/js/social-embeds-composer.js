/**
 * Social Embeds – lazy-load click handler + live composer paste-preview.
 *
 * This script is intentionally enqueued AFTER arshid6social-engagement so that
 * window.ARSHID6SOCIALEng is defined when it runs.
 *
 * Two responsibilities:
 *  1. Global click handler — replaces the lazy-load placeholder with the actual
 *     embed iframe/HTML when the user clicks "Click to load".  Works on every
 *     page that contains embed cards, no ARSHID6SOCIALEng required.
 *
 *  2. Composer paste preview — when a supported URL is pasted into an activity,
 *     comment, or message composer, a preview card appears below the text area.
 *     Requires ARSHID6SOCIALEng (REST URL + nonce).
 */
( function () {
	'use strict';

	// ── 1. Global lazy-load click handler ────────────────────────────────────
	// Registered unconditionally so embed cards work even if ARSHID6SOCIALEng is
	// somehow missing (e.g. aggressive caching).

	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.arshid6social-embed-load-btn' );
		if ( ! btn ) return;

		const placeholder = btn.closest( '.arshid6social-embed-placeholder' );
		if ( ! placeholder ) return;

		const embedHtml = placeholder.getAttribute( 'data-embed' );
		if ( ! embedHtml ) return;

		const wrap = placeholder.parentElement;
		if ( ! wrap ) return;

		e.preventDefault();

		// Replace the placeholder with the real embed HTML.
		wrap.innerHTML = embedHtml;

		// Re-execute any <script> tags inside the injected HTML (e.g. Twitter widget).
		wrap.querySelectorAll( 'script' ).forEach( ( oldScript ) => {
			const newScript = document.createElement( 'script' );
			Array.from( oldScript.attributes ).forEach( ( attr ) =>
				newScript.setAttribute( attr.name, attr.value )
			);
			newScript.textContent = oldScript.textContent;
			oldScript.parentNode.replaceChild( newScript, oldScript );
		} );
	} );

	// ── 2. Composer paste preview (requires ARSHID6SOCIALEng) ──────────────────────

	if ( ! window.ARSHID6SOCIALEng || ! window.ARSHID6SOCIALEng.enabled.social_embeds ) {
		return;
	}

	const REST_URL   = ( window.ARSHID6SOCIALEng.restUrl || '' ).replace( /\/?$/, '/' );
	const REST_NONCE = window.ARSHID6SOCIALEng.restNonce || '';

	// Composer selectors — covers activity post box, comment box, message input.
	const COMPOSER_SELECTOR = [
		'[name="content"].arshid6social-activity-composer',
		'textarea[name="content"]',
		'.arshid6social-activity-composer',
		'textarea.arshid6social-msg-input',
		'textarea[name="message"]',
	].join( ', ' );

	const URL_RE    = /https?:\/\/[^\s<>"')[\]]+/gi;
	const pending   = new Map(); // textarea → AbortController
	const previewed = new Map(); // textarea → url currently previewed

	document.addEventListener( 'paste', handlePaste, true );
	document.addEventListener( 'input', handleInput, true );

	// ── Paste handler ─────────────────────────────────────────────────────────

	function handlePaste( e ) {
		const ta = e.target;
		if ( ! isComposer( ta ) ) return;

		const text = e.clipboardData && e.clipboardData.getData( 'text/plain' );
		if ( ! text ) return;

		const url = extractFirstUrl( text );
		if ( ! url ) return;

		// Debounce: cancel any in-flight request for this textarea.
		if ( pending.has( ta ) ) {
			pending.get( ta ).abort();
			pending.delete( ta );
		}

		showSkeleton( ta );

		const ctrl = new AbortController();
		pending.set( ta, ctrl );

		fetchPreview( url, ctrl.signal )
			.then( ( data ) => {
				pending.delete( ta );
				if ( data ) {
					showPreview( ta, url, data.html );
				} else {
					removeSkeleton( ta );
				}
			} )
			.catch( () => {
				pending.delete( ta );
				removeSkeleton( ta );
			} );
	}

	// ── Input handler (remove preview when URL is deleted) ────────────────────

	function handleInput( e ) {
		const ta = e.target;
		if ( ! isComposer( ta ) ) return;
		if ( ! previewed.has( ta ) ) return;

		const url = previewed.get( ta );
		if ( ! ta.value.includes( url ) ) {
			removePreview( ta );
		}
	}

	// ── REST call ─────────────────────────────────────────────────────────────

	async function fetchPreview( url, signal ) {
		const resp = await fetch( REST_URL + 'embeds/preview', {
			method : 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce'  : REST_NONCE,
			},
			body  : JSON.stringify( { url } ),
			signal,
		} );

		if ( ! resp.ok ) return null;
		return resp.json();
	}

	// ── UI helpers ────────────────────────────────────────────────────────────

	function getPreviewArea( ta ) {
		let area = ta.parentElement && ta.parentElement.querySelector( '.arshid6social-embed-preview-area' );
		if ( ! area ) {
			area = document.createElement( 'div' );
			area.className = 'arshid6social-embed-preview-area';

			const parent = ta.parentElement || ta.nextSibling;
			if ( parent && ta.nextSibling ) {
				parent.insertBefore( area, ta.nextSibling );
			} else if ( parent ) {
				parent.appendChild( area );
			}
		}
		return area;
	}

	function showSkeleton( ta ) {
		const area = getPreviewArea( ta );
		area.innerHTML = '<div class="arshid6social-embed-wrap" style="padding-bottom:56.25%;"><div class="arshid6social-embed-skeleton" aria-busy="true"></div></div>';
		area.hidden    = false;
	}

	function removeSkeleton( ta ) {
		const area = ta.parentElement && ta.parentElement.querySelector( '.arshid6social-embed-preview-area' );
		if ( area ) {
			area.innerHTML = '';
			area.hidden    = true;
		}
	}

	function showPreview( ta, url, html ) {
		const area = getPreviewArea( ta );

		// Dismiss button.
		const dismiss = document.createElement( 'button' );
		dismiss.type      = 'button';
		dismiss.className = 'arshid6social-embed-preview-dismiss';
		dismiss.setAttribute( 'aria-label', ( window.ARSHID6SOCIALEng.i18n && window.ARSHID6SOCIALEng.i18n.dismissEmbed )
			? window.ARSHID6SOCIALEng.i18n.dismissEmbed
			: 'Remove embed preview' );
		dismiss.innerHTML = '&times;';
		dismiss.addEventListener( 'click', () => removePreview( ta ) );

		area.innerHTML = '';
		area.appendChild( dismiss );

		const wrap = document.createElement( 'div' );
		wrap.className = 'arshid6social-embed-preview-inner';
		wrap.innerHTML = html;
		area.appendChild( wrap );
		area.hidden = false;

		previewed.set( ta, url );

		// Wire up any lazy-load placeholders inside the preview.
		wireEmbedPlaceholders( area );
	}

	function removePreview( ta ) {
		const area = ta.parentElement && ta.parentElement.querySelector( '.arshid6social-embed-preview-area' );
		if ( area ) {
			area.innerHTML = '';
			area.hidden    = true;
		}
		previewed.delete( ta );
	}

	function wireEmbedPlaceholders( root ) {
		root.querySelectorAll( '.arshid6social-embed-placeholder[data-embed]' ).forEach( ( placeholder ) => {
			const btn = placeholder.querySelector( '.arshid6social-embed-load-btn' );
			if ( ! btn ) return;

			// The global delegated listener already handles these clicks;
			// this local wire-up is a no-op kept for clarity.
		} );
	}

	// ── Utils ─────────────────────────────────────────────────────────────────

	function isComposer( el ) {
		return el && el.tagName === 'TEXTAREA' && el.matches( COMPOSER_SELECTOR );
	}

	function extractFirstUrl( text ) {
		const m = text.match( URL_RE );
		return m ? m[0] : null;
	}

} )();
