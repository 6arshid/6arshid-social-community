/**
 * Member Directory block — editor definition.
 *
 * Registers the block, renders an inspector panel with controls,
 * and delegates front-end rendering to PHP (ServerSideRender).
 */

const { registerBlockType }  = wp.blocks;
const { InspectorControls }  = wp.blockEditor;
const { PanelBody, RangeControl, ToggleControl, SelectControl } = wp.components;
const { __ }                 = wp.i18n;
const { ServerSideRender }   = wp.serverSideRender || wp.components;

registerBlockType( 'arshid6social/member-directory', {
	apiVersion: 3,
	edit( { attributes, setAttributes } ) {
		const { perPage, showSearch, defaultType } = attributes;

		return wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement(
				InspectorControls,
				null,
				wp.element.createElement(
					PanelBody,
					{ title: __( 'Member Directory Settings', 'social-network-6' ), initialOpen: true },
					wp.element.createElement( RangeControl, {
						label:    __( 'Members per page', 'social-network-6' ),
						value:    perPage,
						min:      4,
						max:      48,
						step:     4,
						onChange: val => setAttributes( { perPage: val } ),
					} ),
					wp.element.createElement( ToggleControl, {
						label:    __( 'Show search bar', 'social-network-6' ),
						checked:  showSearch,
						onChange: val => setAttributes( { showSearch: val } ),
					} ),
					wp.element.createElement( SelectControl, {
						label:    __( 'Default sort order', 'social-network-6' ),
						value:    defaultType,
						options:  [
							{ label: __( 'Newest', 'social-network-6' ),      value: 'newest'       },
							{ label: __( 'Recently active', 'social-network-6' ), value: 'active'    },
							{ label: __( 'Alphabetical', 'social-network-6' ), value: 'alphabetical' },
						],
						onChange: val => setAttributes( { defaultType: val } ),
					} ),
				)
			),
			wp.element.createElement( ServerSideRender, {
				block:      'arshid6social/member-directory',
				attributes,
			} )
		);
	},

	// Rendering is handled server-side via PHP.
	save: () => null,
} );
