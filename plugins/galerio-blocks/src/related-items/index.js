/**
 * The Related Items block: settings in the sidebar and a preview rendered by
 * the server, as on the front end.
 *
 * Retired: a Query Loop's Related items setting does the same with the
 * theme's cards. The block still renders where it's used, is hidden from the
 * inserter, and transforms into a related Query Loop.
 */
import { createBlock, registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.scss';

const DATA = window.galerioRelatedItems || { relations: [], details: {} };

const RATIOS = [
	{ label: __( 'Square', 'galerio-blocks' ), value: '1' },
	{ label: __( 'Landscape, 4:3', 'galerio-blocks' ), value: '4/3' },
	{ label: __( 'Portrait, 3:4', 'galerio-blocks' ), value: '3/4' },
	{ label: __( 'Landscape, 3:2', 'galerio-blocks' ), value: '3/2' },
	{ label: __( 'Wide, 16:9', 'galerio-blocks' ), value: '16/9' },
	{ label: __( 'Each image’s own shape', 'galerio-blocks' ), value: 'auto' },
];

function Edit( { attributes, setAttributes, context } ) {
	const {
		field,
		heading,
		headingLevel,
		layout,
		columns,
		limit,
		order,
		showImage,
		aspectRatio,
		detail,
	} = attributes;

	// Relationships of the item being edited; in a template, all of them.
	const own = DATA.relations.filter(
		( relation ) => relation.postType === context.postType
	);
	const relations = own.length ? own : DATA.relations;
	const current =
		relations.find( ( relation ) => relation.key === field ) ||
		relations[ 0 ];
	const details = ( current && DATA.details[ current.target ] ) || [];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Items', 'galerio-blocks' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Show', 'galerio-blocks' ) }
						value={ field }
						options={ [
							{
								label: __(
									'The first relationship',
									'galerio-blocks'
								),
								value: '',
							},
							...relations.map( ( relation ) => ( {
								label: relation.label,
								value: relation.key,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { field: value, detail: '' } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'galerio-blocks' ) }
						help={ __(
							'{field} is the relationship’s name; names such as {Works} follow the site’s. Leave empty for none.',
							'galerio-blocks'
						) }
						value={ heading }
						onChange={ ( value ) =>
							setAttributes( { heading: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'galerio-blocks' ) }
						value={ String( headingLevel ) }
						options={ [ 2, 3, 4, 5, 6 ].map( ( level ) => ( {
							label: `H${ level }`,
							value: String( level ),
						} ) ) }
						onChange={ ( value ) =>
							setAttributes( { headingLevel: Number( value ) } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Order', 'galerio-blocks' ) }
						value={ order }
						options={ [
							{
								label: __( 'As saved', 'galerio-blocks' ),
								value: 'saved',
							},
							{
								label: __( 'By title', 'galerio-blocks' ),
								value: 'title',
							},
							{
								label: __(
									'Random on every visit',
									'galerio-blocks'
								),
								value: 'random',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { order: value } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Most to show', 'galerio-blocks' ) }
						help={ __( '0 shows them all.', 'galerio-blocks' ) }
						value={ limit }
						onChange={ ( value ) =>
							setAttributes( { limit: value } )
						}
						min={ 0 }
						max={ 24 }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Cards', 'galerio-blocks' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'galerio-blocks' ) }
						value={ layout }
						options={ [
							{
								label: __( 'Grid', 'galerio-blocks' ),
								value: 'grid',
							},
							{
								label: __(
									'List, image beside the title',
									'galerio-blocks'
								),
								value: 'list',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					{ layout === 'grid' && (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Columns', 'galerio-blocks' ) }
							value={ columns }
							onChange={ ( value ) =>
								setAttributes( { columns: value } )
							}
							min={ 1 }
							max={ 6 }
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show images', 'galerio-blocks' ) }
						checked={ showImage }
						onChange={ ( value ) =>
							setAttributes( { showImage: value } )
						}
					/>
					{ showImage && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Image shape', 'galerio-blocks' ) }
							value={ aspectRatio }
							options={ RATIOS }
							onChange={ ( value ) =>
								setAttributes( { aspectRatio: value } )
							}
						/>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Under the title', 'galerio-blocks' ) }
						value={ detail }
						options={ [
							{
								label: __( 'Nothing', 'galerio-blocks' ),
								value: '',
							},
							...details.map( ( item ) => ( {
								label: item.label,
								value: item.key,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { detail: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					urlQueryArgs={
						context.postId ? { post_id: context.postId } : {}
					}
					skipBlockSupportAttributes
					EmptyResponsePlaceholder={ () => (
						<p>
							{ __(
								'Related items show here when this item has some.',
								'galerio-blocks'
							) }
						</p>
					) }
				/>
			</div>
		</>
	);
}

/**
 * The Query Loop that replaces a Related Items block: the same relationship,
 * order and limit, its heading inside the loop (so it hides with it), and the
 * theme's card for the related type, or else image and title.
 *
 * @param {Object} attributes Related Items attributes.
 * @return {Object} Query Loop block.
 */
function toQueryLoop( attributes ) {
	const relation =
		DATA.relations.find( ( item ) => item.key === attributes.field ) ||
		DATA.relations[ 0 ];
	const target = relation ? relation.target : 'galerio_work';
	const cards = ( window.galerioQueryVariations || {} ).cards || {};
	const heading = ( attributes.heading || '' ).replace(
		'{field}',
		relation ? relation.label : ''
	);
	const order = { saved: undefined, random: 'random', title: 'query' }[
		attributes.order
	];

	const card = cards[ target ]
		? [
				createBlock( 'core/template-part', {
					slug: cards[ target ],
					tagName: 'div',
				} ),
			]
		: [
				createBlock( 'core/post-featured-image', {
					isLink: true,
					aspectRatio: attributes.aspectRatio,
				} ),
				createBlock( 'core/post-title', { isLink: true, level: 3 } ),
			];

	const inner = [
		createBlock(
			'core/post-template',
			{
				layout:
					'list' === attributes.layout
						? { type: 'default' }
						: { type: 'grid', columnCount: attributes.columns },
			},
			card
		),
	];
	if ( heading.trim() ) {
		inner.unshift(
			createBlock( 'core/heading', {
				level: attributes.headingLevel,
				content: heading,
			} )
		);
	}

	return createBlock(
		'core/query',
		{
			query: {
				perPage: attributes.limit > 0 ? attributes.limit : 100,
				pages: 0,
				offset: 0,
				postType: target,
				order: 'title' === attributes.order ? 'asc' : 'desc',
				orderBy: 'title' === attributes.order ? 'title' : 'date',
				inherit: false,
				galerioRelated: relation ? relation.key : undefined,
				galerioRelatedOrder: order,
			},
		},
		inner
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	transforms: {
		to: [
			{
				type: 'block',
				blocks: [ 'core/query' ],
				transform: toQueryLoop,
			},
		],
	},
} );
