/**
 * A Focal point panel in the editor's sidebar, for the image that stands for
 * the item: its featured image, or its own image (a work's definitive image,
 * a collection's first image, a profile's portrait) as the item's "Use the …
 * instead" setting chooses, following Featured_Image.
 *
 * The point belongs to the image, not the item, so it's saved to the image
 * straight away, a moment after it stops moving, and holds wherever the
 * image is shown. Clear goes back to the default.
 *
 * Settings come from `window.galerioFocalPoint`, added by Focal_Point_Panel.
 *
 * @param {Object} wp   WordPress's scripts.
 * @param {Object} data Settings from the server.
 */
( function ( wp, data ) {
	if ( ! wp || ! data || ! wp.editor.PluginDocumentSettingPanel ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { Button, FocalPointPicker } = wp.components;
	const { store: coreStore, useEntityProp } = wp.coreData;
	const { useDispatch, useSelect } = wp.data;
	const { createElement: el, useEffect, useRef, useState } = wp.element;
	const { __, sprintf } = wp.i18n;

	// How long the point rests before it's saved, so dragging saves once.
	const DELAY = 600;

	const isPoint = ( point ) =>
		!! point && 'number' === typeof point.x && 'number' === typeof point.y;

	/**
	 * The image that stands for the item, as the server would choose it.
	 *
	 * @param {string} postType Item's type.
	 * @return {number} Attachment ID, or 0.
	 */
	function useItemImage( postType ) {
		const [ featured ] = useEntityProp(
			'postType',
			postType,
			'featured_media'
		);
		const [ meta ] = useEntityProp( 'postType', postType, 'meta' );
		const [ fields ] = useEntityProp(
			'postType',
			postType,
			'galerio_fields'
		);

		if ( ! data.own ) {
			return featured || 0;
		}

		const value = fields?.[ data.own.name ];
		const own = ( Array.isArray( value ) ? value[ 0 ] : value ) || 0;
		const chosen = !! meta?.[ data.own.meta ];

		if ( data.own.optIn ) {
			return chosen && own ? own : featured || 0;
		}

		if ( featured && ! ( chosen && own ) ) {
			return featured;
		}

		return own || featured || 0;
	}

	function FocalPointPanel() {
		const postType = useSelect(
			( select ) => select( 'core/editor' ).getCurrentPostType(),
			[]
		);
		const imageId = useItemImage( postType );
		const { image, canEdit } = useSelect(
			( select ) =>
				imageId
					? {
							image: select( coreStore ).getMedia( imageId, {
								context: 'edit',
							} ),
							canEdit: select( coreStore ).canUser( 'update', {
								kind: 'postType',
								name: 'attachment',
								id: imageId,
							} ),
						}
					: {},
			[ imageId ]
		);
		const { saveEntityRecord } = useDispatch( coreStore );

		const saved = image?.meta?.[ data.meta ];
		const [ point, setPoint ] = useState( null );
		const timer = useRef();

		// Another image: start from its own point.
		useEffect( () => {
			setPoint( null );
			return () => clearTimeout( timer.current );
		}, [ imageId ] );

		if ( ! imageId || ! image ) {
			return null;
		}

		const save = ( next ) =>
			saveEntityRecord( 'postType', 'attachment', {
				id: imageId,
				meta: { [ data.meta ]: next },
			} );

		const change = ( next ) => {
			setPoint( next );
			clearTimeout( timer.current );
			timer.current = setTimeout( () => save( next ), DELAY );
		};

		const clear = () => {
			clearTimeout( timer.current );
			setPoint( null );
			save( null );
		};

		const has = isPoint( point ) || isPoint( saved );
		const value = point || ( isPoint( saved ) ? saved : data.defaultPoint );
		const url =
			image.media_details?.sizes?.medium_large?.source_url ||
			image.media_details?.sizes?.large?.source_url ||
			image.source_url;

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'galerio-focal-point',
				title: __( 'Focal point', 'galerio-core' ),
				className: 'galerio-focal-point-panel',
			},
			el(
				'p',
				{ className: 'components-base-control__help' },
				__(
					'The part of the item’s image kept in view when it’s cropped, as in cards, covers and carousels. It’s saved with the image straight away and holds wherever the image is shown.',
					'galerio-core'
				)
			),
			canEdit
				? el( FocalPointPicker, {
						__nextHasNoMarginBottom: true,
						url,
						value,
						onChange: change,
					} )
				: el(
						'p',
						{ className: 'components-base-control__help' },
						__(
							'You can’t change this image’s focal point.',
							'galerio-core'
						)
					),
			canEdit && has
				? el(
						Button,
						{
							variant: 'secondary',
							size: 'compact',
							onClick: clear,
						},
						__( 'Clear', 'galerio-core' )
					)
				: null,
			! has
				? el(
						'p',
						{ className: 'components-base-control__help' },
						0.5 === data.defaultPoint.x &&
							0.5 === data.defaultPoint.y
							? __(
									'Not set: the image is centred.',
									'galerio-core'
								)
							: sprintf(
									/* translators: 1: how far across, 2: how far down, as percentages, such as "50%". */
									__(
										'Not set: the image is kept at this kind of item’s default, %1$s across and %2$s down.',
										'galerio-core'
									),
									Math.round( data.defaultPoint.x * 100 ) +
										'%',
									Math.round( data.defaultPoint.y * 100 ) +
										'%'
								)
					)
				: null
		);
	}

	registerPlugin( 'galerio-focal-point', { render: FocalPointPanel } );
} )( window.wp, window.galerioFocalPoint );
