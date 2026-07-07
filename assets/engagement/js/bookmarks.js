/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALBookmarks = ( function () {
	'use strict';

	let cfg = {};
	let bound = false;

	function notify( msg, type ) {
		const n = document.createElement( 'div' );
		n.textContent = msg;
		const bg = type === 'error' ? '#dc2626' : '#16a34a';
		n.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;background:' + bg + ';color:#fff;padding:10px 16px;border-radius:8px;font-size:14px;max-width:320px;box-shadow:0 4px 12px rgba(0,0,0,.2)';
		document.body.appendChild( n );
		setTimeout( function () { n.remove(); }, 5000 );
	}

	function init( c ) {
		cfg = c;
		bind();
	}

	function bindIn( root, c ) {
		cfg = c;
		bind();
	}

	function bind() {
		if ( bound ) return;
		bound = true;
		document.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '.arshid6social-bookmark-btn' );
			if ( ! btn ) return;
			e.preventDefault();
			if ( ! cfg.userId ) {
				window.location.href = cfg.loginUrl || '/wp-login.php';
				return;
			}
			toggle( btn );
		} );
	}

	function toggle( btn ) {
		const activityId = parseInt( btn.dataset.activityId, 10 );
		if ( ! activityId ) return;

		const saved = btn.classList.contains( 'saved' );
		btn.disabled = true;

		const fd = new FormData();
		fd.append( 'action',      'arshid6social_bookmark_toggle' );
		fd.append( 'nonce',       cfg.nonce );
		fd.append( 'object_id',   String( activityId ) );
		fd.append( 'object_type', 'activity' );

		fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res.success ) {
					const isNowSaved = !! res.data.bookmarked;
					btn.classList.toggle( 'saved', isNowSaved );
					btn.setAttribute( 'aria-pressed', String( isNowSaved ) );
				} else {
					const msg = ( res.data && res.data.message ) ? res.data.message : 'Could not save bookmark.';
					notify( msg, 'error' );
					// eslint-disable-next-line no-console
					console.error( '[WPSN Bookmark]', msg );
				}
			} )
			.catch( function ( err ) {
				notify( 'Bookmark: network error', 'error' );
				// eslint-disable-next-line no-console
				console.error( '[WPSN Bookmark] network error', err );
			} )
			.finally( function () { btn.disabled = false; } );
	}

	return { init: init, bindIn: bindIn };
} )();
