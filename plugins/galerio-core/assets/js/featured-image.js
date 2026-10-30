/**
 * Under the editor's Featured image panel: which image stands for the item
 * where a featured image shows (cards, lists, carousels, feeds, shared
 * links), its featured image or its own (a work's definitive image, a
 * collection's first image, a profile's portrait).
 *
 * - Profiles: a switch to use the portrait, whenever there is one. It's
 *   never used without it.
 * - Other types: with both images, a switch to use their own image instead
 *   of the featured image. With only their own image, a note that it's used.
 *
 * Settings come from `window.galerioFeaturedImage`, added by Featured_Image.
 * Whether the item has its own image comes from its `galerio_fields`, as
 * last saved.
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

	const name = data.multiple
		? sprintf(
				/* translators: %s: an image list field's name, such as "Images". */
				__( 'the first of its “%s”', 'galerio-core' ),
				data.field
			)
		: sprintf(
				/* translators: %s: an image field's name, such as "Definitive image". */
				__( 'the “%s”', 'galerio-core' ),
				data.field
			);

	const WHERE = __(
		'That’s the image in cards, lists, carousels, feeds and links shared on social media.',
		'galerio-core'
	);

	function ImageChoice() {
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
		const [ fields ] = useEntityProp(
			'postType',
			postType,
			'galerio_fields'
		);

		const value = fields?.[ data.name ];
		const hasOwn = Array.isArray( value ) ? value.length > 0 : !! value;

		if ( ! meta || ! hasOwn ) {
			return null;
		}

		const chosen = !! meta[ data.meta ];
		const set = ( next ) =>
			setMeta( Object.assign( {}, meta, { [ data.meta ]: next } ) );
		const pageNote = data.isWork
			? ' ' +
				__(
					'The work’s own page shows its definitive image either way.',
					'galerio-core'
				)
			: '';

		if ( data.optIn ) {
			return el( ToggleControl, {
				__nextHasNoMarginBottom: true,
				className: 'galerio-featured-image-choice',
				label: featured
					? sprintf(
							/* translators: %s: e.g. "the “Portrait”". */
							__(
								'Use %s instead of the featured image',
								'galerio-core'
							),
							name
						)
					: sprintf(
							/* translators: %s: e.g. "the “Portrait”". */
							__( 'Use %s as its image', 'galerio-core' ),
							name
						),
				help:
					( chosen
						? __(
								'Shown while the profile shows its portrait.',
								'galerio-core'
							)
						: __(
								'Off, the portrait is never used as the profile’s image.',
								'galerio-core'
							) ) +
					' ' +
					WHERE,
				checked: chosen,
				onChange: set,
			} );
		}

		if ( ! featured ) {
			return el(
				'p',
				{
					className:
						'galerio-featured-image-choice components-base-control__help',
				},
				sprintf(
					/* translators: %s: e.g. "the “Definitive image”". */
					__( 'With no featured image, %s is used.', 'galerio-core' ),
					name
				) +
					' ' +
					WHERE +
					pageNote
			);
		}

		return el( ToggleControl, {
			__nextHasNoMarginBottom: true,
			className: 'galerio-featured-image-choice',
			label: sprintf(
				/* translators: %s: e.g. "the “Definitive image”". */
				__( 'Use %s instead', 'galerio-core' ),
				name
			),
			help:
				( chosen
					? __( 'The featured image isn’t used.', 'galerio-core' )
					: __( 'The featured image is used.', 'galerio-core' ) ) +
				' ' +
				WHERE +
				pageNote,
			checked: chosen,
			onChange: set,
		} );
	}

	addFilter(
		'editor.PostFeaturedImage',
		'galerio/featured-image-choice',
		( OriginalComponent ) => ( props ) =>
			el(
				Fragment,
				null,
				el( OriginalComponent, props ),
				el( ImageChoice )
			)
	);
} )( window.wp, window.galerioFeaturedImage );
