/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALCommentAtt = ( function () {
	'use strict';

	let cfg = {};

	function init( c ) {
		cfg = c;
		bindIn( document, c );
		initLightbox();
	}

	function bindIn( root, c ) {
		cfg = c;
		root.querySelectorAll( '.arshid6social-comment-att-zone' ).forEach( bindZone );
		root.querySelectorAll( '.arshid6social-att-thumb' ).forEach( bindLightboxThumb );
	}

	function bindZone( zone ) {
		if ( zone._ARSHID6SOCIALAttBound ) return;
		zone._ARSHID6SOCIALAttBound = true;

		const form     = zone.closest( 'form, .arshid6social-comment-form' );
		const input    = zone.querySelector( 'input[type="file"]' );
		const list     = zone.querySelector( '.arshid6social-att-list' ) || createList( zone );

		// Click to pick file.
		zone.addEventListener( 'click', function ( e ) {
			if ( e.target === zone ) input?.click();
		} );

		// Drag and drop.
		zone.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			zone.classList.add( 'dragover' );
		} );
		zone.addEventListener( 'dragleave', function () { zone.classList.remove( 'dragover' ); } );
		zone.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			zone.classList.remove( 'dragover' );
			Array.from( e.dataTransfer?.files || [] ).forEach( f => uploadFile( f, form, list ) );
		} );

		// Paste image.
		document.addEventListener( 'paste', function ( e ) {
			const items = e.clipboardData?.items || [];
			Array.from( items ).forEach( function ( item ) {
				if ( item.type.startsWith( 'image/' ) ) {
					const file = item.getAsFile();
					if ( file ) uploadFile( file, form, list );
				}
			} );
		} );

		if ( input ) {
			input.addEventListener( 'change', function () {
				Array.from( input.files || [] ).forEach( f => uploadFile( f, form, list ) );
				input.value = '';
			} );
		}
	}

	function createList( zone ) {
		const ul = document.createElement( 'div' );
		ul.className = 'arshid6social-att-list';
		zone.appendChild( ul );
		return ul;
	}

	function uploadFile( file, form, list ) {
		const formData = new FormData();
		formData.append( 'action',     'arshid6social_comment_upload_attachment' );
		formData.append( 'nonce',      cfg.nonce );
		formData.append( 'attachment', file );

		// Placeholder.
		const placeholder = document.createElement( 'div' );
		placeholder.className = 'arshid6social-att-item';
		placeholder.innerHTML = '<div class="arshid6social-gif-placeholder" style="width:80px;height:80px;border-radius:8px"></div>';
		list.appendChild( placeholder );

		fetch( cfg.ajaxUrl, { method: 'POST', body: formData } )
			.then( r => r.json() )
			.then( data => {
				placeholder.remove();
				if ( ! data.success ) return;
				const att = data.data;
				renderAttItem( att, form, list );
			} )
			.catch( () => placeholder.remove() );
	}

	function renderAttItem( att, form, list ) {
		const item = document.createElement( 'div' );
		item.className = 'arshid6social-att-item';
		item.dataset.attId = att.attachment_id;

		if ( att.media_type === 'image' ) {
			const img = document.createElement( 'img' );
			img.src   = att.thumb_url || att.serve_url;
			img.className = 'arshid6social-att-thumb';
			img.alt   = att.file_name;
			img.dataset.full = att.serve_url;
			img.addEventListener( 'click', function () { openLightbox( att.serve_url ); } );
			item.appendChild( img );
		} else {
			const a = document.createElement( 'a' );
			a.href      = att.serve_url;
			a.className = 'arshid6social-att-file';
			a.target    = '_blank';
			a.textContent = att.file_name;
			item.appendChild( a );
		}

		const removeBtn = document.createElement( 'button' );
		removeBtn.type      = 'button';
		removeBtn.className = 'arshid6social-att-remove';
		removeBtn.textContent = '×';
		removeBtn.setAttribute( 'aria-label', 'Remove' );
		removeBtn.addEventListener( 'click', function () {
			deleteAttachment( att.attachment_id );
			item.remove();
			removeHidden( form, att.attachment_id );
		} );
		item.appendChild( removeBtn );
		list.appendChild( item );

		// Store ID for submission.
		const hidden = document.createElement( 'input' );
		hidden.type  = 'hidden';
		hidden.name  = 'sn_comment_attachments[]';
		hidden.value = att.attachment_id;
		form.appendChild( hidden );
	}

	function removeHidden( form, attId ) {
		form?.querySelectorAll( 'input[name="sn_comment_attachments[]"]' ).forEach( function ( el ) {
			if ( el.value == attId ) el.remove(); // eslint-disable-line eqeqeq
		} );
	}

	function deleteAttachment( attId ) {
		const fd = new FormData();
		fd.append( 'action',        'arshid6social_comment_delete_attachment' );
		fd.append( 'nonce',         cfg.nonce );
		fd.append( 'attachment_id', attId );
		fetch( cfg.ajaxUrl, { method: 'POST', body: fd } ).catch( () => {} );
	}

	// ── Lightbox ──────────────────────────────────────────────────────────────

	let overlay = null;

	function initLightbox() {
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'arshid6social-att-thumb' ) ) {
				openLightbox( e.target.dataset.full || e.target.src );
			}
		} );
	}

	function openLightbox( src ) {
		if ( overlay ) overlay.remove();
		overlay = document.createElement( 'div' );
		overlay.className = 'arshid6social-lightbox-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );

		const img = document.createElement( 'img' );
		img.src = src;
		img.alt = '';

		const close = document.createElement( 'button' );
		close.className   = 'arshid6social-lightbox-close';
		close.textContent = '×';
		close.setAttribute( 'aria-label', 'Close' );

		overlay.appendChild( img );
		overlay.appendChild( close );
		document.body.appendChild( overlay );

		close.addEventListener( 'click', closeLightbox );
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) closeLightbox();
		} );
		document.addEventListener( 'keydown', onEsc );
	}

	function closeLightbox() {
		overlay?.remove();
		overlay = null;
		document.removeEventListener( 'keydown', onEsc );
	}

	function onEsc( e ) {
		if ( e.key === 'Escape' ) closeLightbox();
	}

	function bindLightboxThumb( img ) {
		img.style.cursor = 'pointer';
		img.addEventListener( 'click', function () {
			openLightbox( img.dataset.full || img.src );
		} );
	}

	return { init, bindIn };
} )();
