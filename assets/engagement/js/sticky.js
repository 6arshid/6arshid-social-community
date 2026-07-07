/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALSticky = ( function () {
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

	function bind() {
		if ( bound ) return;
		bound = true;
		document.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '[data-sn-sticky-toggle]' );
			if ( ! btn ) return;
			e.preventDefault();

			const activityId = btn.dataset.activityId;
			const scope      = btn.dataset.scope || 'profile';
			const scopeId    = parseInt( btn.dataset.scopeId || '0', 10 );
			const pinned     = btn.dataset.pinned === '1';
			const method     = pinned ? 'DELETE' : 'POST';

			btn.disabled = true;
			fetch( cfg.restUrl + 'activity/' + activityId + '/sticky?scope=' + encodeURIComponent( scope ) + '&scope_id=' + scopeId, {
				method,
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
				body: JSON.stringify( { scope, scope_id: scopeId } ),
			} )
				.then( r => {
					if ( ! r.ok ) return null;
					return method === 'DELETE' ? {} : r.json();
				} )
				.then( data => {
					if ( data === null ) return;
					btn.dataset.pinned = pinned ? '0' : '1';
					btn.textContent    = pinned ? ( btn.dataset.labelPin || '📌 Pin' ) : ( btn.dataset.labelUnpin || '📌 Unpin' );
					const item = btn.closest( '.arshid6social-activity-item' );
					if ( item ) item.classList.toggle( 'is-sticky', ! pinned );
				} )
				.catch( () => {} )
				.finally( () => { btn.disabled = false; } );
		} );
	}

	return { init, bindIn };
} )();
