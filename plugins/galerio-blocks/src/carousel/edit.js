/**
 * Editor side of the Carousel block: settings in the sidebar, with a preview
 * rendered by the server for the block's own query, or the wrapped Query Loop.
 */
import { __, _x } from '@wordpress/i18n';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	FormTokenField,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * A Query Loop to start from, with a featured image and title per item.
 */
const LOOP_TEMPLATE = [
	[
		'core/query',
		{
			query: {
				perPage: 5,
				pages: 0,
				offset: 0,
				postType: 'post',
				order: 'desc',
				orderBy: 'date',
				inherit: false,
			},
		},
		[
			[
				'core/post-template',
				{},
				[
					[ 'core/post-featured-image', { isLink: true } ],
					[ 'core/post-title', { isLink: true } ],
				],
			],
		],
	],
];

const EMPTY = [];

const POSITIONS = [
	{ label: __( 'Top left', 'galerio-blocks' ), value: 'top left' },
	{ label: __( 'Top centre', 'galerio-blocks' ), value: 'top center' },
	{ label: __( 'Top right', 'galerio-blocks' ), value: 'top right' },
	{ label: __( 'Middle left', 'galerio-blocks' ), value: 'center left' },
	{ label: __( 'Middle', 'galerio-blocks' ), value: 'center center' },
	{ label: __( 'Middle right', 'galerio-blocks' ), value: 'center right' },
	{ label: __( 'Bottom left', 'galerio-blocks' ), value: 'bottom left' },
	{ label: __( 'Bottom centre', 'galerio-blocks' ), value: 'bottom center' },
	{ label: __( 'Bottom right', 'galerio-blocks' ), value: 'bottom right' },
];

const ORDERS = [
	{ label: __( 'Newest first', 'galerio-blocks' ), value: 'date/desc' },
	{ label: __( 'Oldest first', 'galerio-blocks' ), value: 'date/asc' },
	{ label: __( 'Title, A to Z', 'galerio-blocks' ), value: 'title/asc' },
	{ label: __( 'Title, Z to A', 'galerio-blocks' ), value: 'title/desc' },
	{
		label: __( 'Recently updated first', 'galerio-blocks' ),
		value: 'modified/desc',
	},
	{ label: __( 'Page order', 'galerio-blocks' ), value: 'menu_order/asc' },
	{ label: __( 'Random', 'galerio-blocks' ), value: 'rand/desc' },
];

/**
 * The transitions offered, including any added by the PHP filter.
 *
 * @return {Object[]} Options with `label` and `value`.
 */
function transitionOptions() {
	return (
		window.galerioCarousel?.transitions || [
			{ label: __( 'Slide', 'galerio-blocks' ), value: 'slide' },
			{ label: __( 'Fade', 'galerio-blocks' ), value: 'fade' },
		]
	);
}

/**
 * Pick records by name in a token field, storing their IDs in order.
 *
 * @param {Object}                  props
 * @param {string}                  props.label    Field label.
 * @param {string}                  props.help     Help text.
 * @param {Object[]}                props.records  Known records, each with `id` and `name`.
 * @param {number[]}                props.value    Chosen IDs.
 * @param {(ids: number[]) => void} props.onChange Receives the new IDs.
 * @param {(text: string) => void}  props.onSearch Receives the text typed so far.
 */
function RecordTokens( { label, help, records, value, onChange, onSearch } ) {
	const byId = new Map( records.map( ( record ) => [ record.id, record ] ) );
	const byName = new Map(
		records.map( ( record ) => [ record.name, record.id ] )
	);

	return (
		<FormTokenField
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			value={ value
				.filter( ( id ) => byId.has( id ) )
				.map( ( id ) => byId.get( id ).name ) }
			suggestions={ records.map( ( record ) => record.name ) }
			onChange={ ( names ) =>
				onChange(
					names
						.map( ( name ) => byName.get( name ) )
						.filter( Boolean )
				)
			}
			onInputChange={ onSearch }
			__experimentalExpandOnFocus
			__experimentalShowHowTo={ false }
			help={ help }
		/>
	);
}

/**
 * Settings of the block's own query.
 *
 * @param {Object}                 props
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Attribute setter.
 */
