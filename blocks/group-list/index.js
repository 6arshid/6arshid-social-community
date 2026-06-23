/**
 * Group List block — editor definition.
 */

const { registerBlockType }  = wp.blocks;
const { InspectorControls }  = wp.blockEditor;
const { PanelBody, RangeControl, ToggleControl, SelectControl } = wp.components;
const { __ }                 = wp.i18n;
const { ServerSideRender }   = wp.serverSideRender || wp.components;

registerBlockType( 'arshid6social/group-list', {
	apiVersion: 3,
	edit( { attributes, setAttributes } ) {
		const { perPage, showSearch, showCreateButton, status } = attributes;

		return wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement(
				InspectorControls,
				null,
				wp.element.createElement(
					PanelBody,
					{ title: __( 'Group List Settings', 'social-network-6' ), initialOpen: true },
					wp.element.createElement( RangeControl, {
						label:    __( 'Groups per page', 'social-network-6' ),
						value:    perPage,
						min:      3,
						max:      36,
						step:     3,
						onChange: val => setAttributes( { perPage: val } ),
					} ),
					wp.element.createElement( SelectControl, {
						label:    __( 'Show groups', 'social-network-6' ),
						value:    status,
						options:  [
							{ label: __( 'Public only', 'social-network-6' ), value: 'public' },
							{ label: __( 'Public and private', 'social-network-6' ), value: 'all'    },
						],
						onChange: val => setAttributes( { status: val } ),
					} ),
					wp.element.createElement( ToggleControl, {
						label:    __( 'Show search bar', 'social-network-6' ),
						checked:  showSearch,
						onChange: val => setAttributes( { showSearch: val } ),
					} ),
					wp.element.createElement( ToggleControl, {
						label:    __( 'Show "Create Group" button', 'social-network-6' ),
						checked:  showCreateButton,
						onChange: val => setAttributes( { showCreateButton: val } ),
					} ),
				)
			),
			wp.element.createElement( ServerSideRender, {
				block:      'arshid6social/group-list',
				attributes,
			} )
		);
	},

	save: () => null,
} );
