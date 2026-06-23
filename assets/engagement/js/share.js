/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALShare = ( function () {
	'use strict';

	let cfg = {};
	let bound = false;

	function init( c ) {
		cfg = c;
		bind();
	}

	function bindIn( root, c ) {
		cfg = c;
		bind();
	}

	function isMobile() {
		return window.innerWidth <= 700;
	}

	function getBackdrop() {
		let bd = document.getElementById( 'arshid6social-share-backdrop' );
		if ( ! bd ) {
			bd = document.createElement( 'div' );
			bd.id = 'arshid6social-share-backdrop';
			bd.className = 'arshid6social-share-backdrop';
			bd.addEventListener( 'click', closeAll );
			document.body.appendChild( bd );
		}
		return bd;
	}

	// Move dropdown to body on mobile to avoid parent overflow/transform clipping.
	function teleportOpen( drop ) {
		drop._origin = drop.parentNode;
		drop._next   = drop.nextSibling;
		document.body.appendChild( drop );
		drop.classList.add( 'arshid6social-share-bottom-sheet' );
		getBackdrop().classList.add( 'open' );
	}

	// Restore dropdown to original position.
	function teleportClose( drop ) {
		if ( drop._origin ) {
			drop._origin.insertBefore( drop, drop._next || null );
			drop._origin = null;
			drop._next   = null;
		}
		drop.classList.remove( 'arshid6social-share-bottom-sheet' );
		const bd = document.getElementById( 'arshid6social-share-backdrop' );
		if ( bd ) bd.classList.remove( 'open' );
	}

	function bind() {
		if ( bound ) return;
		bound = true;

		// Toggle share dropdown open/close.
		document.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '.arshid6social-share-toggle' );
			if ( btn ) {
				e.stopPropagation();
				if ( ! cfg.userId ) {
					window.location.href = cfg.loginUrl || '/wp-login.php';
					return;
				}
				const menu = btn.closest( '.arshid6social-share-menu' );
				const drop = menu && menu.querySelector( '.arshid6social-share-dropdown' );
				if ( drop ) {
					const wasOpen = drop.classList.contains( 'open' );
					closeAll();
					if ( ! wasOpen ) {
						drop.classList.add( 'open' );
						if ( isMobile() ) teleportOpen( drop );
					}
				}
				return;
			}

			// Share actions (repost / quote).
			const action = e.target.closest( '[data-sn-share-action]' );
			if ( action ) {
				const type       = action.dataset.snShareAction;
				const activityId = action.dataset.activityId;
				if ( ! activityId ) return;
				if ( ! cfg.userId ) {
					window.location.href = cfg.loginUrl || '/wp-login.php';
					return;
				}
				if ( type === 'repost' ) {
					doShare( activityId, 'repost', '' );
				} else if ( type === 'quote' ) {
					const comment = prompt( action.dataset.quotePlaceholder || 'Add a comment…' );
					if ( comment !== null ) doShare( activityId, 'repost', comment );
				}
				closeAll();
				return;
			}

			// Click outside — close all dropdowns.
			closeAll();
		} );
	}

	function closeAll() {
		document.querySelectorAll( '.arshid6social-share-dropdown.open' ).forEach( function ( d ) {
			teleportClose( d );
			d.classList.remove( 'open' );
		} );
	}

	function doShare( activityId, type, comment ) {
		const body = new URLSearchParams( {
			action:      'arshid6social_share_post',
			nonce:       cfg.nonce,
			activity_id: activityId,
			comment:     comment,
			target_type: 'profile',
			target_id:   0,
		} );

		fetch( cfg.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:    body.toString(),
		} )
			.then( r => r.json() )
			.then( data => {
				if ( data.success && data.data && data.data.activity ) {
					document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:share:done', { detail: data.data } ) );
				} else {
					const msg = ( data.data && data.data.message ) || 'Could not share this post.';
					document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:notice', { detail: { message: msg, type: 'error' } } ) );
				}
			} )
			.catch( () => {
				document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:notice', { detail: { message: 'Could not share this post.', type: 'error' } } ) );
			} );
	}

	return { init, bindIn };
} )();
