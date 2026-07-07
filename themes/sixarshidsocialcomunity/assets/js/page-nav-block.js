/**
 * 6Arshid Social Community — "Page Navigation" dynamic block.
 *
 * Interaction lives in the INSPECTOR panel (right sidebar), which renders in
 * the MAIN document — outside the editor's <iframe>. That is the only place
 * where clicks, HTML5 drag-and-drop and Modals fire reliably; inside the
 * canvas iframe Gutenberg intercepts pointer events to select/move blocks,
 * which is why in-canvas drag/click did nothing.
 *
 *   • Inspector → reorder pages (drag ⠿ or use ▲▼) and pick each icon (✎)
 *   • Canvas    → live read-only preview of the resulting nav
 *   • Front-end → server-rendered (render_callback in PHP)
 */
( function ( wp ) {
	'use strict';

	var element     = wp.element;
	var el          = element.createElement;
	var Fragment    = element.Fragment;
	var useState    = element.useState;
	var useEffect   = element.useEffect;
	var __          = wp.i18n.__;
	var blocks      = wp.blocks;
	var blockEditor = wp.blockEditor;
	var components  = wp.components;
	var apiFetch    = wp.apiFetch;

	var useBlockProps     = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody         = components.PanelBody;
	var TextControl       = components.TextControl;
	var Modal             = components.Modal;
	var Spinner           = components.Spinner;
	var Button            = components.Button;

	var CFG = window.a6scPageNav || { iconsUrl: '' };

	// ── Bootstrap-Icons data (loaded once) ───────────────────────────────────
	var iconsCache = null;
	function loadIcons() {
		if ( iconsCache ) { return Promise.resolve( iconsCache ); }
		if ( ! CFG.iconsUrl ) { return Promise.resolve( {} ); }
		return fetch( CFG.iconsUrl )
			.then( function ( r ) { return r.ok ? r.json() : {}; } )
			.then( function ( j ) { iconsCache = j || {}; return iconsCache; } )
			.catch( function () { return {}; } );
	}

	function svgMarkup( inner, size ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size +
			'" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">' + inner + '</svg>';
	}

	function RawSvg( props ) {
		return el( 'span', {
			className: props.className || '',
			style: props.style || {},
			dangerouslySetInnerHTML: { __html: props.html }
		} );
	}

	// ── Icon picker modal ────────────────────────────────────────────────────
	function IconPickerModal( props ) {
		var iconsState = useState( null );
		var icons = iconsState[0], setIcons = iconsState[1];
		var qState = useState( '' );
		var query = qState[0], setQuery = qState[1];

		useEffect( function () { loadIcons().then( setIcons ); }, [] );

		var names = [];
		if ( icons ) {
			names = Object.keys( icons );
			if ( query ) {
				var q = query.toLowerCase();
				names = names.filter( function ( n ) { return n.indexOf( q ) !== -1; } );
			}
		}
		var LIMIT = 150;
		var shown = names.slice( 0, LIMIT );

		return el(
			Modal,
			{ title: __( 'Select an Icon', '6arshid-social-community' ), onRequestClose: props.onClose, className: 'a6sc-icp-modal' },
			el( TextControl, {
				placeholder: __( 'Search icons…', '6arshid-social-community' ),
				value: query, onChange: setQuery, autoComplete: 'off'
			} ),
			! icons
				? el( 'div', { style: { padding: '24px', textAlign: 'center' } }, el( Spinner, null ) )
				: el( 'div', { className: 'a6sc-icp-grid' },
					shown.map( function ( name ) {
						return el( 'button', {
							key: name, type: 'button', className: 'a6sc-icp-cell', title: name,
							onClick: function () { props.onSelect( name ); }
						}, el( RawSvg, { html: svgMarkup( icons[ name ], 22 ) } ) );
					} )
				),
			icons && names.length > LIMIT
				? el( 'p', { className: 'a6sc-icp-more' }, __( 'Showing first 150 — keep typing to narrow results.', '6arshid-social-community' ) )
				: null
		);
	}

	// ── Shared data hook: page list + icons, with save helpers ───────────────
	function useNavData( menu ) {
		var listState  = useState( null );
		var list = listState[0], setList = listState[1];
		var iconsState = useState( null );
		var icons = iconsState[0], setIcons = iconsState[1];
		var busyState  = useState( 0 );
		var busy = busyState[0], setBusy = busyState[1];

		useEffect( function () {
			apiFetch( { path: '/a6sc/v1/page-nav-items?menu=' + encodeURIComponent( menu || 'socialnetworksix-primary' ) } )
				.then( function ( items ) { setList( items || [] ); } )
				.catch( function () { setList( [] ); } );
		}, [ menu ] );

		useEffect( function () { loadIcons().then( setIcons ); }, [] );

		function persistOrder( arr ) {
			var order = arr.map( function ( it ) { return it.key; } );
			apiFetch( { path: '/a6sc/v1/page-order', method: 'POST', data: { order: order } } ).catch( function () {} );
		}

		function move( from, to ) {
			if ( from === to || from < 0 || to < 0 ) { return; }
			setList( function ( prev ) {
				var a = ( prev || [] ).slice();
				if ( to >= a.length ) { return a; }
				var moved = a.splice( from, 1 )[0];
				a.splice( to, 0, moved );
				persistOrder( a );
				return a;
			} );
		}

		function saveIcon( pageId, iconName ) {
			setBusy( pageId );
			apiFetch( { path: '/a6sc/v1/page-icon', method: 'POST', data: { page_id: pageId, icon: iconName } } )
				.then( function () {
					setList( function ( prev ) {
						return ( prev || [] ).map( function ( it ) {
							return it.page_id === pageId ? Object.assign( {}, it, { icon_name: iconName } ) : it;
						} );
					} );
					setBusy( 0 );
				} )
				.catch( function () { setBusy( 0 ); } );
		}

		return { list: list, icons: icons, busy: busy, move: move, saveIcon: saveIcon };
	}

	// ── Inspector manager: drag ⠿ / ▲▼ to reorder, ✎ to change icon ──────────
	function NavManager( props ) {
		var data = props.data;
		var pickerState = useState( 0 );
		var picker = pickerState[0], setPicker = pickerState[1];
		var dragState = useState( -1 );
		var dragIndex = dragState[0], setDragIndex = dragState[1];

		var list  = data.list;
		var icons = data.icons;

		if ( list === null ) {
			return el( 'div', { style: { padding: '12px', textAlign: 'center' } }, el( Spinner, null ) );
		}
		if ( ! list.length ) {
			return el( 'p', { style: { color: '#888' } },
				__( 'No pages found. Create a Page, or assign a menu to “Primary Navigation”.', '6arshid-social-community' ) );
		}

		return el(
			Fragment,
			null,
			el( 'p', { className: 'a6sc-icp-hint' },
				__( 'Drag ⠿ (or use ▲▼) to reorder · click ✎ to change a page’s icon.', '6arshid-social-community' ) ),
			el( 'div', { className: 'a6sc-icp-list' },
				list.map( function ( item, index ) {
					var hasIcon  = item.icon_name && icons && icons[ item.icon_name ];
					var iconCell = data.busy === item.page_id
						? el( Spinner, null )
						: ( hasIcon
							? el( RawSvg, { className: 'a6sc-icp-row__icon', html: svgMarkup( icons[ item.icon_name ], 20 ) } )
							: el( 'span', { className: 'a6sc-icp-row__icon', style: { color: '#bbb' } }, '●' ) );

					return el(
						'div',
						{
							key: item.key || item.label,
							className: 'a6sc-icp-row' + ( dragIndex === index ? ' is-dragging' : '' ),
							draggable: true,
							onDragStart: function ( e ) { setDragIndex( index ); e.dataTransfer.effectAllowed = 'move'; },
							onDragOver:  function ( e ) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; },
							onDrop:      function ( e ) { e.preventDefault(); data.move( dragIndex, index ); setDragIndex( -1 ); },
							onDragEnd:   function () { setDragIndex( -1 ); }
						},
						el( 'span', { className: 'a6sc-icp-row__handle', title: __( 'Drag to reorder', '6arshid-social-community' ) }, '⠿' ),
						iconCell,
						el( 'span', { className: 'a6sc-icp-row__label' }, item.label ),
						el( 'span', { className: 'a6sc-icp-row__btns' },
							el( Button, {
								icon: 'arrow-up-alt2', label: __( 'Move up', '6arshid-social-community' ),
								disabled: index === 0,
								onClick: function () { data.move( index, index - 1 ); }
							} ),
							el( Button, {
								icon: 'arrow-down-alt2', label: __( 'Move down', '6arshid-social-community' ),
								disabled: index === list.length - 1,
								onClick: function () { data.move( index, index + 1 ); }
							} ),
							item.page_id
								? el( Button, {
									className: 'a6sc-icp-row__edit',
									label: __( 'Change icon', '6arshid-social-community' ),
									onClick: function () { setPicker( item.page_id ); }
								}, '✎' )
								: null
						)
					);
				} )
			),
			picker
				? el( IconPickerModal, {
					onClose: function () { setPicker( 0 ); },
					onSelect: function ( name ) { data.saveIcon( picker, name ); setPicker( 0 ); }
				} )
				: null
		);
	}

	// ── Canvas preview (read-only) ───────────────────────────────────────────
	var PS = {
		wrap:  { display: 'flex', flexDirection: 'column', gap: '2px', padding: '8px 0' },
		row:   { display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 12px', borderRadius: '9999px' },
		icon:  { flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: '26px', height: '26px', color: '#0f1419' },
		label: { flex: '1 1 auto', minWidth: 0, fontSize: '19px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', color: '#0f1419' }
	};

	function NavPreview( props ) {
		var list  = props.data.list;
		var icons = props.data.icons;

		if ( list === null ) {
			return el( 'div', { style: { padding: '16px' } }, el( Spinner, null ) );
		}
		if ( ! list.length ) {
			return el( 'p', { style: { padding: '16px', color: '#888' } },
				__( 'No pages yet — manage this nav from the block settings panel on the right.', '6arshid-social-community' ) );
		}

		return el( 'div', { style: PS.wrap },
			el( 'p', { style: { fontSize: '12px', color: '#888', padding: '0 12px 4px', margin: 0 } },
				__( '↗ Reorder pages & change icons in the “Navigation” panel on the right.', '6arshid-social-community' ) ),
			list.map( function ( item ) {
				var hasIcon = item.icon_name && icons && icons[ item.icon_name ];
				return el( 'div', { key: item.key || item.label, style: PS.row },
					el( 'span', { style: PS.icon },
						hasIcon
							? el( RawSvg, { html: svgMarkup( icons[ item.icon_name ], 26 ) } )
							: el( 'span', { style: { color: '#bbb', fontSize: '12px' } }, '●' )
					),
					el( 'span', { style: PS.label }, item.label )
				);
			} )
		);
	}

	// ── Block registration ───────────────────────────────────────────────────
	blocks.registerBlockType( '6arshid-social-community/page-nav', {
		apiVersion: 2,
		title: __( 'Page Navigation (6Arshid Social Community)', '6arshid-social-community' ),
		description: __( 'Lists your Pages. Reorder and pick icons from the block settings panel.', '6arshid-social-community' ),
		category: 'theme',
		icon: 'menu',
		supports: { html: false, reusable: false },
		attributes: { menu: { type: 'string', default: 'socialnetworksix-primary' } },

		edit: function ( props ) {
			var blockProps = useBlockProps();
			var data = useNavData( props.attributes.menu );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{ key: 'inspector' },
					el(
						PanelBody,
						{ title: __( 'Navigation — reorder & icons', '6arshid-social-community' ), initialOpen: true },
						el( NavManager, { data: data } )
					),
					el(
						PanelBody,
						{ title: __( 'Navigation Source', '6arshid-social-community' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Menu location slug', '6arshid-social-community' ),
							help: __( 'Reads pages from the menu assigned to this theme location. If none is assigned, the default app nav is used.', '6arshid-social-community' ),
							value: props.attributes.menu,
							onChange: function ( v ) { props.setAttributes( { menu: v } ); }
						} )
					)
				),
				el( NavPreview, { data: data } )
			);
		},

		save: function () { return null; }
	} );

} )( window.wp );