function QuerySettings( { attributes, setAttributes } ) {
	const { postType, perPage, orderBy, order, taxonomy, terms, postIds } =
		attributes;
	const [ search, setSearch ] = useState( '' );

	const { allTypes, taxonomies, termRecords, chosen, found } = useSelect(
		( select ) => {
			const { getPostTypes, getTaxonomies, getEntityRecords } =
				select( coreStore );

			return {
				allTypes: getPostTypes( { per_page: -1 } ) || EMPTY,
				taxonomies:
					getTaxonomies( { type: postType, per_page: -1 } ) || EMPTY,
				termRecords:
					( taxonomy &&
						getEntityRecords( 'taxonomy', taxonomy, {
							per_page: 100,
							_fields: 'id,name',
						} ) ) ||
					EMPTY,
				chosen:
					( postIds.length &&
						getEntityRecords( 'postType', postType, {
							include: postIds,
							per_page: postIds.length,
							_fields: 'id,title',
						} ) ) ||
					EMPTY,
				found:
					getEntityRecords( 'postType', postType, {
						search,
						per_page: 20,
						status: 'publish',
						_fields: 'id,title',
					} ) || EMPTY,
			};
		},
		[ postType, taxonomy, postIds, search ]
	);

	const postTypes = allTypes.filter(
		( type ) => type.viewable && type.slug !== 'attachment'
	);
	const posts = [];
	const seen = new Set();
	[ ...chosen, ...found ].forEach( ( post ) => {
		if ( ! seen.has( post.id ) ) {
			seen.add( post.id );
			posts.push( {
				id: post.id,
				name: post.title?.rendered || `#${ post.id }`,
			} );
		}
	} );

	return (
		<PanelBody title={ __( 'Items', 'galerio-blocks' ) }>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Content type', 'galerio-blocks' ) }
				value={ postType }
				options={ postTypes.map( ( type ) => ( {
					label: type.name,
					value: type.slug,
				} ) ) }
				onChange={ ( value ) =>
					setAttributes( {
						postType: value,
						taxonomy: '',
						terms: [],
						postIds: [],
					} )
				}
			/>
			<RecordTokens
				label={ __( 'Chosen items', 'galerio-blocks' ) }
				help={ __(
					'Leave empty to show the latest items. Chosen items replace the settings below and show in the order chosen.',
					'galerio-blocks'
				) }
				records={ posts }
				value={ postIds }
				onChange={ ( ids ) => setAttributes( { postIds: ids } ) }
				onSearch={ setSearch }
			/>
			{ ! postIds.length && (
				<>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Number of items', 'galerio-blocks' ) }
						value={ perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 1 }
						max={ 20 }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Order', 'galerio-blocks' ) }
						value={ `${ orderBy }/${ order }` }
						options={ ORDERS }
						onChange={ ( value ) => {
							const [ by, direction ] = value.split( '/' );
							setAttributes( { orderBy: by, order: direction } );
						} }
					/>
					{ !! taxonomies.length && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Only items in', 'galerio-blocks' ) }
							value={ taxonomy }
							options={ [
								{
									label: _x(
										'Any',
										'taxonomy',
										'galerio-blocks'
									),
									value: '',
								},
								...taxonomies.map( ( item ) => ( {
									label: item.name,
									value: item.slug,
								} ) ),
							] }
							onChange={ ( value ) =>
								setAttributes( { taxonomy: value, terms: [] } )
							}
						/>
					) }
					{ !! taxonomy && (
						<RecordTokens
							label={ __( 'Terms', 'galerio-blocks' ) }
							records={ termRecords }
							value={ terms }
							onChange={ ( ids ) =>
								setAttributes( { terms: ids } )
							}
						/>
					) }
				</>
			) }
		</PanelBody>
	);
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		source,
		imageFit,
		height,
		contentPosition,
		showTitle,
		titleLevel,
		showExcerpt,
		buttonText,
		slidesPerView,
		transition,
		duration,
		loop,
		autoplay,
		interval,
		showArrows,
		arrowsPosition,
		showDots,
		label,
	} = attributes;
	const fromLoop = source === 'loop';
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Source', 'galerio-blocks' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Slides come from', 'galerio-blocks' ) }
						value={ source }
						options={ [
							{
								label: __(
									'Items with their images',
									'galerio-blocks'
								),
								value: 'query',
							},
							{
								label: __( 'A Query Loop', 'galerio-blocks' ),
								value: 'loop',
							},
						] }
						help={
							fromLoop
								? __(
										'Each item of the Query Loop inside becomes a slide, laid out by the loop’s own template.',
										'galerio-blocks'
									)
								: __(
										'Each item is a slide: its featured image, with its title and a button over it.',
										'galerio-blocks'
									)
						}
						onChange={ ( value ) => {
							if ( value === 'query' ) {
								replaceInnerBlocks( clientId, [] );
							}
							setAttributes( { source: value } );
						} }
					/>
				</PanelBody>
				{ ! fromLoop && (
					<QuerySettings
						attributes={ attributes }
						setAttributes={ setAttributes }
					/>
				) }
				{ ! fromLoop && (
					<PanelBody title={ __( 'Slides', 'galerio-blocks' ) }>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Height', 'galerio-blocks' ) }
							help={ __(
								'For example 70vh (70% of the window’s height), 500px or 30rem.',
								'galerio-blocks'
							) }
							value={ height }
							onChange={ ( value ) =>
								setAttributes( { height: value.trim() } )
							}
						/>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Caption position', 'galerio-blocks' ) }
							value={ contentPosition }
							options={ POSITIONS }
							onChange={ ( value ) =>
								setAttributes( { contentPosition: value } )
							}
						/>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Image', 'galerio-blocks' ) }
							value={ imageFit }
							options={ [
								{
									label: __(
										'Fill the slide (may crop)',
										'galerio-blocks'
									),
									value: 'cover',
								},
								{
									label: __(
										'Show it whole',
										'galerio-blocks'
									),
									value: 'contain',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { imageFit: value } )
							}
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Show the title', 'galerio-blocks' ) }
							checked={ showTitle }
							onChange={ ( value ) =>
								setAttributes( { showTitle: value } )
							}
						/>
						{ showTitle && (
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Title heading level',
									'galerio-blocks'
								) }
								value={ String( titleLevel ) }
								options={ [ 1, 2, 3, 4, 5, 6 ].map(
									( level ) => ( {
										label: `H${ level }`,
										value: String( level ),
									} )
								) }
								onChange={ ( value ) =>
									setAttributes( {
										titleLevel: Number( value ),
									} )
								}
							/>
						) }
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Show the excerpt', 'galerio-blocks' ) }
							help={ __(
								'Shown for items with a written excerpt.',
								'galerio-blocks'
							) }
							checked={ showExcerpt }
							onChange={ ( value ) =>
								setAttributes( { showExcerpt: value } )
							}
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Button text', 'galerio-blocks' ) }
							help={ __(
								'Leave empty for no button; the title links to the item instead.',
								'galerio-blocks'
							) }
							value={ buttonText }
							onChange={ ( value ) =>
								setAttributes( { buttonText: value } )
							}
						/>
					</PanelBody>
				) }
				<PanelBody title={ __( 'Motion', 'galerio-blocks' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Transition', 'galerio-blocks' ) }
						value={ transition }
						options={ transitionOptions() }
						onChange={ ( value ) =>
							setAttributes( { transition: value } )
						}
					/>
					{ transition !== 'fade' && (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Slides in view', 'galerio-blocks' ) }
							value={ slidesPerView }
							onChange={ ( value ) =>
								setAttributes( { slidesPerView: value } )
							}
							min={ 1 }
							max={ 6 }
						/>
					) }
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Transition length', 'galerio-blocks' ) }
						help={ __( 'Higher is slower.', 'galerio-blocks' ) }
						value={ duration }
						onChange={ ( value ) =>
							setAttributes( { duration: value } )
						}
						min={ 10 }
						max={ 60 }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Go back to the start after the last slide',
							'galerio-blocks'
						) }
						checked={ loop }
						onChange={ ( value ) =>
							setAttributes( { loop: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Play automatically', 'galerio-blocks' ) }
						help={ __(
							'Visitors get a pause button. It starts paused for visitors who prefer less motion.',
							'galerio-blocks'
						) }
						checked={ autoplay }
						onChange={ ( value ) =>
							setAttributes( { autoplay: value } )
						}
					/>
					{ autoplay && (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Seconds per slide',
								'galerio-blocks'
							) }
							value={ interval / 1000 }
							onChange={ ( value ) =>
								setAttributes( { interval: value * 1000 } )
							}
							min={ 2 }
							max={ 30 }
							step={ 0.5 }
						/>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Navigation', 'galerio-blocks' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show arrows', 'galerio-blocks' ) }
						checked={ showArrows }
						onChange={ ( value ) =>
							setAttributes( { showArrows: value } )
						}
					/>
					{ showArrows && ! fromLoop && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Arrows sit', 'galerio-blocks' ) }
							value={ arrowsPosition }
							options={ [
								{
									label: __(
										'At the sides',
										'galerio-blocks'
									),
									value: 'sides',
								},
								{
									label: __(
										'On the caption',
										'galerio-blocks'
									),
									value: 'caption',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { arrowsPosition: value } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show dots', 'galerio-blocks' ) }
						checked={ showDots }
						onChange={ ( value ) =>
							setAttributes( { showDots: value } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Name for screen readers',
							'galerio-blocks'
						) }
						help={ __(
							'For example “Current exhibitions”. Leave empty for “Carousel”.',
							'galerio-blocks'
						) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ fromLoop ? (
					<InnerBlocks
						allowedBlocks={ [ 'core/query' ] }
						template={ LOOP_TEMPLATE }
						templateLock="insert"
					/>
				) : (
					<ServerSideRender
						block="galerio/carousel"
						attributes={ attributes }
						skipBlockSupportAttributes
					/>
				) }
			</div>
		</>
	);
}
