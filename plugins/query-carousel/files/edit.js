/**
 * src/edit.js
 *
 * The Edit component rendered inside the Gutenberg block editor.
 * Provides InspectorControls for all carousel configuration attributes
 * and restricts inner blocks strictly to `core/query`.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	Notice,
} from '@wordpress/components';
import { addFilter, applyFilters } from '@wordpress/hooks';

/**
 * The default inner blocks template.
 * Pre-populates the carousel with a single core/query block so the user
 * immediately sees a functional starting point rather than an empty container.
 *
 * The core/query block receives a post-template child so the layout
 * renders in the editor.
 *
 * @type {Array}
 */
const INNER_BLOCKS_TEMPLATE = [
	[
		'core/query',
		{
			queryId: 0,
			query: {
				perPage: 3,
				pages: 0,
				offset: 0,
				postType: 'post',
				order: 'desc',
				orderBy: 'date',
				author: '',
				search: '',
				exclude: [],
				sticky: '',
				inherit: true,
			},
			layout: { type: 'default' },
		},
		[
			[
				'core/post-template',
				{},
				[
					[ 'core/post-featured-image', {} ],
					[ 'core/post-title', { isLink: true } ],
					[ 'core/post-excerpt', {} ],
				],
			],
		],
	],
];

/**
 * Only one core/query block is allowed as a direct child.
 * This restriction is enforced via InnerBlocks `allowedBlocks`.
 *
 * @type {string[]}
 */
const ALLOWED_BLOCKS = [ 'core/query' ];

/**
 * Build the transition effect options array.
 *
 * This merges the default options (from PHP-localised data if available,
 * or hardcoded defaults) with any additional effects registered via the
 * WordPress JS hooks API using the `query_carousel.transition_effects` filter.
 *
 * Third-party developers can add effects:
 *
 *   import { addFilter } from '@wordpress/hooks';
 *   addFilter(
 *     'query_carousel.transition_effects',
 *     'my-plugin/flip-effect',
 *     ( effects ) => [ ...effects, { label: 'Flip', value: 'flip' } ]
 *   );
 *
 * @returns {Array<{label: string, value: string}>}
 */
function getTransitionEffectOptions() {
	// Prefer PHP-localised data so server-side filters are respected.
	const phpEffects =
		window?.queryCarouselData?.transitionEffects ?? [
			{ label: __( 'Slide', 'query-carousel-block' ), value: 'slide' },
			{ label: __( 'Fade', 'query-carousel-block' ), value: 'fade' },
		];

	// Allow JS-side augmentation via WordPress hooks.
	return applyFilters(
		'query_carousel.transition_effects',
		phpEffects
	);
}

/**
 * The Edit component for the custom/query-carousel block.
 *
 * @param {Object}   props               Block props injected by the editor.
 * @param {Object}   props.attributes    Block attribute values.
 * @param {Function} props.setAttributes Attribute setter function.
 * @returns {JSX.Element}
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		transitionEffect,
		autoplay,
		autoplaySpeed,
		loop,
		showArrows,
		showDots,
	} = attributes;

	// useBlockProps wires up the block wrapper with necessary HTML attributes,
	// class names, event handlers, and data attributes for the editor.
	const blockProps = useBlockProps( {
		className: 'query-carousel-editor-wrapper',
	} );

	const transitionEffectOptions = getTransitionEffectOptions();

	return (
		<>
			{/* ── Sidebar Inspector Controls ───────────────────────────── */}
			<InspectorControls>
				<PanelBody
					title={
						window?.queryCarouselData?.i18n?.panelTitle ??
						__( 'Carousel Settings', 'query-carousel-block' )
					}
					initialOpen={ true }
				>
					{/* Transition Effect */}
					<SelectControl
						label={
							window?.queryCarouselData?.i18n?.transitionLabel ??
							__( 'Transition Effect', 'query-carousel-block' )
						}
						value={ transitionEffect }
						options={ transitionEffectOptions }
						onChange={ ( value ) =>
							setAttributes( { transitionEffect: value } )
						}
						__nextHasNoMarginBottom
					/>

					{/* Loop */}
					<ToggleControl
						label={
							window?.queryCarouselData?.i18n?.loopLabel ??
							__( 'Loop', 'query-carousel-block' )
						}
						help={
							window?.queryCarouselData?.i18n?.loopHelp ??
							__( 'Loop slides back to the beginning.', 'query-carousel-block' )
						}
						checked={ loop }
						onChange={ ( value ) =>
							setAttributes( { loop: value } )
						}
						__nextHasNoMarginBottom
					/>

					{/* Autoplay */}
					<ToggleControl
						label={
							window?.queryCarouselData?.i18n?.autoplayLabel ??
							__( 'Autoplay', 'query-carousel-block' )
						}
						help={
							window?.queryCarouselData?.i18n?.autoplayHelp ??
							__( 'Automatically advance slides.', 'query-carousel-block' )
						}
						checked={ autoplay }
						onChange={ ( value ) =>
							setAttributes( { autoplay: value } )
						}
						__nextHasNoMarginBottom
					/>

					{/* Autoplay Speed – only shown when autoplay is enabled */}
					{ autoplay && (
						<RangeControl
							label={
								window?.queryCarouselData?.i18n?.autoplaySpeed ??
								__( 'Autoplay Speed (ms)', 'query-carousel-block' )
							}
							value={ autoplaySpeed }
							onChange={ ( value ) =>
								setAttributes( { autoplaySpeed: value } )
							}
							min={ 500 }
							max={ 10000 }
							step={ 100 }
							__nextHasNoMarginBottom
						/>
					) }

					{/* Navigation Arrows */}
					<ToggleControl
						label={
							window?.queryCarouselData?.i18n?.arrowsLabel ??
							__( 'Show Navigation Arrows', 'query-carousel-block' )
						}
						checked={ showArrows }
						onChange={ ( value ) =>
							setAttributes( { showArrows: value } )
						}
						__nextHasNoMarginBottom
					/>

					{/* Pagination Dots */}
					<ToggleControl
						label={
							window?.queryCarouselData?.i18n?.dotsLabel ??
							__( 'Show Pagination Dots', 'query-carousel-block' )
						}
						checked={ showDots }
						onChange={ ( value ) =>
							setAttributes( { showDots: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			{/* ── Editor Block Wrapper ──────────────────────────────────── */}
			<div { ...blockProps }>
				{/*
				 * Editor-only notice so content editors understand the
				 * carousel behaviour is only active on the frontend.
				 * This avoids confusion when editing nested query blocks.
				 */}
				<Notice
					status="info"
					isDismissible={ false }
					className="query-carousel-editor-notice"
				>
					{ __(
						'Carousel wrapper — add one Query Loop block inside. Swipe behaviour is active on the frontend.',
						'query-carousel-block'
					) }
				</Notice>

				{/*
				 * InnerBlocks:
				 * - allowedBlocks: hard-gates children to core/query only.
				 * - template: inserts a starter Query Loop if the block is new.
				 * - templateLock: false allows the inner query to be reconfigured
				 *   but "all" could be used to lock it down after scaffolding.
				 */}
				<InnerBlocks
					allowedBlocks={ ALLOWED_BLOCKS }
					template={ INNER_BLOCKS_TEMPLATE }
					templateLock={ false }
					renderAppender={ InnerBlocks.ButtonBlockAppender }
				/>
			</div>
		</>
	);
}
