/**
 * Editor side of the Archive Filters block: what to show, in the sidebar,
 * and a still preview. The real dropdowns depend on the archive being
 * viewed, which the page shows.
 *
 * @param {Object} wp WordPress's scripts.
 */
( function ( wp ) {
	if ( ! wp ) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	/**
	 * A still dropdown for the preview.
	 *
	 * @param {string} label Label.
	 * @param {string} value Shown choice.
	 * @return {Object} Element.
	 */
	function preview( label, value ) {
		return el(
			'p',
			{ className: 'wp-block-galerio-archive-filters__field' },
			el( 'label', null, label ),
			el( 'select', { disabled: true }, el( 'option', null, value ) )
		);
	}

	/**
	 * The block in the editor.
	 *
	 * @param {Object}                       props               Block props.
	 * @param {Object}                       props.attributes    Attributes.
	 * @param {(attributes: Object) => void} props.setAttributes Attribute setter.
	 * @return {Object} Element.
	 */
	function Edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();

		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Settings', 'galerio-core' ) },
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Filters', 'galerio-core' ),
						help: __(
							'A dropdown for each of the type’s taxonomies, such as mediums, with two or more terms in use.',
							'galerio-core'
						),
						checked: attributes.showFilters,
						onChange: ( value ) =>
							setAttributes( { showFilters: value } ),
					} ),
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Sort', 'galerio-core' ),
						checked: attributes.showSort,
						onChange: ( value ) =>
							setAttributes( { showSort: value } ),
					} )
				)
			),
			el(
				'div',
				blockProps,
				el(
					'div',
					{
						className: 'wp-block-galerio-archive-filters__form',
						style: {
							display: 'flex',
							flexWrap: 'wrap',
							gap: '0.75em 1.5em',
						},
					},
					attributes.showFilters &&
						preview(
							__( 'Medium', 'galerio-core' ),
							__( 'All Mediums', 'galerio-core' )
						),
					attributes.showSort &&
						preview(
							__( 'Sort', 'galerio-core' ),
							__( 'Newest first', 'galerio-core' )
						)
				)
			)
		);
	}

	registerBlockType( 'galerio/archive-filters', {
		edit: Edit,
		save: () => null,
	} );
} )( window.wp );
