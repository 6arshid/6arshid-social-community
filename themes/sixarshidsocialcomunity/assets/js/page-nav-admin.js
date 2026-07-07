/**
 * 6Arshid Social Community — Sidebar Navigation admin screen.
 *
 * Plain-DOM manager (no iframe, no Gutenberg) so drag-and-drop, clicks and the
 * icon picker all work reliably. Reads / writes through the same REST routes
 * used elsewhere:
 *   GET  /a6sc/v1/page-nav-items
 *   POST /a6sc/v1/page-icon   { page_id, icon }
 *   POST /a6sc/v1/page-order  { order: [key, …] }
 */
( function () {
	'use strict';

	var CFG  = window.a6scPageNav || {};
	var I18N = CFG.i18n || {};
	var apiFetch = window.wp && window.wp.apiFetch;

	var root = document.getElementById( 'a6sc-nav-admin' );
	if ( ! root || ! apiFetch ) { return; }

	if ( CFG.nonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( CFG.nonce ) );
	}

	var state = { list: [], icons: null, dragIndex: -1 };

	// ── Helpers ──────────────────────────────────────────────────────────────
	function svg( inner, size ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size +
			'" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">' + inner + '</svg>';
	}

	function loadIcons() {
		if ( state.icons ) { return Promise.resolve( state.icons ); }
		if ( ! CFG.iconsUrl ) { state.icons = {}; return Promise.resolve( {} ); }
		return fetch( CFG.iconsUrl )
			.then( function ( r ) { return r.ok ? r.json() : {}; } )
			.then( function ( j ) { state.icons = j || {}; return state.icons; } )
			.catch( function () { state.icons = {}; return {}; } );
	}

	// ── Persistence ──────────────────────────────────────────────────────────
	function persistOrder() {
		var order = state.list.map( function ( it ) { return it.key; } );
		apiFetch( { path: '/a6sc/v1/page-order', method: 'POST', data: { order: order } } ).catch( function () {} );
	}

	function saveIcon( pageId, iconName ) {
		apiFetch( { path: '/a6sc/v1/page-icon', method: 'POST', data: { page_id: pageId, icon: iconName } } )
			.then( function () {
				state.list.forEach( function ( it ) {
					if ( it.page_id === pageId ) { it.icon_name = iconName; }
				} );
				render();
			} )
			.catch( function () {} );
	}

	function move( from, to ) {
		if ( from === to || from < 0 || to < 0 || to >= state.list.length ) { return; }
		var moved = state.list.splice( from, 1 )[0];
		state.list.splice( to, 0, moved );
		persistOrder();
		render();
	}

	// ── Icon picker modal ────────────────────────────────────────────────────
	function openPicker( pageId ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'a6sc-modal-overlay';

		var modal = document.createElement( 'div' );
		modal.className = 'a6sc-modal';
		modal.innerHTML =
			'<div class="a6sc-modal__head">' +
				'<strong>' + ( I18N.pickIcon || 'Select an Icon' ) + '</strong>' +
				'<button type="button" class="a6sc-modal__close" aria-label="' + ( I18N.close || 'Close' ) + '">&times;</button>' +
			'</div>' +
			'<input type="search" class="a6sc-modal__search" placeholder="' + ( I18N.search || 'Search icons…' ) + '" />' +
			'<div class="a6sc-icp-grid"></div>' +
			'<p class="a6sc-icp-more" style="display:none;">' + ( I18N.more || '' ) + '</p>';

		overlay.appendChild( modal );
		document.body.appendChild( overlay );

		var grid     = modal.querySelector( '.a6sc-icp-grid' );
		var moreNote = modal.querySelector( '.a6sc-icp-more' );
		var search   = modal.querySelector( '.a6sc-modal__search' );
		var LIMIT    = 150;

		function close() { overlay.remove(); }
		modal.querySelector( '.a6sc-modal__close' ).addEventListener( 'click', close );
		overlay.addEventListener( 'click', function ( e ) { if ( e.target === overlay ) { close(); } } );
		document.addEventListener( 'keydown', function esc( e ) {
			if ( e.key === 'Escape' ) { close(); document.removeEventListener( 'keydown', esc ); }
		} );

		function draw( q ) {
			grid.innerHTML = '';
			var names = Object.keys( state.icons || {} );
			if ( q ) {
				q = q.toLowerCase();
				names = names.filter( function ( n ) { return n.indexOf( q ) !== -1; } );
			}
			moreNote.style.display = names.length > LIMIT ? 'block' : 'none';
			names.slice( 0, LIMIT ).forEach( function ( name ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'a6sc-icp-cell';
				b.title = name;
				b.innerHTML = svg( state.icons[ name ], 22 );
				b.addEventListener( 'click', function () { saveIcon( pageId, name ); close(); } );
				grid.appendChild( b );
			} );
		}

		search.addEventListener( 'input', function () { draw( search.value.trim() ); } );

		grid.innerHTML = '<span class="spinner is-active" style="float:none;"></span>';
		loadIcons().then( function () { draw( '' ); search.focus(); } );
	}

	// ── Render the list ──────────────────────────────────────────────────────
	function render() {
		root.innerHTML = '';

		if ( ! state.list.length ) {
			var empty = document.createElement( 'p' );
			empty.textContent = I18N.noPages || 'No pages found.';
			root.appendChild( empty );
			return;
		}

		var hint = document.createElement( 'p' );
		hint.className = 'a6sc-icp-hint';
		hint.textContent = I18N.reorder || '';
		root.appendChild( hint );

		var listEl = document.createElement( 'div' );
		listEl.className = 'a6sc-icp-list';

		state.list.forEach( function ( item, index ) {
			var row = document.createElement( 'div' );
			row.className = 'a6sc-icp-row';
			row.draggable = true;

			row.addEventListener( 'dragstart', function ( e ) {
				state.dragIndex = index; row.classList.add( 'is-dragging' );
				e.dataTransfer.effectAllowed = 'move';
			} );
			row.addEventListener( 'dragover', function ( e ) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; } );
			row.addEventListener( 'drop', function ( e ) { e.preventDefault(); move( state.dragIndex, index ); } );
			row.addEventListener( 'dragend', function () { state.dragIndex = -1; row.classList.remove( 'is-dragging' ); } );

			// handle
			var handle = document.createElement( 'span' );
			handle.className = 'a6sc-icp-row__handle';
			handle.textContent = '⠿';
			row.appendChild( handle );

			// icon
			var icon = document.createElement( 'span' );
			icon.className = 'a6sc-icp-row__icon';
			var hasIcon = item.icon_name && state.icons && state.icons[ item.icon_name ];
			icon.innerHTML = hasIcon ? svg( state.icons[ item.icon_name ], 20 ) : '<span style="color:#bbb;">●</span>';
			row.appendChild( icon );

			// label
			var label = document.createElement( 'span' );
			label.className = 'a6sc-icp-row__label';
			label.textContent = item.label;
			row.appendChild( label );

			// buttons
			var btns = document.createElement( 'span' );
			btns.className = 'a6sc-icp-row__btns';

			var up = document.createElement( 'button' );
			up.type = 'button'; up.className = 'button button-small'; up.innerHTML = '&#9650;';
			up.title = I18N.moveUp || 'Move up'; up.disabled = index === 0;
			up.addEventListener( 'click', function () { move( index, index - 1 ); } );
			btns.appendChild( up );

			var down = document.createElement( 'button' );
			down.type = 'button'; down.className = 'button button-small'; down.innerHTML = '&#9660;';
			down.title = I18N.moveDown || 'Move down'; down.disabled = index === state.list.length - 1;
			down.addEventListener( 'click', function () { move( index, index + 1 ); } );
			btns.appendChild( down );

			if ( item.page_id ) {
				var edit = document.createElement( 'button' );
				edit.type = 'button'; edit.className = 'button button-small a6sc-icp-row__edit'; edit.textContent = '✎';
				edit.title = I18N.edit || 'Change icon';
				edit.addEventListener( 'click', function () { openPicker( item.page_id ); } );
				btns.appendChild( edit );
			}

			row.appendChild( btns );
			listEl.appendChild( row );
		} );

		root.appendChild( listEl );
	}

	// ── Boot ─────────────────────────────────────────────────────────────────
	Promise.all( [
		apiFetch( { path: '/a6sc/v1/page-nav-items' } ).catch( function () { return []; } ),
		loadIcons()
	] ).then( function ( res ) {
		state.list = res[0] || [];
		render();
	} );

} )();
