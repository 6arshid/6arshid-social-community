/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALGifs = ( function () {
	'use strict';

	let cfg = {};

	function init( c ) {
		cfg = c;
		bindIn( document, c );
	}

	function bindIn( root, c ) {
		cfg = c;
		root.querySelectorAll( '.arshid6social-gif-trigger' ).forEach( bindTrigger );
	}

	function bindTrigger( btn ) {
		if ( btn._ARSHID6SOCIALGifBound ) return;
		btn._ARSHID6SOCIALGifBound = true;

		const form    = btn.closest( 'form, .arshid6social-comment-form' );
		if ( ! form ) return;

		let picker = null;

		btn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( picker ) {
				closePicker();
				return;
			}
			picker = buildPicker();
			positionPicker( picker, btn );
			document.body.appendChild( picker );
			picker.querySelector( '.arshid6social-gif-search' )?.focus();
			loadTrending( picker );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( picker && ! picker.contains( e.target ) && e.target !== btn ) {
				closePicker();
			}
		} );

		function closePicker() {
			picker?.remove();
			picker = null;
		}

		function buildPicker() {
			const el = document.createElement( 'div' );
			el.className = 'arshid6social-gif-picker open';

			const search = document.createElement( 'input' );
			search.type        = 'text';
			search.className   = 'arshid6social-gif-search';
			search.placeholder = cfg.i18n?.searchGifs || 'Search GIFs…';

			const grid = document.createElement( 'div' );
			grid.className = 'arshid6social-gif-grid';

			el.appendChild( search );
			el.appendChild( grid );

			let searchTimer;
			search.addEventListener( 'input', function () {
				clearTimeout( searchTimer );
				searchTimer = setTimeout( function () {
					const q = search.value.trim();
					if ( q ) searchGifs( q, grid );
					else loadTrending( el );
				}, 300 );
			} );

			return el;
		}

		function loadTrending( el ) {
			const grid = el.querySelector( '.arshid6social-gif-grid' );
			renderLoaders( grid );
			const fd = new FormData();
			fd.append( 'action', 'arshid6social_gif_trending' );
			fd.append( 'nonce',  cfg.nonce );
			fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
				.then( r => r.json() )
				.then( data => renderGifs( grid, data.success ? data.data : [] ) )
				.catch( () => { grid.innerHTML = ''; } );
		}

		function searchGifs( q, grid ) {
			renderLoaders( grid );
			const fd = new FormData();
			fd.append( 'action', 'arshid6social_gif_search' );
			fd.append( 'nonce',  cfg.nonce );
			fd.append( 'q',      q );
			fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
				.then( r => r.json() )
				.then( data => renderGifs( grid, data.success ? data.data : [] ) )
				.catch( () => { grid.innerHTML = ''; } );
		}

		function renderLoaders( grid ) {
			grid.innerHTML = '';
			for ( let i = 0; i < 6; i++ ) {
				const ph = document.createElement( 'div' );
				ph.className = 'arshid6social-gif-placeholder';
				grid.appendChild( ph );
			}
		}

		function renderGifs( grid, gifs ) {
			grid.innerHTML = '';
			if ( ! gifs.length ) {
				grid.innerHTML = '<div class="arshid6social-gif-empty">' + ( cfg.i18n?.noGifs || 'No GIFs found' ) + '</div>';
				return;
			}
			gifs.forEach( gif => {
				const item = document.createElement( 'div' );
				item.className = 'arshid6social-gif-item';
				const img = document.createElement( 'img' );
				img.className = 'lazy';
				img.dataset.src = gif.thumb || gif.url;
				img.alt         = gif.title || '';
				item.appendChild( img );
				item.addEventListener( 'click', function () {
					insertGif( gif );
					closePicker();
				} );
				grid.appendChild( item );
			} );
			lazyLoad( grid );
		}

		function insertGif( gif ) {
			// Store chosen GIF data on the form for submission.
			let input = form.querySelector( 'input[name="sn_gif_url"]' );
			if ( ! input ) {
				input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = 'sn_gif_url';
				form.appendChild( input );
			}
			input.value = gif.url;

			// Show preview near comment field.
			let preview = form.querySelector( '.arshid6social-gif-preview' );
			if ( ! preview ) {
				preview = document.createElement( 'div' );
				preview.className = 'arshid6social-gif-preview';
				form.appendChild( preview );
			}
			preview.innerHTML = '<img src="' + encodeURI( gif.url ) + '" style="max-height:120px;border-radius:4px">' +
				'<button type="button" class="arshid6social-att-remove" aria-label="Remove">×</button>';
			preview.querySelector( '.arshid6social-att-remove' )?.addEventListener( 'click', function () {
				preview.remove();
				input.value = '';
			} );
		}
	}

	function lazyLoad( container ) {
		const imgs = container.querySelectorAll( 'img.lazy' );
		if ( 'IntersectionObserver' in window ) {
			const obs = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						const img = entry.target;
						img.src = img.dataset.src || '';
						img.classList.replace( 'lazy', 'loaded' );
						obs.unobserve( img );
					}
				} );
			} );
			imgs.forEach( img => obs.observe( img ) );
		} else {
			imgs.forEach( img => {
				img.src = img.dataset.src || '';
				img.classList.replace( 'lazy', 'loaded' );
			} );
		}
	}

	function positionPicker( picker, anchor ) {
		const r = anchor.getBoundingClientRect();
		picker.style.position = 'fixed';
		picker.style.top  = ( r.bottom + 8 ) + 'px';
		picker.style.left = r.left + 'px';
		picker.style.zIndex = 9999;
	}

	return { init, bindIn };
} )();
