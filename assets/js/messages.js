( function() {
	'use strict';

	// Only run on the messages page.
	if ( ! document.getElementById( 'arshid6social-messages-page' ) ) return;

	// -- Toast -------------------------------------------------------------
	function ARSHID6SOCIALToast( msg, type ) {
		var wrap = document.getElementById( 'arshid6social-toast-wrap' );
		if ( ! wrap ) {
			wrap = document.createElement( 'div' );
			wrap.id = 'arshid6social-toast-wrap';
			wrap.style.cssText = 'position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;align-items:center;gap:.5rem;pointer-events:none;';
			document.body.appendChild( wrap );
		}
		var t = document.createElement( 'div' );
		t.textContent = msg;
		t.style.cssText = [
			'padding:.65rem 1.25rem',
			'border-radius:.5rem',
			'font-size:.9rem',
			'font-weight:500',
			'color:#fff',
			'max-width:90vw',
			'word-break:break-word',
			'box-shadow:0 4px 12px rgba(0,0,0,.25)',
			'opacity:1',
			'transition:opacity .4s',
			'background:' + ( type === 'error' ? '#dc2626' : type === 'success' ? '#16a34a' : '#1d4ed8' ),
		].join( ';' );
		wrap.appendChild( t );
		setTimeout( function() {
			t.style.opacity = '0';
			setTimeout( function() { t.remove(); }, 450 );
		}, type === 'error' ? 7000 : 3000 );
	}

	// -- Bootstrap: read PHP data from data attribute ----------------------
	var _d = {};
	try {
		var _el = document.getElementById( 'arshid6social-messages-page' );
		var _raw = _el ? _el.getAttribute( 'data-arshid6social-cfg' ) : '';
		if ( _raw ) _d = JSON.parse( _raw ) || {};
	} catch ( _e ) {
		ARSHID6SOCIALToast( 'Config error: ' + _e.message, 'error' );
	}
	var cfg        = window.ARSHID6SOCIALConfig || {};
	var AJAX_URL   = _d.ajaxUrl   || cfg.ajaxUrl   || '';
	var AJAX_NONCE = _d.nonce     || cfg.ajaxNonce || '';
	var MY_ID      = _d.userId    || parseInt( cfg.userId ) || 0;
	var MY_NAME    = _d.userName  || cfg.currentUserName   || '';
	var L10N       = _d.l10n || {
		noConversations:   'No conversations yet.',
		loading:           'Loading...',
		noUsersFound:      'No users found.',
		couldNotLoadUsers: 'Could not load users.',
		loadMore:          'Load more',
		errorPrefix:       'Error:',
		conversation:      'Conversation',
		noMessagesYet:     'No messages yet.',
	};
	let activeThreadId = _d.threadId || 0;
	let lastMessageId  = 0;
	let userSearchTimer = null;
	// Infinite scroll state for messages
	let msgPage     = 1;
	let msgHasMore  = false;
	let msgLoading  = false;
	// Pending attachment files (File objects, before upload)
	let pendingFiles = [];
	let pollingTimer = null;
	// Thread list pagination & search state.
	let threadPage        = 1;
	let threadHasMore     = false;
	let threadLoading     = false;
	let threadSearch      = '';
	let threadSearchTimer = null;
	let threadGeneration  = 0;   // bumped on each reset so stale responses are ignored
	let threadObserver    = null; // IntersectionObserver for infinite scroll sentinel

	const layout       = document.getElementById( 'arshid6social-messages-layout' );
	const threadListEl = document.getElementById( 'arshid6social-thread-list' );
	const messagePaneEl = document.getElementById( 'arshid6social-message-pane' );

	// -- Helpers ----------------------------------------------------------

	async function doAjax( action, data ) {
		const form = new FormData();
		form.append( 'action', action );
		form.append( 'nonce', AJAX_NONCE );
		Object.entries( data || {} ).forEach( ( [ k, v ] ) => form.append( k, String( v ) ) );
		let r, text;
		try {
			r    = await fetch( AJAX_URL, { method: 'POST', body: form } );
			text = await r.text();
		} catch ( e ) {
			ARSHID6SOCIALToast( 'Network error: ' + e.message, 'error' );
			return { success: false, data: { message: e.message } };
		}
		try {
			const json = JSON.parse( text );
			if ( ! json.success && json.data?.message ) {
				ARSHID6SOCIALToast( json.data.message, 'error' );
			}
			return json;
		} catch( e ) {
			const preview = text.substring( 0, 200 );
			console.error( '[WPSN] Non-JSON response for', action, r.status, preview );
			ARSHID6SOCIALToast( 'Server error (' + r.status + '): ' + preview, 'error' );
			return { success: false, data: { message: 'HTTP ' + r.status } };
		}
	}

	function escHtml( str ) {
		return String( str ).split('&').join('&amp;').split('<').join('&lt;').split('>').join('&gt;').split('"').join('&quot;');
	}

	function avatarHtml( name, url, sizeClass, profileUrl ) {
		var initial = escHtml( ( name || '?' ).charAt( 0 ).toUpperCase() );
		var cls     = 'arshid6social-avatar ' + sizeClass;
		var img;
		if ( ! url ) {
			img = '<div class="' + cls + ' arshid6social-avatar-initial">' + initial + '</div>';
		} else {
			img = '<img class="' + cls + '" src="' + escHtml(url) + '" alt="' + escHtml(name||'') + '" loading="lazy" />';
		}
		if ( profileUrl ) {
			return '<a href="' + escHtml(profileUrl) + '" class="arshid6social-avatar-link" title="' + escHtml(name||'') + '">' + img + '</a>';
		}
		return img;
	}

	// -- Mobile responsive -------------------------------------------------

	function isMobile() {
		return window.matchMedia( '(max-width: 640px)' ).matches;
	}

	function showPane() {
		if ( isMobile() ) {
			layout.classList.add( 'show-pane' );
			document.getElementById( 'arshid6social-back-btn' ).style.display = 'inline-flex';
		}
	}

	function showThreadList() {
		layout.classList.remove( 'show-pane' );
	}

	document.getElementById( 'arshid6social-back-btn' ).addEventListener( 'click', showThreadList );

	// -- Thread list -------------------------------------------------------

	// Event delegation — set up once; works for all dynamically added thread items.
	( function initThreadListDelegation() {
		var el = document.getElementById( 'arshid6social-thread-list-inner' );
		if ( ! el ) return;
		el.addEventListener( 'click', function( e ) {
			var delBtn = e.target.closest( '.arshid6social-thread-delete-btn' );
			if ( delBtn ) {
				e.stopPropagation();
				showDeleteConfirm( parseInt( delBtn.dataset.threadId ) );
				return;
			}
			var item = e.target.closest( '.arshid6social-thread-item' );
			if ( item ) openThread( parseInt( item.dataset.threadId ), item.dataset.threadName );
		} );
		el.addEventListener( 'keydown', function( e ) {
			var item = e.target.closest( '.arshid6social-thread-item' );
			if ( item && ( e.key === 'Enter' || e.key === ' ' ) ) openThread( parseInt( item.dataset.threadId ), item.dataset.threadName );
		} );
	} )();

	// Search input — debounced 350 ms; always cancels any in-progress load (reset = true).
	document.getElementById( 'arshid6social-thread-search' )?.addEventListener( 'input', function( e ) {
		clearTimeout( threadSearchTimer );
		var val = e.target.value;
		threadSearchTimer = setTimeout( function() {
			threadSearch = val.trim();
			loadThreads( true );
		}, 350 );
	} );

	function renderThreadItem( t ) {
		var others     = t.participants.filter( function(p) { return p.id !== MY_ID; } );
		var p0         = others[0] || t.participants[0] || {};
		var rawName    = t.subject || others.map( function(p) { return p.name; } ).join( ', ' ) || t.participants.map( function(p) { return p.name; } ).join( ', ' );
		var name       = escHtml( rawName );
		var rawPreview = t.lastMessage ? t.lastMessage.content.replace( /<[^>]+>/g, '' ).substring( 0, 60 ) : '';
		var preview    = rawPreview ? escHtml( rawPreview ) : '';
		var cls        = 'arshid6social-thread-item' + ( t.unreadCount > 0 ? ' is-unread' : '' ) + ( activeThreadId === t.id ? ' is-active' : '' );
		var badge      = t.unreadCount > 0 ? '<span class="arshid6social-unread-badge">' + t.unreadCount + '</span>' : '';
		var prevHtml   = preview ? '<div class="arshid6social-thread-item__preview">' + preview + '</div>' : '';
		var delBtn     = '<button class="arshid6social-thread-delete-btn" data-thread-id="' + t.id + '" aria-label="Delete conversation" title="Delete conversation">&#x1F5D1;</button>';
		return '<div class="' + cls + '" data-thread-id="' + t.id + '" data-thread-name="' + name + '" role="button" tabindex="0" aria-label="' + escHtml( L10N.conversation ) + ': ' + name + '">' +
			avatarHtml( p0.name || rawName, p0.avatarUrl || '', 'arshid6social-avatar--sm', p0.profileUrl || '' ) +
			'<div class="arshid6social-thread-item__info"><div class="arshid6social-thread-item__name">' + name + '</div>' + prevHtml + '</div>' +
			badge + delBtn + '</div>';
	}

	// Attach IntersectionObserver sentinel so next page loads when the sentinel enters view.
	function attachThreadSentinel( el ) {
		if ( threadObserver ) { threadObserver.disconnect(); threadObserver = null; }
		if ( ! threadHasMore ) return;

		var sentinel = document.createElement( 'div' );
		sentinel.className   = 'arshid6social-thread-sentinel';
		sentinel.style.height = '1px';
		el.appendChild( sentinel );

		threadObserver = new IntersectionObserver( function( entries ) {
			if ( entries[0].isIntersecting && threadHasMore && ! threadLoading ) {
				threadObserver.disconnect();
				threadObserver = null;
				sentinel.remove();
				loadThreads( false );
			}
		}, { root: el, threshold: 0 } );

		threadObserver.observe( sentinel );
	}

	async function loadThreads( reset ) {
		// reset = true (search / refresh): cancel any in-progress load and start fresh.
		if ( reset ) {
			threadGeneration++;
			threadLoading = false;
			if ( threadObserver ) { threadObserver.disconnect(); threadObserver = null; }
		}

		if ( threadLoading ) return;
		threadLoading = true;

		const myGen = threadGeneration;
		const el    = document.getElementById( 'arshid6social-thread-list-inner' );
		if ( ! el ) { threadLoading = false; return; }

		if ( reset ) {
			threadPage   = 1;
			el.innerHTML = '<div style="padding:1.5rem;text-align:center;"><div class="arshid6social-spinner"></div></div>';
		} else {
			el.insertAdjacentHTML( 'beforeend', '<div class="arshid6social-user-list-loading arshid6social-thread-loadmore-spinner"><div class="arshid6social-spinner"></div></div>' );
		}

		const res = await doAjax( 'arshid6social_get_threads', { page: threadPage, search: threadSearch } );

		// A newer reset fired while we were waiting — discard this stale response.
		if ( myGen !== threadGeneration ) { threadLoading = false; return; }

		if ( reset ) el.innerHTML = '';
		else el.querySelector( '.arshid6social-thread-loadmore-spinner' )?.remove();

		threadLoading = false;

		if ( ! res.success || ! res.data ) {
			if ( reset ) el.innerHTML = '<div class="arshid6social-thread-empty">' + escHtml( L10N.noConversations ) + '</div>';
			return;
		}

		const threads  = res.data.threads || [];
		threadHasMore  = !! res.data.has_more;
		threadPage++;

		if ( reset && ! threads.length ) {
			el.innerHTML = '<div class="arshid6social-thread-empty">' + escHtml( L10N.noConversations ) + '</div>';
			return;
		}

		el.insertAdjacentHTML( 'beforeend', threads.map( renderThreadItem ).join( '' ) );
		attachThreadSentinel( el );

		if ( reset && res.data.total_unread !== undefined ) {
			updateMsgBadge( res.data.total_unread );
		}
	}

	function updateMsgBadge( count ) {
		document.querySelectorAll( '#arshid6social-messages-count' ).forEach( ( el ) => {
			el.textContent = count;
			el.hidden      = count === 0;
		} );
	}

	// -- Open thread -------------------------------------------------------

	async function openThread( id, name ) {
		activeThreadId = id;
		msgPage        = 1;
		msgHasMore     = false;
		msgLoading     = false;

		document.getElementById( 'arshid6social-message-composer' ).hidden = false;
		document.getElementById( 'arshid6social-pane-title' ).textContent = name || L10N.loading;

		showPane();

		const list = document.getElementById( 'arshid6social-message-list' );
		if ( list ) list.innerHTML = '<div class="arshid6social-user-list-loading"><div class="arshid6social-spinner"></div></div>';

		// Load latest 10 messages (DESC order, page 1) then reverse for display.
		const res = await doAjax( 'arshid6social_get_thread_messages', { thread_id: id, page: 1, order: 'DESC', per_page: 10 } );
		if ( ! res.success || ! list ) return;

		const messages = ( res.data?.messages || [] ).reverse(); // oldest first, newest last
		msgHasMore     = ( res.data?.total_pages || 1 ) > 1;

		list.innerHTML = messages.length
			? messages.map( m => renderBubble( m, MY_ID ) ).join( '' )
			: '<div class="arshid6social-thread-empty" id="arshid6social-thread-empty">' + escHtml( L10N.noMessagesYet ) + '</div>';

		if ( messages.length ) {
			lastMessageId = messages[ messages.length - 1 ].id;
		}

		list.scrollTop = list.scrollHeight;
		startPolling();

		// Add scroll listener for infinite scroll upward.
		list.onscroll = () => {
			if ( list.scrollTop < 60 && msgHasMore && ! msgLoading ) {
				loadOlderMessages();
			}
		};

		document.querySelectorAll( '.arshid6social-thread-item' ).forEach( i => {
			i.classList.toggle( 'is-active', parseInt( i.dataset.threadId ) === id );
			if ( parseInt( i.dataset.threadId ) === id ) {
				const badge = i.querySelector( '.arshid6social-unread-badge' );
				if ( badge ) {
					const removed = parseInt( badge.textContent ) || 0;
					badge.remove();
					const navBadge = document.querySelector( '#arshid6social-messages-count' );
					if ( navBadge ) {
						const newCount = Math.max( 0, ( parseInt( navBadge.textContent ) || 0 ) - removed );
						updateMsgBadge( newCount );
					}
				}
				i.classList.remove( 'is-unread' );
			}
		} );
	}

	async function loadOlderMessages() {
		if ( msgLoading || ! msgHasMore ) return;
		msgLoading = true;

		const list = document.getElementById( 'arshid6social-message-list' );
		const prevScrollHeight = list.scrollHeight;

		// Show spinner at top.
		const spinner = document.createElement( 'div' );
		spinner.className = 'arshid6social-user-list-loading';
		spinner.innerHTML = '<div class="arshid6social-spinner"></div>';
		list.prepend( spinner );

		msgPage++;
		const res = await doAjax( 'arshid6social_get_thread_messages', { thread_id: activeThreadId, page: msgPage, order: 'DESC', per_page: 10 } );

		spinner.remove();
		msgLoading = false;

		if ( ! res.success ) return;

		const older = ( res.data?.messages || [] ).reverse();
		msgHasMore  = msgPage < ( res.data?.total_pages || 1 );

		if ( older.length ) {
			const html = older.map( m => renderBubble( m, MY_ID ) ).join( '' );
			list.insertAdjacentHTML( 'afterbegin', html );
			// Restore scroll position so user doesn't jump.
			list.scrollTop = list.scrollHeight - prevScrollHeight;
		}
	}

	function renderAttachmentHtml( att ) {
		// att.serve_url is already HTML-safe (output of PHP esc_url()), so use it directly.
		// Running escHtml() on it would double-encode &amp; → &amp;amp; breaking the URL.
		var url  = att.serve_url || '';
		var name = escHtml( att.file_name || '' );
		if ( att.media_type === 'image' ) {
			return '<div class="arshid6social-bubble-att-item"><img src="' + url + '" class="arshid6social-att-thumb" alt="' + name + '" loading="lazy"></div>';
		} else if ( att.media_type === 'audio' ) {
			return '<div class="arshid6social-bubble-att-item"><audio controls class="arshid6social-att-audio" src="' + url + '"></audio></div>';
		}
		return '<div class="arshid6social-bubble-att-item"><a href="' + url + '" class="arshid6social-att-file" target="_blank" rel="noopener noreferrer">' + name + '</a></div>';
	}

	function renderBubble( m, myId ) {
		var mine     = m.senderId === myId || m.isMine;
		var cls      = 'arshid6social-message-bubble' + ( mine ? ' arshid6social-message-bubble--mine' : '' );
		var sender   = mine ? '' : '<div class="arshid6social-message-bubble__sender">' + escHtml(m.senderName) + '</div>';
		var editedLbl = m.isEdited ? '<span class="arshid6social-msg-edited">(' + escHtml(L10N.edited || 'edited') + ')</span>' : '';
		var editBtn  = mine ? '<button class="arshid6social-msg-action arshid6social-msg-action--edit" data-action="edit" aria-label="' + escHtml(L10N.edit || 'Edit') + '">' + escHtml(L10N.edit || 'Edit') + '</button>' : '';
		var delBtn   = '<button class="arshid6social-msg-action arshid6social-msg-action--delete" data-action="delete" aria-label="' + escHtml(L10N.delete || 'Delete') + '">' + escHtml(L10N.delete || 'Delete') + '</button>';
		var actions  = '<div class="arshid6social-msg-actions">' + editBtn + delBtn + '</div>';
		var attsHtml = '';
		if ( m.attachments && m.attachments.length ) {
			attsHtml = '<div class="arshid6social-bubble-att-list">' + m.attachments.map( renderAttachmentHtml ).join( '' ) + '</div>';
		}
		var contentHtml = m.message ? '<div class="arshid6social-message-bubble__content">' + m.message + '</div>' : '';
		return '<div class="' + cls + '" data-message-id="' + ( m.id || '' ) + '" data-is-mine="' + ( mine ? '1' : '0' ) + '">' +
			avatarHtml( m.senderName, m.senderAvatar, 'arshid6social-avatar--sm', m.senderProfileUrl || '' ) +
			'<div class="arshid6social-message-bubble__body">' +
			sender +
			contentHtml +
			attsHtml +
			'<div class="arshid6social-message-bubble__meta">' + editedLbl + '</div>' +
			'</div>' +
			actions +
			'</div>';
	}

	// -- Attachment pending files ------------------------------------------

	function renderPendingFilePreviews() {
		const preview = document.getElementById( 'arshid6social-att-preview' );
		if ( ! preview ) return;
		if ( ! pendingFiles.length ) {
			preview.hidden = true;
			preview.innerHTML = '';
			return;
		}
		preview.hidden = false;
		preview.innerHTML = pendingFiles.map( ( f, i ) =>
			'<div class="arshid6social-att-pending-item">' +
			'<span class="arshid6social-att-pending-name">' + escHtml( f.name ) + '</span>' +
			'<button type="button" class="arshid6social-att-pending-remove" data-index="' + i + '" aria-label="' + escHtml( L10N.removeAttachment || 'Remove' ) + '">&#x2715;</button>' +
			'</div>'
		).join( '' );
		preview.querySelectorAll( '.arshid6social-att-pending-remove' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				pendingFiles.splice( parseInt( this.dataset.index ), 1 );
				renderPendingFilePreviews();
			} );
		} );
	}

	( function initAttachBtn() {
		const attachBtn   = document.getElementById( 'arshid6social-attach-btn' );
		const attachInput = document.getElementById( 'arshid6social-attach-input' );
		if ( ! attachBtn || ! attachInput ) return;

		const attCfg  = _d.attachments || {};
		const maxBytes = ( attCfg.maxMb || 10 ) * 1024 * 1024;

		attachBtn.addEventListener( 'click', () => attachInput.click() );
		attachInput.addEventListener( 'change', function () {
			Array.from( this.files || [] ).forEach( file => {
				if ( file.size > maxBytes ) {
					ARSHID6SOCIALToast( ( L10N.fileTooLarge || 'File too large.' ), 'error' );
					return;
				}
				pendingFiles.push( file );
			} );
			this.value = '';
			renderPendingFilePreviews();
		} );
	} )();

	function appendAttachmentToBubble( bubble, att ) {
		let attList = bubble.querySelector( '.arshid6social-bubble-att-list' );
		if ( ! attList ) {
			attList = document.createElement( 'div' );
			attList.className = 'arshid6social-bubble-att-list';
			const body = bubble.querySelector( '.arshid6social-message-bubble__body' );
			const meta = body?.querySelector( '.arshid6social-message-bubble__meta' );
			if ( meta ) body.insertBefore( attList, meta );
			else if ( body ) body.appendChild( attList );
		}
		attList.insertAdjacentHTML( 'beforeend', renderAttachmentHtml( att ) );
	}

	async function uploadFilesForMessage( msgId, files, list ) {
		for ( const file of files ) {
			const fd = new FormData();
			fd.append( 'action',      'arshid6social_message_upload_attachment' );
			fd.append( 'nonce',       AJAX_NONCE );
			fd.append( 'message_id',  msgId );
			fd.append( 'attachment',  file );
			try {
				const r    = await fetch( AJAX_URL, { method: 'POST', body: fd } );
				const data = await r.json();
				if ( data.success ) {
					const bubble = list?.querySelector( '.arshid6social-message-bubble[data-message-id="' + msgId + '"]' );
					if ( bubble ) appendAttachmentToBubble( bubble, data.data );
				} else if ( data.data?.message ) {
					ARSHID6SOCIALToast( data.data.message, 'error' );
				}
			} catch ( e ) {
				// network error — silently skip
			}
		}
	}

	// -- Send message ------------------------------------------------------

	document.getElementById( 'arshid6social-send-btn' )?.addEventListener( 'click', sendMessage );
	document.getElementById( 'arshid6social-message-input' )?.addEventListener( 'keydown', e => {
		if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); sendMessage(); }
	} );

	async function sendMessage() {
		const input   = document.getElementById( 'arshid6social-message-input' );
		const sendBtn = document.getElementById( 'arshid6social-send-btn' );
		const content = input?.value.trim();
		const files   = pendingFiles.slice();

		if ( ( ! content && ! files.length ) || ! activeThreadId ) return;

		input.value    = '';
		input.disabled = true;
		if ( sendBtn ) sendBtn.disabled = true;
		pendingFiles = [];
		renderPendingFilePreviews();

		const res = await doAjax( 'arshid6social_send_message', { thread_id: activeThreadId, content: content || '' } );
		if ( res.success ) {
			const list  = document.getElementById( 'arshid6social-message-list' );
			const msgId = res.data?.message_id || 0;
			const dummy = {
				id:               msgId,
				senderId:         MY_ID,
				isMine:           true,
				senderName:       MY_NAME,
				senderAvatar:     cfg.currentUserAvatar || '',
				senderProfileUrl: _d.currentUserProfileUrl || '',
				message:          content ? content.replace( /</g, '&lt;' ) : '',
				attachments:      [],
			};
			list?.querySelector( '#arshid6social-thread-empty' )?.remove();
			list?.insertAdjacentHTML( 'beforeend', renderBubble( dummy, MY_ID ) );
			if ( list ) list.scrollTop = list.scrollHeight;
			if ( msgId ) {
				lastMessageId = msgId;
				if ( files.length ) {
					uploadFilesForMessage( msgId, files, list );
				}
			}
		}

		input.disabled = false;
		if ( sendBtn ) sendBtn.disabled = false;
		input.focus();
	}

	// -- Compose modal (new message) ---------------------------------------

	const modal        = document.getElementById( 'arshid6social-compose-modal' );
	const PER_PAGE     = 10;
	let userPage       = 1;
	let userSearch     = '';
	let userTotalPages = 1;
	let userLoading    = false;

	document.getElementById( 'arshid6social-compose-btn' ).addEventListener( 'click', openComposeModal );

	function openComposeModal() {
		modal.hidden  = false;
		userLoading   = false;
		userSearch    = '';
		userPage      = 1;
		userTotalPages = 1;
		document.getElementById( 'arshid6social-user-search' ).value = '';
		document.getElementById( 'arshid6social-user-list' ).innerHTML = '<div class="arshid6social-user-list-loading"><div class="arshid6social-spinner"></div></div>';
		loadUsers( true );
		setTimeout( () => document.getElementById( 'arshid6social-user-search' ).focus(), 50 );
	}

	document.getElementById( 'arshid6social-compose-close' ).addEventListener( 'click', closeModal );
	modal.addEventListener( 'click', e => { if ( e.target === modal ) closeModal(); } );
	document.addEventListener( 'keydown', e => { if ( e.key === 'Escape' && ! modal.hidden ) closeModal(); } );

	function closeModal() {
		modal.hidden = true;
		userLoading  = false;
	}

	document.getElementById( 'arshid6social-user-search' ).addEventListener( 'input', e => {
		clearTimeout( userSearchTimer );
		userSearchTimer = setTimeout( () => {
			userSearch = e.target.value.trim();
			userPage   = 1;
			loadUsers( true );
		}, 300 );
	} );

	async function loadUsers( reset ) {
		if ( userLoading ) return;
		userLoading = true;

		const list = document.getElementById( 'arshid6social-user-list' );

		if ( reset ) {
			list.innerHTML = '<div class="arshid6social-user-list-loading"><div class="arshid6social-spinner"></div></div>';
		} else {
			list.querySelector( '.arshid6social-user-list-more' )?.remove();
			list.insertAdjacentHTML( 'beforeend', '<div class="arshid6social-user-list-loading"><div class="arshid6social-spinner"></div></div>' );
		}

		let res;
		try {
			res = await doAjax( 'arshid6social_get_members', {
				search:   userSearch,
				type:     'newest',
				page:     userPage,
				per_page: PER_PAGE,
			} );
		} catch ( err ) {
			list.querySelector( '.arshid6social-user-list-loading' )?.remove();
			userLoading = false;
			console.error( '[WPSN] loadUsers error:', err );
			if ( reset ) list.innerHTML = '<div class="arshid6social-thread-empty">' + escHtml( L10N.errorPrefix ) + ' ' + escHtml( String( err.message ) ) + '</div>';
			return;
		}

		list.querySelector( '.arshid6social-user-list-loading' )?.remove();
		userLoading = false;

		if ( ! res || ! res.success ) {
			if ( reset ) {
				const msg = ( res?.data?.message ) || L10N.couldNotLoadUsers;
				console.error( '[WPSN] arshid6social_get_members failed:', res );
				list.innerHTML = '<div class="arshid6social-thread-empty">' + escHtml( msg ) + '</div>';
			}
			return;
		}

		const members  = res.data.members || [];
		userTotalPages = res.data.total_pages || 1;

		if ( reset && ! members.length ) {
			list.innerHTML = '<div class="arshid6social-thread-empty">' + escHtml( L10N.noUsersFound ) + '</div>';
			return;
		}

		if ( reset ) list.innerHTML = '';

		members.forEach( u => {
			const item = document.createElement( 'div' );
			item.className        = 'arshid6social-user-list-item';
			item.dataset.userId   = u.id;
			item.dataset.userName = u.name;
			item.setAttribute( 'role', 'option' );
			item.setAttribute( 'tabindex', '0' );
			var usernamePart = u.username ? '<div class="arshid6social-user-list-item__username">@' + escHtml(u.username) + '</div>' : '';
			item.innerHTML = avatarHtml( u.name, u.avatarUrl, 'arshid6social-avatar--sm' ) +
				'<div class="arshid6social-user-list-item__info">' +
				'<div class="arshid6social-user-list-item__name">' + escHtml(u.name) + '</div>' +
				usernamePart +
				'</div>';
			item.addEventListener( 'click', () => startConversation( parseInt( item.dataset.userId ), item.dataset.userName ) );
			item.addEventListener( 'keydown', e => { if ( e.key === 'Enter' || e.key === ' ' ) startConversation( parseInt( item.dataset.userId ), item.dataset.userName ); } );
			list.appendChild( item );
		} );

		if ( userPage < userTotalPages ) {
			const btn = document.createElement( 'button' );
			btn.className   = 'arshid6social-user-list-more';
			btn.textContent = L10N.loadMore;
			btn.addEventListener( 'click', () => { userPage++; loadUsers( false ); } );
			list.appendChild( btn );
		}
	}

	async function startConversation( recipientId, recipientName ) {
		closeModal();

		// Get existing thread or create a new empty one (server-side dedup).
		const res = await doAjax( 'arshid6social_get_or_create_thread', { recipient_id: recipientId } );

		if ( res.success && res.data.thread_id ) {
			await loadThreads( true );
			openThread( res.data.thread_id, recipientName );
		}
	}

	// -- Delete thread -------------------------------------------------------

	let deleteConfirmThreadId = 0;

	function showDeleteConfirm( threadId ) {
		deleteConfirmThreadId = threadId;
		var modal = document.getElementById( 'arshid6social-delete-confirm-modal' );
		if ( modal ) modal.hidden = false;
	}

	function closeDeleteConfirm() {
		deleteConfirmThreadId = 0;
		var modal = document.getElementById( 'arshid6social-delete-confirm-modal' );
		if ( modal ) modal.hidden = true;
	}

	async function deleteThread( forBoth ) {
		var threadId = deleteConfirmThreadId;
		closeDeleteConfirm();
		if ( ! threadId ) return;

		const res = await doAjax( 'arshid6social_delete_thread', { thread_id: threadId, delete_for_both: forBoth ? 1 : 0 } );
		if ( res.success ) {
			const item = document.querySelector( '.arshid6social-thread-item[data-thread-id="' + threadId + '"]' );
			if ( item ) item.remove();
			if ( activeThreadId === threadId ) {
				activeThreadId = 0;
				const pane = document.getElementById( 'arshid6social-message-list' );
				if ( pane ) pane.innerHTML = '';
				document.getElementById( 'arshid6social-message-composer' ).hidden = true;
				document.getElementById( 'arshid6social-pane-title' ).textContent = '';
				if ( isMobile() ) showThreadList();
			}
			const inner = document.getElementById( 'arshid6social-thread-list-inner' );
			if ( inner && ! inner.querySelector( '.arshid6social-thread-item' ) ) {
				inner.innerHTML = '<div class="arshid6social-thread-empty">' + escHtml( L10N.noConversations ) + '</div>';
			}
		}
	}

	// Wire up delete confirm modal buttons after DOM ready.
	(function initDeleteModal() {
		var modal   = document.getElementById( 'arshid6social-delete-confirm-modal' );
		var btnSelf = document.getElementById( 'arshid6social-delete-for-self' );
		var btnBoth = document.getElementById( 'arshid6social-delete-for-both' );
		var btnCancel = document.getElementById( 'arshid6social-delete-cancel' );
		if ( btnSelf )   btnSelf.addEventListener( 'click', () => deleteThread( false ) );
		if ( btnBoth )   btnBoth.addEventListener( 'click', () => deleteThread( true ) );
		if ( btnCancel ) btnCancel.addEventListener( 'click', closeDeleteConfirm );
		if ( modal )     modal.addEventListener( 'click', e => { if ( e.target === modal ) closeDeleteConfirm(); } );
		document.addEventListener( 'keydown', e => { if ( e.key === 'Escape' && modal && ! modal.hidden ) closeDeleteConfirm(); } );
	})();

	// -- Single message edit / delete ----------------------------------------

	let pendingDeleteMsgId  = 0;
	let pendingDeleteIsMine = false;

	function openDeleteMsgModal( messageId, isMine ) {
		pendingDeleteMsgId  = messageId;
		pendingDeleteIsMine = isMine;
		var modal    = document.getElementById( 'arshid6social-delete-msg-modal' );
		var btnBoth  = document.getElementById( 'arshid6social-delete-msg-both' );
		if ( modal )   modal.hidden   = false;
		if ( btnBoth ) btnBoth.hidden = ! isMine;
	}

	function closeDeleteMsgModal() {
		pendingDeleteMsgId  = 0;
		pendingDeleteIsMine = false;
		var modal = document.getElementById( 'arshid6social-delete-msg-modal' );
		if ( modal ) modal.hidden = true;
	}

	async function execDeleteMsg( forBoth ) {
		var msgId = pendingDeleteMsgId;
		closeDeleteMsgModal();
		if ( ! msgId ) return;

		const res = await doAjax( 'arshid6social_delete_message', { message_id: msgId, delete_for_both: forBoth ? 1 : 0 } );
		if ( res.success ) {
			var bubble = document.querySelector( '.arshid6social-message-bubble[data-message-id="' + msgId + '"]' );
			if ( bubble ) bubble.remove();
		}
	}

	function startEditMessage( bubble ) {
		if ( bubble.querySelector( '.arshid6social-msg-edit-form' ) ) return;

		var contentEl = bubble.querySelector( '.arshid6social-message-bubble__content' );
		var current   = contentEl ? contentEl.textContent : '';
		var msgId     = parseInt( bubble.dataset.messageId ) || 0;
		if ( ! msgId ) return;

		var form = document.createElement( 'div' );
		form.className = 'arshid6social-msg-edit-form';
		form.innerHTML =
			'<textarea class="arshid6social-msg-edit-input">' + escHtml( current ) + '</textarea>' +
			'<div class="arshid6social-msg-edit-actions">' +
			'<button class="arshid6social-btn arshid6social-btn--primary arshid6social-msg-edit-save">' + escHtml( L10N.save || 'Save' ) + '</button>' +
			'<button class="arshid6social-btn arshid6social-btn--ghost arshid6social-msg-edit-cancel">' + escHtml( L10N.cancel || 'Cancel' ) + '</button>' +
			'</div>';

		var body = bubble.querySelector( '.arshid6social-message-bubble__body' );
		if ( body ) {
			body.appendChild( form );
			if ( contentEl ) contentEl.hidden = true;
		}

		var textarea = form.querySelector( '.arshid6social-msg-edit-input' );
		if ( textarea ) {
			textarea.value = current;
			textarea.focus();
			textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
		}

		form.querySelector( '.arshid6social-msg-edit-cancel' ).addEventListener( 'click', function() {
			form.remove();
			if ( contentEl ) contentEl.hidden = false;
		} );

		form.querySelector( '.arshid6social-msg-edit-save' ).addEventListener( 'click', async function() {
			var newContent = textarea ? textarea.value.trim() : '';
			if ( ! newContent ) return;
			var saveBtn = this;
			saveBtn.disabled = true;

			const res = await doAjax( 'arshid6social_edit_message', { message_id: msgId, content: newContent } );
			if ( res.success ) {
				if ( contentEl ) {
					contentEl.textContent = newContent;
					contentEl.hidden      = false;
				}
				form.remove();
				var metaEl  = bubble.querySelector( '.arshid6social-message-bubble__meta' );
				if ( metaEl && ! metaEl.querySelector( '.arshid6social-msg-edited' ) ) {
					var lbl = document.createElement( 'span' );
					lbl.className   = 'arshid6social-msg-edited';
					lbl.textContent = '(' + ( L10N.edited || 'edited' ) + ')';
					metaEl.appendChild( lbl );
				}
			} else {
				saveBtn.disabled = false;
			}
		} );
	}

	// Event delegation on message list for action buttons.
	document.getElementById( 'arshid6social-message-list' )?.addEventListener( 'click', function( e ) {
		var actionBtn = e.target.closest( '.arshid6social-msg-action' );
		if ( ! actionBtn ) return;

		var bubble  = actionBtn.closest( '.arshid6social-message-bubble' );
		if ( ! bubble ) return;

		var msgId  = parseInt( bubble.dataset.messageId ) || 0;
		var isMine = bubble.dataset.isMine === '1';
		var action = actionBtn.dataset.action;

		if ( action === 'edit' && isMine ) {
			startEditMessage( bubble );
		} else if ( action === 'delete' ) {
			openDeleteMsgModal( msgId, isMine );
		}
	} );

	(function initDeleteMsgModal() {
		var modal     = document.getElementById( 'arshid6social-delete-msg-modal' );
		var btnBoth   = document.getElementById( 'arshid6social-delete-msg-both' );
		var btnSelf   = document.getElementById( 'arshid6social-delete-msg-self' );
		var btnCancel = document.getElementById( 'arshid6social-delete-msg-cancel' );
		if ( btnBoth )   btnBoth.addEventListener( 'click', () => execDeleteMsg( true ) );
		if ( btnSelf )   btnSelf.addEventListener( 'click', () => execDeleteMsg( false ) );
		if ( btnCancel ) btnCancel.addEventListener( 'click', closeDeleteMsgModal );
		if ( modal )     modal.addEventListener( 'click', e => { if ( e.target === modal ) closeDeleteMsgModal(); } );
		document.addEventListener( 'keydown', e => { if ( e.key === 'Escape' && modal && ! modal.hidden ) closeDeleteMsgModal(); } );
	})();

	// -- Real-time polling -------------------------------------------------

	function startPolling() {
		stopPolling();
		if ( ! activeThreadId ) return;
		pollingTimer = setInterval( pollNewMessages, 2000 );
	}

	function stopPolling() {
		if ( pollingTimer ) {
			clearInterval( pollingTimer );
			pollingTimer = null;
		}
	}

	async function pollNewMessages() {
		if ( ! activeThreadId ) return;
		const res = await doAjax( 'arshid6social_poll_messages', {
			thread_id:       activeThreadId,
			last_message_id: lastMessageId,
		} );
		if ( ! res.success ) return;

		const msgs = res.data?.messages || [];
		const list = document.getElementById( 'arshid6social-message-list' );
		msgs.forEach( m => {
			if ( ! list ) return;
			if ( list.querySelector( '.arshid6social-message-bubble[data-message-id="' + m.id + '"]' ) ) {
				lastMessageId = Math.max( lastMessageId, m.id );
				return;
			}
			list.querySelector( '.arshid6social-thread-empty' )?.remove();
			const isNearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
			list.insertAdjacentHTML( 'beforeend', renderBubble( m, MY_ID ) );
			if ( isNearBottom ) list.scrollTop = list.scrollHeight;
			lastMessageId = Math.max( lastMessageId, m.id );
		} );

		if ( res.data?.unread_count !== undefined ) {
			updateMsgBadge( res.data.unread_count );
		}
	}

	document.addEventListener( 'visibilitychange', function() {
		if ( document.hidden ) {
			stopPolling();
		} else if ( activeThreadId ) {
			startPolling();
		}
	} );

	// -- Heartbeat polling --------------------------------------------------

	document.addEventListener( 'heartbeat-send', e => {
		if ( activeThreadId && lastMessageId ) {
			e.detail.arshid6social_messages_poll = { thread_id: activeThreadId, last_message_id: lastMessageId };
		}
	} );

	document.addEventListener( 'heartbeat-tick', e => {
		const data = e.detail;
		if ( data.arshid6social_new_messages ) {
			const list = document.getElementById( 'arshid6social-message-list' );
			data.arshid6social_new_messages.forEach( m => {
				if ( list ) {
					list.querySelector( '.arshid6social-thread-empty' )?.remove();
					list.insertAdjacentHTML( 'beforeend', renderBubble( m, MY_ID ) );
					list.scrollTop = list.scrollHeight;
					lastMessageId  = Math.max( lastMessageId, m.id );
				}
			} );
		}

		if ( data.arshid6social_unread_count !== undefined ) {
			updateMsgBadge( data.arshid6social_unread_count );
		}
	} );

	var composeRecipientId = _d.composeRecipientId || 0;
	if ( composeRecipientId ) {
		startConversation( composeRecipientId, '' );
	} else {
		loadThreads( true ).then( function() {
			if ( activeThreadId ) {
				var tlInner  = document.getElementById( 'arshid6social-thread-list-inner' );
				var initItem = tlInner ? tlInner.querySelector( '.arshid6social-thread-item[data-thread-id="' + activeThreadId + '"]' ) : null;
				openThread( activeThreadId, initItem ? initItem.dataset.threadName : '' );
			}
		} );
	}
} )();
