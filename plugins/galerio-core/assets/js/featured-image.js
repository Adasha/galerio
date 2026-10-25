/**
 * A switch under the editor's Featured image panel: whether the item uses
 * its own image (a work's definitive image, a profile's portrait…) when it
 * has no featured image, or a plain colour instead.
 *
 * Settings come from `window.galerioFeaturedImage`, added by Featured_Image.
 *
 * @param {Object} wp   WordPress's scripts.
 * @param {Object} data Settings from the server.
 */
( function ( wp, data ) {
	if ( ! wp || ! data ) {
		return;
	}

	const { addFilter } = wp.hooks;
	const { ToggleControl } = wp.components;
	const { useEntityProp } = wp.coreData;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __, sprintf } = wp.i18n;

	function FallbackSwitch() {
		const postType = useSelect(
			( select ) => select( 'core/editor' ).getCurrentPostType(),
			[]
		);
		const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
		const [ featured ] = useEntityProp(
			'postType',
			postType,
			'featured_media'
		);

		if ( ! meta || featured ) {
			return null;
		}

		const off = !! meta[ data.meta ];

		return el( ToggleControl, {
			__nextHasNoMarginBottom: true,
			className: 'galerio-featured-image-fallback',
			label: data.multiple
				? sprintf(
						/* translators: %s: an image list field's name, such as "Images". */
						__(
							'Use the first of its “%s” instead',
							'galerio-core'
						),
						data.field
					)
				: sprintf(
						/* translators: %s: an image field's name, such as "Portrait". */
						__( 'Use the “%s” instead', 'galerio-core' ),
						data.field
					),
			help: off
				? __(
						'A plain colour shows where the featured image would be.',
						'galerio-core'
					)
				: __(
						'Shown where the featured image would be, while none is set.',
						'galerio-core'
					),
			checked: ! off,
			onChange: ( value ) =>
				setMeta(
					Object.assign( {}, meta, { [ data.meta ]: ! value } )
				),
		} );
	}

	addFilter(
		'editor.PostFeaturedImage',
		'galerio/featured-image-fallback',
		( OriginalComponent ) => ( props ) =>
			el(
				Fragment,
				null,
				el( OriginalComponent, props ),
				el( FallbackSwitch )
			)
	);
} )( window.wp, window.galerioFeaturedImage );
