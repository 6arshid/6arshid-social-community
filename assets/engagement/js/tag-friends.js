/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALMentions = ( function () {
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

		document.addEventListener( 'input', debounce( function ( e ) {
			const field = e.target;
			if ( ! field.matches( 'textarea[data-sn-mention], input[data-sn-mention]' ) ) return;
			onInput( field );
		}, 250 ) );

		document.addEventListener( 'keydown', function ( e ) {
			const field = e.target;
			if ( ! field.matches( 'textarea[data-sn-mention], input[data-sn-mention]' ) ) return;
			onKeydown( e, field );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.arshid6social-mention-dropdown' ) ) {
				closeAllDrops();
			}
		} );
	}

	const drops = new WeakMap();

	function onInput( field ) {
		const { word } = currentWord( field );
		if ( ! word || ! word.startsWith( '@' ) || word.length < 2 ) {
			closeDrop( field );
			return;
		}
		const query = word.slice( 1 );
		fetch( cfg.restUrl + 'members?search=' + encodeURIComponent( query ) + '&per_page=8', {
			headers: { 'X-WP-Nonce': cfg.restNonce },
		} )
			.then( r => r.ok ? r.json() : [] )
			.then( users => renderDrop( users, field, word ) )
			.catch( () => {} );
	}

	function onKeydown( e, field ) {
		const list = drops.get( field );
		if ( ! list ) return;
		const items = list.querySelectorAll( 'li' );
		let idx = parseInt( list.dataset.idx || '-1', 10 );
		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			idx = Math.min( idx + 1, items.length - 1 );
			list.dataset.idx = idx;
			highlight( items, idx );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			idx = Math.max( idx - 1, 0 );
			list.dataset.idx = idx;
			highlight( items, idx );
		} else if ( e.key === 'Enter' && idx >= 0 ) {
			e.preventDefault();
			items[ idx ] && items[ idx ].click();
		} else if ( e.key === 'Escape' ) {
			closeDrop( field );
		}
	}

	function highlight( items, idx ) {
		items.forEach( ( li, i ) => li.classList.toggle( 'active', i === idx ) );
	}

	function renderDrop( users, field, typed ) {
		closeDrop( field );
		if ( ! users.length ) return;
		const list = document.createElement( 'ul' );
		list.className = 'arshid6social-mention-dropdown';
		list.dataset.idx = '-1';
		users.forEach( user => {
			const li  = document.createElement( 'li' );
			const img = document.createElement( 'img' );
			img.src   = user.avatarUrl || user.avatar_url || '';
			img.className = 'arshid6social-mention-avatar';
			img.alt   = '';
			const name = document.createElement( 'span' );
			name.textContent = user.name || user.username || user.user_login || '';
			li.appendChild( img );
			li.appendChild( name );
			li.addEventListener( 'click', function () {
				insertWord( field, typed, '@' + ( user.username || user.user_login || '' ) );
				closeDrop( field );
			} );
			list.appendChild( li );
		} );
		positionDrop( list, field );
		document.body.appendChild( list );
		drops.set( field, list );
	}

	function closeDrop( field ) {
		const list = drops.get( field );
		if ( list ) { list.remove(); drops.delete( field ); }
	}

	function closeAllDrops() {
		document.querySelectorAll( '.arshid6social-mention-dropdown' ).forEach( d => d.remove() );
	}

	function currentWord( field ) {
		const val   = field.value;
		const pos   = field.selectionStart;
		const left  = val.slice( 0, pos );
		const match = left.match( /@[\p{L}\p{N}_\-\.]*$/u );
		return match ? { word: match[0], start: pos - match[0].length } : { word: '', start: pos };
	}

	function insertWord( field, typed, replacement ) {
		const { start } = currentWord( field );
		const val = field.value;
		const end = start + typed.length;
		field.value = val.slice( 0, start ) + replacement + ' ' + val.slice( end );
		field.setSelectionRange( start + replacement.length + 1, start + replacement.length + 1 );
		field.dispatchEvent( new Event( 'input' ) );
	}

	function positionDrop( drop, field ) {
		const r = field.getBoundingClientRect();
		drop.style.cssText = 'position:fixed;top:' + r.bottom + 'px;left:' + r.left + 'px;z-index:9999';
	}

	function debounce( fn, ms ) {
		let t;
		return function ( e ) { clearTimeout( t ); t = setTimeout( () => fn( e ), ms ); };
	}

	return { init, bindIn };
} )();
