/**
 * 6Arshid Social Community — Bootstrap Icons Picker
 *
 * Reads icons from the bundled assets/icons/bootstrap-icons.json (no CDN).
 *
 * Bootstrap Icons © The Bootstrap Authors — MIT License
 * https://icons.getbootstrap.com/
 */
( function () {
	'use strict';

	const PAGE_SIZE = 120;

	let allIcons    = null; // null = not yet fetched, {} = loaded
	let filtered    = [];
	let page        = 0;
	let searchTimer = null;

	// ── DOM refs ───────────────────────────────────────────────────────────────

	let wrap, hiddenInput, previewSvg, previewName;
	let openBtn, clearBtn;
	let modal, grid, loadMoreBtn, searchInput, iconCount;

	// ── Init ───────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		wrap = document.getElementById( 'arshid6social-icp-wrap' );
		if ( ! wrap ) return;

		hiddenInput = document.getElementById( 'arshid6social_page_icon' );
		previewSvg  = document.getElementById( 'arshid6social-icp-preview-svg' );
		previewName = document.getElementById( 'arshid6social-icp-preview-name' );
		openBtn     = document.getElementById( 'arshid6social-icp-open' );
		clearBtn    = document.getElementById( 'arshid6social-icp-clear' );
		modal       = document.getElementById( 'arshid6social-icp-modal' );
		grid        = document.getElementById( 'arshid6social-icp-grid' );
		loadMoreBtn = document.getElementById( 'arshid6social-icp-load-more' );
		searchInput = document.getElementById( 'arshid6social-icp-search' );
		iconCount   = document.getElementById( 'arshid6social-icp-icon-count' );

		if ( openBtn )  openBtn.addEventListener( 'click', openModal );
		if ( clearBtn ) clearBtn.addEventListener( 'click', clearIcon );

		const backdrop = document.getElementById( 'arshid6social-icp-backdrop' );
		const closeBtn = document.getElementById( 'arshid6social-icp-close' );
		if ( backdrop )  backdrop.addEventListener( 'click', closeModal );
		if ( closeBtn )  closeBtn.addEventListener( 'click', closeModal );
		if ( loadMoreBtn ) loadMoreBtn.addEventListener( 'click', loadMore );
		if ( searchInput ) searchInput.addEventListener( 'input', onSearch );

		document.addEventListener( 'keydown', e => {
			if ( e.key === 'Escape' && modal && ! modal.hidden ) closeModal();
		} );
	}

	// ── Modal open / close ─────────────────────────────────────────────────────

	async function openModal() {
		if ( ! modal ) return;

		modal.hidden = false;
		document.body.style.overflow = 'hidden';
		if ( searchInput ) searchInput.focus();

		if ( allIcons === null ) {
			await fetchIcons();
		}

		applyFilter( searchInput ? searchInput.value : '' );
	}

	function closeModal() {
		if ( ! modal ) return;
		modal.hidden = true;
		document.body.style.overflow = '';
	}

	// ── Fetch bundled icons.json ───────────────────────────────────────────────

	async function fetchIcons() {
		const url = ( ARSHID6SOCIALICP && ARSHID6SOCIALICP.iconsUrl ) ? ARSHID6SOCIALICP.iconsUrl : '';
		if ( ! url ) {
			allIcons = {};
			return;
		}

		if ( grid ) grid.innerHTML = '<div class="arshid6social-icp-loading">Loading icons…</div>';

		try {
			const res  = await fetch( url + '?v=' + ( ARSHID6SOCIALICP.version || '1' ) );
			if ( ! res.ok ) throw new Error( 'HTTP ' + res.status );
			allIcons = await res.json();
		} catch ( e ) {
			if ( grid ) grid.innerHTML = '<div class="arshid6social-icp-error">Could not load icons: ' + e.message + '</div>';
			allIcons = {};
		}
	}

	// ── Filter + render ────────────────────────────────────────────────────────

	function applyFilter( query ) {
		if ( ! allIcons ) return;

		const q = query.trim().toLowerCase();
		filtered = q
			? Object.keys( allIcons ).filter( n => n.includes( q ) )
			: Object.keys( allIcons );

		page = 0;
		if ( grid ) grid.innerHTML = '';

		if ( iconCount ) iconCount.textContent = '(' + filtered.length + ')';

		if ( filtered.length === 0 ) {
			if ( grid ) grid.innerHTML = '<div class="arshid6social-icp-empty">No icons found.</div>';
			if ( loadMoreBtn ) loadMoreBtn.hidden = true;
			return;
		}

		renderChunk();
	}

	function renderChunk() {
		const start = page * PAGE_SIZE;
		const slice = filtered.slice( start, start + PAGE_SIZE );

		const frag = document.createDocumentFragment();
		slice.forEach( name => frag.appendChild( createIconBtn( name ) ) );
		if ( grid ) grid.appendChild( frag );

		page++;
		if ( loadMoreBtn ) loadMoreBtn.hidden = ( page * PAGE_SIZE ) >= filtered.length;
	}

	function loadMore() { renderChunk(); }

	function createIconBtn( name ) {
		const inner = ( allIcons && allIcons[ name ] ) ? allIcons[ name ] : '';
		const btn   = document.createElement( 'button' );
		btn.type        = 'button';
		btn.className   = 'arshid6social-icp-icon-btn';
		btn.title       = name;
		btn.dataset.name = name;

		if ( hiddenInput && hiddenInput.value === name ) {
			btn.classList.add( 'is-selected' );
		}

		btn.innerHTML =
			'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">' +
			inner + '</svg>' +
			'<span>' + name + '</span>';

		btn.addEventListener( 'click', () => selectIcon( name, inner ) );
		return btn;
	}

	// ── Select / clear ─────────────────────────────────────────────────────────

	function selectIcon( name, svgPaths ) {
		if ( hiddenInput ) hiddenInput.value = name;
		if ( previewName ) previewName.textContent = name;

		if ( previewSvg ) {
			previewSvg.innerHTML =
				'<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">' +
				svgPaths + '</svg>';
		}

		if ( clearBtn ) clearBtn.style.display = '';

		if ( grid ) {
			grid.querySelectorAll( '.arshid6social-icp-icon-btn' ).forEach( b => {
				b.classList.toggle( 'is-selected', b.dataset.name === name );
			} );
		}

		closeModal();
	}

	function clearIcon() {
		if ( hiddenInput ) hiddenInput.value = '';
		if ( previewSvg )  previewSvg.innerHTML = '';
		if ( previewName ) previewName.textContent = '— none —';
		if ( clearBtn )    clearBtn.style.display = 'none';

		if ( grid ) {
			grid.querySelectorAll( '.is-selected' ).forEach( b => b.classList.remove( 'is-selected' ) );
		}
	}

	// ── Search debounce ────────────────────────────────────────────────────────

	function onSearch() {
		clearTimeout( searchTimer );
		searchTimer = setTimeout( () => applyFilter( searchInput.value ), 220 );
	}

} )();
