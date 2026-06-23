/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALHashtags = ( function () {
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

		// Event delegation on document — catches all current and future hashtag fields.
		document.addEventListener( 'input', debounce( function ( e ) {
			const field = e.target;
			if ( ! field.matches( 'textarea[data-sn-hashtag], input[data-sn-hashtag]' ) ) return;
			onInput( field );
		}, 200 ) );

		document.addEventListener( 'keydown', function ( e ) {
			const field = e.target;
			if ( ! field.matches( 'textarea[data-sn-hashtag], input[data-sn-hashtag]' ) ) return;
			onKeydown( e, field );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.arshid6social-hashtag-dropdown' ) ) {
				closeAllDrops();
			}
		} );
	}

	// Map from field → dropdown element.
	const drops = new WeakMap();

	function onInput( field ) {
		const { word } = currentWord( field );
		if ( ! word || ! word.startsWith( '#' ) || word.length < 2 ) {
			closeDrop( field );
			return;
		}
		const query = word.slice( 1 );
		const url = cfg.ajaxUrl + '?action=arshid6social_hashtag_autocomplete&nonce='
			+ encodeURIComponent( cfg.nonce ) + '&q=' + encodeURIComponent( query );
		fetch( url )
			.then( r => r.json() )
			.then( data => renderDrop( data.success ? data.data : [], field, word ) )
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

	function renderDrop( items, field, typed ) {
		closeDrop( field );
		if ( ! items.length ) return;
		const list = document.createElement( 'ul' );
		list.className = 'arshid6social-hashtag-dropdown';
		list.dataset.idx = '-1';
		items.forEach( item => {
			const li = document.createElement( 'li' );
			li.textContent = '#' + ( item.slug || item );
			const itemCount = item.use_count || item.count;
			if ( itemCount ) {
				const span = document.createElement( 'span' );
				span.className = 'arshid6social-tag-count';
				span.textContent = itemCount;
				li.appendChild( span );
			}
			li.addEventListener( 'click', function () {
				insertWord( field, typed, '#' + ( item.slug || item ) );
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
		document.querySelectorAll( '.arshid6social-hashtag-dropdown' ).forEach( d => d.remove() );
	}

	function currentWord( field ) {
		const val   = field.value;
		const pos   = field.selectionStart;
		const left  = val.slice( 0, pos );
		const match = left.match( /#[\p{L}\p{N}_]*$/u );
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
