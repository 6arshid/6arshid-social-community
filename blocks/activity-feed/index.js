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
					{ title: __( 'Activity Feed Settings', '6arshid-social-community' ), initialOpen: true },
					wp.element.createElement( SelectControl, {
						label:    __( 'Feed scope', '6arshid-social-community' ),
						value:    scope,
						options:  [
							{ label: __( 'Site-wide', '6arshid-social-community' ), value: 'site'    },
							{ label: __( 'Friends only', '6arshid-social-community' ), value: 'friends' },
							{ label: __( 'My activity', '6arshid-social-community' ), value: 'self'    },
						],
						onChange: val => setAttributes( { scope: val } ),
					} ),
					wp.element.createElement( RangeControl, {
						label:    __( 'Items per page', '6arshid-social-community' ),
						value:    perPage,
						min:      5,
						max:      50,
						step:     5,
						onChange: val => setAttributes( { perPage: val } ),
					} ),
					wp.element.createElement( ToggleControl, {
						label:    __( 'Show post composer', '6arshid-social-community' ),
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
