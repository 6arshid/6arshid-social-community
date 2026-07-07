/* global ARSHID6SOCIALEng */
window.ARSHID6SOCIALMsgAtt = ( function () {
	'use strict';

	let cfg = {};

	function init( c ) {
		cfg = c;
		bindIn( document, c );
	}

	function bindIn( root, c ) {
		cfg = c;
		root.querySelectorAll( '.arshid6social-msg-att-zone' ).forEach( bindZone );
		root.querySelectorAll( '.arshid6social-msg-att-list' ).forEach( bindRenderedList );
	}

	// ── Upload zone ───────────────────────────────────────────────────────────

	function bindZone( zone ) {
		if ( zone._ARSHID6SOCIALMsgAttBound ) return;
		zone._ARSHID6SOCIALMsgAttBound = true;

		const form      = zone.closest( 'form, .arshid6social-msg-form' );
		const fileInput = zone.querySelector( 'input[type="file"]' );
		const list      = zone.querySelector( '.arshid6social-att-list' ) || createList( zone );

		zone.addEventListener( 'click', function ( e ) {
			if ( e.target === zone ) fileInput?.click();
		} );
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
		if ( fileInput ) {
			fileInput.addEventListener( 'change', function () {
				Array.from( fileInput.files || [] ).forEach( f => uploadFile( f, form, list ) );
				fileInput.value = '';
			} );
		}
	}

	function createList( zone ) {
		const el = document.createElement( 'div' );
		el.className = 'arshid6social-att-list';
		zone.appendChild( el );
		return el;
	}

	function uploadFile( file, form, list ) {
		const fd = new FormData();
		fd.append( 'action',      'arshid6social_message_upload_attachment' );
		fd.append( 'nonce',       cfg.nonce );
		fd.append( 'message_id',  form?.dataset.messageId || 0 );
		fd.append( 'attachment',  file );

		const ph = document.createElement( 'div' );
		ph.className = 'arshid6social-att-item';
		ph.innerHTML = '<div class="arshid6social-gif-placeholder" style="width:80px;height:80px;border-radius:8px"></div>';
		list.appendChild( ph );

		fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
			.then( r => r.json() )
			.then( data => {
				ph.remove();
				if ( ! data.success ) return;
				const att = data.data;
				renderUploadItem( att, form, list );
			} )
			.catch( () => ph.remove() );
	}

	function renderUploadItem( att, form, list ) {
		const item = document.createElement( 'div' );
		item.className  = 'arshid6social-att-item';
		item.dataset.id = att.attachment_id;

		if ( att.media_type === 'image' ) {
			const img = document.createElement( 'img' );
			img.src       = att.serve_url;
			img.className = 'arshid6social-att-thumb';
			img.alt       = att.file_name;
			item.appendChild( img );
		} else if ( att.media_type === 'audio' ) {
			const audio = document.createElement( 'audio' );
			audio.controls   = true;
			audio.className  = 'arshid6social-att-audio';
			audio.src        = att.serve_url;
			item.appendChild( audio );
		} else {
			const a = document.createElement( 'a' );
			a.href       = att.serve_url;
			a.className  = 'arshid6social-att-file';
			a.target     = '_blank';
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
			form?.querySelector( 'input[value="' + att.attachment_id + '"]' )?.remove();
		} );
		item.appendChild( removeBtn );
		list.appendChild( item );

		if ( form ) {
			const hidden = document.createElement( 'input' );
			hidden.type  = 'hidden';
			hidden.name  = 'sn_msg_attachments[]';
			hidden.value = att.attachment_id;
			form.appendChild( hidden );
		}
	}

	function deleteAttachment( attId ) {
		const fd = new FormData();
		fd.append( 'action',        'arshid6social_message_delete_attachment' );
		fd.append( 'nonce',         cfg.nonce );
		fd.append( 'attachment_id', attId );
		fetch( cfg.ajaxUrl, { method: 'POST', body: fd } ).catch( () => {} );
	}

	// ── Render already-sent attachments ───────────────────────────────────────

	function bindRenderedList( list ) {
		if ( list._ARSHID6SOCIALRendered ) return;
		list._ARSHID6SOCIALRendered = true;
		list.querySelectorAll( '[data-att-type]' ).forEach( renderSentItem );
	}

	function renderSentItem( el ) {
		const type     = el.dataset.attType;
		const serveUrl = el.dataset.serveUrl;
		const fileName = el.dataset.fileName;

		el.innerHTML = '';
		if ( type === 'image' ) {
			const img = document.createElement( 'img' );
			img.src       = serveUrl;
			img.className = 'arshid6social-att-thumb';
			img.alt       = fileName;
			img.addEventListener( 'click', function () {
				if ( window.ARSHID6SOCIALCommentAtt?.openLightbox ) {
					window.ARSHID6SOCIALCommentAtt.openLightbox( serveUrl );
				}
			} );
			el.appendChild( img );
		} else if ( type === 'audio' ) {
			const audio = document.createElement( 'audio' );
			audio.controls  = true;
			audio.className = 'arshid6social-att-audio';
			audio.src       = serveUrl;
			el.appendChild( audio );
		} else {
			const a = document.createElement( 'a' );
			a.href       = serveUrl;
			a.className  = 'arshid6social-att-file';
			a.target     = '_blank';
			a.textContent = fileName;
			el.appendChild( a );
		}
	}

	return { init, bindIn };
} )();
