( function () {
	'use strict';

	var stored = localStorage.getItem( 'a6sc-theme' );
	if ( stored === 'system' ) {
		document.documentElement.removeAttribute( 'data-a6sc-theme' );
	} else if ( stored ) {
		document.documentElement.setAttribute( 'data-a6sc-theme', stored );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.a6sc-theme-toggle' );
		if ( ! btn ) {
			return;
		}
		var html = document.documentElement;
		var cur  = html.getAttribute( 'data-a6sc-theme' ) || 'light';
		var next = ( cur === 'dark' ) ? 'light' : 'dark';
		html.setAttribute( 'data-a6sc-theme', next );
		localStorage.setItem( 'a6sc-theme', next );

		if ( typeof window.a6scThemeToggle === 'undefined' ) {
			return;
		}
		var fd = new FormData();
		fd.append( 'action', 'a6sc_set_theme_mode' );
		fd.append( 'nonce', window.a6scThemeToggle.nonce );
		fd.append( 'mode', next );
		fetch( window.a6scThemeToggle.ajaxUrl, { method: 'POST', body: fd } );
	} );
} )();
