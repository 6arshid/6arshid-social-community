/**
 * 6Arshid Social Community — Unified Search Page
 *
 * Mounts into #arshid6social-search-root on the WordPress /?s= search page.
 * Reads ARSHID6SOCIALSearch config injected by PHP.
 */
( function () {
	'use strict';

	/* ── Config (injected by PHP) ────────────────────────────────────────── */
	const CFG            = window.ARSHID6SOCIALSearch || {};
	const MAIN_CFG       = window.ARSHID6SOCIALConfig || {};
	const SEARCH_ENDPOINT = CFG.searchEndpoint || '';
	const REST_URL       = CFG.restUrl  || MAIN_CFG.restUrl  || '';
	const NONCE          = CFG.nonce    || MAIN_CFG.nonce    || '';
	const PAGINATION     = CFG.paginationType || 'pagination'; // 'pagination' | 'infinite_scroll'
	const SITE_URL       = CFG.siteUrl  || MAIN_CFG.siteUrl  || window.location.origin + '/';
	const I18N           = CFG.i18n     || {};

	/* ── Strings ─────────────────────────────────────────────────────────── */
	const T = {
		all:         I18N.all         || 'All',
		activity:    I18N.activity    || 'Activity',
		members:     I18N.members     || 'People',
		groups:      I18N.groups      || 'Groups',
		marketplace: I18N.marketplace || 'Marketplace',
		search:      I18N.search      || 'Search',
		noResults:   I18N.noResults   || 'No results found',
		noResultsSub: I18N.noResultsSub || 'Try a different keyword.',
		seeAll:      I18N.seeAll      || 'See all',
		results:     I18N.results     || 'results',
		loadMore:    I18N.loadMore    || 'Load more',
		loading:     I18N.loading     || 'Loading…',
		viewPost:    I18N.viewPost    || 'View post',
		free:        I18N.free        || 'Free',
		members_lc:  I18N.members_lc  || 'members',
		prev:        I18N.prev        || '← Prev',
		next:        I18N.next        || 'Next →',
	};

	/* ── State ───────────────────────────────────────────────────────────── */
	let state = {
		query:       '',
		activeTab:   'all',
		pages:       { activity: 1, members: 1, groups: 1, marketplace: 1 },
		totals:      { activity: 0, members: 0, groups: 0, marketplace: 0 },
		totalPages:  { activity: 1, members: 1, groups: 1, marketplace: 1 },
		items:       { activity: [], members: [], groups: [], marketplace: [] },
		loading:     false,
		initialized: false,
	};

	/* ── DOM refs ────────────────────────────────────────────────────────── */
	let root, searchInput, tabsBar, body;

	/* ── Init ────────────────────────────────────────────────────────────── */
	function init() {
		root = document.getElementById( 'arshid6social-search-root' );
		if ( ! root ) return;

		// Read initial query from URL (?s=)
		const urlParams = new URLSearchParams( window.location.search );
		state.query = urlParams.get( 's' ) || '';

		render();
		bindEvents();

		if ( state.query ) {
			loadAll();
		}
	}

	/* ── Render shell ────────────────────────────────────────────────────── */
	function render() {
		root.className = 'arshid6social-search-wrap';
		root.innerHTML = `
			<form class="arshid6social-search-form" id="arshid6social-search-form">
				<input
					type="search"
					class="arshid6social-search-form__input"
					id="arshid6social-search-input"
					placeholder="${esc( T.search )}…"
					value="${esc( state.query )}"
					autocomplete="off"
				/>
				<button type="submit" class="arshid6social-search-form__btn">${esc( T.search )}</button>
			</form>
			<nav class="arshid6social-search-tabs" id="arshid6social-search-tabs" role="tablist">
				${ renderTabs() }
			</nav>
			<div class="arshid6social-search-body" id="arshid6social-search-body">
				${ state.query ? renderSkeleton() : '' }
			</div>
		`;

		searchInput = root.querySelector( '#arshid6social-search-input' );
		tabsBar     = root.querySelector( '#arshid6social-search-tabs' );
		body        = root.querySelector( '#arshid6social-search-body' );
	}

	/* ── Tabs ─────────────────────────────────────────────────────────────── */
	function renderTabs() {
		const tabs = [
			{ key: 'all',         label: T.all },
			{ key: 'activity',    label: T.activity },
			{ key: 'members',     label: T.members },
			{ key: 'groups',      label: T.groups },
			{ key: 'marketplace', label: T.marketplace },
		];

		return tabs.map( ( tab ) => {
			const count   = tab.key !== 'all' ? state.totals[ tab.key ] : null;
			const isActive = state.activeTab === tab.key;
			const countHtml = ( count !== null && state.initialized )
				? `<span class="arshid6social-search-tab__count">${ fmtCount( count ) }</span>`
				: '';
			return `<button
				class="arshid6social-search-tab${ isActive ? ' is-active' : '' }"
				data-tab="${ esc( tab.key ) }"
				role="tab"
				aria-selected="${ isActive }"
			>${ esc( tab.label ) }${ countHtml }</button>`;
		} ).join( '' );
	}

	function refreshTabs() {
		if ( tabsBar ) tabsBar.innerHTML = renderTabs();
	}

	/* ── Skeleton ─────────────────────────────────────────────────────────── */
	function renderSkeleton( count = 4 ) {
		const row = `<div class="arshid6social-search-skeleton__row">
			<div class="arshid6social-search-skeleton__circle"></div>
			<div class="arshid6social-search-skeleton__lines">
				<div class="arshid6social-search-skeleton__line arshid6social-search-skeleton__line--medium"></div>
				<div class="arshid6social-search-skeleton__line arshid6social-search-skeleton__line--short"></div>
			</div>
		</div>`;
		return `<div class="arshid6social-search-skeleton">${ row.repeat( count ) }</div>`;
	}

	/* ── API calls ────────────────────────────────────────────────────────── */
	async function fetchSearch( section, page = 1 ) {
		// Use the pre-built endpoint URL from PHP (handles pretty & non-pretty permalinks).
		const endpoint = SEARCH_ENDPOINT || ( REST_URL.replace( /\/?$/, '/' ) + 'search' );
		if ( ! endpoint ) {
			throw new Error( 'Search endpoint not configured' );
		}

		// Append query params without breaking existing ?rest_route= style URLs.
		const sep    = endpoint.includes( '?' ) ? '&' : '?';
		const params = 'q='       + encodeURIComponent( state.query )
		             + '&section=' + encodeURIComponent( section )
		             + '&page='    + encodeURIComponent( page );
		const url    = endpoint + sep + params;

		const res = await fetch( url, {
			headers: { 'X-WP-Nonce': NONCE },
		} );

		if ( ! res.ok ) {
			const text = await res.text().catch( () => '' );
			throw new Error( 'Search failed (' + res.status + '): ' + text.slice( 0, 200 ) );
		}
		return res.json();
	}

	/**
	 * Load "all" overview: hits the REST once, gets N results per section.
	 */
	async function loadAll() {
		if ( state.loading ) return;
		state.loading = true;
		body.innerHTML = renderSkeleton( 5 );

		try {
			const data = await fetchSearch( 'all', 1 );
			const r    = data.results || {};

			for ( const section of [ 'activity', 'members', 'groups', 'marketplace' ] ) {
				if ( r[ section ] ) {
					state.items[ section ]      = r[ section ].items || [];
					state.totals[ section ]     = r[ section ].total || 0;
					state.totalPages[ section ] = r[ section ].total_pages || 0;
					state.pages[ section ]      = 1;
				}
			}

			state.initialized = true;
			refreshTabs();
			renderAllView();
		} catch ( e ) {
			body.innerHTML = renderError( e && e.message );
		} finally {
			state.loading = false;
		}
	}

	/**
	 * Load a specific section (paginated view).
	 */
	async function loadSection( section, page = 1, append = false ) {
		if ( state.loading ) return;
		state.loading = true;

		if ( ! append ) {
			body.innerHTML = renderSkeleton( 6 );
		} else {
			const loadMoreBtn = body.querySelector( '.arshid6social-search-load-more__btn' );
			if ( loadMoreBtn ) {
				loadMoreBtn.disabled = true;
				loadMoreBtn.textContent = T.loading;
			}
		}

		try {
			const data  = await fetchSearch( section, page );
			const r     = data.results[ section ] || {};
			const items = r.items || [];

			if ( append ) {
				state.items[ section ] = state.items[ section ].concat( items );
			} else {
				state.items[ section ] = items;
			}

			state.totals[ section ]     = r.total || 0;
			state.totalPages[ section ] = r.total_pages || 0;
			state.pages[ section ]      = page;

			refreshTabs();
			renderSectionView( section );
		} catch ( e ) {
			body.innerHTML = renderError( e && e.message );
		} finally {
			state.loading = false;
		}
	}

	/* ── Render views ─────────────────────────────────────────────────────── */
	function renderAllView() {
		const sections = [ 'activity', 'members', 'groups', 'marketplace' ];
		const hasAny   = sections.some( ( s ) => state.items[ s ].length > 0 );

		if ( ! hasAny ) {
			body.innerHTML = renderEmptyState();
			return;
		}

		let html = '';
		for ( const section of sections ) {
			const items = state.items[ section ];
			if ( ! items.length ) continue;

			const sectionLabel = T[ section ] || section;
			const total        = state.totals[ section ];

			html += `<div class="arshid6social-search-section">
				<div class="arshid6social-search-section__header">
					<span class="arshid6social-search-section__title">${ esc( sectionLabel ) }</span>
					${ total > items.length
						? `<button class="arshid6social-search-section__see-all" data-section="${ esc( section ) }">
								${ esc( T.seeAll ) } ${ fmtCount( total ) } ${ esc( T.results ) }
							</button>`
						: ''
					}
				</div>
				${ renderItems( section, items ) }
			</div>`;
		}

		body.innerHTML = html || renderEmptyState();
	}

	function renderSectionView( section ) {
		const items      = state.items[ section ];
		const total      = state.totals[ section ];
		const page       = state.pages[ section ];
		const totalPages = state.totalPages[ section ];

		let html = items.length ? renderItems( section, items ) : renderEmptyState();

		if ( PAGINATION === 'infinite_scroll' ) {
			if ( page < totalPages ) {
				html += `<div class="arshid6social-search-load-more">
					<button class="arshid6social-search-load-more__btn" data-section="${ esc( section ) }" data-page="${ page + 1 }">
						${ esc( T.loadMore ) }
					</button>
				</div>`;
			}
		} else {
			// Basic pagination
			if ( totalPages > 1 ) {
				html += renderPagination( section, page, totalPages );
			}
		}

		body.innerHTML = html;

		// Infinite scroll sentinel
		if ( PAGINATION === 'infinite_scroll' && page < totalPages ) {
			observeLoadMore( section );
		}
	}

	/* ── Item renderers ───────────────────────────────────────────────────── */
	function renderItems( section, items ) {
		switch ( section ) {
			case 'activity':    return items.map( renderActivityItem ).join( '' );
			case 'members':     return items.map( renderMemberItem ).join( '' );
			case 'groups':      return items.map( renderGroupItem ).join( '' );
			case 'marketplace': return items.map( renderListingItem ).join( '' );
			default:            return '';
		}
	}

	function renderActivityItem( a ) {
		return `<div class="arshid6social-search-activity-item">
			<img
				class="arshid6social-search-activity-item__avatar"
				src="${ esc( a.userAvatarUrl || '' ) }"
				alt="${ esc( a.userName || '' ) }"
				loading="lazy"
				width="40" height="40"
			/>
			<div class="arshid6social-search-activity-item__body">
				<a href="${ esc( a.userProfileUrl || '#' ) }" class="arshid6social-search-activity-item__author">
					${ esc( a.userName || '' ) }
				</a>
				<div class="arshid6social-search-activity-item__content">${ a.content || '' }</div>
				<div class="arshid6social-search-activity-item__meta">
					${ esc( relativeDate( a.dateRecorded ) ) }
					${ a.permalink
						? `<a href="${ esc( a.permalink ) }" class="arshid6social-search-activity-item__link" target="_blank" rel="noopener">${ esc( T.viewPost ) }</a>`
						: ''
					}
				</div>
			</div>
		</div>`;
	}

	function renderMemberItem( m ) {
		const verified = m.isVerified
			? `<span class="arshid6social-search-member-item__badge" title="Verified">✓</span>`
			: '';
		return `<a href="${ esc( m.profileUrl || '#' ) }" class="arshid6social-search-member-item">
			<img
				class="arshid6social-search-member-item__avatar"
				src="${ esc( m.avatarUrl || '' ) }"
				alt="${ esc( m.name || '' ) }"
				loading="lazy"
				width="44" height="44"
			/>
			<div class="arshid6social-search-member-item__info">
				<div class="arshid6social-search-member-item__name">
					${ esc( m.name || '' ) } ${ verified }
				</div>
				${ m.bio ? `<div class="arshid6social-search-member-item__bio">${ esc( m.bio.slice( 0, 80 ) ) }</div>` : '' }
			</div>
		</a>`;
	}

	function renderGroupItem( g ) {
		const avatarInner = g.avatarUrl
			? `<img src="${ esc( g.avatarUrl ) }" alt="${ esc( g.name ) }" loading="lazy" />`
			: '👥';
		return `<a href="${ esc( g.url || '#' ) }" class="arshid6social-search-group-item">
			<div class="arshid6social-search-group-item__avatar">${ avatarInner }</div>
			<div class="arshid6social-search-group-item__info">
				<div class="arshid6social-search-group-item__name">${ esc( g.name || '' ) }</div>
				<div class="arshid6social-search-group-item__meta">
					${ fmtCount( g.memberCount || 0 ) } ${ esc( T.members_lc ) }
					· ${ esc( g.status || '' ) }
				</div>
			</div>
		</a>`;
	}

	function renderListingItem( l ) {
		return `<a href="${ esc( l.url || '#' ) }" class="arshid6social-search-listing-item">
			<div class="arshid6social-search-listing-item__icon">🛍️</div>
			<div class="arshid6social-search-listing-item__info">
				<div class="arshid6social-search-listing-item__title">${ esc( l.title || '' ) }</div>
				<div class="arshid6social-search-listing-item__price">${ esc( l.price_formatted || '' ) }</div>
				${ l.location_city
					? `<div class="arshid6social-search-listing-item__meta">${ esc( l.location_city ) }</div>`
					: '' }
			</div>
		</a>`;
	}

	/* ── Pagination ───────────────────────────────────────────────────────── */
	function renderPagination( section, page, totalPages ) {
		const MAX_VISIBLE = 5;
		let pages = [];

		if ( totalPages <= MAX_VISIBLE ) {
			for ( let i = 1; i <= totalPages; i++ ) pages.push( i );
		} else {
			pages.push( 1 );
			if ( page > 3 ) pages.push( '…' );
			for ( let i = Math.max( 2, page - 1 ); i <= Math.min( totalPages - 1, page + 1 ); i++ ) {
				pages.push( i );
			}
			if ( page < totalPages - 2 ) pages.push( '…' );
			pages.push( totalPages );
		}

		const btns = pages.map( ( p ) => {
			if ( p === '…' ) return `<span class="arshid6social-search-page-btn" style="border:none;cursor:default">…</span>`;
			return `<button
				class="arshid6social-search-page-btn${ p === page ? ' is-active' : '' }"
				data-section="${ esc( section ) }"
				data-page="${ p }"
				${ p === page ? 'aria-current="page"' : '' }
			>${ p }</button>`;
		} );

		const prevBtn = `<button class="arshid6social-search-page-btn" data-section="${ esc( section ) }" data-page="${ page - 1 }" ${ page <= 1 ? 'disabled' : '' } aria-label="Previous">${ esc( T.prev ) }</button>`;
		const nextBtn = `<button class="arshid6social-search-page-btn" data-section="${ esc( section ) }" data-page="${ page + 1 }" ${ page >= totalPages ? 'disabled' : '' } aria-label="Next">${ esc( T.next ) }</button>`;

		return `<div class="arshid6social-search-pagination">
			${ prevBtn }
			${ btns.join( '' ) }
			${ nextBtn }
		</div>`;
	}

	/* ── Infinite scroll ──────────────────────────────────────────────────── */
	function observeLoadMore( section ) {
		const sentinel = body.querySelector( '.arshid6social-search-load-more' );
		if ( ! sentinel || ! window.IntersectionObserver ) return;

		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries[ 0 ].isIntersecting ) {
				observer.disconnect();
				const nextPage = state.pages[ section ] + 1;
				loadSection( section, nextPage, true );
			}
		}, { rootMargin: '200px' } );

		observer.observe( sentinel );
	}

	/* ── Empty / error states ─────────────────────────────────────────────── */
	function renderEmptyState() {
		return `<div class="arshid6social-search-empty">
			<div class="arshid6social-search-empty__icon">🔍</div>
			<div class="arshid6social-search-empty__title">${ esc( T.noResults ) }</div>
			<p class="arshid6social-search-empty__text">${ esc( T.noResultsSub ) }</p>
		</div>`;
	}

	function renderError( msg ) {
		/* eslint-disable no-console */
		if ( msg ) console.warn( '[arshid6social-search]', msg );
		/* eslint-enable no-console */
		const detail = msg ? `<p class="arshid6social-search-empty__text" style="font-size:11px;opacity:.6;word-break:break-all">${ esc( msg ) }</p>` : '';
		return `<div class="arshid6social-search-empty">
			<div class="arshid6social-search-empty__icon">⚠️</div>
			<div class="arshid6social-search-empty__title">Something went wrong</div>
			${ detail }
		</div>`;
	}

	/* ── Events ───────────────────────────────────────────────────────────── */
	function bindEvents() {
		// Search form submit
		root.querySelector( '#arshid6social-search-form' ).addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			const q = searchInput.value.trim();
			if ( ! q ) return;

			state.query       = q;
			state.activeTab   = 'all';
			state.initialized = false;

			// Update URL without reload
			const url = new URL( window.location.href );
			url.searchParams.set( 's', q );
			window.history.pushState( {}, '', url.toString() );

			refreshTabs();
			loadAll();
		} );

		// Tab clicks
		root.addEventListener( 'click', ( e ) => {
			const tabBtn = e.target.closest( '[data-tab]' );
			if ( tabBtn ) {
				const tab = tabBtn.dataset.tab;
				switchTab( tab );
				return;
			}

			// "See all" button in overview
			const seeAllBtn = e.target.closest( '[data-section]' );
			if ( seeAllBtn && seeAllBtn.classList.contains( 'arshid6social-search-section__see-all' ) ) {
				switchTab( seeAllBtn.dataset.section );
				return;
			}

			// Pagination page button
			if ( e.target.classList.contains( 'arshid6social-search-page-btn' ) ) {
				const section = e.target.dataset.section;
				const page    = parseInt( e.target.dataset.page, 10 );
				if ( section && page && ! isNaN( page ) ) {
					loadSection( section, page, false );
					body.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
				return;
			}

			// Load more button (infinite scroll, manual click)
			const loadMoreBtn = e.target.closest( '.arshid6social-search-load-more__btn' );
			if ( loadMoreBtn ) {
				const section = loadMoreBtn.dataset.section;
				const page    = parseInt( loadMoreBtn.dataset.page, 10 );
				if ( section && page ) {
					loadSection( section, page, true );
				}
			}
		} );

		// Browser back/forward
		window.addEventListener( 'popstate', () => {
			const urlParams = new URLSearchParams( window.location.search );
			const q = urlParams.get( 's' ) || '';
			if ( q !== state.query ) {
				state.query       = q;
				state.activeTab   = 'all';
				state.initialized = false;
				if ( searchInput ) searchInput.value = q;
				refreshTabs();
				if ( q ) loadAll(); else body.innerHTML = '';
			}
		} );
	}

	function switchTab( tab ) {
		if ( state.activeTab === tab ) return;
		state.activeTab = tab;
		refreshTabs();

		if ( tab === 'all' ) {
			renderAllView();
		} else {
			// If we already have items for this section, render them; else fetch
			if ( state.items[ tab ] && state.items[ tab ].length > 0 ) {
				renderSectionView( tab );
			} else {
				loadSection( tab, 1, false );
			}
		}
	}

	/* ── Helpers ──────────────────────────────────────────────────────────── */
	function esc( str ) {
		return String( str ?? '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function fmtCount( n ) {
		if ( n === undefined || n === null ) return '';
		if ( n >= 1000000 ) return ( n / 1000000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'M';
		if ( n >= 1000 )    return ( n / 1000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'K';
		return String( n );
	}

	function relativeDate( dateStr ) {
		if ( ! dateStr ) return '';
		const diff = Math.floor( ( Date.now() - new Date( dateStr ).getTime() ) / 1000 );
		if ( diff < 60 )     return diff + 's';
		if ( diff < 3600 )   return Math.floor( diff / 60 ) + 'm';
		if ( diff < 86400 )  return Math.floor( diff / 3600 ) + 'h';
		if ( diff < 604800 ) return Math.floor( diff / 86400 ) + 'd';
		return new Date( dateStr ).toLocaleDateString();
	}

	/* ── Boot ─────────────────────────────────────────────────────────────── */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
