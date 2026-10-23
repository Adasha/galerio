/**
 * Editor side of the Field block: settings in the sidebar and a live preview
 * rendered by the server, as on the front end.
 *
 * Field definitions come from `window.galerioFieldBindings`, which the
 * galerio/field bindings script provides.
 */
( function ( wp, data ) {
	if ( ! wp || ! data ) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, Placeholder } =
		wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __, sprintf } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	/**
	 * The fields of the post type being edited, or all fields when it isn't
	 * a Galerio type (for example in a template).
	 *
	 * @param {string} postType Post type from the block's context.
	 * @return {Object[]} Field definitions.
	 */
	function fieldsFor( postType ) {
		const object = Object.keys( data.objects ).find(
			( name ) => data.objects[ name ] === postType
		);
		return object
			? data.fields[ object ] || []
			: Object.values( data.fields ).flat();
	}

	/**
	 * The heading as the front end shows it: "{field}" becomes the field's
	 * label, and name tokens such as "{Collections}" the site's type names.
	 *
	 * @param {string}      text  Heading as written in the block.
	 * @param {Object|null} field Field definition.
	 * @return {string} Heading.
	 */
	function headingText( text, field ) {
		const names = data.names || {};

		return text
			.replace( '{field}', field ? field.label : '' )
			.replace( /\{[A-Za-z]+\}/g, ( token ) =>
				Object.prototype.hasOwnProperty.call( names, token )
					? names[ token ]
					: token
			);
	}

	registerBlockType( 'galerio/field-display', {
		edit( { attributes, setAttributes, context } ) {
			const fields = fieldsFor( context.postType );
			const field = fields.find(
				( item ) =>
					item.key === attributes.key || item.name === attributes.key
			);
			const switches = fields.filter(
				( item ) => item.type === 'boolean'
			);
			const blockProps = useBlockProps();

			const controls = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Field', 'galerio-core' ) },
					el( SelectControl, {
						label: __( 'Field', 'galerio-core' ),
						value: attributes.key,
						options: [
							{
								label: __( 'Choose a field', 'galerio-core' ),
								value: '',
							},
						].concat(
							fields
								.filter( ( item ) => item.type !== 'boolean' )
								.map( ( item ) => ( {
									label: item.label + ' (' + item.key + ')',
									value: item.key,
								} ) )
						),
						onChange: ( key ) => setAttributes( { key } ),
						__nextHasNoMarginBottom: true,
					} ),
					el( TextControl, {
						label: __( 'Heading', 'galerio-core' ),
						help: __(
							'Shown above the value. Leave empty for none. {field} is the field\'s name, and {Collections}, {works} and similar are your site\'s names for its content types.',
							'galerio-core'
						),
						value: attributes.label,
						onChange: ( label ) => setAttributes( { label } ),
						__nextHasNoMarginBottom: true,
					} ),
					el( SelectControl, {
						label: __( 'Layout', 'galerio-core' ),
						value: attributes.layout,
						options: [
							{
								label: __( 'As written', 'galerio-core' ),
								value: 'inline',
							},
							{
								label: __( 'One per line', 'galerio-core' ),
								value: 'list',
							},
						],
						onChange: ( layout ) => setAttributes( { layout } ),
						__nextHasNoMarginBottom: true,
					} ),
					el( SelectControl, {
						label: __( 'Show only when', 'galerio-core' ),
						value: attributes.showIf,
						options: [
							{
								label: __( 'Always', 'galerio-core' ),
								value: '',
							},
						].concat(
							switches.map( ( item ) => ( {
								label: item.label,
								value: item.key,
							} ) )
						),
						onChange: ( showIf ) => setAttributes( { showIf } ),
						__nextHasNoMarginBottom: true,
					} )
				)
			);

			let preview;
			if ( ! attributes.key ) {
				preview = el( Placeholder, {
					label: __( 'Field', 'galerio-core' ),
					instructions: __(
						'Choose a field in the block settings.',
						'galerio-core'
					),
				} );
			} else if ( ! context.postId ) {
				// In a template there is no post to read, so name the field instead.
				preview = el(
					'div',
					null,
					attributes.label &&
						el(
							'h4',
							{
								className:
									'wp-block-galerio-field-display__label',
							},
							headingText( attributes.label, field )
						),
					el(
						'p',
						null,
						sprintf(
							/* translators: %s: field name */
							__( '[%s]', 'galerio-core' ),
							field ? field.label : attributes.key
						)
					)
				);
			} else {
				preview = el( ServerSideRender, {
					block: 'galerio/field-display',
					attributes,
					urlQueryArgs: { post_id: context.postId },
					EmptyResponsePlaceholder: () =>
						el(
							'p',
							{
								className:
									'wp-block-galerio-field-display__empty',
							},
							sprintf(
								/* translators: %s: field name */
								__(
									'%s is empty, so this block is hidden.',
									'galerio-core'
								),
								field ? field.label : attributes.key
							)
						),
				} );
			}

			return el(
				Fragment,
				null,
				controls,
				el( 'div', blockProps, preview )
			);
		},

		save: () => null,
	} );
} )( window.wp, window.galerioFieldBindings );
