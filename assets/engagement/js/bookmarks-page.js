/* global ARSHID6SOCIALEng, ARSHID6SOCIALConfig */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const feed     = document.getElementById( 'arshid6social-bookmarks-feed' );
		const sentinel = document.getElementById( 'arshid6social-bookmarks-sentinel' );
		if ( ! feed ) return;

		const eng     = window.ARSHID6SOCIALEng  || {};
		const cfg     = window.ARSHID6SOCIALConfig || {};
		const ajaxUrl = eng.ajaxUrl || cfg.ajaxUrl || '';
		const nonce   = eng.nonce   || cfg.ajaxNonce || '';

		let page      = 1;
		let isLoading = false;
		let hasMore   = true;

		function esc( s ) {
			const d = document.createElement( 'div' );
			d.textContent = String( s );
			return d.innerHTML;
		}

		function relDate( str ) {
			if ( ! str ) return '';
			const diff = Math.floor( ( Date.now() - new Date( str ).getTime() ) / 1000 );
			if ( diff < 60 )  return diff + 's ago';
			if ( diff < 3600 ) return Math.floor( diff / 60 ) + 'm ago';
			if ( diff < 86400 ) return Math.floor( diff / 3600 ) + 'h ago';
			return Math.floor( diff / 86400 ) + 'd ago';
		}

		function renderItem( a ) {
			if ( ! a ) return '';
			const avatarUrl  = a.userAvatarUrl || a.avatarUrl || '';
			const profileUrl = a.userProfileUrl || a.profileUrl || '#';
			const activityUrl = a.permalink || a.primaryLink || profileUrl;
			const avatar     = avatarUrl
				? `<img src="${esc( avatarUrl )}" width="42" height="42" style="border-radius:50%;object-fit:cover" alt="">`
				: `<div style="width:42px;height:42px;border-radius:50%;background:#e2e8f0"></div>`;

			const poll = a.poll
				? renderPoll( a.poll )
				: `<div class="arshid6social-activity-item-content">${a.content || ''}</div>`;

			return `<article class="arshid6social-activity-item" id="arshid6social-activity-${esc(a.id)}" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;display:flex;gap:12px;margin-bottom:12px">
				<a href="${esc(profileUrl)}">${avatar}</a>
				<div style="flex:1;min-width:0">
					<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
						<a href="${esc(activityUrl)}" style="font-weight:600;color:#0f172a;text-decoration:none">${esc(a.userName||a.displayName||'')}</a>
						<span style="font-size:.8rem;color:#64748b">${esc(relDate(a.dateRecorded))}</span>
					</div>
					${poll}
					<div style="display:flex;align-items:center;gap:12px;margin-top:10px">
						<button class="arshid6social-bookmark-btn saved" data-activity-id="${esc(a.id)}"
							aria-label="Remove bookmark" aria-pressed="true"
							style="background:none;border:none;cursor:pointer;padding:4px;color:#2563eb">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
								<path d="M5 3h14a1 1 0 0 1 1 1v17l-8-4-8 4V4a1 1 0 0 1 1-1z"/>
							</svg>
						</button>
					</div>
				</div>
			</article>`;
		}

		function renderPoll( poll ) {
			if ( ! poll || ! poll.options ) return '';
			let optsHtml = '';
			( poll.options || [] ).forEach( function ( opt ) {
				const pct   = poll.hasVoted && opt.percentage != null ? opt.percentage : 0;
				const votes = poll.hasVoted && opt.voteCount   != null ? opt.voteCount : '';
				optsHtml += `<div class="arshid6social-poll-option" data-option-id="${esc(opt.id)}">`;
				if ( poll.hasVoted ) {
					optsHtml += `<span class="arshid6social-poll-option-text">${esc(opt.text)}</span>`;
					optsHtml += `<div class="arshid6social-poll-bar-wrap"><div class="arshid6social-poll-bar-track"><div class="arshid6social-poll-bar-fill" style="width:${pct}%"></div></div>`;
					optsHtml += `<div class="arshid6social-poll-bar-label">${pct}% &mdash; ${esc(votes)}</div></div>`;
				} else {
					const inp = poll.pollType === 'multiple' ? 'checkbox' : 'radio';
					optsHtml += `<label><input type="${inp}" name="poll_option" value="${esc(opt.id)}"> ${esc(opt.text)}</label>`;
				}
				optsHtml += '</div>';
			} );
			const eng = window.ARSHID6SOCIALEng || {};
			return `<div class="arshid6social-poll" data-poll-id="${esc(poll.pollId)}" data-poll-type="${esc(poll.pollType||'single')}" data-ajax="${esc(eng.ajaxUrl||'')}" data-nonce="${esc(eng.nonce||'')}">
				<p class="arshid6social-poll-question"><strong>${esc(poll.question)}</strong></p>
				<div class="arshid6social-poll-options">${optsHtml}</div>
				<div class="arshid6social-poll-footer">
					${!poll.hasVoted && poll.status !== 'closed' ? '<button type="button" class="arshid6social-btn arshid6social-btn--primary arshid6social-btn--sm arshid6social-poll-vote-btn">Vote</button>' : ''}
					<span class="arshid6social-poll-meta">${esc(poll.totalVotes||0)} votes</span>
				</div>
			</div>`;
		}

		function renderListing( l ) {
			if ( ! l ) return '';
			const thumb = l.thumb
				? `<img src="${esc(l.thumb)}" style="width:100%;height:140px;object-fit:cover;border-radius:6px 6px 0 0;display:block" alt="">`
				: `<div style="width:100%;height:140px;background:#f1f5f9;border-radius:6px 6px 0 0"></div>`;
			return `<article style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:12px">
				<a href="${esc(l.url)}" style="text-decoration:none;color:inherit;display:block">
					${thumb}
					<div style="padding:12px">
						<div style="font-weight:600;color:#0f172a;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(l.title||'')}</div>
						<div style="font-size:.9rem;color:#2563eb;font-weight:600">${esc(l.price_formatted||'')}</div>
						<div style="font-size:.75rem;color:#94a3b8;margin-top:4px">${esc(l.date_relative||'')}</div>
					</div>
				</a>
			</article>`;
		}

		function showEmpty() {
			feed.innerHTML = '<p style="padding:1rem;color:#64748b">You have no saved posts yet.</p>';
		}

		function showError() {
			feed.innerHTML = '<p style="padding:1rem;color:#dc2626">Could not load saved posts.</p>';
		}

		function showSkeleton() {
			let s = '';
			for ( let i = 0; i < 3; i++ ) {
				s += '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px">' +
					'<div style="height:14px;background:#f1f5f9;border-radius:4px;width:40%;margin-bottom:10px"></div>' +
					'<div style="height:12px;background:#f1f5f9;border-radius:4px;width:80%;margin-bottom:6px"></div>' +
					'<div style="height:12px;background:#f1f5f9;border-radius:4px;width:60%"></div>' +
					'</div>';
			}
			feed.innerHTML = s;
		}

		async function load( append ) {
			if ( isLoading || ( append && ! hasMore ) ) return;
			isLoading = true;
			if ( ! append ) showSkeleton();

			const params = new URLSearchParams( {
				action:   'arshid6social_bookmarks_feed',
				nonce:    nonce,
				page:     page,
				per_page: 10,
			} );

			try {
				const r    = await fetch( ajaxUrl + '?' + params );
				const data = await r.json();

				if ( ! data.success ) { if ( ! append ) showError(); return; }

				const activities = data.data.activities || [];
				const listings   = data.data.listings   || [];
				if ( ! activities.length && ! listings.length && ! append ) { showEmpty(); return; }

				const html = listings.map( renderListing ).join( '' ) + activities.map( renderItem ).join( '' );
				if ( ! append ) feed.innerHTML = html;
				else            feed.insertAdjacentHTML( 'beforeend', html );

				hasMore = page < ( data.data.total_pages || 1 );

				// Let engagement modules bind to the new items.
				document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:activity:loaded', { detail: { container: feed } } ) );
			} catch ( e ) {
				if ( ! append ) showError();
			} finally {
				isLoading = false;
			}
		}

		load( false );

		if ( sentinel ) {
			new IntersectionObserver( function ( entries ) {
				if ( entries[ 0 ].isIntersecting && ! isLoading && hasMore ) {
					page++;
					load( true );
				}
			}, { rootMargin: '200px' } ).observe( sentinel );
		}
	} );
} )();
