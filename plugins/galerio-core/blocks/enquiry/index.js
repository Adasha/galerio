/**
 * Editor side of the Enquiry block: the button's words in the sidebar, and
 * a still preview of the button. The preview makes no requests, as the form
 * depends on the work and the catalogue, which the page shows.
 *
 * @param {Object} wp WordPress's scripts.
 */
( function ( wp ) {
	if ( ! wp ) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

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
					el( TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Button text', 'galerio-core' ),
						help: __(
							'Leave empty for “Enquire about this work”, with the site’s name for works. Name tokens such as {work} work here too.',
							'galerio-core'
						),
						value: attributes.buttonText,
						onChange: ( value ) =>
							setAttributes( { buttonText: value } ),
					} )
				)
			),
			el(
				'div',
				blockProps,
				el(
					'span',
					{ className: 'wp-element-button' },
					attributes.buttonText ||
						__( 'Enquire about this work', 'galerio-core' )
				),
				el(
					'p',
					{ className: 'wp-block-galerio-enquiry__note' },
					__(
						'Opens a form sent by email. Shown only while the catalogue is on and the work is available or reserved, with no dealer.',
						'galerio-core'
					)
				)
			)
		);
	}

	registerBlockType( 'galerio/enquiry', {
		edit: Edit,
		save: () => null,
	} );
} )( window.wp );
