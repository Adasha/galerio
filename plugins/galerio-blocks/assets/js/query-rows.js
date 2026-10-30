/**
 * The Query Loop's "Rows" panel: show only so many rows of items and hide
 * the rest.
 *
 * The setting is the `galerioRows` attribute, added to the Query Loop here
 * and applied on the site by query-rows-view.js. The editor shows every
 * item.
 *
 * @param {Object} wp WordPress's scripts.
 */
( function ( wp ) {
	if ( ! wp ) {
		return;
	}

	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, RangeControl } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	addFilter(
		'blocks.registerBlockType',
		'galerio/query-rows-attributes',
		( settings, name ) =>
			name !== 'core/query'
				? settings
				: Object.assign( {}, settings, {
						attributes: Object.assign( {}, settings.attributes, {
							galerioRows: { type: 'number', default: 0 },
						} ),
					} )
	);

	addFilter(
		'editor.BlockEdit',
		'galerio/query-rows',
		createHigherOrderComponent(
			( BlockEdit ) => ( props ) => {
				if ( props.name !== 'core/query' ) {
					return el( BlockEdit, props );
				}

				const { galerioRows: rows } = props.attributes;

				return el(
					Fragment,
					null,
					el( BlockEdit, props ),
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{
								title: __( 'Rows', 'galerio-blocks' ),
								initialOpen: !! rows,
							},
							el( RangeControl, {
								__next40pxDefaultSize: true,
								__nextHasNoMarginBottom: true,
								label: __( 'Rows to show', 'galerio-blocks' ),
								help: rows
									? __(
											'Counted as laid out on the screen, so a phone shows fewer items. The editor shows them all. To load fewer, lower “Items per page” instead.',
											'galerio-blocks'
										)
									: __(
											'All the items on the page are shown.',
											'galerio-blocks'
										),
								value: rows || undefined,
								min: 1,
								max: 10,
								allowReset: true,
								resetFallbackValue: 0,
								onChange: ( value ) =>
									props.setAttributes( {
										galerioRows: value || 0,
									} ),
							} )
						)
					)
				);
			},
			'withGalerioQueryRows'
		)
	);
} )( window.wp );
