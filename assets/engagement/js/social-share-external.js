/* global ARSHID6SOCIALEng */
/**
 * External Social Share – frontend module.
 *
 * Handles the share button that appears on each activity post and the modal
 * that lets users pick from 80+ external networks.
 *
 * Exported as window.ARSHID6SOCIALExtShare so engagement.js can call init() / bindIn().
 */
window.ARSHID6SOCIALExtShare = ( function () {
	'use strict';

	let cfg      = {};
	let settings = {};    // cfg.extShare
	let networks = {};    // cfg.extShare.networks (only enabled ones)
	let modal    = null;  // the single shared modal element
	let bound    = false;

	// ── Public API ─────────────────────────────────────────────────────────────

	function init( c ) {
		cfg      = c;
		settings = c.extShare || {};
		networks = settings.networks || {};

		if ( ! Object.keys( networks ).length ) return;

		buildModal();
		bindGlobal();

		if ( settings.position === 'floating' ) {
			injectFloating();
		}
	}

	function bindIn( root, c ) {
		cfg      = c;
		settings = c.extShare || {};
		networks = settings.networks || {};
		// No extra work needed — we use event delegation on document.
	}

	// ── Modal builder ──────────────────────────────────────────────────────────

	function buildModal() {
		if ( modal ) return;

		modal = document.createElement( 'div' );
		modal.id        = 'arshid6social-ext-share-modal';
		modal.className = 'arshid6social-ext-share-modal';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-label', settings.i18n.shareTitle || 'Share this post' );
		modal.hidden = true;

		const maxVisible = parseInt( settings.maxVisible, 10 ) || 8;
		const style      = settings.style || 'icon_text';

		modal.innerHTML =
			'<div class="arshid6social-ext-share-modal__backdrop"></div>' +
			'<div class="arshid6social-ext-share-modal__box">' +
				'<div class="arshid6social-ext-share-modal__header">' +
					'<button type="button" class="arshid6social-ext-share-modal__close" aria-label="' + esc( settings.i18n.close || 'Close' ) + '">' +
						'<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
					'</button>' +
					'<span class="arshid6social-ext-share-modal__title">' + esc( settings.i18n.shareTitle || 'Share this post' ) + '</span>' +
					'<span class="arshid6social-ext-share-modal__header-spacer"></span>' +
				'</div>' +
				'<div class="arshid6social-ext-share-modal__search-wrap">' +
					'<svg class="arshid6social-ext-share-search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
					'<input type="search" class="arshid6social-ext-share-modal__search" placeholder="' + esc( settings.i18n.dmSearchPlaceholder || 'Search' ) + '" autocomplete="off" />' +
				'</div>' +
				'<div class="arshid6social-ext-share-users-section">' +
					'<div class="arshid6social-ext-share-users-grid"></div>' +
					'<div class="arshid6social-ext-share-users-empty" hidden>' + esc( settings.i18n.dmLoginRequired || 'Log in to send messages.' ) + '</div>' +
				'</div>' +
				'<div class="arshid6social-ext-share-divider"></div>' +
				'<div class="arshid6social-ext-share-networks-strip">' +
					buildNetworkStrip() +
				'</div>' +
				'<div class="arshid6social-ext-share-modal__copied" hidden>' + esc( settings.i18n.copied || 'Link copied!' ) + '</div>' +
				'<div class="arshid6social-ext-share-dm-notice" hidden></div>' +
			'</div>';

		document.body.appendChild( modal );

		// Search — filters both user cards and network buttons.
		var searchTimer = null;
		var searchInput = modal.querySelector( '.arshid6social-ext-share-modal__search' );
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				clearTimeout( searchTimer );
				var q = searchInput.value.trim();
				filterNetworkStrip( q );
				if ( cfg.userId ) {
					searchTimer = setTimeout( function () { loadUsers( q ); }, 300 );
				}
			} );
		}

		// Close on backdrop click
		modal.querySelector( '.arshid6social-ext-share-modal__backdrop' ).addEventListener( 'click', closeModal );
		modal.querySelector( '.arshid6social-ext-share-modal__close' ).addEventListener( 'click', closeModal );
	}

	// Brand SVG paths (viewBox 0 0 24 24, fill="currentColor" unless noted).
	var NETWORK_SVGS = {
		facebook:    '<path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.791-4.697 4.533-4.697 1.313 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.269h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>',
		x:           '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.63L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
		twitter:     '<path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>',
		whatsapp:    '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>',
		telegram:    '<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
		linkedin:    '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
		reddit:      '<path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>',
		email:       '<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>',
		copy_link:   '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		threads:     '<path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.02-5.11.925-6.54 2.666C4.307 6.382 3.616 8.583 3.59 12c.026 3.418.717 5.619 2.057 7.215 1.43 1.74 3.63 2.644 6.54 2.663 2.65-.016 4.384-.73 5.165-2.124.231-.413.39-.888.48-1.419a4.945 4.945 0 0 1-3.972-.592 4.516 4.516 0 0 1-2.03-3.278 4.528 4.528 0 0 1 1.011-3.485 4.55 4.55 0 0 1 3.25-1.636c.196-.013.39-.02.58-.02 1.495 0 2.891.55 3.896 1.543A5.5 5.5 0 0 1 21.83 14.3a7.1 7.1 0 0 1-.327 2.47c-.944 3.21-3.477 5.107-7.254 5.228l-.063.002zm.73-9.394c-.067 0-.135.003-.203.008a2.6 2.6 0 0 0-1.862.942 2.58 2.58 0 0 0-.57 1.981 2.566 2.566 0 0 0 1.147 1.856 2.913 2.913 0 0 0 2.56.345c.62-.192 1.042-.537 1.284-.993.302-.566.408-1.298.31-2.175-.139-1.247-.918-1.964-2.666-1.964z"/>',
		bluesky:     '<path d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 1.565.139 1.908 0 3.08 0 3.768c0 .69.378 5.65.624 6.479.815 2.736 3.713 3.66 6.383 3.364.136-.02.275-.039.415-.056-.138.022-.276.04-.415.056-3.912.58-7.387 2.005-2.83 7.078 5.013 5.19 6.87-1.113 7.823-4.308.953 3.195 2.05 9.271 7.733 4.308 4.267-4.308 1.172-6.498-2.74-7.078a8.741 8.741 0 0 1-.415-.056c.14.017.279.036.415.056 2.67.297 5.568-.628 6.383-3.364.246-.828.624-5.79.624-6.478 0-.69-.139-1.861-.902-2.206-.659-.298-1.664-.62-4.3 1.24C16.046 4.748 13.087 8.687 12 10.8z"/>',
		mastodon:    '<path d="M23.268 5.313c-.35-2.578-2.617-4.61-5.304-5.004C17.51.242 15.792 0 11.813 0h-.03c-3.98 0-4.835.242-5.288.309C3.882.692 1.496 2.518.917 5.127.64 6.412.61 7.837.661 9.143c.074 1.874.088 3.745.26 5.611.118 1.24.325 2.47.62 3.68.55 2.237 2.777 4.098 4.96 4.857 2.336.792 4.849.923 7.256.38.265-.061.527-.132.786-.213.585-.184 1.27-.39 1.774-.753a.057.057 0 0 0 .023-.043v-1.809a.052.052 0 0 0-.02-.041.053.053 0 0 0-.046-.01 20.282 20.282 0 0 1-4.709.545c-2.73 0-3.463-1.284-3.674-1.818a5.593 5.593 0 0 1-.319-1.433.053.053 0 0 1 .066-.054c1.517.363 3.072.546 4.632.546.376 0 .75 0 1.125-.01 1.57-.044 3.224-.124 4.768-.422.038-.008.077-.015.11-.024 2.435-.464 4.753-1.92 4.989-5.604.008-.145.03-1.52.03-1.67.002-.512.167-3.63-.024-5.545zm-3.748 9.195h-2.561V8.29c0-1.309-.55-1.976-1.67-1.976-1.23 0-1.846.79-1.846 2.35v3.403h-2.546V8.663c0-1.56-.617-2.35-1.848-2.35-1.112 0-1.668.668-1.67 1.977v6.218H4.822V8.102c0-1.31.337-2.35 1.011-3.12.696-.77 1.608-1.164 2.74-1.164 1.311 0 2.302.5 2.962 1.498l.638 1.06.638-1.06c.66-.999 1.65-1.498 2.96-1.498 1.13 0 2.043.395 2.74 1.164.675.77 1.012 1.81 1.012 3.12z"/>',
		pinterest:   '<path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/>',
		tumblr:      '<path d="M14.563 24c-5.093 0-7.031-3.756-7.031-6.411V9.747H5.116V6.648c3.63-1.313 4.512-4.596 4.71-6.469C9.84.051 9.941 0 9.999 0h3.517v6.114h4.801v3.633h-4.82v7.47c.016 1.001.375 2.371 2.228 2.371h.08c.607 0 1.38-.116 1.38-.116v3.717s-1.55.609-4.622.609z"/>',
		vk:          '<path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.391 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.525-2.049-1.714-1.033-1.01-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.575c0 .424-.135.678-1.253.678-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4.03 8.57 4.03 8.096c0-.254.102-.491.593-.491h1.744c.44 0 .61.203.78.678.864 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.204.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.17.508.271.508.22 0 .407-.136.813-.542 1.27-1.422 2.168-3.608 2.168-3.608.119-.254.322-.491.762-.491h1.744c.525 0 .644.271.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.049.17.49-.085.744-.576.744z"/>',
		viber:       '<path d="M11.398.002C9.473.028 5.331.344 3.014 2.467 1.2 4.28.596 6.93.525 9.96c-.072 3.03-.158 8.709 5.33 10.284v2.363s-.038.999.621 1.2c.805.245 1.277-.52 2.045-1.347.42-.453.999-1.12 1.436-1.621 3.955.333 6.993-.428 7.34-.54.797-.261 5.308-.836 6.04-6.816.757-6.158-.366-10.052-2.505-11.802l-.003-.003C19.25.603 15.9-.03 11.398.002zm.066 1.93c3.893-.027 6.88.553 8.554 2.083 1.749 1.596 2.658 4.898 2.01 10.205-.613 5.008-4.22 5.398-4.877 5.613-.29.095-3.04.773-6.493.552 0 0-2.576 3.106-3.38 3.913-.126.127-.274.177-.375.154-.15-.035-.19-.205-.188-.454l.024-3.827c-4.613-1.289-4.337-6.132-4.273-8.77.064-2.637.551-4.875 2.063-6.383 1.908-1.827 5.49-2.115 6.935-2.086zm-.328 3.115c-.337-.001-.528.484-.199.705 1.017.677 1.73 1.393 2.2 2.3.469.907.7 1.943.727 3.198.012.444.685.428.676-.016-.03-1.394-.293-2.583-.856-3.652-.561-1.068-1.397-1.903-2.548-2.535zm-3.024 1.254c-.147.007-.29.069-.434.172-.387.27-1.104 1.05-1.237 1.327-.326.68-.139 1.35.17 2.043.563 1.26 1.518 2.53 2.555 3.536 1.037 1.006 2.34 1.935 3.627 2.49.695.3 1.37.483 2.054.148.274-.136 1.05-.86 1.31-1.258.22-.338.24-.653.076-.843-.47-.539-1.544-1.215-2.083-1.441-.262-.108-.543-.035-.753.188-.318.336-.626.762-.927.843-.262.071-.51-.025-1.06-.453-.845-.645-1.592-1.569-2.09-2.55-.274-.538-.378-.876-.338-1.132.053-.34.486-.62.823-.938.217-.203.276-.494.169-.77-.259-.67-.909-1.65-1.393-1.895-.118-.059-.348-.114-.469-.108z"/>',
		line:        '<path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.07 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>',
		snapchat:    '<path d="M12.206.793c.99 0 4.347.276 5.93 3.821.529 1.193.403 3.219.304 4.814l-.004.06c-.012.18-.022.345-.03.51.053.022.127.05.227.05.117 0 .262-.026.42-.1.016-.008.033-.012.05-.012.096 0 .177.073.179.175.003.112-.067.178-.214.202a.785.785 0 0 1-.075.004c-.109 0-.371-.017-.712-.125.063.318.075.581.007.78 0 0 .278.067.557.067.218 0 .444-.046.55-.264.047-.1.108-.142.187-.142.12 0 .229.098.221.208-.035.435-1.002.895-2.14.895-.396 0-.818-.063-1.206-.215a2.75 2.75 0 0 1-1.015-.6 2.025 2.025 0 0 1-.38-.489c-.097-.172-.187-.341-.269-.5a3.69 3.69 0 0 0-.384-.558c-.159-.175-.34-.262-.53-.262-.207 0-.36.113-.445.174l-.031.021c-.09.062-.229.133-.396.133-.145 0-.307-.059-.45-.197-.127-.123-.184-.289-.208-.455a1.264 1.264 0 0 1-.021-.208v-.017a1.2 1.2 0 0 1 .01-.13c.046-.309.172-.574.361-.778a2.57 2.57 0 0 1 .38-.35 5.337 5.337 0 0 0 1.26-1.688c.229-.509.35-1.064.35-1.643 0-.56-.113-1.057-.318-1.47a3.255 3.255 0 0 0-.87-1.091A3.997 3.997 0 0 0 12.39 3.25c-.28-.021-.558-.032-.83-.032-.574 0-1.12.077-1.623.23a3.768 3.768 0 0 0-1.217.636 3.206 3.206 0 0 0-.785 1.056A3.33 3.33 0 0 0 7.61 6.605c0 .567.118 1.112.344 1.617.215.478.538.945.964 1.39.18.189.301.454.345.765.006.044.009.09.009.135v.016c0 .07-.007.138-.022.206-.024.166-.081.332-.208.455-.143.138-.305.197-.45.197-.167 0-.306-.071-.396-.133l-.031-.021c-.085-.061-.238-.174-.445-.174-.19 0-.371.087-.53.262-.14.155-.268.345-.384.558-.082.159-.172.328-.269.5a2.025 2.025 0 0 1-.38.489c-.252.227-.601.436-1.015.6-.388.152-.81.215-1.206.215-1.138 0-2.105-.46-2.14-.895a.204.204 0 0 1 .221-.208c.079 0 .14.042.187.142.106.218.332.264.55.264.279 0 .557-.067.557-.067-.068-.199-.056-.462.007-.78a3.3 3.3 0 0 1-.711.125.785.785 0 0 1-.075-.004c-.147-.024-.217-.09-.214-.202a.18.18 0 0 1 .179-.175.196.196 0 0 1 .05.012c.158.074.303.1.42.1.1 0 .174-.028.227-.05-.008-.165-.018-.33-.03-.51l-.004-.06c-.099-1.595-.225-3.621.304-4.814C7.553 1.069 10.91.793 11.9.793h.306z"/>',
		skype:       '<path d="M12.069 18.874c-4.023 0-5.82-1.979-5.82-3.464 0-.765.561-1.296 1.333-1.296 1.723 0 1.273 2.477 4.487 2.477 1.641 0 2.55-.895 2.55-1.811 0-.551-.269-1.16-1.354-1.429l-3.576-.895c-2.88-.724-3.403-2.286-3.403-3.751 0-3.047 2.861-4.191 5.549-4.191 2.471 0 5.393 1.373 5.393 3.199 0 .789-.688 1.24-1.451 1.24-1.531 0-1.269-2.119-4.098-2.119-1.464 0-2.281.668-2.281 1.599s1.086 1.238 2.033 1.459l2.637.587c2.891.649 3.761 2.271 3.761 3.999 0 2.498-1.914 4.396-5.779 4.396m11.084-4.882l-.029.135a6.042 6.042 0 0 1 .098 1.089c0 3.421-2.845 6.198-6.362 6.198-.695 0-1.367-.104-2-.294C13.512 23.546 12.275 24 10.93 24 7.514 24 4.724 21.336 4.724 18.018c0-1.114.312-2.161.857-3.052a5.96 5.96 0 0 1-.236-1.686c0-3.42 2.845-6.197 6.362-6.197.695 0 1.366.104 2 .294C14.489.454 15.726 0 17.07 0 20.487 0 23.278 2.664 23.278 5.982c0 1.113-.313 2.16-.857 3.052a5.96 5.96 0 0 1 .732 2.958"/>',
		messenger:   '<path d="M12 0C5.373 0 0 4.974 0 11.111c0 3.498 1.744 6.614 4.469 8.652V24l4.088-2.242c1.092.3 2.246.464 3.443.464 6.627 0 12-4.975 12-11.111S18.627 0 12 0zm1.191 14.963l-3.055-3.26-5.963 3.26L10.732 8.1l3.131 3.259L19.752 8.1l-6.561 6.863z"/>',
		pocket:      '<path d="M21.927 1.557H2.073C.928 1.557 0 2.485 0 3.63v7.545c0 5.716 4.654 10.372 10.369 10.372h3.262c5.716 0 10.369-4.656 10.369-10.372V3.63c0-1.145-.928-2.073-2.073-2.073zm-3.184 7.063l-5.586 5.349a1.035 1.035 0 0 1-1.421.011L6.302 8.62a1.034 1.034 0 0 1 .012-1.462 1.034 1.034 0 0 1 1.463.012l3.717 3.565 4.869-4.66a1.035 1.035 0 0 1 1.463.023 1.033 1.033 0 0 1-.083 1.522z"/>',
		gmail:       '<path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/>',
		print:       '<path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>',
		wechat:      '<path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.182a1.17 1.17 0 0 1-1.162 1.182A1.17 1.17 0 0 1 4.623 7.173c0-.653.52-1.182 1.162-1.182zm5.813 0c.642 0 1.162.529 1.162 1.182a1.17 1.17 0 0 1-1.162 1.182 1.17 1.17 0 0 1-1.162-1.182c0-.653.52-1.182 1.162-1.182zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.11.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-7.062-6.122zm-3.554 3.01c.535 0 .969.44.969.983a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.543.434-.983.969-.983zm4.844 0c.535 0 .969.44.969.983a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.543.434-.983.969-.983z"/>',
		hacker_news: '<path d="M0 24V0h24v24H0zM6.951 5.896l4.112 7.708v5.064h1.583v-4.972l4.148-7.799h-1.749l-2.457 4.875c-.372.745-.688 1.434-.688 1.434s-.297-.708-.651-1.434L8.831 5.896z"/>',
		flipboard:   '<path d="M.557 0H24v23.443h-7.901V7.901H8.458V0H.557zm7.9 7.9v7.9h7.9V7.9z"/>',
		buffer:      '<path d="M23.984 6.426L12.12.023a.397.397 0 0 0-.36 0L.016 6.42a.389.389 0 0 0 0 .685l11.744 6.235a.397.397 0 0 0 .36 0l11.864-6.23a.389.389 0 0 0 0-.684zm-.283 5.274l-2.056-1.083-9.525 5.012a.397.397 0 0 1-.36 0L2.239 10.61.016 11.698a.389.389 0 0 0 0 .685l11.744 6.235a.397.397 0 0 0 .36 0l11.581-6.234a.389.389 0 0 0 0-.684zm0 5.536l-2.056-1.084-9.525 5.013a.397.397 0 0 1-.36 0L2.239 16.15.016 17.235a.389.389 0 0 0 0 .684l11.744 6.235a.397.397 0 0 0 .36 0l11.581-6.234a.389.389 0 0 0 0-.684z"/>',
		trello:      '<path d="M21 0H3C1.343 0 0 1.343 0 3v18c0 1.656 1.343 3 3 3h18c1.656 0 3-1.344 3-3V3c0-1.657-1.344-3-3-3zM10.44 18.18c0 .795-.645 1.44-1.44 1.44H4.56c-.795 0-1.44-.645-1.44-1.44V4.56c0-.795.645-1.44 1.44-1.44H9c.795 0 1.44.645 1.44 1.44v13.62zm10.44-6c0 .794-.645 1.44-1.44 1.44H15c-.795 0-1.44-.646-1.44-1.44V4.56c0-.795.645-1.44 1.44-1.44h4.44c.795 0 1.44.645 1.44 1.44v7.62z"/>',
		teams:       '<path d="M20.625 7.342a3.375 3.375 0 1 0 0-6.75 3.375 3.375 0 0 0 0 6.75zm1.922 1.033H18.72a1.953 1.953 0 0 0-1.953 1.953v5.39c0 .413.13.8.35 1.12.37.545.99.91 1.695.91h.188v3.94c0 .17.135.312.309.312h2.578a.312.312 0 0 0 .312-.312v-3.94h.188c1.078 0 1.953-.875 1.953-1.953V10.33a1.953 1.953 0 0 0-1.793-1.954zM13.172 6.75a2.813 2.813 0 1 0 0-5.625 2.813 2.813 0 0 0 0 5.625zm3.14 3.61a3.14 3.14 0 0 0-.328-.235 3.568 3.568 0 0 0-1.89-.547h-4.22a3.572 3.572 0 0 0-3.562 3.563v4.89c0 .69.56 1.25 1.25 1.25H7.7v3.406c0 .17.135.313.313.313h2.578a.312.312 0 0 0 .312-.313v-3.406h1.14c.69 0 1.25-.56 1.25-1.25v-4.89c0-.572.135-1.113.375-1.593l.088-.172a3.567 3.567 0 0 1 .554-.814z"/>',
	};

	function networkSvg( key ) {
		var path = NETWORK_SVGS[ key ];
		if ( ! path ) { return null; }
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">' + path + '</svg>';
	}

	function buildNetworkButtons( style ) {
		var html = '';
		Object.keys( networks ).forEach( function ( key ) {
			var net   = networks[ key ];
			var label = net.label || key;
			var color = net.color || '#555';
			var svg   = networkSvg( key );
			var iconInner = svg ? svg : label.charAt( 0 ).toUpperCase();

			var iconHtml = '<span class="arshid6social-ext-share-icon" style="background:' + esc( color ) + ';color:' + contrastColor( color ) + ';">' + iconInner + '</span>';
			var labelHtml = ( style !== 'icon_only' )
				? '<span class="arshid6social-ext-share-label">' + esc( label ) + '</span>'
				: '<span class="sr-only">' + esc( label ) + '</span>';

			html +=
				'<button type="button"' +
					' class="arshid6social-ext-share-network-btn"' +
					' draggable="true"' +
					' data-network="' + esc( key ) + '"' +
					' data-url="' + esc( net.url || '' ) + '"' +
					' data-action="' + esc( net.action || '' ) + '"' +
					' data-target="' + esc( net.target || '_blank' ) + '"' +
					' title="' + esc( label ) + '"' +
					' aria-label="' + esc( ( settings.i18n.shareTo || 'Share to' ) + ' ' + label ) + '">' +
					iconHtml + labelHtml +
				'</button>';
		} );
		return html;
	}

	// ── Global event delegation ────────────────────────────────────────────────

	function bindGlobal() {
		if ( bound ) return;
		bound = true;

		document.addEventListener( 'click', function ( e ) {
			// Main share button on each post
			const trigger = e.target.closest( '.arshid6social-ext-share-btn' );
			if ( trigger ) {
				e.stopPropagation();
				openModal(
					trigger.dataset.shareUrl   || '',
					trigger.dataset.shareTitle || '',
					trigger.dataset.activityId || ''
				);
				return;
			}

			// User card inside modal — send DM
			const userCard = e.target.closest( '.arshid6social-ext-share-user-card' );
			if ( userCard ) {
				e.preventDefault();
				sendDm( parseInt( userCard.dataset.userId, 10 ), userCard.dataset.userName || '' );
				return;
			}

			// Network button inside modal
			const netBtn = e.target.closest( '.arshid6social-ext-share-network-btn' );
			if ( netBtn ) {
				e.preventDefault();
				handleNetworkClick( netBtn );
				return;
			}
		} );

		// Keyboard: Escape closes modal
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && modal && ! modal.hidden ) {
				closeModal();
			}
		} );
	}

	// ── Modal open / close ─────────────────────────────────────────────────────

	function openModal( shareUrl, shareTitle, activityId ) {
		if ( ! modal ) return;

		// On mobile, try the native Web Share API first.
		var isMobile = /Android|iPhone|iPad|iPod/i.test( navigator.userAgent );
		if ( settings.useNative && isMobile && navigator.share ) {
			navigator.share( {
				title: shareTitle || document.title,
				url:   shareUrl  || window.location.href,
			} ).catch( function () {/* user dismissed – no action needed */} );
			return;
		}

		// Store the current share target on the modal.
		modal.dataset.shareUrl   = shareUrl;
		modal.dataset.shareTitle = shareTitle;
		modal.dataset.activityId = activityId;

		// Reset search
		var search = modal.querySelector( '.arshid6social-ext-share-modal__search' );
		if ( search ) { search.value = ''; }
		filterNetworkStrip( '' );

		modal.hidden = false;
		document.body.classList.add( 'arshid6social-ext-share-open' );

		// Load initial user suggestions.
		if ( cfg.userId ) {
			loadUsers( '' );
		} else {
			var empty = modal.querySelector( '.arshid6social-ext-share-users-empty' );
			if ( empty ) { empty.hidden = false; }
		}
	}

	function closeModal() {
		if ( ! modal ) return;
		modal.hidden = true;
		document.body.classList.remove( 'arshid6social-ext-share-open' );
	}

	// ── Network click handling ─────────────────────────────────────────────────

	function handleNetworkClick( btn ) {
		var shareUrl   = ( modal && modal.dataset.shareUrl )   || window.location.href;
		var shareTitle = ( modal && modal.dataset.shareTitle ) || document.title;
		var action     = btn.dataset.action  || '';
		var urlPattern = btn.dataset.url     || '';
		var target     = btn.dataset.target  || '_blank';

		switch ( action ) {
			case 'copy':
				copyToClipboard( shareUrl );
				return;

			case 'print':
				window.print();
				closeModal();
				return;

			case 'wechat':
				openWeChatQr( shareUrl );
				return;

			case 'native':
				if ( navigator.share ) {
					navigator.share( { title: shareTitle, url: shareUrl } )
						.catch( function () {} );
				}
				closeModal();
				return;

			default: {
				var finalUrl = buildShareUrl( urlPattern, shareUrl, shareTitle );
				if ( target === '_self' ) {
					window.location.href = finalUrl;
				} else {
					var width  = 600;
					var height = 500;
					var left   = Math.max( 0, Math.round( screen.width  / 2 - width  / 2 ) );
					var top    = Math.max( 0, Math.round( screen.height / 2 - height / 2 ) );
					window.open(
						finalUrl,
						'arshid6social_share',
						'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
					);
				}
				closeModal();
				return;
			}
		}
	}

	function buildShareUrl( pattern, url, title ) {
		return pattern
			.replace( /\{URL\}/g,   encodeURIComponent( url ) )
			.replace( /\{TITLE\}/g, encodeURIComponent( title ) );
	}

	// ── Clipboard ─────────────────────────────────────────────────────────────

	function copyToClipboard( text ) {
		var done = function () {
			showCopied();
			setTimeout( closeModal, 1200 );
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done ).catch( function () {
				legacyCopy( text );
				done();
			} );
		} else {
			legacyCopy( text );
			done();
		}
	}

	function legacyCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0';
		document.body.appendChild( ta );
		ta.focus();
		ta.select();
		try { document.execCommand( 'copy' ); } catch ( _e ) { /* silent fail */ }
		document.body.removeChild( ta );
	}

	function showCopied() {
		if ( ! modal ) return;
		var notice = modal.querySelector( '.arshid6social-ext-share-modal__copied' );
		if ( notice ) {
			notice.hidden = false;
			setTimeout( function () { notice.hidden = true; }, 2000 );
		}
	}

	// ── WeChat QR placeholder ──────────────────────────────────────────────────

	function openWeChatQr( url ) {
		var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent( url );
		var win = window.open( '', 'arshid6social_wechat', 'width=260,height=300,scrollbars=no' );
		if ( win ) {
			win.document.write(
				'<html><body style="margin:16px;font-family:sans-serif;text-align:center">' +
				'<p style="margin:0 0 8px;font-size:14px">Scan with WeChat</p>' +
				'<img src="' + qrUrl + '" width="200" height="200" alt="QR code" /></body></html>'
			);
			win.document.close();
		}
		closeModal();
	}

	// ── Frontend modal drag & drop sort ───────────────────────────────────────

	var dndSrc = null; // the button currently being dragged

	function enableModalDnd( grid ) {
		grid.addEventListener( 'dragstart', onDragStart );
		grid.addEventListener( 'dragover',  onDragOver );
		grid.addEventListener( 'dragleave', onDragLeave );
		grid.addEventListener( 'drop',      onDrop );
		grid.addEventListener( 'dragend',   onDragEnd );
	}

	function onDragStart( e ) {
		var btn = e.target.closest( '.arshid6social-ext-share-network-btn' );
		if ( ! btn ) return;
		dndSrc = btn;
		// Delay adding class so the drag image captures the normal look.
		setTimeout( function () { btn.classList.add( 'arshid6social-dnd-dragging' ); }, 0 );
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/plain', '' ); // required for Firefox
	}

	function onDragOver( e ) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		var btn = e.target.closest( '.arshid6social-ext-share-network-btn' );
		if ( btn && btn !== dndSrc ) {
			// Remove highlight from all, add to current target
			btn.closest( '.arshid6social-ext-share-modal__grid' )
				.querySelectorAll( '.arshid6social-dnd-over' )
				.forEach( function ( el ) { el.classList.remove( 'arshid6social-dnd-over' ); } );
			btn.classList.add( 'arshid6social-dnd-over' );
		}
	}

	function onDragLeave( e ) {
		var btn = e.target.closest( '.arshid6social-ext-share-network-btn' );
		if ( btn ) { btn.classList.remove( 'arshid6social-dnd-over' ); }
	}

	function onDrop( e ) {
		e.preventDefault();
		var target = e.target.closest( '.arshid6social-ext-share-network-btn' );
		if ( ! target || ! dndSrc || target === dndSrc ) return;

		var grid = target.closest( '.arshid6social-ext-share-modal__grid' );
		if ( ! grid ) return;

		// Determine relative position: insert before or after target.
		var tRect   = target.getBoundingClientRect();
		var midX    = tRect.left + tRect.width  / 2;
		var midY    = tRect.top  + tRect.height / 2;
		var after   = e.clientX > midX || ( e.clientX === midX && e.clientY > midY );

		if ( after ) {
			target.after( dndSrc );
		} else {
			target.before( dndSrc );
		}

		target.classList.remove( 'arshid6social-dnd-over' );
	}

	function onDragEnd() {
		if ( dndSrc ) { dndSrc.classList.remove( 'arshid6social-dnd-dragging' ); }
		if ( modal ) {
			modal.querySelectorAll( '.arshid6social-dnd-over' )
				.forEach( function ( el ) { el.classList.remove( 'arshid6social-dnd-over' ); } );
		}
		dndSrc = null;
	}

	// ── Search / filter ────────────────────────────────────────────────────────

	// ── Network strip filter (bottom row) ─────────────────────────────────────

	function filterNetworkStrip( query ) {
		if ( ! modal ) return;
		var q = query.toLowerCase();
		modal.querySelectorAll( '.arshid6social-ext-share-network-btn' ).forEach( function ( btn ) {
			var label = ( btn.title || '' ).toLowerCase();
			btn.style.display = ( ! q || label.indexOf( q ) !== -1 ) ? '' : 'none';
		} );
	}

	// ── Users grid (top section) ───────────────────────────────────────────────

	function loadUsers( query ) {
		var grid  = modal && modal.querySelector( '.arshid6social-ext-share-users-grid' );
		var empty = modal && modal.querySelector( '.arshid6social-ext-share-users-empty' );
		if ( ! grid ) return;

		grid.innerHTML = '<div class="arshid6social-ext-share-users-loading"></div>';
		if ( empty ) { empty.hidden = true; }

		var url = cfg.restUrl + 'members?per_page=12' + ( query ? '&search=' + encodeURIComponent( query ) : '' );
		fetch( url, { headers: { 'X-WP-Nonce': cfg.restNonce } } )
			.then( function ( r ) { return r.ok ? r.json() : []; } )
			.then( function ( users ) {
				grid.innerHTML = '';
				if ( ! users.length ) {
					grid.innerHTML = '<p class="arshid6social-ext-share-users-empty-msg">' + esc( query ? 'No users found.' : '' ) + '</p>';
					return;
				}
				users.forEach( function ( u ) {
					var name = u.name || u.displayName || u.user_login || '';
					var short = name.length > 10 ? name.substring( 0, 9 ) + '…' : name;
					var card = document.createElement( 'button' );
					card.type      = 'button';
					card.className = 'arshid6social-ext-share-user-card';
					card.dataset.userId   = u.id;
					card.dataset.userName = name;
					card.innerHTML =
						'<span class="arshid6social-ext-share-user-avatar-wrap">' +
							'<img src="' + esc( u.avatarUrl || u.avatar_url || '' ) + '" alt="" loading="lazy" />' +
							'<span class="arshid6social-ext-share-user-sent-badge" hidden>✓</span>' +
						'</span>' +
						'<span class="arshid6social-ext-share-user-name">' + esc( short ) + '</span>';
					grid.appendChild( card );
				} );
			} )
			.catch( function () { grid.innerHTML = ''; } );
	}

	// ── Build network strip (horizontal scroll at bottom) ─────────────────────

	function buildNetworkStrip() {
		var html = '';
		Object.keys( networks ).forEach( function ( key ) {
			if ( key === 'send_dm' ) return; // handled by user grid
			var net   = networks[ key ];
			var label = net.label || key;
			var color = net.color || '#555';
			var svg   = networkSvg( key );
			var inner = svg ? svg : '<span style="font-size:.75rem;font-weight:700">' + esc( label.charAt( 0 ).toUpperCase() ) + '</span>';

			html +=
				'<button type="button"' +
				' class="arshid6social-ext-share-network-btn"' +
				' data-network="' + esc( key ) + '"' +
				' data-url="' + esc( net.url || '' ) + '"' +
				' data-action="' + esc( net.action || '' ) + '"' +
				' data-target="' + esc( net.target || '_blank' ) + '"' +
				' title="' + esc( label ) + '"' +
				' aria-label="' + esc( ( settings.i18n.shareTo || 'Share to' ) + ' ' + label ) + '">' +
				'<span class="arshid6social-ext-share-strip-icon" style="background:' + esc( color ) + ';color:' + contrastColor( color ) + ';">' + inner + '</span>' +
				'<span class="arshid6social-ext-share-strip-label">' + esc( label ) + '</span>' +
				'</button>';
		} );
		return html;
	}

	// ── Send DM ────────────────────────────────────────────────────────────────

	function sendDm( recipientId, recipientName ) {
		if ( ! cfg.userId ) {
			showDmNotice( settings.i18n.dmLoginRequired || 'You must be logged in.', 'error' );
			return;
		}
		var shareUrl   = ( modal && modal.dataset.shareUrl )   || window.location.href;
		var shareTitle = ( modal && modal.dataset.shareTitle ) || document.title;
		var content    = ( shareTitle ? shareTitle + '\n' : '' ) + shareUrl;

		// Mark card as sending.
		var card = modal && modal.querySelector( '.arshid6social-ext-share-user-card[data-user-id="' + recipientId + '"]' );
		if ( card ) { card.classList.add( 'is-sending' ); }

		fetch( cfg.restUrl + 'threads', {
			method:  'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
			body:    JSON.stringify( { recipients: [ recipientId ], content: content } ),
		} )
			.then( function ( r ) { return r.json().then( function ( d ) { return { ok: r.ok, data: d }; } ); } )
			.then( function ( res ) {
				if ( card ) { card.classList.remove( 'is-sending' ); }
				if ( res.ok ) {
					if ( card ) {
						card.classList.add( 'is-sent' );
						var badge = card.querySelector( '.arshid6social-ext-share-user-sent-badge' );
						if ( badge ) { badge.hidden = false; }
					}
					showDmNotice( ( settings.i18n.dmSent || 'Message sent!' ) + ( recipientName ? ' → ' + recipientName : '' ), 'success' );
				} else {
					showDmNotice( settings.i18n.dmError || 'Could not send message.', 'error' );
				}
			} )
			.catch( function () {
				if ( card ) { card.classList.remove( 'is-sending' ); }
				showDmNotice( settings.i18n.dmError || 'Could not send message.', 'error' );
			} );
	}

	function showDmNotice( message, type ) {
		// Reuse the copied notice slot for DM feedback.
		var notice = modal && modal.querySelector( '.arshid6social-ext-share-dm-notice' );
		if ( ! notice ) return;
		notice.textContent = message;
		notice.className   = 'arshid6social-ext-share-dm-notice arshid6social-ext-share-dm-notice--' + type;
		notice.hidden      = false;
		setTimeout( function () { notice.hidden = true; }, 2500 );
	}

	// ── Floating button (position: floating) ───────────────────────────────────

	function injectFloating() {
		if ( document.getElementById( 'arshid6social-ext-share-floating' ) ) return;

		var btn = document.createElement( 'button' );
		btn.id        = 'arshid6social-ext-share-floating';
		btn.className = 'arshid6social-ext-share-floating-btn';
		btn.type      = 'button';
		btn.title     = settings.i18n.share || 'Share';
		btn.setAttribute( 'aria-label', settings.i18n.share || 'Share' );
		btn.innerHTML = svgShareIcon();
		document.body.appendChild( btn );

		btn.addEventListener( 'click', function () {
			openModal( window.location.href, document.title, '' );
		} );
	}

	// ── Helpers ────────────────────────────────────────────────────────────────

	function esc( str ) {
		return String( str ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function contrastColor( hex ) {
		// Returns black or white depending on the brightness of the bg.
		var h = hex.replace( '#', '' );
		if ( h.length === 3 ) { h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; }
		var r = parseInt( h.substr(0,2), 16 );
		var g = parseInt( h.substr(2,2), 16 );
		var b = parseInt( h.substr(4,2), 16 );
		var luminance = ( 0.299*r + 0.587*g + 0.114*b ) / 255;
		return luminance > 0.6 ? '#111827' : '#ffffff';
	}

	function svgShareIcon() {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
			'<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>' +
			'<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>' +
			'</svg>';
	}

	return { init: init, bindIn: bindIn };
} )();
