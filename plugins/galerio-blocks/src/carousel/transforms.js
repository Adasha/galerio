/**
 * Transforms between the carousel and a Query Loop, both ways, and from the
 * two prototypes this block replaces: the Post Slider
 * (`post-slider-block/slider`) and the Query Carousel (`custom/query-carousel`).
 * The prototypes' are offered while their plugin is still active.
 */
import { createBlock } from '@wordpress/blocks';

const POSITIONS = {
	left: 'top left',
	center: 'top center',
	right: 'top right',
	'bottom-left': 'bottom left',
	'bottom-center': 'bottom center',
	'bottom-right': 'bottom right',
};

/**
 * The order of a carousel's related items, as a Query Loop's
 * `galerioRelatedOrder`.
 *
 * @param {string} orderBy The carousel's order.
 * @return {string} "saved", "random" or "query".
 */
function relatedOrder( orderBy ) {
	if ( orderBy === 'post__in' ) {
		return 'saved';
	}
	return orderBy === 'rand' ? 'random' : 'query';
}

/**
 * A Query Loop that shows what a carousel's own query shows: the same items
 * in a grid of the slide design, or the theme's card for the type, or else
 * image and title.
 *
 * @param {Object} attributes The carousel's attributes.
 * @return {Object} A core/query block.
 */
function queryFromCarousel( attributes ) {
	const query = {
		perPage: attributes.perPage,
		pages: 0,
		offset: 0,
		postType: attributes.postType,
		order: attributes.order,
		orderBy:
			attributes.orderBy === 'post__in' ? 'date' : attributes.orderBy,
		author: '',
		search: '',
		exclude: [],
		sticky: '',
		inherit: false,
	};

	if ( attributes.taxonomy && attributes.terms.length ) {
		// WordPress 7.0's format, which the editor reads.
		query.taxQuery = {
			include: { [ attributes.taxonomy ]: attributes.terms },
		};
	}

	const extra = {};
	if ( attributes.related ) {
		query.galerioRelated = attributes.related;
		extra.galerioRelatedOrder = relatedOrder( attributes.orderBy );
	}

	const cards = window.galerioQueryVariations?.cards ?? {};
	const part = attributes.slidePart || cards[ attributes.postType ];
	const item = part
		? [
				createBlock( 'core/template-part', {
					slug: part,
					tagName: 'div',
				} ),
			]
		: [
				createBlock( 'core/post-featured-image', {
					isLink: true,
					aspectRatio: '4/3',
				} ),
				createBlock( 'core/post-title', {
					isLink: true,
					level: 3,
					fontSize: 'medium',
				} ),
			];

	return createBlock( 'core/query', { query, ...extra }, [
		createBlock(
			'core/post-template',
			{
				layout: {
					type: 'grid',
					columnCount: Math.min(
						Math.max( attributes.slidesPerView, 1 ),
						3
					),
				},
			},
			item
		),
	] );
}

const transforms = {
	from: [
		{
			// Wrap the Query Loop: its items become the slides, in its own
			// design. A grid keeps as many items in view as it had columns.
			type: 'block',
			blocks: [ 'core/query' ],
			transform: ( attributes, innerBlocks ) => {
				const template = innerBlocks.find(
					( block ) => block.name === 'core/post-template'
				);
				const layout = template?.attributes.layout;
				const columns =
					layout?.type === 'grid' ? layout.columnCount || 3 : 1;

				return createBlock(
					'galerio/carousel',
					{
						source: 'loop',
						slidesPerView: Math.min( columns, 6 ),
					},
					[ createBlock( 'core/query', attributes, innerBlocks ) ]
				);
			},
		},
		{
			type: 'block',
			blocks: [ 'post-slider-block/slider' ],
			transform: ( attributes ) =>
				createBlock( 'galerio/carousel', {
					source: 'query',
					postType: attributes.postType ?? 'post',
					perPage: attributes.numberOfPosts ?? 5,
					taxonomy: attributes.taxonomy ?? '',
					terms: attributes.selectedTerms ?? [],
					orderBy: attributes.orderBy ?? 'date',
					order: String( attributes.order ?? 'desc' ).toLowerCase(),
					imageSize: attributes.imageSize ?? 'large',
					height: `${ attributes.slideHeight ?? 520 }px`,
					contentPosition:
						POSITIONS[ attributes.contentPosition ] ?? 'top left',
					titleLevel:
						Number(
							String( attributes.headingTag ?? 'h3' ).slice( 1 )
						) || 3,
					showExcerpt: attributes.showExcerpt ?? true,
					buttonText:
						attributes.showReadMore === false
							? ''
							: ( attributes.readMoreText ?? 'Read More' ),
					// Fade is the only one of its six transitions kept.
					transition:
						attributes.transition === 'fade' ? 'fade' : 'slide',
					autoplay: attributes.autoplay ?? false,
					interval: attributes.autoplayInterval ?? 5000,
					showArrows: attributes.showNavigation ?? true,
					showDots: attributes.showDots ?? true,
				} ),
		},
		{
			type: 'block',
			blocks: [ 'custom/query-carousel' ],
			transform: ( attributes, innerBlocks ) =>
				createBlock(
					'galerio/carousel',
					{
						source: 'loop',
						transition:
							attributes.transitionEffect === 'fade'
								? 'fade'
								: 'slide',
						autoplay: attributes.autoplay ?? false,
						interval: attributes.autoplaySpeed ?? 3000,
						loop: attributes.loop ?? true,
						showArrows: attributes.showArrows ?? true,
						showDots: attributes.showDots ?? true,
					},
					innerBlocks
				),
		},
	],
	to: [
		{
			// Unwrap the carousel's Query Loop, or make one from the
			// carousel's own query. Items picked by hand have no Query Loop
			// equivalent, so that carousel isn't offered the change.
			type: 'block',
			blocks: [ 'core/query' ],
			isMatch: ( attributes, block ) =>
				attributes.source === 'loop'
					? !! block?.innerBlocks?.some(
							( inner ) => inner.name === 'core/query'
						)
					: ! attributes.postIds.length,
			transform: ( attributes, innerBlocks ) => {
				if ( attributes.source === 'loop' ) {
					const loop = innerBlocks.find(
						( block ) => block.name === 'core/query'
					);
					return createBlock(
						'core/query',
						loop.attributes,
						loop.innerBlocks
					);
				}
				return queryFromCarousel( attributes );
			},
		},
	],
};

export default transforms;
