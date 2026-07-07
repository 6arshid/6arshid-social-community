/**
 * 6Arshid Social Community \u2014 Frontend JS bundle
 * Vanilla JS, no jQuery dependency.
 * @version 1.0.5
 */

/* global ARSHID6SOCIALConfig */
( function () {
	'use strict';

	// -- Config -------------------------------------------------------------
	const cfg        = window.ARSHID6SOCIALConfig || {};
	const REST_URL   = cfg.restUrl   || '';
	const NONCE      = cfg.nonce     || '';
	const AJAX_URL   = cfg.ajaxUrl   || '';
	const AJAX_NONCE = cfg.ajaxNonce || '';
	const USER_ID        = parseInt( cfg.userId, 10 ) || 0;
	const I18N           = cfg.i18n          || {};
	const EJTEMSN_ON      = !! cfg.sixarshidscEnabled;
	const EJTEMSN_REST    = cfg.sixarshidscRestUrl      || '';
	const EJTEMSN_PUB_KEY = cfg.sixarshidscStripePubKey || '';

	// -- Utility: AJAX ------------------------------------------------------
	async function doAjax( action, data = {} ) {
		const form = new FormData();
		form.append( 'action', action );
		form.append( 'nonce', AJAX_NONCE );
		Object.entries( data ).forEach( ( [ k, v ] ) => form.append( k, v ) );
		const r = await fetch( AJAX_URL, { method: 'POST', body: form } );
		return r.json();
	}

	// -- Utility: REST fetch ------------------------------------------------
	async function apiFetch( path, options = {} ) {
		const url  = REST_URL.replace( /\/$/, '' ) + '/' + path.replace( /^\//, '' );
		const opts = Object.assign( { headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE } }, options );
		if ( opts.body && typeof opts.body === 'object' && ! ( opts.body instanceof FormData ) ) {
			opts.body = JSON.stringify( opts.body );
		}
		const response = await fetch( url, opts );
		if ( ! response.ok ) throw new Error( ( await response.json() ).message || I18N.error );
		return response.json();
	}

	// -- Utility: relative date ---------------------------------------------
	function relativeDate( dateString ) {
		const date    = new Date( dateString );
		const seconds = Math.floor( ( Date.now() - date.getTime() ) / 1000 );
		if ( seconds < 60 )   return I18N.justNow || 'just now';
		const mins = Math.floor( seconds / 60 );
		if ( mins < 60 )      return ( mins === 1 ? I18N.minuteAgo : I18N.minutesAgo || '%d minutes ago' ).replace( '%d', mins );
		const hours = Math.floor( mins / 60 );
		if ( hours < 24 )     return ( hours === 1 ? I18N.hourAgo : I18N.hoursAgo || '%d hours ago' ).replace( '%d', hours );
		const days = Math.floor( hours / 24 );
		if ( days < 30 )      return ( days === 1 ? I18N.dayAgo : I18N.daysAgo || '%d days ago' ).replace( '%d', days );
		return date.toLocaleDateString( cfg.locale || undefined );
	}

	// -- Utility: escape HTML ----------------------------------------------
	function esc( str ) {
		const el = document.createElement( 'span' );
		el.textContent = str;
		return el.innerHTML;
	}

	// -- Media attachments rendering ----------------------------------------
	function renderActivityMedia( media ) {
		if ( ! media || ! media.length ) return '';

		const items = media.map( ( m ) => {
			const dataAttrs = `data-media-id="${esc( String( m.id ) )}" data-media-url="${esc( m.fileUrl )}" data-media-name="${esc( m.fileName )}"`;
			if ( m.mediaType === 'image' ) {
				return `<img src="${esc( m.fileUrl )}" class="arshid6social-media-image" loading="lazy" alt="${esc( m.fileName )}" ${dataAttrs} />`;
			}
			if ( m.mediaType === 'video' ) {
				return `<video src="${esc( m.fileUrl )}" class="arshid6social-media-video" controls preload="metadata" ${dataAttrs}></video>`;
			}
			if ( m.mediaType === 'audio' ) {
				return `<audio src="${esc( m.fileUrl )}" class="arshid6social-media-audio" controls ${dataAttrs}></audio>`;
			}
			return `<a href="${esc( m.fileUrl )}" class="arshid6social-media-doc arshid6social-btn arshid6social-btn--secondary arshid6social-btn--sm" download target="_blank" rel="noopener noreferrer" ${dataAttrs}>&#128196; ${esc( m.fileName )}</a>`;
		} ).join( '' );

		return `<div class="arshid6social-activity-media arshid6social-media-grid-${Math.min( media.length, 4 )}">${items}</div>`;
	}

	// -- Single comment rendering -------------------------------------------
	function getReactionCount( reactions, type ) {
		if ( ! Array.isArray( reactions ) ) return 0;
		const r = reactions.find( ( x ) => x.reaction_type === type );
		return r ? parseInt( r.count, 10 ) : 0;
	}

	function renderComment( c ) {
		const date      = relativeDate( c.dateRecorded );
		const deleteBtn = c.canDelete
			? `<button class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-delete-activity" data-id="${esc( String( c.id ) )}" aria-label="${I18N.deleteActivity || 'Delete'}">&times;</button>`
			: '';

		const attachments = Array.isArray( c.attachments ) ? c.attachments : [];
		let attHtml = '';
		if ( attachments.length ) {
			const items = attachments.map( ( att ) => {
				if ( att.mediaType === 'image' ) {
					return `<div class="arshid6social-att-item"><img src="${esc( att.url )}" class="arshid6social-att-thumb" alt="${esc( att.fileName )}"></div>`;
				}
				return `<div class="arshid6social-att-item"><a href="${esc( att.url )}" class="arshid6social-att-file" target="_blank">${esc( att.fileName )}</a></div>`;
			} ).join( '' );
			attHtml = `<div class="arshid6social-att-list">${items}</div>`;
		}

		const likeCount    = getReactionCount( c.reactions, 'heart' );
		const dislikeCount = getReactionCount( c.reactions, 'thumbs_down' );
		const userReaction = c.currentUserReaction || null;

		const actionsHtml = USER_ID ? `
			<div class="arshid6social-comment-actions">
				<button class="arshid6social-comment-react${userReaction === 'heart' ? ' active' : ''}" data-id="${esc( String( c.id ) )}" data-type="heart" title="${I18N.like || 'Like'}"><span class="arshid6social-react-emoji">\u2764\uFE0F</span>${ likeCount ? '<span class="arshid6social-react-count">' + likeCount + '</span>' : '' }</button>
				<button class="arshid6social-comment-react${userReaction === 'thumbs_down' ? ' active' : ''}" data-id="${esc( String( c.id ) )}" data-type="thumbs_down" title="${I18N.dislike || 'Dislike'}"><span class="arshid6social-react-emoji">\uD83D\uDC4E</span>${ dislikeCount ? '<span class="arshid6social-react-count">' + dislikeCount + '</span>' : '' }</button>
				<button class="arshid6social-comment-reply-btn" data-comment-id="${esc( String( c.id ) )}" data-username="${esc( c.userName )}">${ I18N.reply || 'Reply' }</button>
			</div>` : '';

		return `
			<div class="arshid6social-comment-item${c.parentCommentId ? ' arshid6social-comment-reply' : ''}" id="arshid6social-activity-${c.id}" data-parent-comment="${c.parentCommentId || 0}">
				<a href="${esc( c.userProfileUrl )}" tabindex="-1">
					<img class="arshid6social-avatar arshid6social-avatar--sm" src="${esc( c.userAvatarUrl )}" alt="${esc( c.userName )}" loading="lazy" width="32" height="32" />
				</a>
				<div class="arshid6social-comment-body">
					<div class="arshid6social-comment-author">
						<a href="${esc( c.userProfileUrl )}">${esc( c.userName )}</a>
						<a href="${esc( c.permalink || '#' )}" class="arshid6social-activity-permalink-time">
							<time class="arshid6social-activity-item-time">${date}</time>
						</a>
						${deleteBtn}
					</div>
					<div class="arshid6social-comment-content">${c.content}</div>
					${attHtml}
					${actionsHtml}
				</div>
			</div>`;
	}

	// -- Emoji reaction definitions -----------------------------------------
	const REACTION_EMOJIS = [
		{ type: 'heart',      emoji: '\u2764\uFE0F'  },
		{ type: 'thumbs_up',  emoji: '\uD83D\uDC4D'  },
		{ type: 'thumbs_down',emoji: '\uD83D\uDC4E'  },
		{ type: 'haha',       emoji: '\uD83D\uDE02'  },
		{ type: 'wow',        emoji: '\uD83D\uDE2E'  },
		{ type: 'sad',        emoji: '\uD83D\uDE22'  },
		{ type: 'angry',      emoji: '\uD83D\uDE21'  },
		{ type: 'celebrate',  emoji: '\uD83C\uDF89'  },
		{ type: 'fire',       emoji: '\uD83D\uDD25'  },
		{ type: 'clap',       emoji: '\uD83D\uDC4F'  },
		{ type: 'pray',       emoji: '\uD83D\uDE4F'  },
		{ type: 'love',       emoji: '\uD83D\uDE0D'  },
		{ type: 'hundred',    emoji: '\uD83D\uDCAF'  },
		{ type: 'strong',     emoji: '\uD83D\uDCAA'  },
		{ type: 'cool',       emoji: '\uD83D\uDE0E'  },
	];

	// 'like' was the legacy type before we standardised on 'heart'.
	function normalizeType( type ) {
		return type === 'like' ? 'heart' : type;
	}

	function emojiByType( type ) {
		return REACTION_EMOJIS.find( ( r ) => r.type === normalizeType( type ) )?.emoji || '\u2764\uFE0F';
	}

	function renderReactionPills( reactions, userReaction, activityId ) {
		// Merge reactions that resolve to the same emoji (e.g. legacy 'like' + 'heart' \u2192 one \u2764\uFE0F pill).
		const groups = new Map();
		( reactions || [] ).forEach( ( r ) => {
			if ( ! r.count ) return;
			const type = normalizeType( r.reaction_type );
			const em   = emojiByType( type );
			if ( ! groups.has( type ) ) {
				groups.set( type, { emoji: em, count: 0, type, isMine: false } );
			}
			const g = groups.get( type );
			g.count += r.count;
			if ( normalizeType( userReaction ) === type ) g.isMine = true;
		} );

		return [ ...groups.values() ]
			.filter( ( g ) => g.count > 0 )
			.map( ( g ) => `<button class="arshid6social-reaction-pill${g.isMine ? ' is-mine' : ''}"
				data-activity-id="${activityId}" data-reaction="${g.type}"
				aria-pressed="${g.isMine}">
				${g.emoji} <span>${g.count}</span>
			</button>` ).join( '' );
	}

	function renderEmojiReactionArea( a ) {
		const userReaction = a.currentUserReaction || '';
		const userEmoji    = userReaction ? emojiByType( userReaction ) : '';
		const pills        = renderReactionPills( a.reactions, userReaction, a.id );
		const opts         = REACTION_EMOJIS.map( ( r ) =>
			`<button class="arshid6social-emoji-opt${userReaction === r.type ? ' is-selected' : ''}"
				data-reaction="${r.type}" aria-label="${r.type}">${r.emoji}</button>`
		).join( '' );

		// Note: picker visibility is controlled by .is-open class, NOT hidden attribute,
		// because CSS display:flex would override the hidden attribute.
		return `<div class="arshid6social-emoji-reaction-wrap" data-activity-id="${a.id}">
			<div class="arshid6social-emoji-picker" aria-hidden="true" role="dialog" aria-label="Pick a reaction">${opts}</div>
			<button class="arshid6social-emoji-trigger${userEmoji ? ' is-reacted' : ''}"
				data-activity-id="${a.id}" aria-label="React" aria-haspopup="true" aria-expanded="false">
				${userEmoji || '\uD83D\uDE42'}<span class="arshid6social-react-label">${I18N.react || 'React'}</span>
			</button>
			<div class="arshid6social-reaction-counts">${pills}</div>
		</div>`;
	}

	function renderHeartReactionArea( a ) {
		const isReacted = !! a.currentUserReaction;
		const pills     = renderReactionPills( a.reactions, a.currentUserReaction || '', a.id );
		return `<div class="arshid6social-heart-reaction-wrap" data-activity-id="${a.id}">
			<button class="arshid6social-activity-reaction-btn${isReacted ? ' is-reacted' : ''}"
				data-activity-id="${a.id}" aria-pressed="${isReacted}">
				\u2764\uFE0F
			</button>
			<div class="arshid6social-reaction-counts">${pills}</div>
		</div>`;
	}

	// -- Content truncation ("See more") ------------------------------------
	const SEE_MORE_LIMIT = 1000;

	function truncateContent( html, id ) {
		if ( ! html || html.length <= SEE_MORE_LIMIT ) return html;
		const preview = html.slice( 0, SEE_MORE_LIMIT );
		const safeId  = esc( String( id ) );
		return `<span class="arshid6social-content-preview">${preview}&hellip;</span>`
			+ `<span class="arshid6social-content-full" hidden>${html}</span>`
			+ `<button class="arshid6social-see-more" data-activity-id="${safeId}" aria-expanded="false">${ I18N.seeMore || 'See more' }</button>`;
	}

	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.arshid6social-see-more' );
		if ( ! btn ) return;
		const wrap    = btn.closest( '.arshid6social-activity-item-content' );
		const preview = wrap && wrap.querySelector( '.arshid6social-content-preview' );
		const full    = wrap && wrap.querySelector( '.arshid6social-content-full' );
		if ( ! preview || ! full ) return;
		const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		preview.hidden = ! expanded;
		full.hidden    = expanded;
		btn.setAttribute( 'aria-expanded', String( ! expanded ) );
		btn.textContent = expanded ? ( I18N.seeMore || 'See more' ) : ( I18N.seeLess || 'See less' );
	} );

	// -- Render activity item -----------------------------------------------
	const PRIVACY_ICONS = { public: '\uD83C\uDF10', friends: '\uD83D\uDC65', private: '\uD83D\uDD12', paid: '\uD83D\uDCB0' };

	function renderActivity( a ) {
		const date      = relativeDate( a.dateRecorded );
		const privacy   = a.privacy || 'public';
		const privacyBadge = `<span class="arshid6social-activity-privacy" title="${esc( privacy )}">${PRIVACY_ICONS[ privacy ] || '\uD83C\uDF10'}</span>`;
		const editBtn   = a.canEdit
			? `<button class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-edit-activity" data-id="${esc( String( a.id ) )}" aria-label="${I18N.edit || 'Edit'}" role="menuitem">\u270E ${I18N.edit || 'Edit'}</button>`
			: '';
		const deleteBtn = a.canDelete
			? `<button class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-delete-activity" data-id="${esc( String( a.id ) )}" aria-label="${I18N.deleteActivity || 'Delete'}" role="menuitem">&times; ${I18N.deleteActivity || 'Delete'}</button>`
			: '';
		const mediaHtml      = renderActivityMedia( a.media || [] );
		const allowComments  = cfg.allowComments !== false;
		const commentCount   = a.commentCount || 0;
		const reactionCount  = a.reactionCount || 0;
		const viewCount      = a.viewCount     || 0;
		// -- Engagement Pack additions --------------------------------------
		const eng        = window.ARSHID6SOCIALEng || {};
		const engEnabled = eng.enabled || {};
		const engI18n    = eng.i18n || {};

		const stickyBtn = ( engEnabled.sticky_posts && a.canEdit )
			? `<button type="button" class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm"
				data-sn-sticky-toggle data-activity-id="${a.id}"
				data-pinned="${a.isSticky ? '1' : '0'}" data-scope="profile"
				data-scope-id="${a.userId}"
				data-label-pin="${engI18n.pin || '\uD83D\uDCCC Pin'}" data-label-unpin="${engI18n.unpin || '\uD83D\uDCCC Unpin'}"
				title="${a.isSticky ? ( engI18n.unpin || 'Unpin' ) : ( engI18n.pin || 'Pin to top' )}">
				${a.isSticky ? ( engI18n.unpin || '\uD83D\uDCCC Unpin' ) : ( engI18n.pin || '\uD83D\uDCCC Pin' )}
			</button>`
			: '';

		// External social share button (opens multi-network modal).
		const extShareSettings = eng.extShare || {};
		const extSharePos      = extShareSettings.position || 'bottom';
		const extShareEnabled  = engEnabled.social_share_external && extSharePos !== 'floating';
		const extShareUrl      = esc( a.extShareUrl   || a.permalink || '' );
		const extShareTitle    = esc( ( a.extShareTitle || '' ).substring( 0, 200 ) );
		const extShareLabel    = ( extShareSettings.i18n && extShareSettings.i18n.share ) || 'Share';
		const extShareBtn      = extShareEnabled
			? `<button type="button" class="arshid6social-ext-share-btn"
				data-share-url="${extShareUrl}"
				data-share-title="${extShareTitle}"
				data-activity-id="${esc( String( a.id ) )}"
				aria-label="${esc( extShareLabel )}">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
					<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
				</svg>
				${esc( extShareLabel )}
			</button>`
			: '';

		// Above-content share strip (position === 'top').
		const extShareAbove  = ( extShareEnabled && extSharePos === 'top' )
			? `<div class="arshid6social-ext-share-above" aria-label="${esc( extShareLabel )}">${extShareBtn}</div>`
			: '';
		const extShareBottom = ( extShareEnabled && extSharePos === 'bottom' ) ? extShareBtn : '';

		const commentTextareaAttrs = [
			engEnabled.hashtags    ? 'data-sn-hashtag' : '',
			engEnabled.tag_friends ? 'data-sn-mention'  : '',
		].filter( Boolean ).join( ' ' );

		const gifBtn = ( USER_ID && engEnabled.comments_gifs )
			? `<button type="button" class="arshid6social-gif-trigger arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm"
				aria-label="${engI18n.gif || 'GIF'}" style="flex-shrink:0">GIF</button>`
			: '';

		const attBtn = ( USER_ID && engEnabled.comments_attachments )
			? `<label class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm" title="${engI18n.attach || 'Attach file'}"
				style="flex-shrink:0;cursor:pointer;display:inline-flex;align-items:center">
				&#128206;<input type="file" class="arshid6social-comment-att-input" style="display:none"
					accept="image/jpeg,image/png,image/gif,image/webp,application/pdf">
			</label>
			<div class="arshid6social-comment-att-preview arshid6social-att-list"></div>`
			: '';

		const commentForm = USER_ID
			? `<form class="arshid6social-comment-form" data-activity-id="${a.id}">
				<div class="arshid6social-comment-form-row">
					<textarea class="arshid6social-comment-input" placeholder="${I18N.writeComment || 'Write a comment\u2026'}"
						rows="1" maxlength="2000" ${commentTextareaAttrs}></textarea>
					<div class="arshid6social-comment-form-actions">
						${gifBtn}
						${attBtn}
						<button type="submit" class="arshid6social-btn arshid6social-btn--primary arshid6social-btn--sm">
							${I18N.comment || 'Comment'}
						</button>
					</div>
				</div>
			</form>`
			: '';

		const commentsSection = allowComments ? `
			<div class="arshid6social-comments-section" data-activity-id="${a.id}">
				${commentForm}
				<div class="arshid6social-comments-list"></div>
				<div class="arshid6social-comments-paginate"></div>
			</div>` : '';

		const shareCount = ( engEnabled.share_posts && a.shareCount != null ) ? ( a.shareCount || 0 ) : null;

		function fmtCount( n ) {
			if ( n >= 1000000 ) return ( n / 1000000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'M';
			if ( n >= 1000 )    return ( n / 1000 ).toFixed( 1 ).replace( /\.0$/, '' ) + 'K';
			return String( n );
		}

		// Interactive action-stats bar (replaces separate actions bar + old stats bar).
		const userReaction = a.currentUserReaction || '';
		const userEmoji    = userReaction ? emojiByType( userReaction ) : '';
		const emojiOpts    = REACTION_EMOJIS.map( r =>
			`<button class="arshid6social-emoji-opt${userReaction === r.type ? ' is-selected' : ''}" data-reaction="${r.type}" aria-label="${r.type}">${r.emoji}</button>`
		).join( '' );

		const reactionStatsItem = ( cfg.reactionStyle || 'emoji' ) === 'emoji'
			? `<div class="arshid6social-emoji-reaction-wrap arshid6social-stats-item" data-activity-id="${a.id}">
				<div class="arshid6social-emoji-picker" aria-hidden="true" role="dialog" aria-label="Pick a reaction">${emojiOpts}</div>
				<button class="arshid6social-emoji-trigger${userEmoji ? ' is-reacted' : ''}" data-activity-id="${a.id}" aria-label="${I18N.react || 'React'}" aria-haspopup="true" aria-expanded="false">
					<svg viewBox="0 0 24 24" fill="${userEmoji ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="1.75" width="18" height="18" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
					<span class="arshid6social-stats-count">${fmtCount( reactionCount )}</span>
				</button>
			</div>`
			: `<div class="arshid6social-heart-reaction-wrap arshid6social-stats-item" data-activity-id="${a.id}">
				<button class="arshid6social-activity-reaction-btn${a.currentUserReaction ? ' is-reacted' : ''}" data-activity-id="${a.id}" aria-pressed="${!! a.currentUserReaction}">
					<svg viewBox="0 0 24 24" fill="${a.currentUserReaction ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="1.75" width="18" height="18" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
					<span class="arshid6social-stats-count">${fmtCount( reactionCount )}</span>
				</button>
			</div>`;

		const shareStatsItem = engEnabled.share_posts
			? `<div class="arshid6social-share-menu arshid6social-stats-item">
				<button type="button" class="arshid6social-share-toggle" aria-label="${engI18n.share || 'Share'}" aria-haspopup="true" aria-expanded="false">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="18" height="18" aria-hidden="true"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
					<span class="arshid6social-stats-count">${shareCount !== null ? fmtCount( shareCount ) : ''}</span>
				</button>
				<div class="arshid6social-share-dropdown" role="menu">
					<button type="button" role="menuitem" data-sn-share-action="repost" data-activity-id="${a.id}">&#8635; ${engI18n.repost || 'Repost'}</button>
					<button type="button" role="menuitem" data-sn-share-action="quote" data-activity-id="${a.id}" data-quote-placeholder="${engI18n.addComment || 'Add a comment…'}">&#128172; ${engI18n.quote || 'Quote'}</button>
				</div>
			</div>`
			: '';

		const bookmarkStatsItem = engEnabled.bookmarks
			? `<button class="arshid6social-bookmark-btn arshid6social-stats-item" data-activity-id="${a.id}" aria-label="${engI18n.bookmark || 'Bookmark'}" aria-pressed="false">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
			</button>`
			: '';

		const actionStatsBar = `
			<div class="arshid6social-activity-stats-bar" data-activity-id="${a.id}">
				${ allowComments
					? `<button class="arshid6social-comments-toggle-btn arshid6social-stats-item arshid6social-stats-comments" data-activity-id="${a.id}" aria-expanded="false">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="18" height="18" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						<span class="arshid6social-stats-count arshid6social-comment-count">${fmtCount( commentCount )}</span>
					</button>`
					: `<span class="arshid6social-stats-item arshid6social-stats-comments">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="18" height="18" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						<span class="arshid6social-stats-count">${fmtCount( commentCount )}</span>
					</span>` }
				${shareStatsItem}
				${reactionStatsItem}
				${bookmarkStatsItem}
				${extShareBottom}
				<span class="arshid6social-stats-item arshid6social-stats-views">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" width="18" height="18" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
					<span class="arshid6social-stats-count">${fmtCount( viewCount )}</span>
				</span>
			</div>`;

		// --- Paid / locked card ---
		const isLocked = EJTEMSN_ON && !! a.locked;
		const lockedBlock = isLocked ? ( () => {
			const preview   = a.lockedPreview ? `<p class="sixarshidsc-locked-preview" style="margin:0 0 12px;color:var(--sn-text-muted,#6b7280);font-style:italic;">${esc( a.lockedPreview )}</p>` : '';
			const priceStr  = esc( a.ppvPriceFormatted || '' );
			const btnLabel  = USER_ID
				? `🔓 ${ I18N.unlockFor ? I18N.unlockFor.replace( '%s', priceStr ) : 'Unlock for ' + priceStr }`
				: `🔓 ${ I18N.loginToUnlock || 'Login to unlock' }`;
			const action = USER_ID
				? `<button type="button" class="arshid6social-btn arshid6social-btn--primary sixarshidsc-unlock-btn" data-activity-id="${a.id}" data-price="${esc( priceStr )}">${btnLabel}</button>`
				: `<a href="${esc( cfg.siteUrl || '/' )}login/" class="arshid6social-btn arshid6social-btn--primary">${btnLabel}</a>`;
			return `<div class="sixarshidsc-locked-content" style="border:2px dashed var(--sn-border,#e5e7eb);border-radius:10px;padding:20px 16px;text-align:center;margin:8px 0;">
				${preview}
				<div style="font-size:1.5rem;margin-bottom:8px;">💰</div>
				<p style="margin:0 0 12px;font-weight:600;">${ I18N.paidContent || 'Paid content' }</p>
				${action}
			</div>`;
		} )() : '';

		const bodyContent = isLocked
			? lockedBlock
			: ( a.poll ? renderPoll( a.poll ) : truncateContent( a.content, a.id ) );

		return `
			<article class="arshid6social-activity-item" id="arshid6social-activity-${a.id}" role="article">
				<a href="${esc( a.userProfileUrl )}" tabindex="-1">
					<img class="arshid6social-avatar arshid6social-avatar--md" src="${esc( a.userAvatarUrl )}" alt="${esc( a.userName )}" loading="lazy" width="48" height="48" />
				</a>
				<div class="arshid6social-activity-item-body">
					<div class="arshid6social-activity-item-author">
						<a href="${esc( a.userProfileUrl )}">${esc( a.userName )}</a>
						<a href="${esc( a.permalink || a.primaryLink || '#' )}" class="arshid6social-activity-permalink-time">
							<time class="arshid6social-activity-item-time">${date}</time>
						</a>
						${privacyBadge}
					</div>
					${extShareAbove}
					<div class="arshid6social-activity-item-content">${bodyContent}</div>
					${ isLocked ? '' : mediaHtml }
					${actionStatsBar}
					${ isLocked ? '' : commentsSection }
				</div>
				${ ( stickyBtn || editBtn || deleteBtn )
				? `<div class="arshid6social-more-menu">
					<button type="button" class="arshid6social-more-toggle" aria-label="${I18N.moreOptions || 'More options'}" aria-haspopup="true" aria-expanded="false">
						<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
					</button>
					<div class="arshid6social-more-dropdown" role="menu">
						${stickyBtn}
						${editBtn}
						${deleteBtn}
					</div>
				</div>`
				: '' }
			</article>`;
	}

	// -- Ads ──────────────────────────────────────────────────────────────────

	function renderAdCard( ad ) {
		const id  = ad.id;
		const cfg = window.ARSHID6SOCIALAds || {};
		let inner = '';

		if ( ad.ad_type === 'image' ) {
			const img = `<img src="${esc( ad.file_url )}" alt="${esc( ad.title )}" loading="lazy" class="arshid6social-ad-card__img">`;
			inner = ad.click_url
				? `<a href="${esc( ad.click_url )}" target="_blank" rel="noopener sponsored" class="arshid6social-ad-card__link" data-ad-id="${id}">${img}</a>`
				: img;
		} else if ( ad.ad_type === 'video' ) {
			const clickAttr = ad.click_url ? ` data-ad-click-url="${esc( ad.click_url )}" data-ad-id="${id}"` : '';
			inner = `<video controls playsinline preload="metadata" class="arshid6social-ad-card__video"${clickAttr}><source src="${esc( ad.file_url )}"></video>`;
		} else {
			inner = ad.js_code || '';
		}

		return `<div class="arshid6social-ad-card" data-ad-id="${id}">
			<div class="arshid6social-ad-card__label">Sponsored</div>
			${ ad.title ? `<div class="arshid6social-ad-card__title">${esc( ad.title )}</div>` : '' }
			<div class="arshid6social-ad-card__content">${inner}</div>
		</div>`;
	}

	/**
	 * Builds feed HTML interleaving ad cards every N posts.
	 * Returns { html, newOffset } so the caller can persist the post counter.
	 *
	 * @param {Array}  activities  Activity objects.
	 * @param {number} postOffset  How many posts have already been rendered (for ad timing).
	 * @returns {{ html: string, newOffset: number }}
	 */
	function buildFeedHtmlWithAds( activities, postOffset ) {
		const adsCfg  = window.ARSHID6SOCIALAds;
		const hasAds  = adsCfg && adsCfg.ads && adsCfg.ads.length;
		const every   = hasAds ? ( parseInt( adsCfg.everyNPosts, 10 ) || 5 ) : 0;

		if ( ! hasAds || ! every ) {
			return { html: activities.map( renderActivity ).join( '' ), newOffset: postOffset + activities.length };
		}

		let html       = '';
		let count      = postOffset;
		let adIndex    = 0;

		for ( const a of activities ) {
			html += renderActivity( a );
			count++;
			if ( count % every === 0 ) {
				html += renderAdCard( adsCfg.ads[ adIndex % adsCfg.ads.length ] );
				adIndex++;
			}
		}

		return { html, newOffset: count };
	}

	function initAdClickTracking() {
		const adsCfg = window.ARSHID6SOCIALAds;
		if ( ! adsCfg ) return;

		document.addEventListener( 'click', ( e ) => {
			const link = e.target.closest( '.arshid6social-ad-card__link[data-ad-id]' );
			if ( link ) {
				const adId = link.dataset.adId;
				fetch( `${adsCfg.ajaxUrl}?action=arshid6social_ad_click`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: `nonce=${adsCfg.nonce}&ad_id=${adId}`,
				} ).catch( () => {} );
			}
		} );

		document.addEventListener( 'click', ( e ) => {
			const video = e.target.closest( '.arshid6social-ad-card__video[data-ad-click-url]' );
			if ( video && video.paused ) {
				const adId    = video.dataset.adId;
				const clickUrl = video.dataset.adClickUrl;
				fetch( `${adsCfg.ajaxUrl}?action=arshid6social_ad_click`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: `nonce=${adsCfg.nonce}&ad_id=${adId}`,
				} ).catch( () => {} );
				window.open( clickUrl, '_blank', 'noopener' );
			}
		} );
	}

	function injectPollComposer( form ) {
		const toggleBtn = document.createElement( 'button' );
		toggleBtn.type      = 'button';
		toggleBtn.className = 'arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-poll-toggle-btn';
		toggleBtn.textContent = '\uD83D\uDCCA ' + ( ( window.ARSHID6SOCIALEng?.i18n?.addPoll ) || 'Poll' );
		toggleBtn.style.cssText = 'margin-top:6px';

		const pollBox = document.createElement( 'div' );
		pollBox.className = 'arshid6social-poll-composer';
		pollBox.style.cssText = 'display:none;margin-top:8px;padding:12px;border:1px solid var(--arshid6social-border,#e0e0e0);border-radius:8px;background:var(--arshid6social-surface,#fff)';
		const inputStyle = 'padding:8px;border:1px solid #ccc;border-radius:4px;background:#fff;color:#111;width:100%;box-sizing:border-box';
		pollBox.innerHTML = `
			<label style="display:block;font-size:13px;font-weight:600;color:inherit;margin-bottom:4px">${( window.ARSHID6SOCIALEng?.i18n?.pollQuestion ) || 'Ask a question\u2026'}</label>
			<input type="text" class="arshid6social-poll-question" placeholder="${( window.ARSHID6SOCIALEng?.i18n?.pollQuestion ) || 'Ask a question\u2026'}"
				maxlength="255" style="${inputStyle};margin-bottom:8px">
			<div class="arshid6social-poll-opts" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">
				<input type="text" class="arshid6social-poll-opt" placeholder="Option 1" maxlength="200" style="${inputStyle}">
				<input type="text" class="arshid6social-poll-opt" placeholder="Option 2" maxlength="200" style="${inputStyle}">
			</div>
			<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
				<button type="button" class="arshid6social-add-poll-opt arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm">+ Option</button>
				<select class="arshid6social-poll-type" style="padding:6px;border:1px solid #ccc;border-radius:4px;font-size:13px;background:#fff;color:#111">
					<option value="single">Single choice</option>
					<option value="multiple">Multiple choice</option>
				</select>
			</div>`;

		const addOptBtn = pollBox.querySelector( '.arshid6social-add-poll-opt' );
		const optsWrap  = pollBox.querySelector( '.arshid6social-poll-opts' );
		addOptBtn.addEventListener( 'click', function () {
			const n = optsWrap.querySelectorAll( '.arshid6social-poll-opt' ).length + 1;
			if ( n > 10 ) return;
			const inp = document.createElement( 'input' );
			inp.type        = 'text';
			inp.className   = 'arshid6social-poll-opt';
			inp.placeholder = 'Option ' + n;
			inp.maxLength   = 200;
			inp.style.cssText = 'padding:8px;border:1px solid #ccc;border-radius:4px;background:#fff;color:#111;width:100%;box-sizing:border-box';
			optsWrap.appendChild( inp );
		} );

		toggleBtn.addEventListener( 'click', function () {
			const open = pollBox.style.display !== 'none';
			pollBox.style.display = open ? 'none' : 'block';
		} );

		const submitEl = form.querySelector( '[type="submit"]' );
		if ( submitEl && submitEl.parentElement ) {
			submitEl.parentElement.insertBefore( toggleBtn, submitEl );
		} else {
			form.appendChild( toggleBtn );
		}
		form.appendChild( pollBox );

		return function () {
			if ( pollBox.style.display === 'none' ) return null;
			const question = pollBox.querySelector( '.arshid6social-poll-question' )?.value?.trim() || '';
			const options  = Array.from( pollBox.querySelectorAll( '.arshid6social-poll-opt' ) )
				.map( i => i.value.trim() ).filter( Boolean );
			const poll_type = pollBox.querySelector( '.arshid6social-poll-type' )?.value || 'single';
			if ( ! question || options.length < 2 ) return null;
			return { question, options, poll_type };
		};
	}

	function renderPoll( poll ) {
		if ( ! poll || ! poll.options ) return '';
		const typeAttr  = esc( poll.pollType || 'single' );
		const inputType = typeAttr === 'multiple' ? 'checkbox' : 'radio';
		const hasVoted  = poll.hasVoted;

		let optsHtml = '';
		( poll.options || [] ).forEach( opt => {
			const pct     = hasVoted && opt.percentage != null ? opt.percentage : 0;
			const votes   = hasVoted && opt.voteCount != null ? opt.voteCount : '';
			const cls     = opt.userVoted ? ' arshid6social-poll-voted' : '';
			optsHtml += `<div class="arshid6social-poll-option${cls}" data-option-id="${esc( String( opt.id ) )}">`;
			if ( hasVoted ) {
				optsHtml += `<span class="arshid6social-poll-option-text">${esc( opt.text )}</span>`;
				optsHtml += `<div class="arshid6social-poll-bar-wrap"><div class="arshid6social-poll-bar-track">`;
				optsHtml += `<div class="arshid6social-poll-bar-fill" style="width:${pct}%"></div>`;
				optsHtml += `</div><div class="arshid6social-poll-bar-label">${pct}% &mdash; ${esc( String( votes ) )}</div></div>`;
			} else {
				optsHtml += `<label><input type="${inputType}" name="poll_option" value="${esc( String( opt.id ) )}"> ${esc( opt.text )}</label>`;
			}
			optsHtml += '</div>';
		} );

		const voteBtn = ( ! hasVoted && poll.status !== 'closed' )
			? `<button type="button" class="arshid6social-btn arshid6social-btn--primary arshid6social-btn--sm arshid6social-poll-vote-btn">Vote</button>`
			: '';

		const total = poll.totalVotes != null ? poll.totalVotes : 0;

		const eng = window.ARSHID6SOCIALEng || {};
		return `<div class="arshid6social-poll" data-poll-id="${esc( String( poll.pollId ) )}" data-poll-type="${typeAttr}" data-ajax="${esc( eng.ajaxUrl || '' )}" data-nonce="${esc( eng.nonce || '' )}">
			<p class="arshid6social-poll-question"><strong>${esc( poll.question )}</strong></p>
			<div class="arshid6social-poll-options">${optsHtml}</div>
			<div class="arshid6social-poll-footer">${voteBtn}<span class="arshid6social-poll-meta">${esc( String( total ) )} votes</span></div>
		</div>`;
	}

	function renderSkeletons( count ) {
		return Array.from( { length: count }, () =>
			'<div class="arshid6social-activity-item"><div class="arshid6social-skeleton" style="width:48px;height:48px;border-radius:50%;flex-shrink:0;"></div><div style="flex:1;display:flex;flex-direction:column;gap:8px;"><div class="arshid6social-skeleton" style="height:14px;width:60%;"></div><div class="arshid6social-skeleton" style="height:60px;"></div></div></div>'
		).join( '' );
	}

	// -- Build media upload toolbar -----------------------------------------
	function buildMediaToolbar( form, onFilesChange ) {
		const acceptMap = {
			image:    'image/jpeg,image/png,image/gif,image/webp',
			video:    'video/mp4,video/webm,video/ogg',
			audio:    'audio/mpeg,audio/wav,audio/ogg',
			document: 'application/pdf',
		};
		const accept = ( cfg.allowedMediaTypes || [] ).map( ( t ) => acceptMap[ t ] || '' ).filter( Boolean ).join( ',' );
		const uid    = Math.random().toString( 36 ).slice( 2 );

		// Hidden file input \u2014 appended to form (not visible).
		const fileInput = document.createElement( 'input' );
		fileInput.type     = 'file';
		fileInput.id       = `arshid6social-media-${uid}`;
		fileInput.className = 'arshid6social-media-input';
		fileInput.multiple = true;
		fileInput.accept   = accept;
		fileInput.style.display = 'none';
		form.appendChild( fileInput );

		// Attach button \u2014 injected into the footer row before the submit button (same as Poll).
		const attachLabel = document.createElement( 'label' );
		attachLabel.htmlFor   = `arshid6social-media-${uid}`;
		attachLabel.className = 'arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-media-attach-btn';
		attachLabel.title     = I18N.attachMedia || 'Attach file';
		attachLabel.style.cssText = 'cursor:pointer;display:inline-flex;align-items:center;gap:4px;';
		attachLabel.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> ${I18N.attachMedia || 'Attach'}`;

		const footer   = form.querySelector( '.arshid6social-activity-form-footer' );
		const submitEl = footer ? footer.querySelector( '[type="submit"]' ) : null;
		if ( submitEl ) {
			submitEl.parentElement.insertBefore( attachLabel, submitEl );
		} else if ( footer ) {
			footer.appendChild( attachLabel );
		} else {
			form.appendChild( attachLabel );
		}

		// Preview area \u2014 inserted above the footer.
		const previewsEl = document.createElement( 'div' );
		previewsEl.className = 'arshid6social-media-previews';
		if ( footer ) form.insertBefore( previewsEl, footer );
		else form.appendChild( previewsEl );

		let localFiles = [];

		fileInput.addEventListener( 'change', () => {
			localFiles = [ ...localFiles, ...Array.from( fileInput.files ) ];
			onFilesChange( localFiles );
			renderPreviews();
			fileInput.value = '';
		} );

		function renderPreviews() {
			previewsEl.innerHTML = localFiles.map( ( f, i ) => {
				let thumb;
				if ( f.type.startsWith( 'image/' ) ) {
					thumb = `<img src="${URL.createObjectURL( f )}" class="arshid6social-media-preview-thumb" alt="${esc( f.name )}" />`;
				} else if ( f.type.startsWith( 'video/' ) ) {
					thumb = '<span class="arshid6social-media-preview-icon">&#127916;</span>';
				} else if ( f.type.startsWith( 'audio/' ) ) {
					thumb = '<span class="arshid6social-media-preview-icon">&#127925;</span>';
				} else {
					thumb = '<span class="arshid6social-media-preview-icon">&#128196;</span>';
				}
				return `<div class="arshid6social-media-preview-item" data-index="${i}">
					${thumb}
					<span class="arshid6social-media-preview-name">${esc( f.name )}</span>
					<button type="button" class="arshid6social-media-remove" aria-label="Remove">&times;</button>
				</div>`;
			} ).join( '' );

			previewsEl.querySelectorAll( '.arshid6social-media-remove' ).forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					const idx = parseInt( btn.closest( '[data-index]' ).dataset.index, 10 );
					localFiles.splice( idx, 1 );
					onFilesChange( localFiles );
					renderPreviews();
				} );
			} );
		}

		return function reset() {
			localFiles = [];
			onFilesChange( [] );
			previewsEl.innerHTML = '';
		};
	}

	// -- Comments: load (newest-first, paginated) --------------------------
	async function loadComments( activityId, section, page, append ) {
		const listEl   = section.querySelector( '.arshid6social-comments-list' );
		const paginEl  = section.querySelector( '.arshid6social-comments-paginate' );
		const paginType = cfg.paginationType || 'infinite_scroll';

		if ( ! append ) listEl.innerHTML = '<div class="arshid6social-comment-loading"></div>';

		try {
			const params = new URLSearchParams( { action: 'arshid6social_get_comments', nonce: AJAX_NONCE, activity_id: activityId, page } );
			const r      = await fetch( `${AJAX_URL}?${params}` );
			const res    = await r.json();

			if ( res.success ) {
				const { comments, total_pages, current_page } = res.data;
				const html = ( comments || [] ).map( ( c ) => {
					let h = renderComment( c );
					const replies = Array.isArray( c.replies ) ? c.replies : [];
					h += `<div class="arshid6social-comment-replies" data-parent="${esc( String( c.id ) )}">${replies.map( renderComment ).join( '' )}</div>`;
					return h;
				} ).join( '' );

				if ( ! append ) listEl.innerHTML = html;
				else listEl.insertAdjacentHTML( 'beforeend', html );

				initDeleteButtons(); initUnlockButtons();
				initEditButtons();
				initCommentReactions();
				initCommentReplies();

				// Render comment pagination.
				if ( paginEl ) {
					if ( paginType === 'pagination' ) {
						renderCommentBasicPagination( activityId, section, paginEl, total_pages, current_page );
					} else {
						// Infinite scroll: show "load older" button if more pages remain.
						if ( current_page < total_pages ) {
							const nextPage = current_page + 1;
							paginEl.innerHTML = `<button class="arshid6social-load-older-btn arshid6social-btn arshid6social-btn--secondary arshid6social-btn--sm" data-next="${nextPage}">${I18N.loadMore || 'Load older comments'}</button>`;
							paginEl.querySelector( '.arshid6social-load-older-btn' ).addEventListener( 'click', async ( e ) => {
								const btn = e.currentTarget;
								btn.disabled = true;
								await loadComments( activityId, section, parseInt( btn.dataset.next, 10 ), true );
							} );
						} else {
							paginEl.innerHTML = '';
						}
					}
				}
			} else {
				if ( ! append ) listEl.innerHTML = '';
			}
		} catch {
			if ( ! append ) listEl.innerHTML = '';
		}
	}

	function renderCommentBasicPagination( activityId, section, el, totalPages, currentPage ) {
		if ( ! el || totalPages <= 1 ) { if ( el ) el.innerHTML = ''; return; }

		const start = Math.max( 1, currentPage - 2 );
		const end   = Math.min( totalPages, currentPage + 2 );
		let html    = '';

		if ( currentPage > 1 ) html += `<button class="arshid6social-page-btn" data-page="${currentPage - 1}">&#8249;</button>`;
		if ( start > 1 ) html += `<button class="arshid6social-page-btn" data-page="1">1</button>`;
		if ( start > 2 ) html += `<span class="arshid6social-page-dots">&hellip;</span>`;
		for ( let i = start; i <= end; i++ ) {
			html += `<button class="arshid6social-page-btn ${i === currentPage ? 'is-active' : ''}" data-page="${i}">${i}</button>`;
		}
		if ( end < totalPages - 1 ) html += `<span class="arshid6social-page-dots">&hellip;</span>`;
		if ( end < totalPages ) html += `<button class="arshid6social-page-btn" data-page="${totalPages}">${totalPages}</button>`;
		if ( currentPage < totalPages ) html += `<button class="arshid6social-page-btn" data-page="${currentPage + 1}">&#8250;</button>`;

		el.innerHTML = html;

		el.querySelectorAll( '[data-page]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async () => {
				await loadComments( activityId, section, parseInt( btn.dataset.page, 10 ), false );
			} );
		} );
	}

	// -- Comments: toggle button --------------------------------------------
	function initCommentToggles() {
		document.querySelectorAll( '.arshid6social-comments-toggle-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', async () => {
				const activityId = btn.dataset.activityId;
				const section    = btn.closest( '.arshid6social-activity-item-body' )?.querySelector( '.arshid6social-comments-section' );
				if ( ! section ) return;

				const isOpen = btn.getAttribute( 'aria-expanded' ) === 'true';
				btn.setAttribute( 'aria-expanded', isOpen ? 'false' : 'true' );
				section.classList.toggle( 'is-open', ! isOpen );

				if ( ! isOpen && ! section.dataset.loaded ) {
					section.dataset.loaded = '1';
					await loadComments( activityId, section, 1, false );
					initCommentForms();
				}
			} );
		} );
	}

	// -- Comments: auto-open & scroll when URL has #arshid6social-activity-{id} anchor --
	function handleCommentAnchor() {
		const hash = window.location.hash;
		if ( ! hash.startsWith( '#arshid6social-activity-' ) ) return;
		const commentId = hash.slice( '#arshid6social-activity-'.length );
		if ( ! /^\d+$/.test( commentId ) ) return;

		const toggleBtn = document.querySelector( '.arshid6social-comments-toggle-btn' );
		if ( ! toggleBtn ) return;

		const activityId = toggleBtn.dataset.activityId;
		const section    = toggleBtn.closest( '.arshid6social-activity-item-body' )?.querySelector( '.arshid6social-comments-section' );
		if ( ! section ) return;

		const scrollToComment = () => {
			const el = document.getElementById( 'arshid6social-activity-' + commentId );
			if ( el ) {
				el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				el.classList.add( 'arshid6social-comment-highlight' );
				setTimeout( () => el.classList.remove( 'arshid6social-comment-highlight' ), 2500 );
			}
		};

		if ( toggleBtn.getAttribute( 'aria-expanded' ) === 'true' ) {
			scrollToComment();
		} else {
			toggleBtn.setAttribute( 'aria-expanded', 'true' );
			section.classList.add( 'is-open' );
			if ( ! section.dataset.loaded ) {
				section.dataset.loaded = '1';
				loadComments( activityId, section, 1, false ).then( () => {
					initCommentForms();
					setTimeout( scrollToComment, 150 );
				} );
			} else {
				scrollToComment();
			}
		}
	}

	// -- Comments: form submit ----------------------------------------------
	function initCommentForms() {
		document.querySelectorAll( '.arshid6social-comment-form:not([data-bound])' ).forEach( ( form ) => {
			form.dataset.bound = '1';

			// Attachment preview before submit.
			const fileInput = form.querySelector( '.arshid6social-comment-att-input' );
			if ( fileInput ) {
				fileInput.addEventListener( 'change', function () {
					const file    = fileInput.files[ 0 ];
					const preview = form.querySelector( '.arshid6social-comment-att-preview' );
					if ( ! file || ! preview ) return;
					preview.innerHTML = '';
					const item = document.createElement( 'div' );
					item.className = 'arshid6social-att-item';
					if ( file.type.startsWith( 'image/' ) ) {
						const url = URL.createObjectURL( file );
						item.innerHTML = `<img src="${url}" class="arshid6social-att-thumb" alt=""><button type="button" class="arshid6social-att-remove" aria-label="Remove">&times;</button>`;
					} else {
						item.innerHTML = `<span class="arshid6social-att-file">${esc( file.name )}</span><button type="button" class="arshid6social-att-remove" aria-label="Remove">&times;</button>`;
					}
					item.querySelector( '.arshid6social-att-remove' )?.addEventListener( 'click', function () {
						fileInput.value = '';
						preview.innerHTML = '';
					} );
					preview.appendChild( item );
				} );
			}

			form.addEventListener( 'submit', async ( e ) => {
				e.preventDefault();
				const activityId    = form.dataset.activityId;
				const replyToId     = form.dataset.replyToId || '';
				const textarea      = form.querySelector( '.arshid6social-comment-input' );
				const content       = textarea ? textarea.value.trim() : '';
				const gifInput      = form.querySelector( 'input[name="sn_gif_url"]' );
				const gifUrl        = gifInput ? gifInput.value.trim() : '';
				if ( ! content && ! gifUrl ) return;

				const submitBtn = form.querySelector( '[type="submit"]' );
				if ( submitBtn ) submitBtn.disabled = true;

				// Capture file before async call.
				const pendingFile = fileInput?.files?.[ 0 ] || null;

				try {
					const postData = { activity_id: activityId, content };
					if ( gifUrl ) postData.gif_url = gifUrl;
					if ( replyToId ) postData.parent_comment_id = replyToId;
					const res = await doAjax( 'arshid6social_post_comment', postData );
					if ( res.success ) {
						const section = form.closest( '.arshid6social-comments-section' );
						const listEl  = section ? section.querySelector( '.arshid6social-comments-list' ) : null;
						const comment = res.data.comment;

						if ( replyToId && listEl ) {
							// Insert reply into the parent's replies container (newest first = prepend).
							const repliesEl = listEl.querySelector( `.arshid6social-comment-replies[data-parent="${replyToId}"]` );
							if ( repliesEl ) {
								repliesEl.insertAdjacentHTML( 'afterbegin', renderComment( comment ) );
							} else {
								listEl.insertAdjacentHTML( 'afterbegin', renderComment( comment ) );
							}
						} else if ( listEl ) {
							// Top-level comment: prepend with an empty replies container.
							const commentHtml = renderComment( comment ) + `<div class="arshid6social-comment-replies" data-parent="${esc( String( comment.id ) )}"></div>`;
							listEl.insertAdjacentHTML( 'afterbegin', commentHtml );
						}

						// Clear reply state.
						if ( replyToId ) {
							delete form.dataset.replyToId;
							form.querySelector( '.arshid6social-reply-banner' )?.remove();
						}

						if ( textarea ) textarea.value = '';
						if ( fileInput ) fileInput.value = '';
						const attPreview = form.querySelector( '.arshid6social-comment-att-preview' );
						if ( attPreview ) attPreview.innerHTML = '';
						const gifPreview = form.querySelector( '.arshid6social-gif-preview' );
						if ( gifPreview ) gifPreview.remove();
						if ( gifInput ) gifInput.value = '';

						// Upload attachment separately then reload comments to show it.
						const eng = window.ARSHID6SOCIALEng;
						if ( eng && pendingFile ) {
							const commentId = comment?.id;
							if ( commentId ) {
								const fd = new FormData();
								fd.append( 'action',     'arshid6social_comment_upload_attachment' );
								fd.append( 'nonce',      eng.nonce );
								fd.append( 'comment_id', commentId );
								fd.append( 'attachment', pendingFile );
								const doReload = () => { if ( section ) loadComments( activityId, section, 1, false ); };
								fetch( eng.ajaxUrl, { method: 'POST', body: fd } )
									.then( r => r.json() )
									.then( doReload )
									.catch( doReload );
							}
						}

						// Update comment count badge in the toggle button.
						const countEl = document.querySelector( `.arshid6social-comments-toggle-btn[data-activity-id="${activityId}"] .arshid6social-comment-count` );
						if ( countEl ) countEl.textContent = parseInt( countEl.textContent || '0', 10 ) + 1;

						initDeleteButtons(); initUnlockButtons();
						initEditButtons();
						initCommentReactions();
						initCommentReplies();
					} else {
						showNotice( res.data?.message || I18N.error || 'Error posting comment.', 'error' );
					}
				} catch {
					showNotice( I18N.error || 'Error.', 'error' );
				} finally {
					if ( submitBtn ) submitBtn.disabled = false;
				}
			} );
		} );
	}

	// -- Activity: profile page (#arshid6social-activity-feed) -----------------------
	function initActivityFeed() {
		const feed = document.getElementById( 'arshid6social-activity-feed' );
		if ( ! feed ) return;

		let currentPage  = 1;
		let isLoading    = false;
		let hasMore      = true;
		let feedPostCount = 0;

		const userId = parseInt( feed.dataset.userId, 10 ) || 0;
		const scope  = feed.dataset.scope || 'site';

		loadActivity();

		const sentinel = document.getElementById( 'arshid6social-activity-sentinel' );
		if ( sentinel ) {
			new IntersectionObserver( ( entries ) => {
				if ( entries[ 0 ].isIntersecting && ! isLoading && hasMore ) {
					currentPage++;
					loadActivity( true );
				}
			}, { rootMargin: '200px' } ).observe( sentinel );
		}

		async function loadActivity( append = false ) {
			if ( isLoading ) return;
			isLoading = true;
			if ( ! append ) feed.innerHTML = renderSkeletons( 3 );

			try {
				const params = new URLSearchParams( { action: 'arshid6social_get_activity', nonce: AJAX_NONCE, page: currentPage, scope } );
				if ( userId ) params.set( 'user_id', userId );
				const res  = await fetch( `${AJAX_URL}?${params}` );
				const data = await res.json();

				if ( data.success ) {
					if ( ! append ) feedPostCount = 0;
					const { html, newOffset } = buildFeedHtmlWithAds( data.data.activities, feedPostCount );
					feedPostCount = newOffset;
					if ( ! append ) feed.innerHTML = html || `<p class="arshid6social-text-muted">${I18N.noResults || 'No activity yet.'}</p>`;
					else feed.insertAdjacentHTML( 'beforeend', html );
					hasMore = currentPage < data.data.total_pages;
					initReactionButtons();
					initEmojiPicker();
					initDeleteButtons(); initUnlockButtons();
					initEditButtons();
					initCommentReactions();
					initCommentReplies();
					initCommentToggles();
					initCommentForms();
					initViewTracking();
					document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:activity:loaded', { detail: { container: feed } } ) );
				}
			} catch {
				if ( ! append ) feed.innerHTML = `<p class="arshid6social-notice arshid6social-notice-error">${I18N.error || 'Error loading activity.'}</p>`;
			} finally {
				isLoading = false;
			}
		}
	}

	// -- Activity: shortcode / block (.arshid6social-activity-block) ----------------
	function initActivityBlocks() {
		const isHashtagPage = !! document.querySelector( '.arshid6social-activity-block[data-hashtag]' );

		document.querySelectorAll( '.arshid6social-activity-block' ).forEach( ( block ) => {
			if ( block.dataset.initialized ) return;
			// On hashtag archive pages, only initialize the hashtag feed block.
			if ( isHashtagPage && ! block.dataset.hashtag ) return;
			block.dataset.initialized = '1';

			// A block normally owns its feed. A composer-only block ([a6sc_new_activity])
			// has no feed of its own, so it links to the page's feed block instead.
			const ownFeed        = block.querySelector( '.arshid6social-activity-feed' );
			const feed           = ownFeed || document.querySelector( '.arshid6social-activity-feed' );
			const form           = block.querySelector( '.arshid6social-activity-form, form' );
			const sentinel       = block.querySelector( '.arshid6social-load-more-sentinel' );
			const paginationEl   = block.querySelector( '.arshid6social-activity-pagination' );
			let scope            = block.dataset.scope || 'site';
			const perPage        = parseInt( block.dataset.perPage, 10 ) || 10;
			const paginationType = block.dataset.paginationType || cfg.paginationType || 'infinite_scroll';
			const groupId        = parseInt( block.dataset.groupId, 10 ) || 0;
			const hashtagSlug    = block.dataset.hashtag || '';

			if ( ! feed ) return;

			let currentPage  = 1;
			let isLoading    = false;
			let hasMore      = true;
			let mediaFiles   = [];
			let feedPostCount = 0;

			// Feed tabs (All / Follow) — only on the site-wide feed for logged-in users, not on hashtag pages.
			if ( ownFeed && 'site' === scope && USER_ID && ! hashtagSlug ) {
				let activeTab = cfg.activityFeedTab || 'all';
				scope = activeTab === 'follow' ? 'follow' : 'site';

				const tabsEl = document.createElement( 'div' );
				tabsEl.className = 'arshid6social-feed-tabs';
				tabsEl.innerHTML =
					`<button class="arshid6social-feed-tab${ activeTab === 'all'    ? ' is-active' : '' }" data-tab="all">${ I18N.feedTabAll    || 'All' }</button>` +
					`<button class="arshid6social-feed-tab${ activeTab === 'follow' ? ' is-active' : '' }" data-tab="follow">${ I18N.feedTabFollow || 'Follow' }</button>`;

				feed.parentElement.insertBefore( tabsEl, feed );

				tabsEl.querySelectorAll( '.arshid6social-feed-tab' ).forEach( ( btn ) => {
					btn.addEventListener( 'click', () => {
						const tab = btn.dataset.tab;
						if ( tab === activeTab ) return;
						activeTab   = tab;
						scope       = tab === 'follow' ? 'follow' : 'site';

						tabsEl.querySelectorAll( '.arshid6social-feed-tab' ).forEach( ( b ) =>
							b.classList.toggle( 'is-active', b.dataset.tab === tab )
						);

						currentPage = 1;
						hasMore     = true;
						loadActivity();

						doAjax( 'arshid6social_save_user_setting', { setting_key: 'arshid6social_activity_feed_tab', setting_value: tab } );
					} );
				} );
			}

			// Only the feed-owning block loads & paginates the feed. A composer-only
			// block skips this and just prepends new posts into the shared feed.
			if ( ownFeed ) {
				loadActivity();

				// Infinite scroll.
				if ( paginationType === 'infinite_scroll' && sentinel ) {
					new IntersectionObserver( ( entries ) => {
						if ( entries[ 0 ].isIntersecting && ! isLoading && hasMore ) {
							currentPage++;
							loadActivity( true );
						}
					}, { rootMargin: '200px' } ).observe( sentinel );
				}
			}

			// Media upload toolbar.
			let resetMediaToolbar = null;
			if ( form && cfg.allowMedia && cfg.allowedMediaTypes && cfg.allowedMediaTypes.length ) {
				resetMediaToolbar = buildMediaToolbar( form, ( files ) => { mediaFiles = files; } );
			}

			// Show/hide PPV price row when "Paid" privacy is selected.
			if ( form && EJTEMSN_ON ) {
				const privSel  = form.querySelector( '.arshid6social-privacy-select' );
				const priceRow = form.querySelector( '.sixarshidsc-price-row' );
				if ( privSel && priceRow ) {
					const togglePriceRow = () => {
						priceRow.style.display = privSel.value === 'paid' ? 'flex' : 'none';
					};
					privSel.addEventListener( 'change', togglePriceRow );
					togglePriceRow();
				}
			}

			// Engagement: mark composer textarea for hashtag/mention autocomplete.
			if ( form && window.ARSHID6SOCIALEng ) {
				const composer = form.querySelector( '[name="content"], .arshid6social-activity-composer' );
				if ( composer ) {
					if ( window.ARSHID6SOCIALEng.enabled?.hashtags )    composer.setAttribute( 'data-sn-hashtag', '' );
					if ( window.ARSHID6SOCIALEng.enabled?.tag_friends ) composer.setAttribute( 'data-sn-mention', '' );
				}
			}

			// Engagement: poll composer UI.
			let getPollData = null;
			if ( form && window.ARSHID6SOCIALEng?.enabled?.polls ) {
				getPollData = injectPollComposer( form );
			}

			// Post form.
			if ( form ) {
				form.addEventListener( 'submit', async ( e ) => {
					e.preventDefault();
					const textarea = form.querySelector( '[name="content"], .arshid6social-activity-composer' );
					const content  = textarea ? textarea.value.trim() : '';
					const pollData = getPollData ? getPollData() : null;
					if ( ! content && ! mediaFiles.length && ! pollData ) return;

					const submitBtn = form.querySelector( '[type="submit"]' );
					if ( submitBtn ) submitBtn.disabled = true;

					try {
						const formData = new FormData();
						formData.append( 'action', 'arshid6social_post_activity' );
						formData.append( 'nonce', AJAX_NONCE );
						// When posting a poll without text, use the poll question as content
					// so the PHP non-empty check passes. The ajax_create handler overwrites
					// the content with the rendered poll HTML after the poll is saved.
					formData.append( 'content', content || ( pollData ? pollData.question : '' ) );
						const privacyEl = form.querySelector( '[name="privacy"]' );
						const chosenPrivacy = privacyEl ? privacyEl.value : 'public';
						formData.append( 'privacy', chosenPrivacy );
						if ( 'paid' === chosenPrivacy && EJTEMSN_ON ) {
							const priceInput = form.querySelector( '.sixarshidsc-ppv-price-input' );
							const priceDollars = priceInput ? parseFloat( priceInput.value ) || 0 : 0;
							formData.append( 'ppv_price', String( Math.round( priceDollars * 100 ) ) );
						}
						if ( groupId ) formData.append( 'group_id', groupId );

						if ( cfg.allowMedia ) {
							mediaFiles.forEach( ( file ) => formData.append( 'media_files[]', file, file.name ) );
						}

						const r   = await fetch( AJAX_URL, { method: 'POST', body: formData } );
						const res = await r.json();

						if ( res.success ) {
							let activityObj = res.data.activity;

							// Attach poll to the new activity, then reload its rendered HTML.
							if ( pollData ) {
								const eng = window.ARSHID6SOCIALEng;
								const pfd = new FormData();
								pfd.append( 'action',      'arshid6social_poll_create' );
								pfd.append( 'nonce',       eng.nonce );
								pfd.append( 'activity_id', String( activityObj.id ) );
								pfd.append( 'question',    pollData.question );
								pfd.append( 'poll_type',   pollData.poll_type );
								pollData.options.forEach( opt => pfd.append( 'options[]', opt ) );
								const pr   = await fetch( eng.ajaxUrl, { method: 'POST', body: pfd } );
								const pres = await pr.json();
								if ( pres.success && pres.data?.results ) {
									activityObj = Object.assign( {}, activityObj, { poll: pres.data.results } );
								} else {
									const msg = ( pres.data && pres.data.message ) ? pres.data.message : 'Poll could not be saved (unknown error).';
									showNotice( msg, 'error' );
									// eslint-disable-next-line no-console
									console.error( '[WPSN Poll] creation failed:', pres );
								}
								// Reset the poll composer form.
								const pc = form.querySelector( '.arshid6social-poll-composer' );
								if ( pc ) {
									pc.style.display = 'none';
									pc.querySelectorAll( 'input[type="text"]' ).forEach( i => { i.value = ''; } );
								}
							}

							feed.insertAdjacentHTML( 'afterbegin', renderActivity( activityObj ) );
							if ( textarea ) textarea.value = '';

							// Clear media state (localFiles + previews + outer mediaFiles array).
							if ( resetMediaToolbar ) resetMediaToolbar();
							else mediaFiles = [];

							initReactionButtons();
							initEmojiPicker();
							initDeleteButtons(); initUnlockButtons();
							initEditButtons();
							initCommentToggles();
							initCommentForms();
							initViewTracking();
							document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:activity:loaded', { detail: { container: feed } } ) );
						} else {
							showNotice( res.data?.message || I18N.error || 'Error posting.', 'error' );
						}
					} catch {
						showNotice( I18N.error || 'Error posting.', 'error' );
					} finally {
						if ( submitBtn ) submitBtn.disabled = false;
					}
				} );
			}

			async function loadActivity( append = false ) {
				if ( isLoading ) return;
				isLoading = true;
				if ( ! append ) feed.innerHTML = renderSkeletons( 3 );

				try {
					const params = new URLSearchParams( { action: 'arshid6social_get_activity', nonce: AJAX_NONCE, page: currentPage, scope, per_page: perPage } );
					if ( groupId )    params.set( 'group_id', groupId );
					if ( hashtagSlug ) params.set( 'hashtag', hashtagSlug );
					const res    = await fetch( `${AJAX_URL}?${params}` );
					const data   = await res.json();

					if ( data.success ) {
						if ( ! append ) feedPostCount = 0;
						const { html, newOffset } = buildFeedHtmlWithAds( data.data.activities, feedPostCount );
						feedPostCount = newOffset;
						if ( ! append ) {
							const emptyMsg = scope === 'follow'
								? ( I18N.noFollowActivity || 'No activity from users or hashtags you follow yet.' )
								: ( I18N.noResults || 'No activity yet.' );
							feed.innerHTML = html || `<p style="padding:1rem;color:#64748b;">${emptyMsg}</p>`;
						} else {
							feed.insertAdjacentHTML( 'beforeend', html );
						}
						hasMore = currentPage < data.data.total_pages;

						initReactionButtons();
						initEmojiPicker();
						initDeleteButtons(); initUnlockButtons();
						initEditButtons();
						initCommentToggles();
						initCommentForms();
						initViewTracking();
						document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:activity:loaded', { detail: { container: feed } } ) );

						// Basic pagination.
						if ( paginationType === 'pagination' && paginationEl ) {
							renderActivityPagination( data.data, paginationEl, ( page ) => {
								currentPage = page;
								loadActivity( false );
								feed.scrollIntoView( { behavior: 'smooth', block: 'start' } );
							} );
						}
					}
				} catch {
					if ( ! append ) feed.innerHTML = `<p style="padding:1rem;color:#dc2626;">${I18N.error || 'Error loading.'}</p>`;
				} finally {
					isLoading = false;
				}
			}
		} );
	}

	// -- Activity: basic pagination -----------------------------------------
	function renderActivityPagination( data, el, onPageChange ) {
		if ( ! el || data.total_pages <= 1 ) {
			if ( el ) el.innerHTML = '';
			return;
		}

		const current = data.current_page || 1;
		const total   = data.total_pages;
		let html      = '';

		if ( current > 1 ) {
			html += `<button class="arshid6social-page-btn" data-page="${current - 1}" aria-label="Previous">&#8249;</button>`;
		}

		const start = Math.max( 1, current - 2 );
		const end   = Math.min( total, current + 2 );

		if ( start > 1 ) html += `<button class="arshid6social-page-btn" data-page="1">1</button>`;
		if ( start > 2 ) html += `<span class="arshid6social-page-dots">&hellip;</span>`;

		for ( let i = start; i <= end; i++ ) {
			html += `<button class="arshid6social-page-btn ${i === current ? 'is-active' : ''}" data-page="${i}" ${i === current ? 'aria-current="page"' : ''}>${i}</button>`;
		}

		if ( end < total - 1 ) html += `<span class="arshid6social-page-dots">&hellip;</span>`;
		if ( end < total )     html += `<button class="arshid6social-page-btn" data-page="${total}">${total}</button>`;

		if ( current < total ) {
			html += `<button class="arshid6social-page-btn" data-page="${current + 1}" aria-label="Next">&#8250;</button>`;
		}

		el.innerHTML = html;

		el.querySelectorAll( '[data-page]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => onPageChange( parseInt( btn.dataset.page, 10 ) ) );
		} );
	}

	// -- Telegram-style burst animation ------------------------------------
	// Uses Web Animations API so each particle's translate values are passed
	// directly from JS \u2014 no CSS custom-property-in-@keyframes workaround needed.
	// cx/cy are viewport-relative pixel coords (from getBoundingClientRect before any hide).
	function playEmojiAnimation( emoji, cx, cy ) {
		const COUNT = 12;

		for ( let i = 0; i < COUNT; i++ ) {
			const fly = document.createElement( 'div' );
			fly.className   = 'arshid6social-emoji-fly';
			fly.textContent = emoji;
			fly.setAttribute( 'aria-hidden', 'true' );

			const size = 1.2 + Math.random() * 1.0; // 1.2rem \u2013 2.2rem
			fly.style.fontSize = size + 'rem';
			fly.style.left = ( cx - 20 ) + 'px';
			fly.style.top  = ( cy - 20 ) + 'px';
			document.body.appendChild( fly );

			// Full 360\u00b0 spread, evenly distributed with small random jitter.
			const baseAngle = ( 360 / COUNT ) * i + ( Math.random() * 20 - 10 );
			const angle     = baseAngle * ( Math.PI / 180 );
			const dist      = 70 + Math.random() * 80; // 70\u2013150 px
			const dx        = Math.cos( angle ) * dist;
			const dy        = Math.sin( angle ) * dist;
			const dur       = 700 + Math.random() * 400; // 700\u20131100 ms
			const delay     = i * 30;                    // stagger

			const anim = fly.animate(
				[
					{ opacity: 0,   transform: 'translate(0,0) scale(0)'   },
					{ opacity: 1,   transform: `translate(${ dx * 0.15 }px, ${ dy * 0.15 }px) scale(1.4)`, offset: 0.15 },
					{ opacity: 1,   transform: `translate(${ dx * 0.6  }px, ${ dy * 0.6  }px) scale(1.0)`, offset: 0.55 },
					{ opacity: 0,   transform: `translate(${ dx }px, ${ dy }px) scale(0.3)` },
				],
				{ duration: dur, delay: delay, easing: 'cubic-bezier(0.25,0.46,0.45,0.94)', fill: 'forwards' }
			);

			anim.onfinish = () => fly.remove();
		}
	}

	// -- View tracking (stats bar) ------------------------------------------
	function initViewTracking() {
		if ( ! cfg.statsBar ) return;
		if ( ! ( 'IntersectionObserver' in window ) ) return;

		const observer = new IntersectionObserver( ( entries ) => {
			entries.forEach( ( entry ) => {
				if ( ! entry.isIntersecting ) return;
				const bar = entry.target;
				observer.unobserve( bar );
				const id = bar.dataset.activityId;
				if ( ! id ) return;
				fetch( `${REST_URL}activity/${id}/view`, {
					method: 'POST',
					headers: { 'X-WP-Nonce': NONCE },
				} ).catch( () => {} );
			} );
		}, { threshold: 0.5 } );

		document.querySelectorAll( '.arshid6social-activity-stats-bar:not([data-view-bound])' ).forEach( ( bar ) => {
			bar.dataset.viewBound = '1';
			observer.observe( bar );
		} );
	}

	// -- Heart-only reaction ------------------------------------------------
	function initReactionButtons() {
		document.querySelectorAll( '.arshid6social-activity-reaction-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', async () => {
				if ( ! USER_ID ) { showNotice( 'Please log in to react.', 'info' ); return; }
				const wrap = btn.closest( '.arshid6social-heart-reaction-wrap' );
				const id   = wrap?.dataset.activityId || btn.dataset.activityId;
				btn.disabled = true;
				// Capture position before any UI changes.
				const r = btn.getBoundingClientRect();
				const cx = r.left + r.width  / 2;
				const cy = r.top  + r.height / 2;
				try {
					const res = await doAjax( 'arshid6social_react_activity', { activity_id: id, reaction_type: 'heart' } );
					if ( res.success ) {
						const reacted = res.data.reacted;
						btn.classList.toggle( 'is-reacted', reacted );
						btn.setAttribute( 'aria-pressed', reacted );
						if ( wrap ) updateReactionPill( wrap, id, 'heart', res.data.count, reacted );
						if ( reacted ) playEmojiAnimation( '\u2764\uFE0F', cx, cy );
					}
				} finally {
					btn.disabled = false;
				}
			} );
		} );
	}

	// -- Emoji picker reactions ---------------------------------------------
	function getEmojiBackdrop() {
		let bd = document.getElementById( 'arshid6social-emoji-backdrop' );
		if ( ! bd ) {
			bd = document.createElement( 'div' );
			bd.id = 'arshid6social-emoji-backdrop';
			bd.className = 'arshid6social-share-backdrop';
			bd.addEventListener( 'click', closeAllPickers );
			document.body.appendChild( bd );
		}
		return bd;
	}

	function pickerTeleportOpen( picker, trigger ) {
		if ( window.innerWidth > 700 ) return;
		picker._origin  = picker.parentNode;
		picker._next    = picker.nextSibling;
		picker._origCss = picker.style.cssText;
		document.body.appendChild( picker );
		picker.style.cssText = ( picker._origCss || '' ) + ';position:fixed;left:0;right:0;bottom:0;top:auto;width:100%;max-width:unset;border-radius:14px 14px 0 0;flex-wrap:wrap;justify-content:center;padding:16px 8px;z-index:99999;';
		getEmojiBackdrop().classList.add( 'open' );
	}

	function pickerTeleportClose( picker ) {
		if ( picker._origin ) {
			picker._origin.insertBefore( picker, picker._next || null );
			picker.style.cssText = picker._origCss || '';
			picker._origin  = null;
			picker._next    = null;
			picker._origCss = null;
		}
		const bd = document.getElementById( 'arshid6social-emoji-backdrop' );
		if ( bd ) bd.classList.remove( 'open' );
	}

	function closeAllPickers() {
		document.querySelectorAll( '.arshid6social-emoji-picker.is-open' ).forEach( ( p ) => {
			const wrap = p._origin || p.closest( '.arshid6social-emoji-reaction-wrap' );
			pickerTeleportClose( p );
			p.classList.remove( 'is-open' );
			p.setAttribute( 'aria-hidden', 'true' );
			wrap?.querySelector( '.arshid6social-emoji-trigger' )?.setAttribute( 'aria-expanded', 'false' );
		} );
	}

	function initEmojiPicker() {
		// Open / close picker on trigger click
		document.querySelectorAll( '.arshid6social-emoji-trigger:not([data-bound])' ).forEach( ( trigger ) => {
			trigger.dataset.bound = '1';
			trigger.addEventListener( 'click', ( e ) => {
				e.stopPropagation();
				if ( ! USER_ID ) { showNotice( 'Please log in to react.', 'info' ); return; }
				const wrap   = trigger.closest( '.arshid6social-emoji-reaction-wrap' );
				const picker = wrap?.querySelector( '.arshid6social-emoji-picker' );
				if ( ! picker ) return;

				const isOpen = picker.classList.contains( 'is-open' );
				closeAllPickers();
				if ( ! isOpen ) {
					picker.classList.add( 'is-open' );
					picker.setAttribute( 'aria-hidden', 'false' );
					trigger.setAttribute( 'aria-expanded', 'true' );
					pickerTeleportOpen( picker, trigger );
					if ( window.innerWidth > 700 ) {
					// Desktop: reposition so the picker stays within the viewport on all sides.
					picker.style.insetInlineStart = '';
					picker.style.insetInlineEnd   = '';
					picker.style.left             = '';
					picker.style.right            = '';
					const pRect = picker.getBoundingClientRect();
					// Right overflow → right-align within wrap.
					if ( pRect.right > window.innerWidth - 8 ) {
						picker.style.insetInlineStart = 'auto';
						picker.style.insetInlineEnd   = '0';
					}
					// Left overflow → pin to 8px from left edge of viewport.
					const pRect2 = picker.getBoundingClientRect();
					if ( pRect2.left < 8 ) {
						const wrapLeft = wrap.getBoundingClientRect().left;
						picker.style.insetInlineStart = '';
						picker.style.insetInlineEnd   = '';
						picker.style.left  = Math.max( 0, 8 - wrapLeft ) + 'px';
						picker.style.right = 'auto';
					}
					}
				}
			} );
		} );

		// Emoji option click \u2014 react and animate
		document.querySelectorAll( '.arshid6social-emoji-opt:not([data-bound])' ).forEach( ( opt ) => {
			opt.dataset.bound = '1';
			opt.addEventListener( 'click', async ( e ) => {
				e.stopPropagation();
				// picker may be teleported to body \u2014 use _origin to find wrap.
				const pickerEl = opt.closest( '.arshid6social-emoji-picker' );
				const wrap    = pickerEl?._origin || opt.closest( '.arshid6social-emoji-reaction-wrap' );
				const picker  = pickerEl || wrap?.querySelector( '.arshid6social-emoji-picker' );
				const trigger = wrap?.querySelector( '.arshid6social-emoji-trigger' );
				const actId   = wrap?.dataset.activityId;
				const type    = opt.dataset.reaction;
				if ( ! actId ) return;

				const prevReaction = wrap.querySelector( '.arshid6social-emoji-opt.is-selected' )?.dataset.reaction || '';
				const emoji        = emojiByType( type ); // textContent is empty after Twemoji converts emoji to <img>

				// 1. Capture position of the clicked emoji (picker still open → coords valid).
				const optR   = opt.getBoundingClientRect();
				const animCx = optR.left + optR.width  / 2;
				const animCy = optR.top  + optR.height / 2;

				// 2. Play animation immediately — before closing picker.
				playEmojiAnimation( emoji, animCx, animCy );

				// 3. Close picker.
				if ( picker ) {
					pickerTeleportClose( picker );
					picker.classList.remove( 'is-open' );
					picker.setAttribute( 'aria-hidden', 'true' );
				}
				trigger?.setAttribute( 'aria-expanded', 'false' );

				opt.disabled = true;
				try {
					const res = await doAjax( 'arshid6social_react_activity', { activity_id: actId, reaction_type: type } );
					if ( ! res.success ) {
						showNotice( res.data?.message || I18N.error || 'Error.', 'error' );
						return;
					}

					const reacted = res.data.reacted;

					if ( trigger ) {
						trigger.classList.toggle( 'is-reacted', reacted );
						const face = reacted ? emoji : '\uD83D\uDE42';
						trigger.innerHTML = `${face}<span class="arshid6social-react-label">${I18N.react || 'React'}</span>`;
					}

					wrap.querySelectorAll( '.arshid6social-emoji-opt' ).forEach( ( o ) => o.classList.remove( 'is-selected' ) );
					if ( reacted ) opt.classList.add( 'is-selected' );

					// If the user switched from a previous reaction, decrement that pill.
					if ( prevReaction && prevReaction !== type ) {
						decrementReactionPill( wrap, prevReaction );
					}

					updateReactionPill( wrap, actId, type, res.data.count, reacted );
				} finally {
					opt.disabled = false;
				}
			} );
		} );

		// Reaction pill click \u2014 unreact by clicking your own pill
		document.querySelectorAll( '.arshid6social-reaction-pill:not([data-bound])' ).forEach( ( pill ) => {
			pill.dataset.bound = '1';
			pill.addEventListener( 'click', async () => {
				if ( ! USER_ID ) { showNotice( 'Please log in to react.', 'info' ); return; }
				const actId = pill.dataset.activityId;
				const type  = pill.dataset.reaction;
				if ( ! actId ) return;
				pill.disabled = true;
				try {
					const res = await doAjax( 'arshid6social_react_activity', { activity_id: actId, reaction_type: type } );
					if ( ! res.success ) return;
					const wrap    = pill.closest( '.arshid6social-emoji-reaction-wrap' );
					const trigger = wrap?.querySelector( '.arshid6social-emoji-trigger' );
					const reacted = res.data.reacted;
					if ( trigger ) {
						trigger.classList.toggle( 'is-reacted', reacted );
						const face = reacted ? emojiByType( type ) : '\uD83D\uDE42';
						trigger.innerHTML = `${face}<span class="arshid6social-react-label">${I18N.react || 'React'}</span>`;
					}
					wrap?.querySelectorAll( '.arshid6social-emoji-opt' ).forEach( ( o ) => o.classList.remove( 'is-selected' ) );
					if ( reacted ) wrap?.querySelector( `.arshid6social-emoji-opt[data-reaction="${type}"]` )?.classList.add( 'is-selected' );
					updateReactionPill( wrap, actId, type, res.data.count, reacted );
				} finally {
					pill.disabled = false;
				}
			} );
		} );

	}

	// Register the outside-click handler exactly once (not inside initEmojiPicker
	// which is called multiple times after each AJAX render).
	document.addEventListener( 'click', closeAllPickers );

	// Decrements an old pill by 1 (when user switches to a different reaction).
	function decrementReactionPill( wrap, type ) {
		const t    = normalizeType( type );
		const pill = wrap.querySelector( `.arshid6social-reaction-counts [data-reaction="${t}"]` )
				  || wrap.querySelector( `.arshid6social-reaction-counts [data-reaction="${type}"]` );
		if ( ! pill ) return;
		const span = pill.querySelector( 'span' );
		const n    = parseInt( span?.textContent || '1', 10 ) - 1;
		if ( n <= 0 ) {
			pill.remove();
		} else {
			if ( span ) span.textContent = n;
			pill.classList.remove( 'is-mine' );
			pill.setAttribute( 'aria-pressed', 'false' );
		}
	}

	// Updates (or creates / removes) a single reaction pill after a react/unreact.
	function updateReactionPill( wrap, actId, type, count, reacted ) {
		const countsEl = wrap.querySelector( '.arshid6social-reaction-counts' );
		if ( ! countsEl ) return;

		const t    = normalizeType( type );
		// Find existing pill by normalised type OR original type (legacy 'like' pills).
		let pill = countsEl.querySelector( `[data-reaction="${t}"]` )
				|| countsEl.querySelector( `[data-reaction="${type}"]` );

		if ( count <= 0 ) {
			pill?.remove();
			return;
		}

		if ( ! pill ) {
			pill = document.createElement( 'button' );
			pill.className = 'arshid6social-reaction-pill';
			pill.dataset.activityId = actId;
			pill.dataset.reaction   = t;
			pill.setAttribute( 'aria-pressed', 'false' );
			countsEl.appendChild( pill );
			initEmojiPicker();
		}

		pill.innerHTML = `${emojiByType( t )} <span>${count}</span>`;
		pill.classList.toggle( 'is-mine', reacted );
		pill.setAttribute( 'aria-pressed', String( reacted ) );
	}

	// -- Bio inline edit (profile page About card) --------------------------
	function initBioEdit() {
		const editBtn    = document.getElementById( 'arshid6social-bio-edit-btn' );
		const display    = document.getElementById( 'arshid6social-bio-display' );
		const editForm   = document.getElementById( 'arshid6social-bio-edit-form' );
		const textarea   = document.getElementById( 'arshid6social-bio-textarea' );
		const saveBtn    = document.getElementById( 'arshid6social-bio-save-btn' );
		const cancelBtn  = document.getElementById( 'arshid6social-bio-cancel-btn' );
		if ( ! editBtn || ! display || ! editForm || ! textarea ) return;

		editBtn.addEventListener( 'click', () => {
			display.hidden  = true;
			editForm.hidden = false;
			textarea.focus();
		} );

		cancelBtn.addEventListener( 'click', () => {
			display.hidden  = false;
			editForm.hidden = true;
		} );

		saveBtn.addEventListener( 'click', async () => {
			saveBtn.disabled = true;
			try {
				const res = await doAjax( 'arshid6social_save_bio', { bio: textarea.value } );
				if ( res.success ) {
					const bio = res.data.bio;
					display.innerHTML = bio
						? `<p>${bio}</p>`
						: `<p class="arshid6social-text-muted">${I18N.noBioYet || 'No bio yet.'}</p>`;
					display.hidden  = false;
					editForm.hidden = true;
				} else {
					showNotice( res.data?.message || I18N.error || 'Error saving.', 'error' );
				}
			} finally {
				saveBtn.disabled = false;
			}
		} );
	}

	// -- Bio form on settings page ------------------------------------------
	function initBioSettingsForm() {
		const form    = document.getElementById( 'arshid6social-bio-settings-form' );
		if ( ! form ) return;
		const savedEl = form.querySelector( '.arshid6social-bio-settings-saved-msg' );
		const btn     = form.querySelector( '#arshid6social-bio-settings-save-btn' );

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			if ( btn ) btn.disabled = true;
			try {
				const bio = form.querySelector( '#arshid6social-bio-settings-textarea' )?.value || '';
				const res = await doAjax( 'arshid6social_save_bio', { bio } );
				if ( res.success ) {
					if ( savedEl ) { savedEl.hidden = false; setTimeout( () => { savedEl.hidden = true; }, 3000 ); }
				} else {
					showNotice( res.data?.message || I18N.error || 'Error saving.', 'error' );
				}
			} finally {
				if ( btn ) btn.disabled = false;
			}
		} );
	}

	// -- Notification prefs form on settings tab ----------------------------
	// initNotificationsPage() scopes to #arshid6social-notifications-page so the same
	// form rendered inside the settings tab needs its own handler.
	function initSettingsNotifPrefsForm() {
		if ( document.getElementById( 'arshid6social-notifications-page' ) ) return; // handled by initNotificationsPage
		const form = document.getElementById( 'arshid6social-notif-prefs-form' );
		if ( ! form ) return;

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const saveBtn  = form.querySelector( '#arshid6social-notif-prefs-save-btn' );
			const savedMsg = form.querySelector( '.arshid6social-notif-prefs-saved-msg' );
			if ( saveBtn ) saveBtn.disabled = true;
			const formData = new FormData( form );
			const data = {};
			form.querySelectorAll( 'input[type=checkbox]' ).forEach( ( cb ) => {
				if ( ! cb.checked ) delete data[ cb.name ];
			} );
			formData.forEach( ( v, k ) => { data[ k ] = v; } );
			try {
				const res = await doAjax( 'arshid6social_save_notification_prefs', data );
				if ( res.success && savedMsg ) {
					savedMsg.hidden = false;
					setTimeout( () => { savedMsg.hidden = true; }, 3000 );
				} else if ( ! res.success ) {
					showNotice( res.data?.message || I18N.error || 'Error saving.', 'error' );
				}
			} finally {
				if ( saveBtn ) saveBtn.disabled = false;
			}
		} );
	}

	// -- Change display name form on settings page --------------------------
	function initChangeNameForm() {
		const form    = document.getElementById( 'arshid6social-change-name-form' );
		if ( ! form ) return;
		const btn     = document.getElementById( 'arshid6social-change-name-btn' );
		const input   = document.getElementById( 'arshid6social-display-name-input' );
		const savedEl = form.querySelector( '.arshid6social-change-name-saved-msg' );

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const display_name = ( input ? input.value.trim() : '' );
			if ( ! display_name ) return;
			if ( btn ) btn.disabled = true;
			try {
				const res = await doAjax( 'arshid6social_save_display_name', { display_name } );
				if ( res.success ) {
					if ( input ) input.value = display_name;
					// Update the name shown in the profile header on this page.
					const profileName = document.querySelector( '.arshid6social-profile-name' );
					if ( profileName ) {
						const firstText = profileName.childNodes[ 0 ];
						if ( firstText && firstText.nodeType === Node.TEXT_NODE ) {
							firstText.textContent = display_name + ' ';
						} else {
							profileName.prepend( document.createTextNode( display_name + ' ' ) );
						}
					}
					if ( savedEl ) { savedEl.hidden = false; setTimeout( () => { savedEl.hidden = true; }, 3000 ); }
				} else {
					showNotice( res.data?.message || I18N.error || 'Error saving.', 'error' );
				}
			} finally {
				if ( btn ) btn.disabled = false;
			}
		} );
	}

	// -- Friends list privacy form on settings page -------------------------
	function initFriendsPrivacyForm() {
		const form = document.getElementById( 'arshid6social-friends-privacy-form' );
		if ( ! form ) return;
		const btn     = document.getElementById( 'arshid6social-friends-privacy-save-btn' );
		const savedEl = form.querySelector( '.arshid6social-friends-privacy-saved-msg' );

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const friends_privacy = form.querySelector( 'input[name="friends_privacy"]:checked' )?.value;
			if ( ! friends_privacy ) return;
			if ( btn ) btn.disabled = true;
			try {
				const res = await doAjax( 'arshid6social_save_friends_privacy', { friends_privacy } );
				if ( res.success ) {
					if ( savedEl ) { savedEl.hidden = false; setTimeout( () => { savedEl.hidden = true; }, 3000 ); }
				} else {
					showNotice( res.data?.message || I18N.error || 'Error saving.', 'error' );
				}
			} finally {
				if ( btn ) btn.disabled = false;
			}
		} );
	}

	// -- Change username form on settings page ------------------------------
	function initChangeUsernameForm() {
		const form    = document.getElementById( 'arshid6social-change-username-form' );
		if ( ! form ) return;

		const input   = document.getElementById( 'arshid6social-new-username' );
		const btn     = document.getElementById( 'arshid6social-change-username-btn' );
		const iconEl  = document.getElementById( 'arshid6social-username-check-icon' );
		const msgEl   = document.getElementById( 'arshid6social-username-check-msg' );
		const savedEl = form.querySelector( '.arshid6social-change-username-saved-msg' );

		const originalUsername = ( input ? input.defaultValue : '' ).trim();
		let debounceTimer = null;
		let checkState    = 'idle'; // idle | checking | available | taken | same

		function setStatus( state, message ) {
			checkState = state;
			const colorMap = { available: '#16a34a', taken: '#dc2626', error: '#dc2626', checking: '#64748b', same: '#64748b', idle: '#64748b' };
			const iconMap  = { available: '✓', taken: '✗', error: '✗', checking: '…', same: '–', idle: '' };
			if ( iconEl ) { iconEl.textContent = iconMap[ state ] || ''; iconEl.style.color = colorMap[ state ] || ''; }
			if ( msgEl  ) { msgEl.textContent  = message || ''; msgEl.style.color = colorMap[ state ] || ''; }
			if ( btn )    { btn.disabled = state !== 'available'; }
		}

		async function checkUsername( username ) {
			if ( ! username ) { setStatus( 'idle', '' ); return; }
			if ( username.length < 3 ) { setStatus( 'idle', 'At least 3 characters.' ); return; }

			setStatus( 'checking', 'Checking…' );
			try {
				const res = await doAjax( 'arshid6social_check_username_change', { username } );
				if ( res.success ) {
					if ( res.data && res.data.is_current ) {
						setStatus( 'same', res.data.message || 'That is your current username.' );
					} else {
						setStatus( 'available', ( res.data && res.data.message ) || 'Available!' );
					}
				} else {
					setStatus( 'taken', ( res.data && res.data.message ) || 'Username taken.' );
				}
			} catch ( err ) {
				setStatus( 'taken', 'Error checking username.' );
			}
		}

		if ( input ) {
			input.addEventListener( 'input', function () {
				clearTimeout( debounceTimer );
				const val = this.value.trim();
				setStatus( 'idle', '' );
				debounceTimer = setTimeout( function () { checkUsername( val ); }, 500 );
			} );
		}

		form.addEventListener( 'submit', async function ( e ) {
			e.preventDefault();
			if ( checkState !== 'available' ) {
				if ( checkState === 'idle' && input ) {
					checkUsername( input.value.trim() );
				}
				return;
			}

			const newUsername = input ? input.value.trim() : '';
			if ( ! newUsername || newUsername === originalUsername ) return;

			if ( btn ) btn.disabled = true;
			try {
				const res = await doAjax( 'arshid6social_change_username', { new_username: newUsername } );
				if ( res.success ) {
					if ( savedEl ) { savedEl.hidden = false; }
					if ( input ) { input.defaultValue = newUsername; }
					setTimeout( function () {
						if ( res.data && res.data.new_url ) {
							window.location.href = res.data.new_url;
						}
					}, 1200 );
				} else {
					const msg = ( res.data && res.data.message ) || 'Error saving username.';
					setStatus( 'taken', msg );
					showNotice( msg, 'error' );
				}
			} catch ( err ) {
				showNotice( 'Error saving username.', 'error' );
				if ( btn ) btn.disabled = false;
			}
		} );
	}

	// -- Change password form on settings page ------------------------------
	function initChangePasswordForm() {
		const form = document.getElementById( 'arshid6social-change-password-form' );
		if ( ! form ) return;

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const btn     = form.querySelector( '#arshid6social-change-password-btn' );
			const savedEl = form.querySelector( '.arshid6social-change-password-saved-msg' );
			const current = form.querySelector( '#arshid6social-current-password' )?.value || '';
			const newPw   = form.querySelector( '#arshid6social-new-password' )?.value || '';
			const confirm = form.querySelector( '#arshid6social-confirm-password' )?.value || '';

			if ( newPw !== confirm ) {
				showNotice( I18N.passwordMismatch || 'New passwords do not match.', 'error' );
				return;
			}

			if ( btn ) btn.disabled = true;
			try {
				const res = await doAjax( 'arshid6social_change_password', {
					current_password: current,
					new_password:     newPw,
					confirm_password: confirm,
				} );
				if ( res.success ) {
					form.reset();
					if ( savedEl ) { savedEl.hidden = false; setTimeout( () => { savedEl.hidden = true; }, 3000 ); }
				} else {
					showNotice( res.data?.message || I18N.error || 'Error.', 'error' );
				}
			} finally {
				if ( btn ) btn.disabled = false;
			}
		} );
	}

	// -- User settings form -------------------------------------------------
	function initUserSettingsForm() {
		const form = document.getElementById( 'arshid6social-user-settings-form' );
		if ( ! form ) return;

		// Radio labels get is-selected class on change
		form.querySelectorAll( 'input[type="radio"]' ).forEach( ( radio ) => {
			radio.addEventListener( 'change', () => {
				form.querySelectorAll( '.arshid6social-radio-option' ).forEach( ( label ) =>
					label.classList.toggle( 'is-selected', label.querySelector( 'input' )?.checked )
				);
			} );
		} );

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const btn      = form.querySelector( '#arshid6social-settings-save-btn' );
			const savedEl  = form.querySelector( '.arshid6social-settings-saved-msg' );
			const style    = form.querySelector( 'input[name="arshid6social_reaction_style"]:checked' )?.value;
			const feedTab  = form.querySelector( 'input[name="arshid6social_activity_feed_tab"]:checked' )?.value;
			if ( ! style ) return;

			if ( btn ) btn.disabled = true;
			try {
				const saves = [
					doAjax( 'arshid6social_save_user_setting', { setting_key: 'arshid6social_reaction_style', setting_value: style } ),
				];
				if ( feedTab ) {
					saves.push( doAjax( 'arshid6social_save_user_setting', { setting_key: 'arshid6social_activity_feed_tab', setting_value: feedTab } ) );
				}
				const results = await Promise.all( saves );
				const failed  = results.find( ( r ) => ! r.success );
				if ( ! failed ) {
					if ( savedEl ) { savedEl.hidden = false; setTimeout( () => { savedEl.hidden = true; }, 3000 ); }
					cfg.reactionStyle = style;
					if ( feedTab ) cfg.activityFeedTab = feedTab;
				} else {
					showNotice( failed.data?.message || 'Error saving.', 'error' );
				}
			} finally {
				if ( btn ) btn.disabled = false;
			}
		} );
	}

	// -- Delete activity / comment ------------------------------------------
	function initDeleteButtons() {
		document.querySelectorAll( '.arshid6social-delete-activity:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', async () => {
				if ( ! confirm( I18N.confirm || 'Are you sure?' ) ) return;
				const id = btn.dataset.id;
				try {
					const res = await doAjax( 'arshid6social_delete_activity', { activity_id: id } );
					if ( res.success ) document.getElementById( `arshid6social-activity-${id}` )?.remove();
				} catch { showNotice( I18N.error || 'Error.', 'error' ); }
			} );
		} );
	}

	// ── Paid content: Stripe payment flow ─────────────────────────────────────

	let sixarshidscStripeLoaded = false;

	function sixarshidscLoadStripe( pubKey, cb ) {
		if ( window.Stripe ) { cb( window.Stripe( pubKey ) ); return; }
		if ( sixarshidscStripeLoaded ) { const t = setInterval( () => { if ( window.Stripe ) { clearInterval(t); cb( window.Stripe( pubKey ) ); } }, 100 ); return; }
		sixarshidscStripeLoaded = true;
		const s = document.createElement( 'script' );
		s.src = 'https://js.stripe.com/v3/';
		s.onload = () => cb( window.Stripe( pubKey ) );
		document.head.appendChild( s );
	}

	function sixarshidscShowPaymentModal( activityId, checkoutData ) {
		const existing = document.getElementById( 'sixarshidsc-payment-modal' );
		if ( existing ) existing.remove();

		const overlay = document.createElement( 'div' );
		overlay.id = 'sixarshidsc-payment-modal';
		overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px;';
		overlay.innerHTML = `
			<div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:420px;position:relative;">
				<button id="sixarshidsc-modal-close" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:1.4rem;cursor:pointer;line-height:1;" aria-label="Close">&times;</button>
				<h3 style="margin:0 0 4px;font-size:1.1rem;">${ I18N.paidContent || 'Paid content' }</h3>
				<p style="margin:0 0 16px;color:#6b7280;font-size:.9rem;">${ I18N.unlockFor ? I18N.unlockFor.replace('%s', checkoutData.price_formatted) : 'Unlock for ' + checkoutData.price_formatted }</p>
				<div id="sixarshidsc-payment-element" style="margin-bottom:16px;"></div>
				<button id="sixarshidsc-pay-btn" class="arshid6social-btn arshid6social-btn--primary" style="width:100%;">
					${ I18N.payNow || 'Pay now' } — ${esc( checkoutData.price_formatted )}
				</button>
				<p id="sixarshidsc-pay-error" style="color:#dc2626;margin:10px 0 0;display:none;font-size:.875rem;"></p>
				<p id="sixarshidsc-pay-processing" style="color:#6b7280;margin:10px 0 0;display:none;font-size:.875rem;">${ I18N.paymentProcessing || 'Processing payment, please wait…' }</p>
			</div>`;
		document.body.appendChild( overlay );

		const closeModal = () => overlay.remove();
		document.getElementById( 'sixarshidsc-modal-close' ).addEventListener( 'click', closeModal );
		overlay.addEventListener( 'click', ( e ) => { if ( e.target === overlay ) closeModal(); } );

		sixarshidscLoadStripe( checkoutData.pub_key, ( stripe ) => {
			const elements = stripe.elements( { clientSecret: checkoutData.client_secret } );
			const payEl    = elements.create( 'payment' );
			payEl.mount( '#sixarshidsc-payment-element' );

			const payBtn      = document.getElementById( 'sixarshidsc-pay-btn' );
			const errEl       = document.getElementById( 'sixarshidsc-pay-error' );
			const procEl      = document.getElementById( 'sixarshidsc-pay-processing' );

			payBtn.addEventListener( 'click', async () => {
				payBtn.disabled = true;
				errEl.style.display = 'none';

				const { error, paymentIntent } = await stripe.confirmPayment( {
					elements,
					confirmParams: { return_url: checkoutData.return_url || location.href },
					redirect: 'if_required',
				} );

				if ( error ) {
					errEl.textContent = error.message || ( I18N.paymentFailed || 'Payment failed.' );
					errEl.style.display = '';
					payBtn.disabled = false;
					return;
				}

				// Payment confirmed client-side — call verify to grant entitlement
				// immediately (works even when Stripe webhooks can't reach the server).
				procEl.style.display = '';
				payBtn.style.display = 'none';

				const piId = paymentIntent?.id || checkoutData.payment_intent;

				try {
					const vr = await fetch( EJTEMSN_REST + 'ppv/' + activityId + '/verify', {
						method:  'POST',
						headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
						body:    JSON.stringify( { payment_intent: piId } ),
					} );
					const vd = await vr.json();
					if ( vd.entitled ) {
						const dest = checkoutData.activity_url
							|| ( location.origin + location.pathname + '?sixarshidsc_paid_activity=' + activityId );
						location.href = dest;
						return;
					}
				} catch {}

				// Fallback: poll status (e.g. webhook arrived before verify call).
				let polls = 0;
				const poll = setInterval( async () => {
					polls++;
					try {
						const r = await fetch( EJTEMSN_REST + 'ppv/' + activityId + '/status', {
							headers: { 'X-WP-Nonce': cfg.nonce },
						} );
						const d = await r.json();
						if ( d.entitled ) {
							clearInterval( poll );
							const dest = checkoutData.activity_url
								|| ( location.origin + location.pathname + '?sixarshidsc_paid_activity=' + activityId );
							location.href = dest;
							return;
						}
					} catch {}
					if ( polls >= 20 ) { clearInterval( poll ); location.reload(); }
				}, 1500 );
			} );
		} );
	}

	function initUnlockButtons() {
		document.querySelectorAll( '.sixarshidsc-unlock-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', async () => {
				if ( ! EJTEMSN_ON || ! EJTEMSN_REST ) return;
				btn.disabled = true;
				const activityId = btn.dataset.activityId;
				try {
					const r = await fetch( EJTEMSN_REST + 'ppv/' + activityId + '/checkout', {
						method: 'POST',
						headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
					} );
					const data = await r.json();
					if ( data.already_entitled ) {
						location.reload(); return;
					}
					if ( data.client_secret ) {
						sixarshidscShowPaymentModal( activityId, data );
					} else {
						showNotice( ( data.message || I18N.error || 'Error.' ), 'error' );
					}
				} catch {
					showNotice( I18N.error || 'Error.', 'error' );
				} finally {
					btn.disabled = false;
				}
			} );
		} );
	}

	// -- More menu (3-dot) toggle -------------------------------------------
	( function initMoreMenus() {
		function closeAllMoreMenus() {
			document.querySelectorAll( '.arshid6social-more-dropdown.open' ).forEach( ( d ) => {
				d.classList.remove( 'open' );
				d.closest( '.arshid6social-more-menu' )?.querySelector( '.arshid6social-more-toggle' )
					?.setAttribute( 'aria-expanded', 'false' );
			} );
		}

		document.addEventListener( 'click', ( e ) => {
			const toggle = e.target.closest( '.arshid6social-more-toggle' );
			if ( toggle ) {
				e.stopPropagation();
				const menu = toggle.closest( '.arshid6social-more-menu' );
				const drop = menu?.querySelector( '.arshid6social-more-dropdown' );
				if ( ! drop ) return;
				const wasOpen = drop.classList.contains( 'open' );
				closeAllMoreMenus();
				if ( ! wasOpen ) {
					drop.classList.add( 'open' );
					toggle.setAttribute( 'aria-expanded', 'true' );
				}
				return;
			}
			if ( e.target.closest( '.arshid6social-more-dropdown' ) ) {
				closeAllMoreMenus();
				return;
			}
			closeAllMoreMenus();
		} );
	} )();

	// -- Comment reactions (like / dislike) ---------------------------------
	function initCommentReactions() {
		document.querySelectorAll( '.arshid6social-comment-react:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', async () => {
				const id        = btn.dataset.id;
				const type      = btn.dataset.type;
				const emojiEl   = btn.querySelector( '.arshid6social-react-emoji' );
				btn.disabled    = true;

				// Trigger animation immediately on click.
				if ( emojiEl ) {
					btn.classList.remove( 'arshid6social-react-burst' );
					// Force reflow so re-adding the class restarts the animation.
					void btn.offsetWidth;
					btn.classList.add( 'arshid6social-react-burst' );
					emojiEl.addEventListener( 'animationend', () => btn.classList.remove( 'arshid6social-react-burst' ), { once: true } );
				}

				try {
					const res = await doAjax( 'arshid6social_react_activity', { activity_id: id, reaction_type: type } );
					if ( res.success ) {
						const reacted  = res.data.reacted;
						const count    = res.data.count || 0;
						const emoji    = type === 'heart' ? '\u2764\uFE0F' : '\uD83D\uDC4E';
						const countEl  = btn.querySelector( '.arshid6social-react-count' );

						// Update emoji span (preserve it for animation).
						if ( emojiEl ) emojiEl.textContent = emoji;

						// Update or toggle count span.
						if ( countEl ) {
							if ( count ) countEl.textContent = count;
							else countEl.remove();
						} else if ( count ) {
							btn.insertAdjacentHTML( 'beforeend', `<span class="arshid6social-react-count">${count}</span>` );
						}

						btn.classList.toggle( 'active', reacted );

						if ( reacted ) {
							btn.closest( '.arshid6social-comment-actions' )
								?.querySelectorAll( '.arshid6social-comment-react' )
								.forEach( ( b ) => { if ( b !== btn ) { b.classList.remove( 'active' ); b.style.background = ''; } } );
						}
					}
				} catch { /* silent */ } finally {
					btn.disabled = false;
				}
			} );
		} );
	}

	// -- Comment reply ------------------------------------------------------
	function initCommentReplies() {
		document.querySelectorAll( '.arshid6social-comment-reply-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', () => {
				const commentId = btn.dataset.commentId;
				const username  = btn.dataset.username;
				const section   = btn.closest( '.arshid6social-comments-section' );
				if ( ! section ) return;

				const form     = section.querySelector( '.arshid6social-comment-form' );
				const textarea = section.querySelector( '.arshid6social-comment-input' );
				if ( ! form || ! textarea ) return;

				// Store parent comment ID on the form.
				form.dataset.replyToId = commentId;

				// Show/update cancel-reply banner.
				let banner = form.querySelector( '.arshid6social-reply-banner' );
				if ( ! banner ) {
					banner = document.createElement( 'div' );
					banner.className = 'arshid6social-reply-banner';
					form.insertBefore( banner, form.firstChild );
				}
				banner.innerHTML = `<span>${ I18N.replyingTo || 'Replying to' } <strong>${esc( username )}</strong></span><button type="button" class="arshid6social-cancel-reply">&times;</button>`;
				banner.querySelector( '.arshid6social-cancel-reply' ).addEventListener( 'click', () => {
					delete form.dataset.replyToId;
					banner.remove();
					textarea.value = textarea.value.replace( /^@\S+\s*/, '' ).trim();
				} );

				// Pre-fill @mention.
				const mention = '@' + username + ' ';
				if ( ! textarea.value.startsWith( mention ) ) {
					textarea.value = mention + textarea.value.replace( /^@\S+\s*/, '' );
				}
				textarea.focus();
				textarea.setSelectionRange( textarea.value.length, textarea.value.length );
			} );
		} );
	}

	// -- Edit activity (inline) ---------------------------------------------
	function initEditButtons() {
		document.querySelectorAll( '.arshid6social-edit-activity:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', () => {
				const id      = btn.dataset.id;
				const article = document.getElementById( `arshid6social-activity-${id}` );
				if ( ! article || article.dataset.editing ) return;
				article.dataset.editing = '1';

				const bodyEl    = article.querySelector( '.arshid6social-activity-item-body' );
				const contentEl = bodyEl.querySelector( '.arshid6social-activity-item-content' );
				const mediaEl   = bodyEl.querySelector( '.arshid6social-activity-media' );
				const actionsEl = article.querySelector( '.arshid6social-activity-item-actions' );
				const commentsEl= bodyEl.querySelector( '.arshid6social-comments-section' );

				const currentContent = contentEl ? contentEl.innerHTML.replace( /<[^>]*>/g, '' ).trim() : '';
				const currentPrivacy = article.querySelector( '.arshid6social-activity-privacy' )?.title || 'public';

				// Collect existing media data from rendered media items.
				const existingMedia = [];
				if ( mediaEl ) {
					mediaEl.querySelectorAll( '[data-media-id]' ).forEach( ( el ) => {
						existingMedia.push( { id: el.dataset.mediaId, url: el.dataset.mediaUrl || '', name: el.dataset.mediaName || '' } );
					} );
				}

				// Build the edit form.
				const editWrap = document.createElement( 'div' );
				editWrap.className = 'arshid6social-activity-edit-form';
				editWrap.innerHTML = `
					<textarea class="arshid6social-edit-textarea arshid6social-activity-composer" rows="4" maxlength="5000">${esc( currentContent )}</textarea>
					<div class="arshid6social-edit-existing-media"></div>
					<div class="arshid6social-edit-new-media-wrap" style="margin-top:.5rem;">
						<label class="arshid6social-btn arshid6social-btn--secondary arshid6social-btn--sm" style="cursor:pointer;">
							\uD83D\uDCCE ${I18N.attachMedia || 'Add files'}
							<input type="file" class="arshid6social-edit-file-input" multiple accept="image/*,video/*,audio/*,application/pdf" style="display:none;" />
						</label>
						<div class="arshid6social-edit-new-previews"></div>
					</div>
					<div class="arshid6social-edit-footer">
						<select class="arshid6social-privacy-select arshid6social-edit-privacy">
							<option value="public" ${currentPrivacy === 'public' ? 'selected' : ''}>\uD83C\uDF10 ${I18N.privacyPublic || 'Public'}</option>
							<option value="friends" ${currentPrivacy === 'friends' ? 'selected' : ''}>\uD83D\uDC65 ${I18N.privacyFriends || 'Friends'}</option>
							<option value="private" ${currentPrivacy === 'private' ? 'selected' : ''}>\uD83D\uDD12 ${I18N.privacyPrivate || 'Only Me'}</option>
							${EJTEMSN_ON ? `<option value="paid" ${currentPrivacy === 'paid' ? 'selected' : ''}>\uD83D\uDCB0 ${I18N.privacyPaid || 'Paid'}</option>` : ''}
						</select>
						<button class="arshid6social-btn arshid6social-btn--primary arshid6social-btn--sm arshid6social-edit-save">${I18N.save || 'Save'}</button>
						<button class="arshid6social-btn arshid6social-btn--ghost arshid6social-btn--sm arshid6social-edit-cancel">${I18N.cancel || 'Cancel'}</button>
					</div>`;

				// Render existing media with delete X buttons.
				const existingMediaWrap = editWrap.querySelector( '.arshid6social-edit-existing-media' );
				let deletedMediaIds = [];
				function renderExistingMedia() {
					existingMediaWrap.innerHTML = existingMedia
						.filter( ( m ) => ! deletedMediaIds.includes( m.id ) )
						.map( ( m ) => `<div class="arshid6social-edit-media-item" data-id="${esc( String( m.id ) )}">
							<span class="arshid6social-edit-media-name">${esc( m.name )}</span>
							<button type="button" class="arshid6social-edit-media-del" data-id="${esc( String( m.id ) )}" aria-label="Remove">\u2715</button>
						</div>` ).join( '' );
					existingMediaWrap.querySelectorAll( '.arshid6social-edit-media-del' ).forEach( ( b ) => {
						b.addEventListener( 'click', () => {
							deletedMediaIds.push( b.dataset.id );
							renderExistingMedia();
						} );
					} );
				}
				renderExistingMedia();

				// New file input previews.
				let newFiles = [];
				const fileInput    = editWrap.querySelector( '.arshid6social-edit-file-input' );
				const newPreviewsEl= editWrap.querySelector( '.arshid6social-edit-new-previews' );
				fileInput.addEventListener( 'change', () => {
					newFiles = [ ...newFiles, ...Array.from( fileInput.files ) ];
					renderNewPreviews();
					fileInput.value = '';
				} );
				function renderNewPreviews() {
					newPreviewsEl.innerHTML = newFiles.map( ( f, i ) => `<div class="arshid6social-media-preview-item" data-index="${i}">
						<span class="arshid6social-media-preview-name">${esc( f.name )}</span>
						<button type="button" class="arshid6social-media-remove" aria-label="Remove">&times;</button>
					</div>` ).join( '' );
					newPreviewsEl.querySelectorAll( '.arshid6social-media-remove' ).forEach( ( b ) => {
						b.addEventListener( 'click', () => {
							newFiles.splice( parseInt( b.closest( '[data-index]' ).dataset.index, 10 ), 1 );
							renderNewPreviews();
						} );
					} );
				}

				// Hide original content, insert edit form.
				if ( contentEl ) contentEl.hidden = true;
				if ( mediaEl )   mediaEl.hidden   = true;
				if ( actionsEl ) actionsEl.hidden  = true;
				if ( commentsEl ) commentsEl.hidden = true;
				bodyEl.insertBefore( editWrap, commentsEl || null );

				// Cancel.
				editWrap.querySelector( '.arshid6social-edit-cancel' ).addEventListener( 'click', () => {
					editWrap.remove();
					if ( contentEl ) contentEl.hidden = false;
					if ( mediaEl )   mediaEl.hidden   = false;
					if ( actionsEl ) actionsEl.hidden  = false;
					if ( commentsEl ) commentsEl.hidden = false;
					delete article.dataset.editing;
				} );

				// Save.
				editWrap.querySelector( '.arshid6social-edit-save' ).addEventListener( 'click', async () => {
					const saveBtn = editWrap.querySelector( '.arshid6social-edit-save' );
					const content = editWrap.querySelector( '.arshid6social-edit-textarea' ).value.trim();
					const privacy = editWrap.querySelector( '.arshid6social-edit-privacy' ).value;
					if ( ! content ) return;

					saveBtn.disabled = true;
					try {
						const formData = new FormData();
						formData.append( 'action', 'arshid6social_edit_activity' );
						formData.append( 'nonce', AJAX_NONCE );
						formData.append( 'activity_id', id );
						formData.append( 'content', content );
						formData.append( 'privacy', privacy );
						deletedMediaIds.forEach( ( mid ) => formData.append( 'delete_media_ids[]', mid ) );
						newFiles.forEach( ( f ) => formData.append( 'media_files[]', f, f.name ) );

						const r   = await fetch( AJAX_URL, { method: 'POST', body: formData } );
						const res = await r.json();

						if ( res.success ) {
							const updated  = res.data.activity;
							const newHtml  = renderActivity( updated );
							const tempDiv  = document.createElement( 'div' );
							tempDiv.innerHTML = newHtml;
							article.replaceWith( tempDiv.firstElementChild );
							initReactionButtons();
							initEmojiPicker();
							initDeleteButtons(); initUnlockButtons();
							initEditButtons();
							initCommentToggles();
							initCommentForms();
							initViewTracking();
						} else {
							showNotice( res.data?.message || I18N.error || 'Error.', 'error' );
							saveBtn.disabled = false;
						}
					} catch {
						showNotice( I18N.error || 'Error.', 'error' );
						saveBtn.disabled = false;
					}
				} );
			} );
		} );
	}

	// -- Member directory ---------------------------------------------------
	function initMemberDirectory() {
		const dir = document.getElementById( 'arshid6social-member-directory' );
		if ( ! dir ) return;

		let page    = 1;
		let loading = false;
		let hasMore = true;

		const paginationType = dir.dataset.paginationType || cfg.membersPaginationType || 'pagination';
		const searchInput    = document.querySelector( '.arshid6social-member-search' );
		const grid           = dir.querySelector( '.arshid6social-member-grid' );
		const pagination     = dir.querySelector( '.arshid6social-pagination' );
		const sentinel       = dir.querySelector( '.arshid6social-members-load-more-sentinel' );

		let searchTimeout;

		if ( searchInput ) {
			searchInput.addEventListener( 'input', () => {
				clearTimeout( searchTimeout );
				searchTimeout = setTimeout( () => { page = 1; hasMore = true; loadMembers(); }, 350 );
			} );
		}

		const sortSelect = document.querySelector( '.arshid6social-sort-select[data-type="member"]' );
		if ( sortSelect ) {
			sortSelect.addEventListener( 'change', () => { page = 1; hasMore = true; loadMembers(); } );
		}

		loadMembers();

		if ( paginationType === 'infinite_scroll' && sentinel ) {
			new IntersectionObserver( ( entries ) => {
				if ( entries[ 0 ].isIntersecting && ! loading && hasMore ) {
					page++;
					loadMembers( true );
				}
			}, { rootMargin: '200px' } ).observe( sentinel );
		}

		async function loadMembers( append = false ) {
			if ( loading ) return;
			loading = true;
			if ( ! append && grid ) grid.innerHTML = renderMemberSkeletons( 8 );

			const search = searchInput ? searchInput.value : '';
			const type   = sortSelect ? sortSelect.value : 'newest';

			try {
				const res = await doAjax( 'arshid6social_get_members', { page, search, type } );
				if ( res.success ) {
					const cards = res.data.members.map( renderMemberCard ).join( '' );
					if ( append && grid ) {
						grid.insertAdjacentHTML( 'beforeend', cards );
					} else {
						if ( grid ) grid.innerHTML = cards || `<p class="arshid6social-text-muted">${I18N.noResults}</p>`;
					}
					hasMore = ( res.data.current_page || 1 ) < ( res.data.total_pages || 1 );
					if ( paginationType === 'infinite_scroll' ) {
						if ( pagination ) pagination.innerHTML = '';
					} else {
						renderPagination( res.data, pagination );
					}
					initFriendButtons();
				}
			} finally {
				loading = false;
			}
		}

		if ( paginationType !== 'infinite_scroll' && pagination ) {
			pagination.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '[data-page]' );
				if ( btn ) { page = parseInt( btn.dataset.page, 10 ); loadMembers(); window.scrollTo( { top: dir.offsetTop - 20, behavior: 'smooth' } ); }
			} );
		}
	}

	function renderMemberCard( m ) {
		const friendMeta = cfg.memberShowFriendCount
			? `<span class="arshid6social-member-card-meta">${esc( String( m.friendCount ) )} ${I18N.friends || 'friends'}</span>`
			: '';
		return `
			<div class="arshid6social-member-card" role="listitem">
				<div class="arshid6social-avatar-wrap">
					<img class="arshid6social-avatar arshid6social-avatar-lg" src="${esc( m.avatarUrl )}" alt="${esc( m.name )}" width="80" height="80" loading="lazy" />
					${m.isOnline ? '<span class="arshid6social-online-badge"></span>' : ''}
				</div>
				<a class="arshid6social-member-card-name" href="${esc( m.profileUrl )}">${esc( m.name )}</a>
				${friendMeta}
				${USER_ID && USER_ID !== m.id
					? `<button class="arshid6social-btn arshid6social-btn--secondary arshid6social-btn--sm arshid6social-friend-btn" data-user-id="${m.id}" data-status="${esc( m.friendshipStatus || 'not_friends' )}">${I18N.addFriend || 'Add Friend'}</button>`
					: '' }
			</div>`;
	}

	function renderMemberSkeletons( count ) {
		return Array.from( { length: count }, () =>
			'<div class="arshid6social-member-card"><div class="arshid6social-skeleton" style="width:80px;height:80px;border-radius:50%;margin:0 auto .75rem;"></div><div class="arshid6social-skeleton" style="height:14px;width:60%;margin:0 auto .5rem;"></div><div class="arshid6social-skeleton" style="height:12px;width:40%;margin:0 auto;"></div></div>'
		).join( '' );
	}

	// -- Group directory ----------------------------------------------------
	function initGroupDirectory() {
		const grid = document.getElementById( 'arshid6social-group-grid' );
		if ( ! grid ) return;

		const pagination = document.getElementById( 'arshid6social-group-pagination' );
		const search     = document.querySelector( '.arshid6social-group-search' );

		let page    = 1;
		let loading = false;
		let searchTimeout;

		if ( search ) {
			search.addEventListener( 'input', () => {
				clearTimeout( searchTimeout );
				searchTimeout = setTimeout( () => { page = 1; loadGroups(); }, 350 );
			} );
		}

		if ( grid.querySelector( '.arshid6social-skeleton-item' ) ) {
			loadGroups();
		}

		if ( pagination ) {
			pagination.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '[data-page]' );
				if ( btn ) { page = parseInt( btn.dataset.page, 10 ); loadGroups(); }
			} );
		}

		async function loadGroups() {
			if ( loading ) return;
			loading = true;

			const searchVal = search ? search.value : '';

			try {
				const res = await doAjax( 'arshid6social_get_groups', { page, search: searchVal } );
				if ( res.success ) {
					grid.innerHTML = res.data.groups.map( renderGroupCard ).join( '' ) || `<p class="arshid6social-text-muted">${I18N.noResults || 'No groups found.'}</p>`;
					renderPagination( res.data, pagination );
					initGroupJoinButtons();
				}
			} finally {
				loading = false;
			}
		}
	}

	function renderGroupCard( g ) {
		const cover      = g.coverUrl ? `style="background-image:url('${esc( g.coverUrl )}')"` : '';
		const avatarHtml = g.avatarUrl
			? `<img class="arshid6social-avatar arshid6social-avatar-md" src="${esc( g.avatarUrl )}" alt="${esc( g.name )}" width="48" height="48" loading="lazy" />`
			: `<div class="arshid6social-avatar arshid6social-avatar-md arshid6social-avatar-initial" aria-label="${esc( g.name )}">${esc( g.name.charAt( 0 ).toUpperCase() )}</div>`;
		return `
			<div class="arshid6social-group-card arshid6social-card" role="listitem">
				<div class="arshid6social-group-card-cover" ${cover}></div>
				<div class="arshid6social-group-card-body">
					${avatarHtml}
					<div class="arshid6social-group-card-info">
						<a class="arshid6social-group-card-name" href="${esc( g.url )}">${esc( g.name )}</a>
						<span class="arshid6social-group-card-meta">
							<span class="arshid6social-badge arshid6social-badge-${esc( g.status )}">${esc( g.status )}</span>
							${esc( String( g.memberCount ) )} members
						</span>
						${USER_ID && ! g.isMember
							? `<button class="arshid6social-btn arshid6social-btn-secondary arshid6social-btn-sm arshid6social-group-join-btn" data-group-id="${g.id}" data-nonce="${esc( g.joinNonce || '' )}">Join</button>`
							: '' }
					</div>
				</div>
			</div>`;
	}

	function initGroupImageUploads() {
		document.querySelectorAll( '.arshid6social-group-avatar-edit-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', () => {
				const inp   = document.createElement( 'input' );
				inp.type    = 'file';
				inp.accept  = 'image/*';
				inp.addEventListener( 'change', async () => {
					const file = inp.files[ 0 ];
					if ( ! file ) return;
					const fd = new FormData();
					fd.append( 'action',   'arshid6social_upload_group_avatar' );
					fd.append( 'nonce',    AJAX_NONCE );
					fd.append( 'group_id', btn.dataset.groupId );
					fd.append( 'file',     file );
					try {
						const r    = await fetch( AJAX_URL, { method: 'POST', body: fd } );
						const data = await r.json();
						if ( data.success ) {
							const wrap    = btn.closest( '.arshid6social-group-avatar-wrap' );
							const current = wrap?.querySelector( '.arshid6social-group-avatar' );
							if ( current ) {
								const img   = document.createElement( 'img' );
								img.src     = data.data.url;
								img.alt     = current.getAttribute( 'aria-label' ) || current.alt || '';
								img.className = 'arshid6social-avatar arshid6social-avatar--lg arshid6social-group-avatar';
								img.id      = current.id || '';
								img.width   = 80;
								img.height  = 80;
								current.replaceWith( img );
							}
							showNotice( 'Group photo updated!', 'success' );
						} else {
							showNotice( data.data?.message || I18N.error || 'Upload failed.', 'error' );
						}
					} catch { showNotice( I18N.error || 'Upload failed.', 'error' ); }
				} );
				inp.click();
			} );
		} );

		document.querySelectorAll( '.arshid6social-cover-edit-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', () => {
				const inp   = document.createElement( 'input' );
				inp.type    = 'file';
				inp.accept  = 'image/*';
				inp.addEventListener( 'change', async () => {
					const file = inp.files[ 0 ];
					if ( ! file ) return;
					const fd = new FormData();
					fd.append( 'action',   'arshid6social_upload_group_cover' );
					fd.append( 'nonce',    AJAX_NONCE );
					fd.append( 'group_id', btn.dataset.groupId );
					fd.append( 'file',     file );
					try {
						const r    = await fetch( AJAX_URL, { method: 'POST', body: fd } );
						const data = await r.json();
						if ( data.success ) {
							const cover = btn.closest( '.arshid6social-profile-cover' );
							if ( cover ) cover.style.backgroundImage = `url('${data.data.url}')`;
							showNotice( 'Cover updated!', 'success' );
						} else {
							showNotice( data.data?.message || I18N.error || 'Upload failed.', 'error' );
						}
					} catch { showNotice( I18N.error || 'Upload failed.', 'error' ); }
				} );
				inp.click();
			} );
		} );
	}

	function initGroupJoinButtons() {
		document.querySelectorAll( '.arshid6social-group-join-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';
			btn.addEventListener( 'click', async () => {
				const groupId = btn.dataset.groupId;
				btn.disabled  = true;
				try {
					const res = await doAjax( 'arshid6social_join_group', { group_id: groupId } );
					if ( res.success ) btn.textContent = 'Joined \u2713';
					else showNotice( res.data?.message || I18N.error || 'Error.', 'error' );
				} finally {
					btn.disabled = false;
				}
			} );
		} );
	}

	// -- Friend buttons -----------------------------------------------------
	const WTF_ICONS = {
		not_friends:      '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
		pending_sent:     '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
		pending_received: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
		friends:          '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
	};

	function applyWtfBtnColors( btn, status ) {
		const primary = btn.dataset.primaryColor || '';
		const active  = [ 'not_friends', 'pending_received' ].includes( status );
		if ( active && primary ) {
			btn.style.background   = primary;
			btn.style.borderColor  = primary;
			btn.style.color        = '#ffffff';
		} else {
			btn.style.background   = 'transparent';
			btn.style.borderColor  = 'var(--a6sc-border)';
			btn.style.color        = 'var(--a6sc-text-muted)';
		}
	}

	function initFriendButtons() {
		document.querySelectorAll( '.arshid6social-friend-btn:not([data-bound])' ).forEach( ( btn ) => {
			btn.dataset.bound = '1';

			if ( btn.classList.contains( 'arshid6social-wtf-btn' ) ) {
				applyWtfBtnColors( btn, btn.dataset.status || 'not_friends' );
			}

			btn.addEventListener( 'click', async () => {
				const userId = btn.dataset.userId;
				const status = btn.dataset.status || 'not_friends';
				btn.disabled = true;
				try {
					let res;
					if ( status === 'not_friends' ) {
						res = await doAjax( 'arshid6social_send_friend_request', { user_id: userId } );
					} else if ( status === 'friends' ) {
						res = await doAjax( 'arshid6social_remove_friend', { user_id: userId } );
					} else {
						res = await doAjax( 'arshid6social_reject_friend_request', { user_id: userId } );
					}
					if ( res.success ) {
						const newStatus = res.data.status;
						btn.dataset.status = newStatus;
						if ( btn.classList.contains( 'arshid6social-wtf-btn' ) ) {
							const WTF_LABELS = {
								not_friends:      I18N.addFriend || 'Add Friend',
								pending_sent:     I18N.cancelRequest || 'Cancel',
								pending_received: I18N.accept || 'Accept',
								friends:          I18N.friends || 'Friends',
							};
							btn.innerHTML = ( WTF_ICONS[ newStatus ] || WTF_ICONS.not_friends ) + ' ' + ( WTF_LABELS[ newStatus ] || WTF_LABELS.not_friends );
							[ 'not_friends', 'pending_sent', 'pending_received', 'friends' ].forEach( s => btn.classList.remove( 'arshid6social-wtf-btn--' + s ) );
							btn.classList.add( 'arshid6social-wtf-btn--' + newStatus );
							applyWtfBtnColors( btn, newStatus );
						} else {
							btn.textContent = newStatus === 'pending_sent' ? ( I18N.cancelRequest || 'Cancel' ) : newStatus === 'friends' ? ( I18N.friends || 'Friends' ) : ( I18N.addFriend || 'Add Friend' );
						}
					}
				} finally {
					btn.disabled = false;
				}
			} );
		} );
	}

	// -- Pagination helper (members / groups) -----------------------------
	function renderPagination( data, el ) {
		if ( ! el || data.total_pages <= 1 ) { if ( el ) el.innerHTML = ''; return; }
		const current = data.current_page || 1;
		let html = '';
		for ( let i = 1; i <= data.total_pages; i++ ) {
			html += `<button class="arshid6social-page-btn ${i === current ? 'is-active' : ''}" data-page="${i}" aria-label="Page ${i}" ${i === current ? 'aria-current="page"' : ''}>${i}</button>`;
		}
		el.innerHTML = html;
	}

	// -- Notification type meta (icon, colour) -----------------------------
	const NOTIF_TYPES = {
		friend_request:        { icon: '\uD83D\uDC65', color: '#2563eb' },
		friendship_accepted:   { icon: '\uD83E\uDD1D', color: '#16a34a' },
		activity_reaction:     { icon: '\u2764\uFE0F', color: '#dc2626' },
		activity_comment:      { icon: '\uD83D\uDCAC', color: '#0891b2' },
		comment_reply:         { icon: '\u21A9\uFE0F', color: '#7c3aed' },
		activity_mention:      { icon: '\u270D\uFE0F', color: '#7c3aed' },
		new_message:           { icon: '\u2709\uFE0F', color: '#0284c7' },
		group_invitation:      { icon: '\uD83C\uDFD8\uFE0F', color: '#d97706' },
		new_follower:          { icon: '\uD83D\uDC64', color: '#059669' },
		story_reaction:        { icon: '\uD83D\uDE0D', color: '#f59e0b' },
		story_reply:           { icon: '\u21A9\uFE0F', color: '#0284c7' },
		verification_approved: { icon: '\u2713', color: '#16a34a' },
		verification_rejected: { icon: '\u2717', color: '#dc2626' },
	};

	function notifIcon( action ) {
		return NOTIF_TYPES[ action ] || { icon: '\uD83D\uDD14', color: '#6b7280' };
	}

	// Renders one item for the bell dropdown (compact).
	function renderNotifDropdownItem( n ) {
		const t    = notifIcon( n.componentAction );
		const href = n.link && n.link !== '#' ? ` href="${n.link}"` : '';
		const tag  = href ? 'a' : 'div';
		return `<${tag} class="arshid6social-notification-item${n.isNew ? ' is-unread' : ''}" data-id="${n.id}" role="listitem"${href}>
			<div class="arshid6social-notif-icon-wrap" style="background:${t.color};">${t.icon}</div>
			<div class="arshid6social-notif-text">
				<div class="arshid6social-notif-desc">${n.description}</div>
				<time class="arshid6social-notif-time">${relativeDate( n.dateNotified )}</time>
			</div>
		</${tag}>`;
	}

	// Renders one item for the full notifications page (rich card).
	function renderNotifPageItem( n ) {
		const t    = notifIcon( n.componentAction );
		const href = n.link && n.link !== '#' ? n.link : '';
		const isFriendRequest = n.componentAction === 'friend_request';
		const friendActions = isFriendRequest
			? `<div class="arshid6social-notif-friend-actions">
				<button class="arshid6social-btn arshid6social-btn--primary arshid6social-notif-accept-friend" data-id="${n.id}" data-user-id="${n.itemId}">${I18N.accept || 'Confirm'}</button>
				<button class="arshid6social-btn arshid6social-btn--ghost arshid6social-notif-reject-friend" data-id="${n.id}" data-user-id="${n.itemId}">${I18N.reject || 'Remove'}</button>
			</div>`
			: '';
		const defaultAvatar = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=48';
		const avatarSrc = n.senderAvatar || defaultAvatar;
		const cardHref   = isFriendRequest ? '' : href;
		const avatarLink = ( isFriendRequest && n.senderUrl && n.senderUrl !== '#' ) ? n.senderUrl : '';
		const avatarEl   = avatarLink
			? `<a href="${avatarLink}" class="arshid6social-notif-avatar-link" tabindex="-1"><img class="arshid6social-notif-sender-avatar" src="${avatarSrc}" alt="${n.senderName || ''}" onerror="this.src='${defaultAvatar}'" /></a>`
			: `<img class="arshid6social-notif-sender-avatar" src="${avatarSrc}" alt="${n.senderName || ''}" onerror="this.src='${defaultAvatar}'" />`;
		return `<div class="arshid6social-notif-card${n.isNew ? ' is-unread' : ''}${cardHref ? ' arshid6social-notif-card--linked' : ''}" data-id="${n.id}" data-link="${cardHref}" role="listitem">
			<div class="arshid6social-notif-avatar-wrap">
				${avatarEl}
				<span class="arshid6social-notif-type-badge" style="background:${t.color};" title="${n.typeLabel || ''}">${t.icon}</span>
			</div>
			<div class="arshid6social-notif-body">
				<p class="arshid6social-notif-desc">${n.description}</p>
				<time class="arshid6social-notif-time${n.isNew ? ' is-new' : ''}">${relativeDate( n.dateNotified )}</time>
				${friendActions}
			</div>
			<div class="arshid6social-notif-right">
				${n.isNew ? '<span class="arshid6social-notif-unread-dot"></span>' : ''}
				<button class="arshid6social-notif-delete" data-id="${n.id}" title="${I18N.delete || 'Delete'}">\u2715</button>
			</div>
		</div>`;
	}

	// Updates all badge elements that show the unread count.
	function setNotifBadge( count ) {
		document.querySelectorAll( '#arshid6social-notification-count, #arshid6social-notif-page-unread-badge' ).forEach( ( el ) => {
			el.textContent = count;
			el.hidden = count === 0;
		} );
	}

	// -- Notification bell dropdown -----------------------------------------
	function initNotifications() {
		const bell     = document.getElementById( 'arshid6social-notification-bell' );
		const dropdown = document.getElementById( 'arshid6social-notification-dropdown' );

		if ( ! USER_ID ) return;

		// Initial badge load + poll every 60 s.
		async function refreshBadge() {
			try {
				const res = await doAjax( 'arshid6social_unread_notification_count', {} );
				if ( res.success ) setNotifBadge( res.data.count );
			} catch {}
		}
		refreshBadge();
		setInterval( refreshBadge, 30000 );

		if ( ! bell || ! dropdown ) return;

		async function loadDropdown() {
			dropdown.innerHTML = `<div class="arshid6social-notif-dropdown-loading"><div class="arshid6social-spinner"></div></div>`;
			try {
				const res = await doAjax( 'arshid6social_get_notifications', { page: 1 } );
				if ( ! res.success ) throw new Error();
				const items = res.data.notifications.slice( 0, 8 );
				const siteUrl = cfg.siteUrl || '/';

				dropdown.innerHTML = items.length
					? `<div class="arshid6social-notif-dropdown-list" role="list">${items.map( renderNotifDropdownItem ).join( '' )}</div>
					   <div class="arshid6social-notif-dropdown-footer">
					     <a href="${siteUrl}members/${cfg.userSlug || 'me'}/notifications/">${I18N.allNotifications || 'All notifications'}</a>
					     <button id="arshid6social-bell-mark-all" class="arshid6social-link-btn">${I18N.markAllRead || 'Mark all read'}</button>
					   </div>`
					: `<p class="arshid6social-notif-empty">${I18N.noNotifications || 'No notifications yet.'}</p>`;

				dropdown.querySelector( '#arshid6social-bell-mark-all' )?.addEventListener( 'click', async () => {
					await doAjax( 'arshid6social_mark_notifications_read', {} );
					dropdown.querySelectorAll( '.is-unread' ).forEach( ( el ) => el.classList.remove( 'is-unread' ) );
					setNotifBadge( 0 );
				} );
			} catch {
				dropdown.innerHTML = `<p class="arshid6social-notif-empty">${I18N.error || 'Error.'}</p>`;
			}
		}

		bell.addEventListener( 'click', async ( e ) => {
			e.stopPropagation();
			const isOpen = dropdown.classList.toggle( 'is-open' );
			if ( isOpen ) await loadDropdown();
		} );

		document.addEventListener( 'click', ( e ) => {
			if ( ! bell.contains( e.target ) && ! dropdown.contains( e.target ) ) {
				dropdown.classList.remove( 'is-open' );
			}
		} );
	}

	// -- Full notifications page --------------------------------------------
	function isPageDark() {
		const html = document.documentElement;
		const body = document.body;
		// Dark mode ONLY via plugin's own attribute — plugin forces light CSS variables
		// by default and the standalone page must not inherit the theme's dark toggle.
		return html.getAttribute( 'data-arshid6social-dark' ) === 'true'
			|| body.getAttribute( 'data-arshid6social-dark' ) === 'true';
	}

	function applyNotifDarkMode( page ) {
		page.classList.toggle( 'arshid6social-notif-page-is-dark', isPageDark() );
	}

	function initNotificationsPage() {
		const page = document.getElementById( 'arshid6social-notifications-page' );
		if ( ! page || ! USER_ID ) return;

		applyNotifDarkMode( page );

		// Re-apply on any attribute/class change on html or body.
		const mo = new MutationObserver( () => applyNotifDarkMode( page ) );
		mo.observe( document.documentElement, { attributes: true } );
		mo.observe( document.body, { attributes: true } );

		const listEl       = page.querySelector( '#arshid6social-notif-list' );
		const loadMoreWrap = page.querySelector( '#arshid6social-notif-load-more-wrap' );
		const sentinel     = page.querySelector( '#arshid6social-notif-sentinel' );
		const markAllBtn   = page.querySelector( '#arshid6social-notif-mark-all' );
		const filterCheck  = page.querySelector( '#arshid6social-notif-unread-only' );

		let currentPage        = 1;
		let unreadOnly         = false;
		let loading            = false;
		let hasMore            = true;
		let renderedNewHeader  = false;
		let renderedEarlierHeader = false;

		function createSectionHeader( text ) {
			const el = document.createElement( 'h3' );
			el.className = 'arshid6social-notif-section-header';
			el.textContent = text;
			return el;
		}

		async function load( reset = false ) {
			if ( loading ) return;
			loading = true;
			if ( reset ) {
				currentPage = 1;
				renderedNewHeader = false;
				renderedEarlierHeader = false;
				listEl.innerHTML = `<div class="arshid6social-notif-skeleton">
					<div class="arshid6social-skeleton" style="height:72px;margin-bottom:8px;border-radius:12px;"></div>
					<div class="arshid6social-skeleton" style="height:72px;margin-bottom:8px;border-radius:12px;"></div>
				</div>`;
			}
			try {
				const res = await doAjax( 'arshid6social_get_notifications', { page: currentPage, unread_only: unreadOnly ? 1 : 0 } );
				if ( ! res.success ) return;

				const { notifications, hasMore: more, total } = res.data;
				hasMore = !! more;

				if ( reset ) listEl.innerHTML = '';

				if ( notifications.length ) {
					const frag = document.createDocumentFragment();
					notifications.forEach( ( n ) => {
						if ( n.isNew && ! renderedNewHeader ) {
							frag.appendChild( createSectionHeader( I18N.notifSectionNew || 'New' ) );
							renderedNewHeader = true;
						} else if ( ! n.isNew && ! renderedEarlierHeader ) {
							frag.appendChild( createSectionHeader( I18N.notifSectionEarlier || 'Earlier' ) );
							renderedEarlierHeader = true;
						}
						const div = document.createElement( 'div' );
						div.innerHTML = renderNotifPageItem( n );
						frag.appendChild( div.firstElementChild );
					} );
					listEl.appendChild( frag );
					bindPageItemActions( listEl );
				} else if ( reset ) {
					listEl.innerHTML = `<div class="arshid6social-notif-empty-state">
						<span class="arshid6social-notif-empty-icon">\uD83D\uDD14</span>
						<p>${I18N.noNotifications || 'No notifications yet.'}</p>
					</div>`;
				}

				setNotifBadge( res.data.unreadCount || 0 );

				loadMoreWrap.hidden = ! hasMore;
				currentPage++;
			} catch {
				if ( reset ) listEl.innerHTML = `<p style="padding:1rem;">${I18N.error || 'Error.'}</p>`;
			} finally {
				loading = false;
			}
		}

		function bindPageItemActions( container ) {
			// Click on card body \u2192 navigate to link, mark as read.
			container.querySelectorAll( '.arshid6social-notif-card--linked:not([data-bound-nav])' ).forEach( ( card ) => {
				card.dataset.boundNav = '1';
				card.addEventListener( 'click', async ( e ) => {
					if ( e.target.closest( '.arshid6social-notif-right' ) ) return;
					if ( e.target.closest( '.arshid6social-notif-friend-actions' ) ) return;
					if ( e.target.closest( '.arshid6social-notif-avatar-link' ) ) return;
					const href = card.dataset.link;
					if ( ! href ) return;
					const id = parseInt( card.dataset.id, 10 );
					if ( card.classList.contains( 'is-unread' ) ) {
						doAjax( 'arshid6social_mark_notifications_read', { ids: [ id ] } );
						card.classList.remove( 'is-unread' );
						card.querySelector( '.arshid6social-notif-unread-dot' )?.remove();
						card.querySelector( '.arshid6social-notif-time' )?.classList.remove( 'is-new' );
					}
					window.location.href = href;
				} );
			} );

			container.querySelectorAll( '.arshid6social-notif-mark-read:not([data-bound])' ).forEach( ( btn ) => {
				btn.dataset.bound = '1';
				btn.addEventListener( 'click', async () => {
					const id   = parseInt( btn.dataset.id, 10 );
					const card = btn.closest( '.arshid6social-notif-card' );
					await doAjax( 'arshid6social_mark_notifications_read', { ids: [ id ] } );
					card?.classList.remove( 'is-unread' );
					btn.remove();
				} );
			} );

			container.querySelectorAll( '.arshid6social-notif-delete:not([data-bound])' ).forEach( ( btn ) => {
				btn.dataset.bound = '1';
				btn.addEventListener( 'click', async () => {
					const id   = parseInt( btn.dataset.id, 10 );
					const card = btn.closest( '.arshid6social-notif-card' );
					const res  = await doAjax( 'arshid6social_delete_notification', { id } );
					if ( res.success ) {
						card?.remove();
						setNotifBadge( res.data.unreadCount || 0 );
					}
				} );
			} );

			container.querySelectorAll( '.arshid6social-notif-accept-friend:not([data-bound])' ).forEach( ( btn ) => {
				btn.dataset.bound = '1';
				btn.addEventListener( 'click', async ( e ) => {
					e.stopPropagation();
					const userId  = parseInt( btn.dataset.userId, 10 );
					const card    = btn.closest( '.arshid6social-notif-card' );
					const notifId = parseInt( card?.dataset.id, 10 );
					btn.disabled  = true;
					const res = await doAjax( 'arshid6social_accept_friend_request', { user_id: userId } );
					if ( res.success ) {
						if ( notifId ) await doAjax( 'arshid6social_delete_notification', { id: notifId } );
						card?.remove();
					} else {
						btn.disabled = false;
					}
				} );
			} );

			container.querySelectorAll( '.arshid6social-notif-reject-friend:not([data-bound])' ).forEach( ( btn ) => {
				btn.dataset.bound = '1';
				btn.addEventListener( 'click', async ( e ) => {
					e.stopPropagation();
					const userId  = parseInt( btn.dataset.userId, 10 );
					const card    = btn.closest( '.arshid6social-notif-card' );
					const notifId = parseInt( card?.dataset.id, 10 );
					btn.disabled  = true;
					const res = await doAjax( 'arshid6social_reject_friend_request', { user_id: userId } );
					if ( res.success ) {
						if ( notifId ) await doAjax( 'arshid6social_delete_notification', { id: notifId } );
						card?.remove();
					} else {
						btn.disabled = false;
					}
				} );
			} );
		}

		markAllBtn?.addEventListener( 'click', async () => {
			await doAjax( 'arshid6social_mark_notifications_read', {} );
			page.querySelectorAll( '.arshid6social-notif-card.is-unread' ).forEach( ( c ) => c.classList.remove( 'is-unread' ) );
			page.querySelectorAll( '.arshid6social-notif-unread-dot' ).forEach( ( d ) => d.remove() );
			page.querySelectorAll( '.arshid6social-notif-time.is-new' ).forEach( ( t ) => t.classList.remove( 'is-new' ) );
			setNotifBadge( 0 );
		} );

		// Tab buttons (All / Unread)
		page.querySelectorAll( '.arshid6social-notif-tab' ).forEach( ( tab ) => {
			tab.addEventListener( 'click', () => {
				page.querySelectorAll( '.arshid6social-notif-tab' ).forEach( ( t ) => {
					t.classList.remove( 'is-active' );
					t.setAttribute( 'aria-selected', 'false' );
				} );
				tab.classList.add( 'is-active' );
				tab.setAttribute( 'aria-selected', 'true' );
				unreadOnly = tab.dataset.filter === 'unread';
				load( true );
			} );
		} );

		if ( sentinel ) {
			new IntersectionObserver( ( entries ) => {
				if ( entries[ 0 ].isIntersecting && ! loading && hasMore ) {
					load();
				}
			}, { rootMargin: '200px' } ).observe( sentinel );
		}

		// Load more button (standalone page — no sentinel).
		const loadMoreBtn = page.querySelector( '#arshid6social-notif-load-more' );
		loadMoreBtn?.addEventListener( 'click', () => load() );

		// Customize / Preferences toggle.
		const settingsPanel   = page.querySelector( '#arshid6social-notif-settings-panel' );
		const settingsToggles = page.querySelectorAll( '.arshid6social-notif-settings-toggle' );
		settingsToggles.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const isOpen = ! settingsPanel.hidden;
				settingsPanel.hidden = isOpen;
				const mainToggle = page.querySelector( '#arshid6social-notif-settings-toggle' );
				if ( mainToggle ) mainToggle.setAttribute( 'aria-expanded', String( ! isOpen ) );
			} );
		} );

		// Notification preferences form.
		const prefsForm = page.querySelector( '#arshid6social-notif-prefs-form' );
		if ( prefsForm ) {
			prefsForm.addEventListener( 'submit', async ( e ) => {
				e.preventDefault();
				const saveBtn  = prefsForm.querySelector( '#arshid6social-notif-prefs-save-btn' );
				const savedMsg = prefsForm.querySelector( '.arshid6social-notif-prefs-saved-msg' );
				saveBtn.disabled = true;
				const formData = new FormData( prefsForm );
				const data = {};
				// Include unchecked checkboxes as absent (server treats missing = off).
				prefsForm.querySelectorAll( 'input[type=checkbox]' ).forEach( ( cb ) => {
					if ( ! cb.checked ) delete data[ cb.name ];
				} );
				formData.forEach( ( v, k ) => { data[ k ] = v; } );
				const res = await doAjax( 'arshid6social_save_notification_prefs', data );
				saveBtn.disabled = false;
				if ( res.success && savedMsg ) {
					savedMsg.hidden = false;
					setTimeout( () => { savedMsg.hidden = true; }, 3000 );
				}
			} );
		}

		load( true );

		// Real-time: poll every 30 s; show a sticky banner when new notifications arrive.
		let knownUnreadCount = 0;
		let bannerShown      = false;

		async function pollNewNotifications() {
			try {
				const res = await doAjax( 'arshid6social_unread_notification_count', {} );
				if ( ! res.success ) return;
				const fresh = res.data.count || 0;
				setNotifBadge( fresh );
				if ( ! bannerShown && fresh > knownUnreadCount && knownUnreadCount > 0 ) {
					showNewNotifBanner();
				}
				knownUnreadCount = fresh;
			} catch {}
		}

		function showNewNotifBanner() {
			if ( bannerShown ) return;
			bannerShown = true;

			const banner = document.createElement( 'div' );
			banner.className = 'arshid6social-notif-new-banner';
			banner.setAttribute( 'role', 'alert' );
			banner.innerHTML = `<span>${ I18N.newNotificationsAvailable || 'New notifications available.' }</span>
				<button class="arshid6social-btn arshid6social-btn--sm arshid6social-btn--primary arshid6social-notif-banner-refresh">${ I18N.refresh || 'Refresh' }</button>
				<button class="arshid6social-notif-banner-close" aria-label="${ I18N.close || 'Close' }">✕</button>`;

			page.insertBefore( banner, listEl );

			banner.querySelector( '.arshid6social-notif-banner-refresh' ).addEventListener( 'click', () => {
				banner.remove();
				bannerShown      = false;
				knownUnreadCount = 0;
				load( true );
			} );

			banner.querySelector( '.arshid6social-notif-banner-close' ).addEventListener( 'click', () => {
				banner.remove();
				bannerShown = false;
			} );
		}

		// Set the initial known count after first load, then start polling.
		setTimeout( async () => {
			try {
				const r = await doAjax( 'arshid6social_unread_notification_count', {} );
				if ( r.success ) knownUnreadCount = r.data.count || 0;
			} catch {}
			setInterval( pollNewNotifications, 30000 );
		}, 2000 );
	}

	// -- Messages unread badge ----------------------------------------------
	function initMessagesBadge() {
		const badges = document.querySelectorAll( '#arshid6social-messages-count' );
		if ( ! badges.length || ! USER_ID ) return;

		function setMsgBadge( count ) {
			badges.forEach( ( el ) => { el.textContent = count; el.hidden = count === 0; } );
		}

		async function refresh() {
			try {
				const res = await doAjax( 'arshid6social_unread_count', {} );
				if ( res.success ) setMsgBadge( res.data.count );
			} catch {}
		}

		refresh();
		setInterval( refresh, 60000 );
	}

	// -- Notices / toasts ---------------------------------------------------
	function showNotice( message, type = 'info' ) {
		let container = document.getElementById( 'arshid6social-notice-container' );
		if ( ! container ) {
			container    = document.createElement( 'div' );
			container.id = 'arshid6social-notice-container';
			container.style.cssText = 'position:fixed;top:1rem;inset-inline-end:1rem;z-index:99999;display:flex;flex-direction:column;gap:.5rem;max-width:340px;pointer-events:none;';
			document.body.appendChild( container );
		}
		const notice         = document.createElement( 'div' );
		notice.className     = `arshid6social-notice arshid6social-notice-${type}`;
		notice.style.cssText = 'pointer-events:auto;';
		notice.setAttribute( 'role', 'alert' );
		notice.textContent   = message;
		container.appendChild( notice );
		setTimeout( () => notice.remove(), 4000 );
	}

	// -- Avatar upload ------------------------------------------------------
	function initAvatarUpload() {
		const input   = document.getElementById( 'arshid6social-avatar-input' );
		const preview = document.getElementById( 'arshid6social-avatar-preview' );
		const form    = document.getElementById( 'arshid6social-avatar-form' );

		if ( ! input || ! preview || ! form ) return;

		input.addEventListener( 'change', async () => {
			const file = input.files[ 0 ];
			if ( ! file ) return;

			const reader   = new FileReader();
			reader.onload  = ( e ) => { preview.src = e.target.result; };
			reader.readAsDataURL( file );

			const formData = new FormData( form );
			try {
				const res  = await fetch( AJAX_URL, { method: 'POST', body: formData } );
				const data = await res.json();
				if ( data.success ) { preview.src = data.data.url; showNotice( 'Avatar updated!', 'success' ); }
				else showNotice( data.data?.message || I18N.error || 'Upload failed.', 'error' );
			} catch { showNotice( I18N.error || 'Upload failed.', 'error' ); }
		} );
	}

	function initProfileCoverUpload() {
		const input = document.getElementById( 'arshid6social-cover-input' );
		const form  = document.getElementById( 'arshid6social-cover-form' );

		if ( ! input || ! form ) return;

		input.addEventListener( 'change', async () => {
			const file = input.files[ 0 ];
			if ( ! file ) return;

			const formData = new FormData( form );
			try {
				const res  = await fetch( AJAX_URL, { method: 'POST', body: formData } );
				const data = await res.json();
				if ( data.success ) {
					const cover = form.closest( '.arshid6social-profile-cover' );
					if ( cover ) cover.style.backgroundImage = `url('${data.data.url}')`;
					showNotice( 'Cover updated!', 'success' );
				} else {
					showNotice( data.data?.message || I18N.error || 'Upload failed.', 'error' );
				}
			} catch { showNotice( I18N.error || 'Upload failed.', 'error' ); }
		} );
	}

	// -- Appearance settings form -------------------------------------------
	function initAppearanceSettingsForm() {
		const form = document.getElementById( 'arshid6social-appearance-settings-form' );
		if ( ! form ) return;

		form.querySelectorAll( 'input[type="radio"]' ).forEach( ( radio ) => {
			radio.addEventListener( 'change', () => {
				form.querySelectorAll( '.arshid6social-radio-option' ).forEach( ( label ) =>
					label.classList.toggle( 'is-selected', label.querySelector( 'input' )?.checked )
				);
			} );
		} );

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const btn     = form.querySelector( '#arshid6social-appearance-save-btn' );
			const savedEl = form.querySelector( '.arshid6social-appearance-saved-msg' );
			const mode    = form.querySelector( 'input[name="arshid6social_theme_mode"]:checked' )?.value;
			if ( ! mode ) return;

			if ( btn ) btn.disabled = true;
			try {
				const res = await doAjax( 'arshid6social_save_user_setting', {
					setting_key:   'arshid6social_theme_mode',
					setting_value: mode,
				} );
				if ( res.success ) {
					if ( mode === 'system' ) {
						document.documentElement.removeAttribute( 'data-a6sc-theme' );
					} else {
						document.documentElement.setAttribute( 'data-a6sc-theme', mode );
					}
					localStorage.setItem( 'a6sc-theme', mode );
					if ( savedEl ) { savedEl.hidden = false; setTimeout( () => { savedEl.hidden = true; }, 3000 ); }
				} else {
					showNotice( res.data?.message || 'Error saving.', 'error' );
				}
			} finally {
				if ( btn ) btn.disabled = false;
			}
		} );
	}

	// -- Dark mode toggle ---------------------------------------------------
	function initDarkMode() {
		const toggle = document.getElementById( 'arshid6social-dark-toggle' );
		if ( ! toggle ) return;

		toggle.addEventListener( 'click', () => {
			const isDark = document.body.getAttribute( 'data-arshid6social-dark' ) === 'true';
			document.body.setAttribute( 'data-arshid6social-dark', isDark ? 'false' : 'true' );
			document.documentElement.setAttribute( 'data-arshid6social-dark', isDark ? 'false' : 'true' );
		} );
	}

	// -- Friends tab on profile page ----------------------------------------
	function initFriendsTab() {
		const wrap = document.getElementById( 'arshid6social-friends-tab' );
		if ( ! wrap ) return;

		const grid         = document.getElementById( 'arshid6social-friends-grid' );
		const loadMoreWrap = document.getElementById( 'arshid6social-friends-load-more-wrap' );
		const loadMoreBtn  = document.getElementById( 'arshid6social-friends-load-more' );
		const userId       = parseInt( wrap.dataset.userId, 10 );

		let page    = 1;
		let loading = false;

		async function load( reset = false ) {
			if ( loading ) return;
			loading = true;
			if ( reset ) {
				page = 1;
				grid.innerHTML = renderMemberSkeletons( 6 );
			}
			try {
				const res = await doAjax( 'arshid6social_get_friends', { user_id: userId, page } );
				if ( ! res.success ) return;

				const { friends, hasMore, private: isPrivate } = res.data;
				if ( reset ) grid.innerHTML = '';

				if ( isPrivate ) {
					grid.innerHTML = `<p class="arshid6social-text-muted" style="grid-column:1/-1;text-align:center;padding:2rem;">${I18N.friendsPrivate || 'This user\'s friends list is private.'}</p>`;
					loadMoreWrap.hidden = true;
					return;
				}

				if ( friends.length ) {
					friends.forEach( ( m ) => {
						const div = document.createElement( 'div' );
						div.innerHTML = renderFriendCard( m );
						grid.appendChild( div.firstElementChild );
					} );
					initFriendButtons();
				} else if ( reset ) {
					grid.innerHTML = `<p class="arshid6social-text-muted" style="grid-column:1/-1;text-align:center;padding:2rem;">${I18N.noFriends || 'No friends yet.'}</p>`;
				}

				loadMoreWrap.hidden = ! hasMore;
				page++;
			} catch {
				if ( reset ) grid.innerHTML = `<p style="grid-column:1/-1;padding:1rem;">${I18N.error || 'Error.'}</p>`;
			} finally {
				loading = false;
			}
		}

		function renderFriendCard( m ) {
			const status = m.friendshipStatus || 'not_friends';
			const statusLabels = {
				not_friends:      I18N.addFriend       || 'Add Friend',
				pending_sent:     I18N.cancelRequest   || 'Cancel Request',
				pending_received: I18N.acceptRequest   || 'Accept Request',
				friends:          I18N.friends         || 'Friends \u2713',
			};
			const friendBtn = USER_ID && USER_ID !== m.id
				? `<button class="arshid6social-btn arshid6social-btn--secondary arshid6social-btn--sm arshid6social-friend-btn" data-user-id="${m.id}" data-status="${esc( status )}">${esc( statusLabels[ status ] || statusLabels.not_friends )}</button>`
				: '';
			return `<div class="arshid6social-member-card" role="listitem">
				<div class="arshid6social-avatar-wrap">
					<img class="arshid6social-avatar arshid6social-avatar-lg" src="${esc( m.avatarUrl )}" alt="${esc( m.name )}" width="80" height="80" loading="lazy" />
					${m.isOnline ? '<span class="arshid6social-online-badge"></span>' : ''}
				</div>
				<a class="arshid6social-member-card-name" href="${esc( m.profileUrl )}">${esc( m.name )}</a>
				<span class="arshid6social-member-card-meta">${esc( String( m.friendCount ) )} friends</span>
				${friendBtn}
			</div>`;
		}

		loadMoreBtn?.addEventListener( 'click', () => load() );
		load( true );
	}

	// -- Init ---------------------------------------------------------------
	function initSingleActivity() {
		const wrap = document.getElementById( 'arshid6social-single-activity-wrap' );
		if ( ! wrap ) return;
		try {
			const activity = JSON.parse( wrap.dataset.activity || '{}' );
			if ( activity.id ) {
				wrap.querySelector( '.arshid6social-activity-feed' ).innerHTML = renderActivity( activity );
				initReactionButtons();
				initEmojiPicker();
				initCommentToggles();
				initCommentForms();
				initDeleteButtons(); initUnlockButtons();
				initEditButtons();
				initViewTracking();

				// Load more posts from the same user below.
				const moreFeed = document.getElementById( 'arshid6social-single-more-feed' );
				const moreWrap = document.getElementById( 'arshid6social-single-more-wrap' );
				const userId   = parseInt( wrap.dataset.userId, 10 );
				if ( moreFeed && userId ) {
					moreFeed.innerHTML = renderSkeletons( 3 );
					const params = new URLSearchParams( {
						action:   'arshid6social_get_activity',
						nonce:    AJAX_NONCE,
						user_id:  userId,
						scope:    'personal',
						per_page: 5,
						page:     1,
					} );
					fetch( `${AJAX_URL}?${params}` )
						.then( ( r ) => r.json() )
						.then( ( res ) => {
							if ( ! res.success ) { moreFeed.innerHTML = ''; return; }
							const others = ( res.data.activities || [] ).filter( ( a ) => a.id !== activity.id );
							if ( ! others.length ) {
								if ( moreWrap ) moreWrap.hidden = true;
								return;
							}
							moreFeed.innerHTML = others.map( renderActivity ).join( '' );
							initReactionButtons();
							initEmojiPicker();
							initCommentToggles();
							initCommentForms();
							initDeleteButtons(); initUnlockButtons();
							initEditButtons();
							initViewTracking();
						} )
						.catch( () => { moreFeed.innerHTML = ''; } );
				} else if ( moreWrap ) {
					moreWrap.hidden = true;
				}
			}
		} catch ( e ) {}
	}

	// Document-level delegation: show/hide PPV price row whenever the privacy
	// select changes (catches any form, even lazily inserted ones).
	if ( EJTEMSN_ON ) {
		document.addEventListener( 'change', ( e ) => {
			if ( ! e.target.classList.contains( 'arshid6social-privacy-select' ) ) return;
			const form = e.target.closest( 'form' );
			if ( ! form ) return;
			const priceRow = form.querySelector( '.sixarshidsc-price-row' );
			if ( priceRow ) {
				priceRow.style.display = e.target.value === 'paid' ? 'flex' : 'none';
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		initActivityFeed();
		initActivityBlocks();
		initSingleActivity();
		initFriendsTab();
		initMemberDirectory();
		initGroupDirectory();
		initGroupImageUploads();
		initNotifications();
		initNotificationsPage();
		initMessagesBadge();
		initAvatarUpload();
		initProfileCoverUpload();
		initDarkMode();
		initReactionButtons();
		initEmojiPicker();
		initDeleteButtons(); initUnlockButtons();
		initEditButtons();
		initCommentReactions();
		initCommentReplies();
		initFriendButtons();
		initGroupJoinButtons();
		initCommentToggles();
		initCommentForms();
		handleCommentAnchor();
		initUserSettingsForm();
		initAppearanceSettingsForm();
		initBioEdit();
		initBioSettingsForm();
		initSettingsNotifPrefsForm();
		initChangeNameForm();
		initFriendsPrivacyForm();
		initChangeUsernameForm();
		initChangePasswordForm();
		initReportModal();
		initAdClickTracking();
		initViewTracking();

		// Show a toast when redirected from a guest-only page (e.g. login/register while logged in).
		const _urlParams   = new URLSearchParams( window.location.search );
		const _noticeParam = _urlParams.get( 'arshid6social_notice' );
		if ( _noticeParam === 'logout_first' ) {
			showNotice( I18N.logoutFirst || 'You are already logged in. Please log out first.', 'warning' );
			const _cleanUrl = new URL( window.location.href );
			_cleanUrl.searchParams.delete( 'arshid6social_notice' );
			history.replaceState( null, '', _cleanUrl.toString() );
		}
	} );

	// ---------------------------------------------------------------------------
	// Share / Repost — prepend the new activity card to every visible feed
	// ---------------------------------------------------------------------------

	document.addEventListener( 'ARSHID6SOCIAL:notice', ( e ) => {
		const { message, type } = e.detail || {};
		if ( message ) showNotice( message, type || 'info' );
	} );

	document.addEventListener( 'ARSHID6SOCIAL:share:done', ( e ) => {
		const activity = e.detail && e.detail.activity;
		if ( ! activity || ! activity.id ) return;
		const html = renderActivity( activity );
		// Profile page uses id="arshid6social-activity-feed" (no class); blocks use class.
		const feeds = new Set( [
			...document.querySelectorAll( '.arshid6social-activity-feed' ),
			document.getElementById( 'arshid6social-activity-feed' ),
		].filter( Boolean ) );
		feeds.forEach( ( feed ) => feed.insertAdjacentHTML( 'afterbegin', html ) );
		initReactionButtons();
		initEmojiPicker();
		initDeleteButtons(); initUnlockButtons();
		initEditButtons();
		initCommentToggles();
		initCommentForms();
		document.dispatchEvent( new CustomEvent( 'ARSHID6SOCIAL:activity:loaded', { detail: { container: document.body } } ) );
	} );

	// ---------------------------------------------------------------------------
	// Report modal
	// ---------------------------------------------------------------------------

	function initReportModal() {
		const modal    = document.getElementById( 'arshid6social-report-modal' );
		if ( ! modal ) return;

		const reasonsEl  = document.getElementById( 'arshid6social-rm-reasons' );
		const notesEl    = document.getElementById( 'arshid6social-rm-notes' );
		const fileWrap   = document.getElementById( 'arshid6social-rm-attachment-wrap' );
		const fileEl     = document.getElementById( 'arshid6social-rm-file' );
		const feedback   = document.getElementById( 'arshid6social-rm-feedback' );
		const submitBtn  = document.getElementById( 'arshid6social-rm-submit' );
		const cancelBtn  = document.getElementById( 'arshid6social-rm-cancel' );
		const closeBtn   = document.getElementById( 'arshid6social-rm-close' );
		const itemIdEl   = document.getElementById( 'arshid6social-rm-item-id' );
		const itemTypeEl = document.getElementById( 'arshid6social-rm-item-type' );
		const reasonEl   = document.getElementById( 'arshid6social-rm-reason' );

		const reasons = ( cfg.reportReasons && cfg.reportReasons.length ) ? cfg.reportReasons : [ 'Spam', 'Harassment', 'Inappropriate content', 'Other' ];

		// Build reason pills.
		reasonsEl.innerHTML = '';
		reasons.forEach( ( r ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'arshid6social-report-reason-btn';
			btn.textContent = r;
			btn.addEventListener( 'click', () => {
				reasonsEl.querySelectorAll( '.arshid6social-report-reason-btn' ).forEach( b => b.classList.remove( 'is-selected' ) );
				btn.classList.add( 'is-selected' );
				reasonEl.value = r;
				submitBtn.disabled = false;
			} );
			reasonsEl.appendChild( btn );
		} );

		// Show attachment input if enabled.
		if ( cfg.reportAllowAttachments && fileWrap ) {
			fileWrap.hidden = false;
		}

		function openModal( itemId, itemType ) {
			itemIdEl.value   = itemId;
			itemTypeEl.value = itemType;
			reasonEl.value   = '';
			notesEl.value    = '';
			if ( fileEl ) fileEl.value = '';
			submitBtn.disabled = true;
			feedback.hidden    = true;
			reasonsEl.querySelectorAll( '.arshid6social-report-reason-btn' ).forEach( b => b.classList.remove( 'is-selected' ) );
			modal.hidden = false;
			document.body.style.overflow = 'hidden';
		}

		function closeModal() {
			modal.hidden = true;
			document.body.style.overflow = '';
		}

		// Open on .arshid6social-report-btn click.
		document.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '.arshid6social-report-btn' );
			if ( ! btn ) return;
			e.preventDefault();
			openModal( btn.dataset.itemId, btn.dataset.itemType );
		} );

		closeBtn.addEventListener( 'click', closeModal );
		cancelBtn.addEventListener( 'click', closeModal );
		modal.addEventListener( 'click', ( e ) => { if ( e.target === modal ) closeModal(); } );
		document.addEventListener( 'keydown', ( e ) => { if ( e.key === 'Escape' && ! modal.hidden ) closeModal(); } );

		submitBtn.addEventListener( 'click', async () => {
			const itemId   = itemIdEl.value;
			const itemType = itemTypeEl.value;
			const reason   = reasonEl.value;
			const notes    = notesEl.value;

			if ( ! reason ) return;

			submitBtn.disabled = true;
			feedback.hidden    = true;

			const form = new FormData();
			form.append( 'action', 'arshid6social_submit_report' );
			form.append( 'nonce', AJAX_NONCE );
			form.append( 'item_id', itemId );
			form.append( 'item_type', itemType );
			form.append( 'reason', reason );
			form.append( 'notes', notes );
			if ( fileEl && fileEl.files[0] ) {
				form.append( 'attachment', fileEl.files[0] );
			}

			try {
				const res = await fetch( AJAX_URL, { method: 'POST', body: form } );
				const data = await res.json();

				feedback.hidden    = false;
				feedback.className = 'arshid6social-report-feedback ' + ( data.success ? 'arshid6social-report-feedback--success' : 'arshid6social-report-feedback--error' );
				feedback.textContent = data.data?.message || ( data.success ? 'Report submitted.' : 'Error.' );

				if ( data.success ) {
					submitBtn.disabled = true;
					setTimeout( closeModal, 2500 );
				} else {
					submitBtn.disabled = false;
				}
			} catch {
				feedback.hidden      = false;
				feedback.className   = 'arshid6social-report-feedback arshid6social-report-feedback--error';
				feedback.textContent = I18N.error || 'Something went wrong.';
				submitBtn.disabled   = false;
			}
		} );
	}

	// -- Post-payment redirect handler --------------------------------------
	( function sixarshidscHandleReturnFromPayment() {
		if ( ! EJTEMSN_ON || ! EJTEMSN_REST ) return;

		const params     = new URLSearchParams( location.search );
		const activityId = params.get( 'sixarshidsc_paid_activity' );
		if ( ! activityId ) return;

		// Clean the URL so a manual refresh doesn't re-trigger the handler.
		const cleanUrl = new URL( location.href );
		cleanUrl.searchParams.delete( 'sixarshidsc_paid_activity' );
		cleanUrl.searchParams.delete( 'payment_intent' );
		cleanUrl.searchParams.delete( 'payment_intent_client_secret' );
		cleanUrl.searchParams.delete( 'redirect_status' );
		history.replaceState( null, '', cleanUrl.toString() );

		function scrollAndToast() {
			showNotice( I18N.postUnlocked || 'Post unlocked! Payment confirmed.', 'success' );
			const actEl = document.getElementById( 'arshid6social-activity-' + activityId );
			if ( actEl ) {
				actEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				actEl.style.transition = 'box-shadow .4s';
				actEl.style.boxShadow  = '0 0 0 3px var(--sn-primary, #6366f1)';
				setTimeout( () => { actEl.style.boxShadow = ''; }, 2500 );
			}
		}

		// Poll until the webhook has granted entitlement (needed for 3DS redirects).
		let polls = 0;
		const pollInterval = setInterval( async () => {
			polls++;
			try {
				const r = await fetch( EJTEMSN_REST + 'ppv/' + activityId + '/status', {
					headers: { 'X-WP-Nonce': cfg.nonce },
				} );
				const d = await r.json();
				if ( d.entitled ) {
					clearInterval( pollInterval );
					setTimeout( scrollAndToast, 800 ); // brief delay for feed to render
					return;
				}
			} catch {}
			if ( polls >= 30 ) {
				clearInterval( pollInterval );
				showNotice( I18N.paymentProcessing || 'Payment processing. Your post will unlock shortly.', 'info' );
			}
		}, 1500 );
	} )();

} )();
