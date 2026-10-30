/**
 * Stops a Cover that uses the featured image from redrawing a Query Loop
 * without end.
 *
 * A Cover with "Use featured image" works out from the image whether it is
 * dark, and saves that as its `isDark` attribute. In a Query Loop each item
 * has its own image, but the blocks inside, and a template part's above all,
 * are shared by every item: a dark image sets `isDark`, a light one clears
 * it, and each change redraws all of them, which sets it again. On
 * swansea.art, collection cards over a mix of dark and light images
 * flickered without end.
 *
 * Inside a Query Loop, such a Cover keeps the `isDark` it was saved with
 * (one value can't suit every item anyway), and its updates that change
 * nothing are dropped: in a template part each one would be an edit to the
 * part, which redraws every item, whose Covers update again.
 *
 * @param {Object} wp WordPress's scripts.
 */
( function ( wp ) {
	if ( ! wp ) {
		return;
	}

	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { useSelect } = wp.data;
	const { createElement: el, useCallback } = wp.element;

	addFilter(
		'editor.BlockEdit',
		'galerio/loop-covers',
		createHigherOrderComponent(
			( BlockEdit ) => ( props ) => {
				// A Cover showing another item's featured image than the
				// post being edited's: one of a Query Loop's items. (Those
				// not being edited are previews, outside the Query Loop's
				// blocks, so its context is what tells.)
				const itemId =
					props.name === 'core/cover' &&
					props.attributes.useFeaturedImage
						? props.context?.postId
						: undefined;
				const inLoop = useSelect(
					( select ) =>
						!! itemId &&
						itemId !== select( 'core/editor' )?.getCurrentPostId(),
					[ itemId ]
				);

				const { attributes, setAttributes } = props;
				const keepIsDark = useCallback(
					( next ) => {
						// Leave out isDark, and anything already so: inside a
						// template part, even an update that changes nothing
						// is an edit to the part, which redraws every item.
						const changes = {};
						Object.keys( next || {} ).forEach( ( key ) => {
							if (
								key !== 'isDark' &&
								next[ key ] !== attributes[ key ]
							) {
								changes[ key ] = next[ key ];
							}
						} );

						if ( Object.keys( changes ).length ) {
							setAttributes( changes );
						}
					},
					[ attributes, setAttributes ]
				);

				return el(
					BlockEdit,
					inLoop
						? Object.assign( {}, props, {
								setAttributes: keepIsDark,
							} )
						: props
				);
			},
			'withGalerioLoopCovers'
		)
	);
} )( window.wp );
