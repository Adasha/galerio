/**
 * Editor side of the Gallery block: settings in the sidebar and a preview
 * rendered by the server, as on the front end.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	MediaPlaceholder,
	MediaUpload,
	MediaUploadCheck,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	ToolbarButton,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const FIELDS = window.galerioGallery?.fields || [];

const RATIOS = [
	{ label: __( 'Square', 'galerio-blocks' ), value: '1' },
	{ label: __( 'Landscape, 4:3', 'galerio-blocks' ), value: '4/3' },
	{ label: __( 'Portrait, 3:4', 'galerio-blocks' ), value: '3/4' },
	{ label: __( 'Landscape, 3:2', 'galerio-blocks' ), value: '3/2' },
	{ label: __( 'Portrait, 2:3', 'galerio-blocks' ), value: '2/3' },
	{ label: __( 'Wide, 16:9', 'galerio-blocks' ), value: '16/9' },
	{ label: __( 'Each image’s own shape', 'galerio-blocks' ), value: 'auto' },
];

export default function Edit( { attributes, setAttributes, context } ) {
	const {
		source,
		field,
		ids,
		layout,
		columns,
		aspectRatio,
		order,
		caption,
		lightbox,
		imageSize,
	} = attributes;
	const blockProps = useBlockProps();
	const chosen = source === 'media';
	const ownFields = FIELDS.filter(
		( item ) => item.postType === context.postType
	);
	const onSelect = ( media ) =>
		setAttributes( { ids: media.map( ( item ) => item.id ) } );

	const chooser = ( open ) => (
		<ToolbarButton onClick={ open }>
			{ ids.length
				? __( 'Edit images', 'galerio-blocks' )
				: __( 'Choose images', 'galerio-blocks' ) }
		</ToolbarButton>
	);

	return (
		<>
			{ chosen && (
				<BlockControls group="other">
					<MediaUploadCheck>
						<MediaUpload
							multiple
							gallery
							allowedTypes={ [ 'image' ] }
							value={ ids }
							onSelect={ onSelect }
							render={ ( { open } ) => chooser( open ) }
						/>
					</MediaUploadCheck>
				</BlockControls>
			) }
			<InspectorControls>
				<PanelBody title={ __( 'Images', 'galerio-blocks' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Images come from', 'galerio-blocks' ) }
						value={ chosen ? 'media' : field }
						options={ [
							{
								label: __(
									'This item’s images',
									'galerio-blocks'
								),
								value: '',
							},
							...( ownFields.length ? ownFields : FIELDS ).map(
								( item ) => ( {
									label: item.label,
									value: item.key,
								} )
							),
							{
								label: __(
									'Images chosen here',
									'galerio-blocks'
								),
								value: 'media',
							},
						] }
						help={
							chosen
								? sprintf(
										/* translators: %d: number of images. */
										_n(
											'%d image chosen. Use the toolbar to change them.',
											'%d images chosen. Use the toolbar to change them.',
											ids.length,
											'galerio-blocks'
										),
										ids.length
									)
								: __(
										'The work’s, collection’s or other item’s own images, so the same block suits a template.',
										'galerio-blocks'
									)
						}
						onChange={ ( value ) =>
							setAttributes(
								value === 'media'
									? { source: 'media' }
									: { source: 'field', field: value }
							)
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Order', 'galerio-blocks' ) }
						value={ order }
						options={ [
							{
								label: __(
									'Automatic (follows the collection’s Shuffle images)',
									'galerio-blocks'
								),
								value: 'auto',
							},
							{
								label: __( 'As saved', 'galerio-blocks' ),
								value: 'saved',
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
				</PanelBody>
				<PanelBody title={ __( 'Layout', 'galerio-blocks' ) }>
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
									'Masonry (each image’s own shape)',
									'galerio-blocks'
								),
								value: 'masonry',
							},
							{
								label: __(
									'One after another, full width',
									'galerio-blocks'
								),
								value: 'linear',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					{ layout !== 'linear' && (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Columns', 'galerio-blocks' ) }
							help={ __(
								'Phones show two at most.',
								'galerio-blocks'
							) }
							value={ columns }
							onChange={ ( value ) =>
								setAttributes( { columns: value } )
							}
							min={ 1 }
							max={ 8 }
						/>
					) }
					{ layout === 'grid' && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Shape', 'galerio-blocks' ) }
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
						label={ __( 'Captions', 'galerio-blocks' ) }
						value={ caption }
						options={ [
							{
								label: __( 'None', 'galerio-blocks' ),
								value: 'none',
							},
							{
								label: __(
									'The image’s caption',
									'galerio-blocks'
								),
								value: 'caption',
							},
							{
								label: __(
									'Title and caption',
									'galerio-blocks'
								),
								value: 'title',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { caption: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Image size', 'galerio-blocks' ) }
						value={ imageSize }
						options={ [
							{
								label: __( 'Medium', 'galerio-blocks' ),
								value: 'medium',
							},
							{
								label: __( 'Large', 'galerio-blocks' ),
								value: 'large',
							},
							{
								label: __( 'Full size', 'galerio-blocks' ),
								value: 'full',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { imageSize: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Open images in a lightbox',
							'galerio-blocks'
						) }
						checked={ lightbox }
						onChange={ ( value ) =>
							setAttributes( { lightbox: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ chosen && ! ids.length ? (
					<MediaPlaceholder
						icon="format-gallery"
						labels={ {
							title: __( 'Galerio Gallery', 'galerio-blocks' ),
							instructions: __(
								'Choose images from the Media Library, or upload new ones.',
								'galerio-blocks'
							),
						} }
						accept="image/*"
						allowedTypes={ [ 'image' ] }
						multiple
						gallery
						onSelect={ onSelect }
					/>
				) : (
					<ServerSideRender
						block="galerio/gallery"
						attributes={ attributes }
						urlQueryArgs={
							context.postId ? { post_id: context.postId } : {}
						}
						skipBlockSupportAttributes
					/>
				) }
			</div>
		</>
	);
}
