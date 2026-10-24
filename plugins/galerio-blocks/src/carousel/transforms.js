/**
 * Transforms from the two prototypes this block replaces: the Post Slider
 * (`post-slider-block/slider`) and the Query Carousel (`custom/query-carousel`).
 * They're offered while the prototype's plugin is still active.
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

const transforms = {
	from: [
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
};

export default transforms;
