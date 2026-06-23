/**
 * 6Arshid Social Community \u2014 Stories
 *
 * Handles:
 *  - Tray rendering and open/close
 *  - Full-screen viewer with progress bars, tap zones, keyboard nav
 *  - Story creator (text / photo / video)
 *  - Reactions, replies, viewers panel, mute, report, delete
 *  - Highlights management
 */
( function () {
	'use strict';

	/* -- Config ------------------------------------------------ */
	const nonce      = window.ARSHID6SOCIALStories?.nonce  || '';
	const ajaxUrl    = window.ARSHID6SOCIALStories?.ajaxUrl || window.ajaxurl || '';
	const viewerId   = parseInt( window.ARSHID6SOCIALStories?.viewerId || 0, 10 );
	const isLoggedIn = !! viewerId;

	/* -- Viewer state ------------------------------------------- */
	let stories      = [];  // current tray rows loaded from server
	let storyIndex   = 0;   // which story (user) we're viewing
	let itemIndex    = 0;   // which item (within story) we're viewing
	let currentItems = [];  // story items from get_story_items
	let timer        = null;
	let paused       = false;
	let timerStart   = 0;
	let elapsed      = 0;

	/* -- DOM refs ----------------------------------------------- */
	const $viewer      = () => document.getElementById( 'sn-story-viewer' );
	const $creator     = () => document.getElementById( 'sn-story-creator' );
	const $addBtn      = () => document.getElementById( 'sn-add-story-btn' );
	const $progressRow = () => document.getElementById( 'sn-story-progress' );
	const $avatar      = () => document.getElementById( 'sn-story-avatar' );
	const $nameEl      = () => document.getElementById( 'sn-story-name' );
	const $timeEl      = () => document.getElementById( 'sn-story-time' );
	const $userLink    = () => document.getElementById( 'sn-story-user-link' );
	const $mediaImg    = () => document.getElementById( 'sn-story-media-img' );
	const $mediaVideo  = () => document.getElementById( 'sn-story-media-video' );
	const $textCard    = () => document.getElementById( 'sn-story-text-card' );
	const $muteBtn     = () => document.getElementById( 'sn-story-mute-btn' );
	const $deleteBtn   = () => document.getElementById( 'sn-story-delete-btn' );
	const $highlightBtn = () => document.getElementById( 'sn-story-highlight-btn' );
	const $viewersBtn  = () => document.getElementById( 'sn-story-viewers-btn' );
	const $viewersCount = () => document.getElementById( 'sn-story-viewers-count' );
	const $viewersPanel = () => document.getElementById( 'sn-story-viewers-panel' );
	const $replyInput  = () => document.getElementById( 'sn-story-reply-input' );
	const $replySend   = () => document.getElementById( 'sn-story-reply-send' );
	const $reportBtn   = () => document.getElementById( 'sn-story-report-btn' );

	/* -- Helpers ----------------------------------------------- */
	function ajax( action, data ) {
		const form = new FormData();
		form.append( 'action', action );
		form.append( 'nonce', nonce );
		Object.entries( data || {} ).forEach( ( [ k, v ] ) => form.append( k, v ) );
		return fetch( ajaxUrl, { method: 'POST', body: form } ).then( r => r.json() );
	}

	function timeAgo( dateString ) {
		const diff = Date.now() - new Date( dateString ).getTime();
		const m = Math.floor( diff / 60000 );
		if ( m < 60 ) return m + 'm';
		const h = Math.floor( m / 60 );
		if ( h < 24 ) return h + 'h';
		return Math.floor( h / 24 ) + 'd';
	}

	/* -- Tray setup --------------------------------------------- */
	function initTray() {
		// Delegate click on .sn-open-story buttons in the tray or bottom bar.
		document.addEventListener( 'click', e => {
			const bubble = e.target.closest( '.sn-open-story' );
			if ( bubble ) {
				const storyId = parseInt( bubble.dataset.storyId, 10 );
				openViewerForStory( storyId );
			}

			if ( e.target.closest( '#sn-add-story-btn' ) || e.target.closest( '#sn-add-story-btn-bar' ) ) {
				openCreator();
			}
		} );

		// Add padding class to body when bottom bar is present.
		if ( document.getElementById( 'sn-stories-bottom-bar' ) ) {
			document.body.classList.add( 'arshid6social-has-bottom-bar' );
		}
	}

	/* -- Open viewer -------------------------------------------- */
	async function openViewerForStory( storyId ) {
		// Fetch fresh tray so we have the correct order.
		const res = await ajax( 'arshid6social_get_story_tray', {} );
		if ( res.success ) {
			stories = res.data.stories;
		}
		storyIndex = stories.findIndex( s => parseInt( s.id, 10 ) === storyId );
		if ( storyIndex < 0 ) storyIndex = 0;

		openViewer();
	}

	function openViewer() {
		const viewer = $viewer();
		if ( ! viewer ) return;
		viewer.hidden = false;
		viewer.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';
		adminBarHide();
		loadCurrentStory();

		// Keyboard navigation.
		viewer.addEventListener( 'keydown', handleViewerKey );
		viewer.focus();
	}

	function closeViewer() {
		const viewer = $viewer();
		if ( ! viewer ) return;
		stopTimer();
		viewer.hidden = true;
		viewer.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
		adminBarRestore();
		viewer.removeEventListener( 'keydown', handleViewerKey );
		// Refresh unseen rings on the tray.
		refreshTrayRings();
	}

	/* -- Admin bar helpers -------------------------------------- */
	function adminBarHide() {
		const bar = document.getElementById( 'wpadminbar' );
		if ( ! bar ) return;
		bar.dataset.snPrevDisplay = bar.style.display;
		bar.style.setProperty( 'display', 'none', 'important' );
		document.documentElement.style.setProperty( 'margin-top', '0', 'important' );
	}
	function adminBarRestore() {
		const bar = document.getElementById( 'wpadminbar' );
		if ( ! bar ) return;
		bar.style.display = bar.dataset.snPrevDisplay || '';
		document.documentElement.style.marginTop = '';
	}

	/* -- Story loading ------------------------------------------ */
	async function loadCurrentStory() {
		if ( ! stories[ storyIndex ] ) { closeViewer(); return; }
		const story = stories[ storyIndex ];

		// Header.
		if ( $avatar() ) { $avatar().src = story.avatar || ''; $avatar().alt = story.display_name; }
		if ( $nameEl() ) $nameEl().textContent = story.display_name;
		if ( $timeEl() ) $timeEl().textContent = timeAgo( story.expires_at );
		if ( $userLink() ) $userLink().href = story.profile_url || '#';

		// Own-story controls.
		const isOwn = parseInt( story.user_id, 10 ) === viewerId;
		if ( $muteBtn()  ) $muteBtn().hidden  = isOwn;
		if ( $deleteBtn() ) $deleteBtn().hidden = ! isOwn;
		if ( $highlightBtn() ) $highlightBtn().hidden = ! isOwn;
		if ( $viewersBtn() ) $viewersBtn().hidden = ! isOwn;

		// Fetch items.
		const res = await ajax( 'arshid6social_get_story_items', { story_id: story.id } );
		if ( res.success ) {
			currentItems = res.data.items;
		} else {
			currentItems = [];
		}
		if ( ! currentItems.length ) { nextStory(); return; }
		itemIndex = 0;
		buildProgressBars();
		loadItem( 0 );
	}

	function buildProgressBars() {
		const row = $progressRow();
		if ( ! row ) return;
		row.innerHTML = currentItems.map( ( item, i ) =>
			`<div class="sn-story-progress-segment" data-index="${i}">
				<div class="sn-story-progress-fill" id="sn-prog-${i}"></div>
			</div>`
		).join( '' );
	}

	function loadItem( index ) {
		stopTimer();
		if ( ! currentItems[ index ] ) return;
		const item = currentItems[ index ];
		itemIndex = index;

		// Mark past progress bars complete, reset future ones.
		currentItems.forEach( ( _, i ) => {
			const fill = document.getElementById( 'sn-prog-' + i );
			if ( ! fill ) return;
			fill.style.transition = 'none';
			fill.style.width = i < index ? '100%' : '0%';
		} );

		// Show correct media.
		const img   = $mediaImg();
		const video = $mediaVideo();
		const text  = $textCard();
		if ( img )   img.hidden   = true;
		if ( video ) video.hidden = true;
		if ( text )  text.hidden  = true;

		const duration = ( parseInt( item.duration, 10 ) || 5 ) * 1000;

		switch ( item.media_type ) {
			case 'image':
				if ( img ) { img.src = item.file_url; img.hidden = false; }
				break;
			case 'video':
				if ( video ) {
					video.src = item.file_url;
					video.hidden = false;
					video.play().catch( () => {} );
				}
				break;
			default: // text
				if ( text ) {
					text.hidden = false;
					text.textContent = item.text_content;
					text.style.background = item.bg_color || '#2563eb';
				}
				break;
		}

		// Mark viewed.
		if ( isLoggedIn ) {
			ajax( 'arshid6social_mark_story_viewed', { story_item_id: item.id } );
		}

		// Animate progress bar.
		startTimer( index, duration );
	}

	/* -- Timer ------------------------------------------------- */
	function startTimer( index, duration ) {
		stopTimer();
		paused  = false;
		timerStart = performance.now();
		elapsed = 0;

		const fill = document.getElementById( 'sn-prog-' + index );
		if ( fill ) {
			// Force reflow before starting transition.
			// eslint-disable-next-line no-unused-expressions
			fill.offsetWidth;
			fill.style.transition = `width ${duration}ms linear`;
			fill.style.width = '100%';
		}

		timer = setTimeout( () => advanceItem(), duration );
	}

	function stopTimer() {
		clearTimeout( timer );
		timer = null;
	}

	function pauseTimer() {
		if ( paused ) return;
		paused  = true;
		elapsed = performance.now() - timerStart;
		stopTimer();
		// Pause video too.
		const video = $mediaVideo();
		if ( video && ! video.hidden ) video.pause();

		// Freeze progress bar.
		const fill = document.getElementById( 'sn-prog-' + itemIndex );
		if ( fill ) {
			const computed = getComputedStyle( fill ).width;
			fill.style.transition = 'none';
			fill.style.width = computed;
		}
	}

	function resumeTimer() {
		if ( ! paused ) return;
		paused = false;
		const duration = ( parseInt( ( currentItems[ itemIndex ] || {} ).duration, 10 ) || 5 ) * 1000;
		const remaining = duration - elapsed;

		const fill = document.getElementById( 'sn-prog-' + itemIndex );
		if ( fill ) {
			// eslint-disable-next-line no-unused-expressions
			fill.offsetWidth;
			fill.style.transition = `width ${remaining}ms linear`;
			fill.style.width = '100%';
		}

		const video = $mediaVideo();
		if ( video && ! video.hidden ) video.play().catch( () => {} );

		timerStart = performance.now();
		timer = setTimeout( () => advanceItem(), remaining );
	}

	/* -- Navigation --------------------------------------------- */
	function advanceItem() {
		if ( itemIndex + 1 < currentItems.length ) {
			loadItem( itemIndex + 1 );
		} else {
			nextStory();
		}
	}

	function prevItem() {
		if ( itemIndex > 0 ) {
			loadItem( itemIndex - 1 );
		} else {
			prevStory();
		}
	}

	function nextStory() {
		if ( storyIndex + 1 < stories.length ) {
			storyIndex++;
			itemIndex = 0;
			loadCurrentStory();
		} else {
			closeViewer();
		}
	}

	function prevStory() {
		if ( storyIndex > 0 ) {
			storyIndex--;
			itemIndex = 0;
			loadCurrentStory();
		}
	}

	function handleViewerKey( e ) {
		switch ( e.key ) {
			case 'ArrowRight':
			case 'ArrowDown':
				advanceItem();
				break;
			case 'ArrowLeft':
			case 'ArrowUp':
				prevItem();
				break;
			case 'Escape':
				closeViewer();
				break;
			case ' ':
				paused ? resumeTimer() : pauseTimer();
				e.preventDefault();
				break;
		}
	}

	/* -- Tap zones ---------------------------------------------- */
	function initTapZones() {
		const content = document.getElementById( 'sn-story-content' );
		if ( ! content ) return;

		let holdTimeout = null;

		content.addEventListener( 'mousedown', () => {
			holdTimeout = setTimeout( () => pauseTimer(), 180 );
		} );
		content.addEventListener( 'mouseup', e => {
			clearTimeout( holdTimeout );
			if ( paused ) { resumeTimer(); return; }
			const rect = content.getBoundingClientRect();
			if ( e.clientX < rect.left + rect.width / 2 ) {
				prevItem();
			} else {
				advanceItem();
			}
		} );
		content.addEventListener( 'mouseleave', () => {
			clearTimeout( holdTimeout );
			if ( paused ) resumeTimer();
		} );

		// Touch.
		let touchStartX = 0;
		let holdTouch = null;
		content.addEventListener( 'touchstart', e => {
			touchStartX = e.touches[0].clientX;
			holdTouch = setTimeout( () => pauseTimer(), 180 );
		}, { passive: true } );
		content.addEventListener( 'touchend', e => {
			clearTimeout( holdTouch );
			if ( paused ) { resumeTimer(); return; }
			const dx = e.changedTouches[0].clientX - touchStartX;
			if ( Math.abs( dx ) > 60 ) {
				dx < 0 ? nextStory() : prevStory();
			} else {
				const rect = content.getBoundingClientRect();
				const x = e.changedTouches[0].clientX;
				x < rect.left + rect.width / 2 ? prevItem() : advanceItem();
			}
		} );
	}

	/* -- Viewer actions ------------------------------------------ */
	function initViewerActions() {
		// Close.
		document.getElementById( 'sn-story-viewer-close' )?.addEventListener( 'click', closeViewer );

		// Prev / Next arrows.
		document.getElementById( 'sn-story-prev' )?.addEventListener( 'click', prevItem );
		document.getElementById( 'sn-story-next' )?.addEventListener( 'click', advanceItem );

		// Mute.
		$muteBtn()?.addEventListener( 'click', () => {
			const story = stories[ storyIndex ];
			if ( ! story ) return;
			ajax( 'arshid6social_mute_stories', { user_id: story.user_id } ).then( () => {
				closeViewer();
			} );
		} );

		// Delete.
		$deleteBtn()?.addEventListener( 'click', () => {
			if ( ! confirm( window.ARSHID6SOCIALStories?.i18n?.confirmDelete || 'Delete this story?' ) ) return;
			const story = stories[ storyIndex ];
			if ( ! story ) return;
			ajax( 'arshid6social_delete_story', { story_id: story.id } ).then( res => {
				if ( res.success ) {
					stories.splice( storyIndex, 1 );
					if ( stories.length ) {
						if ( storyIndex >= stories.length ) storyIndex = stories.length - 1;
						loadCurrentStory();
					} else {
						closeViewer();
					}
					// Remove bubble from tray and bottom bar.
					document.querySelectorAll( `.sn-story-bubble[data-story-id="${story.id}"]` ).forEach( el => el.remove() );
				}
			} );
		} );

		// Report.
		$reportBtn()?.addEventListener( 'click', () => {
			const story = stories[ storyIndex ];
			if ( ! story ) return;
			ajax( 'arshid6social_report_story', { story_id: story.id, reason: 'spam' } ).then( () => {
				nextStory();
			} );
		} );

		// Reactions.
		document.querySelectorAll( '.sn-react-story' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const item = currentItems[ itemIndex ];
				if ( ! item ) return;
				ajax( 'arshid6social_react_story', { story_item_id: item.id, reaction: btn.dataset.reaction } );

				// Button pop animation.
				btn.classList.remove( 'sn--reacted' );
				void btn.offsetWidth; // reflow to restart animation
				btn.classList.add( 'sn--reacted' );

				// Floating emoji animation.
				const rect = btn.getBoundingClientRect();
				const floater = document.createElement( 'span' );
				floater.className = 'sn-reaction-float';
				floater.textContent = btn.dataset.reaction;
				floater.style.left = ( rect.left + rect.width / 2 - 18 ) + 'px';
				floater.style.top  = ( rect.top  - 10 ) + 'px';
				document.body.appendChild( floater );
				floater.addEventListener( 'animationend', () => floater.remove() );
			} );
		} );

		// Reply.
		$replySend()?.addEventListener( 'click', sendReply );
		$replyInput()?.addEventListener( 'keydown', e => {
			if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); sendReply(); }
			if ( document.activeElement === $replyInput() ) e.stopPropagation();
		} );

		// Pause on reply focus.
		$replyInput()?.addEventListener( 'focus',  () => pauseTimer() );
		$replyInput()?.addEventListener( 'blur',   () => resumeTimer() );

		// Viewers panel.
		$viewersBtn()?.addEventListener( 'click', toggleViewersPanel );

		// Highlight.
		$highlightBtn()?.addEventListener( 'click', () => {
			// Simple prompt for now \u2014 production could show a modal.
			const title = prompt( window.ARSHID6SOCIALStories?.i18n?.highlightTitle || 'Highlight name?' );
			if ( ! title ) return;
			const story = stories[ storyIndex ];
			ajax( 'arshid6social_create_highlight', { title } ).then( res => {
				if ( res.success ) {
					ajax( 'arshid6social_add_to_highlight', {
						story_id: story.id,
						highlight_id: res.data.highlight_id,
					} );
				}
			} );
		} );
	}

	function sendReply() {
		const input = $replyInput();
		if ( ! input || ! input.value.trim() ) return;
		const item = currentItems[ itemIndex ];
		if ( ! item ) return;
		ajax( 'arshid6social_reply_story', { story_item_id: item.id, message: input.value.trim() } ).then( () => {
			input.value = '';
		} );
	}

	async function toggleViewersPanel() {
		const panel = $viewersPanel();
		if ( ! panel ) return;
		if ( ! panel.hidden ) { panel.hidden = true; resumeTimer(); return; }

		pauseTimer();
		panel.hidden = false;
		panel.textContent = '\u2026';

		const item = currentItems[ itemIndex ];
		if ( ! item ) return;
		const res = await ajax( 'arshid6social_get_story_viewers', { story_item_id: item.id } );
		if ( res.success ) {
			const viewers = res.data.viewers;
			panel.innerHTML = viewers.length
				? '<ul style="margin:0;padding:0;list-style:none">' +
				  viewers.map( v =>
					`<li style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,.1)">${escHtml(v.display_name)}</li>`
				  ).join( '' ) + '</ul>'
				: '<p style="margin:0;opacity:.7">' + ( window.ARSHID6SOCIALStories?.i18n?.noViewers || 'No viewers yet.' ) + '</p>';

			if ( $viewersCount() ) $viewersCount().textContent = viewers.length;
		}
	}

	/* -- Tray refresh ------------------------------------------- */
	function refreshTrayRings() {
		ajax( 'arshid6social_get_story_tray', {} ).then( res => {
			if ( ! res.success ) return;
			( res.data.stories || [] ).forEach( story => {
				// Update rings in both the tray and the bottom bar.
				document.querySelectorAll( `.sn-story-bubble[data-story-id="${story.id}"]` ).forEach( bubble => {
					const ring = bubble.querySelector( '.sn-story-bubble__ring' );
					if ( ! ring ) return;
					ring.classList.toggle( 'sn-story-bubble__ring--unseen', parseInt( story.unseen_count, 10 ) > 0 );
					ring.classList.toggle( 'sn-story-bubble__ring--seen',   parseInt( story.unseen_count, 10 ) === 0 );
				} );
			} );
		} );
	}

	/* -- Creator ------------------------------------------------ */
	let creatorMediaType = 'text';
	let creatorFile = null;

	function openCreator() {
		const creator = $creator();
		if ( ! creator ) return;
		creator.hidden = false;
		stopTimer();
	}

	function closeCreator() {
		const creator = $creator();
		if ( creator ) creator.hidden = true;
		creatorFile = null;
		creatorMediaType = 'text';
	}

	function initCreator() {
		document.getElementById( 'sn-story-creator-close' )?.addEventListener( 'click', closeCreator );
		document.getElementById( 'sn-story-creator-cancel' )?.addEventListener( 'click', closeCreator );

		// Tab switching.
		document.querySelectorAll( '.sn-story-tab' ).forEach( tab => {
			tab.addEventListener( 'click', () => {
				document.querySelectorAll( '.sn-story-tab' ).forEach( t => {
					t.classList.remove( 'sn-story-tab--active' );
					t.setAttribute( 'aria-selected', 'false' );
				} );
				tab.classList.add( 'sn-story-tab--active' );
				tab.setAttribute( 'aria-selected', 'true' );
				creatorMediaType = tab.dataset.type;
				switchCreatorPanel( creatorMediaType );
			} );
		} );

		// Background colour swatches.
		document.querySelectorAll( '.sn-story-creator__bg-swatch' ).forEach( swatch => {
			swatch.addEventListener( 'click', () => {
				document.querySelectorAll( '.sn-story-creator__bg-swatch' )
					.forEach( s => s.classList.remove( 'sn-story-creator__bg-swatch--active' ) );
				swatch.classList.add( 'sn-story-creator__bg-swatch--active' );
				const card = document.getElementById( 'sn-creator-text-card' );
				if ( card ) card.style.background = swatch.dataset.color;
			} );
		} );

		// Duration slider.
		const slider = document.getElementById( 'sn-creator-duration' );
		const durVal = document.getElementById( 'sn-creator-duration-val' );
		slider?.addEventListener( 'input', () => {
			if ( durVal ) durVal.textContent = slider.value + 's';
		} );

		// File input.
		const fileInput = document.getElementById( 'sn-creator-file-input' );
		fileInput?.addEventListener( 'change', () => {
			creatorFile = fileInput.files[0] ?? null;
			previewCreatorFile( creatorFile );
		} );

		// Drag-and-drop.
		const uploadArea = document.getElementById( 'sn-creator-upload-area' );
		uploadArea?.addEventListener( 'dragover', e => { e.preventDefault(); uploadArea.classList.add( 'sn--drag-over' ); } );
		uploadArea?.addEventListener( 'dragleave', () => uploadArea.classList.remove( 'sn--drag-over' ) );
		uploadArea?.addEventListener( 'drop', e => {
			e.preventDefault();
			uploadArea.classList.remove( 'sn--drag-over' );
			creatorFile = e.dataTransfer.files[0] ?? null;
			previewCreatorFile( creatorFile );
		} );

		// Remove media.
		document.getElementById( 'sn-creator-remove-media' )?.addEventListener( 'click', () => {
			creatorFile = null;
			const wrap = document.getElementById( 'sn-creator-media-preview' );
			const area = document.getElementById( 'sn-creator-upload-area' );
			if ( wrap ) wrap.hidden = true;
			if ( area ) area.hidden = false;
		} );

		// Submit.
		document.getElementById( 'sn-story-creator-submit' )?.addEventListener( 'click', submitCreator );
	}

	function switchCreatorPanel( type ) {
		const textPanel  = document.getElementById( 'sn-creator-text-panel' );
		const mediaPanel = document.getElementById( 'sn-creator-media-panel' );
		const durWrap    = document.getElementById( 'sn-creator-duration-wrap' );
		if ( textPanel )  textPanel.hidden  = type !== 'text';
		if ( mediaPanel ) mediaPanel.hidden = type === 'text';
		if ( durWrap )    durWrap.hidden    = type === 'video';

		// Update file input accept.
		const fileInput = document.getElementById( 'sn-creator-file-input' );
		if ( fileInput ) {
			fileInput.accept = type === 'video' ? 'video/*' : 'image/*';
		}
	}

	function previewCreatorFile( file ) {
		if ( ! file ) return;
		const wrap  = document.getElementById( 'sn-creator-media-preview' );
		const area  = document.getElementById( 'sn-creator-upload-area' );
		const img   = document.getElementById( 'sn-creator-preview-img' );
		const video = document.getElementById( 'sn-creator-preview-video' );
		if ( ! wrap ) return;

		const url = URL.createObjectURL( file );
		if ( area )  area.hidden  = true;
		if ( wrap )  wrap.hidden  = false;
		if ( img )   img.hidden   = true;
		if ( video ) video.hidden = true;

		if ( file.type.startsWith( 'image/' ) ) {
			if ( img ) { img.src = url; img.hidden = false; }
		} else if ( file.type.startsWith( 'video/' ) ) {
			if ( video ) { video.src = url; video.hidden = false; }
		}
	}

	async function submitCreator() {
		const submitBtn = document.getElementById( 'sn-story-creator-submit' );
		const errorEl   = document.getElementById( 'sn-creator-error' );
		if ( submitBtn ) submitBtn.disabled = true;
		if ( errorEl ) errorEl.hidden = true;

		const form = new FormData();
		form.append( 'action', 'arshid6social_create_story' );
		form.append( 'nonce', nonce );
		form.append( 'privacy',    document.getElementById( 'sn-creator-privacy' )?.value  || 'public' );
		form.append( 'media_type', creatorMediaType );
		form.append( 'duration',   document.getElementById( 'sn-creator-duration' )?.value || 5 );

		if ( 'text' === creatorMediaType ) {
			const textInput = document.getElementById( 'sn-creator-text' );
			const bgSwatch  = document.querySelector( '.sn-story-creator__bg-swatch--active' );
			form.append( 'text_content', textInput?.value.trim() || '' );
			form.append( 'bg_color', bgSwatch?.dataset.color || '#2563eb' );
		} else if ( creatorFile ) {
			form.append( 'media', creatorFile, creatorFile.name );
		} else {
			showCreatorError( 'Please select a file.' );
			if ( submitBtn ) submitBtn.disabled = false;
			return;
		}

		const res = await fetch( ajaxUrl, { method: 'POST', body: form } ).then( r => r.json() );
		if ( submitBtn ) submitBtn.disabled = false;

		if ( res.success ) {
			closeCreator();
			location.reload(); // reload to show new story in tray
		} else {
			showCreatorError( res.data?.message || 'Could not create story.' );
		}
	}

	function showCreatorError( msg ) {
		const el = document.getElementById( 'sn-creator-error' );
		if ( el ) { el.textContent = msg; el.hidden = false; }
	}

	/* -- Escape helper ------------------------------------------ */
	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/* -- Init --------------------------------------------------- */
	function init() {
		initTray();
		initTapZones();
		initViewerActions();
		initCreator();

		// Close when clicking outside the viewer content.
		const viewer = $viewer();
		if ( viewer ) {
			viewer.addEventListener( 'click', e => {
				if ( e.target === viewer ) closeViewer();
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
