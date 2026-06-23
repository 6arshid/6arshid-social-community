/**
 * Activity Feed block — editor definition.
 */

const { registerBlockType }  = wp.blocks;
const { InspectorControls }  = wp.blockEditor;
const { PanelBody, RangeControl, ToggleControl, SelectControl } = wp.components;
const { __ }                 = wp.i18n;
const { ServerSideRender }   = wp.serverSideRender || wp.components;

registerBlockType( 'arshid6social/activity-feed', {
	apiVersion: 3,
	edit( { attributes, setAttributes } ) {
		const { perPage, showComposer, scope } = attributes;

		return wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement(
				InspectorControls,
				null,
				wp.element.createElement(
					PanelBody,
					{ title: __( 'Activity Feed Settings', 'social-network-6' ), initialOpen: true },
					wp.element.createElement( SelectControl, {
						label:    __( 'Feed scope', 'social-network-6' ),
						value:    scope,
						options:  [
							{ label: __( 'Site-wide', 'social-network-6' ), value: 'site'    },
							{ label: __( 'Friends only', 'social-network-6' ), value: 'friends' },
							{ label: __( 'My activity', 'social-network-6' ), value: 'self'    },
						],
						onChange: val => setAttributes( { scope: val } ),
					} ),
					wp.element.createElement( RangeControl, {
						label:    __( 'Items per page', 'social-network-6' ),
						value:    perPage,
						min:      5,
						max:      50,
						step:     5,
						onChange: val => setAttributes( { perPage: val } ),
					} ),
					wp.element.createElement( ToggleControl, {
						label:    __( 'Show post composer', 'social-network-6' ),
						checked:  showComposer,
						onChange: val => setAttributes( { showComposer: val } ),
					} ),
				)
			),
			wp.element.createElement( ServerSideRender, {
				block:      'arshid6social/activity-feed',
				attributes,
			} )
		);
	},

	save: () => null,
} );
