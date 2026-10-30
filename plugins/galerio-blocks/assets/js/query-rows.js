/**
 * The Query Loop's "Rows" panel: show only so many rows of items and hide
 * the rest.
 *
 * The setting is the `galerioRows` attribute, added to the Query Loop here.
 * The Query Loop is marked for it in the editor as on the site, and
 * query-rows-view.js hides the extra items in both.
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

	// Most rows the panel offers, as Query_Rows::MAX.
	const MAX = 10;

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

	// Mark the Query Loop in the editor as the server does on the site.
	addFilter(
		'editor.BlockListBlock',
		'galerio/query-rows-wrapper',
		createHigherOrderComponent(
			( BlockListBlock ) => ( props ) => {
				const rows = props.attributes.galerioRows;

				if ( props.name !== 'core/query' || ! rows ) {
					return el( BlockListBlock, props );
				}

				return el(
					BlockListBlock,
					Object.assign( {}, props, {
						className: [ props.className, 'has-galerio-rows' ]
							.filter( Boolean )
							.join( ' ' ),
						wrapperProps: Object.assign( {}, props.wrapperProps, {
							'data-galerio-rows': Math.min( rows, MAX ),
						} ),
					} )
				);
			},
			'withGalerioQueryRowsWrapper'
		)
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
											'Counted as laid out on the screen, so a phone shows fewer items. To load fewer, lower “Items per page” instead.',
											'galerio-blocks'
										)
									: __(
											'All the items on the page are shown.',
											'galerio-blocks'
										),
								value: rows || undefined,
								min: 1,
								max: MAX,
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
