/**
 * Post Slider Block – editor entry point.
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	TextControl,
	ColorPicker,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import metadata from './block.json';
import './editor.scss';

const TRANSITION_OPTIONS = [
	{ label: __( 'Fade',    'post-slider-block' ), value: 'fade'  },
	{ label: __( 'Slide',   'post-slider-block' ), value: 'slide' },
	{ label: __( 'Zoom In', 'post-slider-block' ), value: 'zoom'  },
	{ label: __( 'Flip',    'post-slider-block' ), value: 'flip'  },
	{ label: __( 'Cube',    'post-slider-block' ), value: 'cube'  },
	{ label: __( 'Blur',    'post-slider-block' ), value: 'blur'  },
];

const ORDER_BY_OPTIONS = [
	{ label: __( 'Date (newest first)', 'post-slider-block' ), value: 'date'          },
	{ label: __( 'Date (oldest first)', 'post-slider-block' ), value: 'date_asc'      },
	{ label: __( 'Title A→Z',           'post-slider-block' ), value: 'title_asc'     },
	{ label: __( 'Title Z→A',           'post-slider-block' ), value: 'title_desc'    },
	{ label: __( 'Random',              'post-slider-block' ), value: 'rand'          },
	{ label: __( 'Most commented',      'post-slider-block' ), value: 'comment_count' },
];

const HEADING_OPTIONS = [
	{ label: 'H2', value: 'h2' },
	{ label: 'H3', value: 'h3' },
	{ label: 'H4', value: 'h4' },
	{ label: 'H5', value: 'h5' },
];

const CONTENT_POSITION_OPTIONS = [
	{ label: __( 'Top left',      'post-slider-block' ), value: 'left'          },
	{ label: __( 'Top centre',    'post-slider-block' ), value: 'center'        },
	{ label: __( 'Top right',     'post-slider-block' ), value: 'right'         },
	{ label: __( 'Bottom left',   'post-slider-block' ), value: 'bottom-left'   },
	{ label: __( 'Bottom centre', 'post-slider-block' ), value: 'bottom-center' },
	{ label: __( 'Bottom right',  'post-slider-block' ), value: 'bottom-right'  },
];

function Edit( { attributes, setAttributes } ) {
	const {
		postType, numberOfPosts, taxonomy, selectedTerms,
		orderBy, order,
		transition, transitionDuration,
		autoplay, autoplayInterval, pauseOnHover,
		showNavigation, showDots,
		showFeaturedImage, imageSize, overlayOpacity, overlayColor,
		showExcerpt, excerptLength, showMeta, showAuthor, showTerms,
		showReadMore, readMoreText, linkTitle, headingTag,
		contentPosition,
		accentColor, textColor, backgroundColor, slideHeight, borderRadius,
	} = attributes;

	const [ postTypes,    setPostTypes    ] = useState( [] );
	const [ taxonomies,   setTaxonomies   ] = useState( [] );
	const [ terms,        setTerms        ] = useState( [] );
	const [ previewPosts, setPreviewPosts ] = useState( [] );

	useEffect( () => {
		apiFetch( { path: '/post-slider-block/v1/post-types' } ).then( setPostTypes ).catch( () => {} );
	}, [] );

	useEffect( () => {
		if ( ! postType ) return;
		apiFetch( { path: `/post-slider-block/v1/taxonomies?post_type=${ postType }` } )
			.then( data => {
				setTaxonomies( data );
				if ( data.length && ! data.find( t => t.value === taxonomy ) ) {
					setAttributes( { taxonomy: data[0].value, selectedTerms: [] } );
				}
			} ).catch( () => {} );
	}, [ postType ] );

	useEffect( () => {
		if ( ! taxonomy ) return;
		apiFetch( { path: `/post-slider-block/v1/terms?taxonomy=${ taxonomy }` } )
			.then( setTerms ).catch( () => {} );
	}, [ taxonomy ] );

	useEffect( () => {
		const typeSlug = postType === 'post' ? 'posts' : postType;
		apiFetch( { path: `/wp/v2/${ typeSlug }?per_page=3&_fields=id,title` } )
			.then( setPreviewPosts ).catch( () => setPreviewPosts( [] ) );
	}, [ postType ] );

	const handleOrderBy = ( val ) => {
		const map = {
			date_asc:   { orderBy: 'date',  order: 'ASC'  },
			title_asc:  { orderBy: 'title', order: 'ASC'  },
			title_desc: { orderBy: 'title', order: 'DESC' },
		};
		if ( map[ val ] ) { setAttributes( map[ val ] ); return; }
		setAttributes( { orderBy: val, order: 'DESC' } );
	};

	const orderByValue = orderBy === 'date' && order === 'ASC'   ? 'date_asc'
		               : orderBy === 'title' && order === 'ASC'  ? 'title_asc'
		               : orderBy === 'title' && order === 'DESC' ? 'title_desc'
		               : orderBy;

	const blockProps = useBlockProps( { className: 'psb-editor-preview' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Query', 'post-slider-block' ) } initialOpen={ true }>
					<SelectControl label={ __( 'Post type', 'post-slider-block' ) } value={ postType } options={ postTypes } onChange={ val => setAttributes( { postType: val, taxonomy: '', selectedTerms: [] } ) } />
					<RangeControl label={ __( 'Number of posts', 'post-slider-block' ) } value={ numberOfPosts } onChange={ val => setAttributes( { numberOfPosts: val } ) } min={ 1 } max={ 20 } />
					{ taxonomies.length > 0 && (
						<SelectControl label={ __( 'Filter by taxonomy', 'post-slider-block' ) } value={ taxonomy } options={ [ { label: __( '— All —', 'post-slider-block' ), value: '' }, ...taxonomies ] } onChange={ val => setAttributes( { taxonomy: val, selectedTerms: [] } ) } />
					) }
					{ taxonomy && terms.length > 0 && (
						<div className="psb-term-checkboxes">
							<p className="psb-label">{ __( 'Filter by term', 'post-slider-block' ) }</p>
							{ terms.map( term => (
								<label key={ term.value } className="psb-term-label">
									<input type="checkbox" checked={ selectedTerms.includes( term.value ) } onChange={ e => {
										setAttributes( { selectedTerms: e.target.checked
											? [ ...selectedTerms, term.value ]
											: selectedTerms.filter( t => t !== term.value ) } );
									} } />
									{ ' ' }{ term.label }
								</label>
							) ) }
						</div>
					) }
					<SelectControl label={ __( 'Order by', 'post-slider-block' ) } value={ orderByValue } options={ ORDER_BY_OPTIONS } onChange={ handleOrderBy } />
				</PanelBody>

				<PanelBody title={ __( 'Transition & Autoplay', 'post-slider-block' ) } initialOpen={ false }>
					<div className="psb-transition-grid">
						{ TRANSITION_OPTIONS.map( opt => (
							<button key={ opt.value } className={ `psb-transition-btn${ transition === opt.value ? ' is-selected' : '' }` } onClick={ () => setAttributes( { transition: opt.value } ) }>
								<span className={ `psb-t-icon psb-t-icon--${ opt.value }` } aria-hidden="true" />
								{ opt.label }
							</button>
						) ) }
					</div>
					<RangeControl label={ __( 'Duration (ms)', 'post-slider-block' ) } value={ transitionDuration } onChange={ val => setAttributes( { transitionDuration: val } ) } min={ 200 } max={ 2000 } step={ 50 } />
					<ToggleControl label={ __( 'Autoplay', 'post-slider-block' ) } checked={ autoplay } onChange={ val => setAttributes( { autoplay: val } ) } />
					{ autoplay && <>
						<RangeControl label={ __( 'Interval (ms)', 'post-slider-block' ) } value={ autoplayInterval } onChange={ val => setAttributes( { autoplayInterval: val } ) } min={ 1000 } max={ 15000 } step={ 500 } />
						<ToggleControl label={ __( 'Pause on hover', 'post-slider-block' ) } checked={ pauseOnHover } onChange={ val => setAttributes( { pauseOnHover: val } ) } />
					</> }
				</PanelBody>

				<PanelBody title={ __( 'Layout', 'post-slider-block' ) } initialOpen={ false }>
					<RangeControl label={ __( 'Slide height (px)', 'post-slider-block' ) } value={ slideHeight } onChange={ val => setAttributes( { slideHeight: val } ) } min={ 200 } max={ 900 } />
					<RangeControl label={ __( 'Border radius (px)', 'post-slider-block' ) } value={ borderRadius } onChange={ val => setAttributes( { borderRadius: val } ) } min={ 0 } max={ 40 } />
					<SelectControl label={ __( 'Content position', 'post-slider-block' ) } value={ contentPosition } options={ CONTENT_POSITION_OPTIONS } onChange={ val => setAttributes( { contentPosition: val } ) } />
					<ToggleControl label={ __( 'Show navigation arrows', 'post-slider-block' ) } checked={ showNavigation } onChange={ val => setAttributes( { showNavigation: val } ) } />
					<ToggleControl label={ __( 'Show dot indicators', 'post-slider-block' ) } checked={ showDots } onChange={ val => setAttributes( { showDots: val } ) } />
				</PanelBody>

				<PanelBody title={ __( 'Featured Image', 'post-slider-block' ) } initialOpen={ false }>
					<ToggleControl label={ __( 'Show featured image', 'post-slider-block' ) } checked={ showFeaturedImage } onChange={ val => setAttributes( { showFeaturedImage: val } ) } />
					{ showFeaturedImage && <>
						<SelectControl label={ __( 'Image size', 'post-slider-block' ) } value={ imageSize } options={ [ { label: 'Thumbnail', value: 'thumbnail' }, { label: 'Medium', value: 'medium' }, { label: 'Medium Large', value: 'medium_large' }, { label: 'Large', value: 'large' }, { label: 'Full', value: 'full' } ] } onChange={ val => setAttributes( { imageSize: val } ) } />
						<RangeControl label={ __( 'Overlay opacity', 'post-slider-block' ) } value={ overlayOpacity } onChange={ val => setAttributes( { overlayOpacity: val } ) } min={ 0 } max={ 1 } step={ 0.05 } />
						<p className="psb-label">{ __( 'Overlay colour', 'post-slider-block' ) }</p>
						<ColorPicker color={ overlayColor } onChange={ val => setAttributes( { overlayColor: val } ) } enableAlpha={ false } />
					</> }
				</PanelBody>

				<PanelBody title={ __( 'Content', 'post-slider-block' ) } initialOpen={ false }>
					<SelectControl label={ __( 'Heading tag', 'post-slider-block' ) } value={ headingTag } options={ HEADING_OPTIONS } onChange={ val => setAttributes( { headingTag: val } ) } />
					<ToggleControl label={ __( 'Link title to post', 'post-slider-block' ) } checked={ linkTitle } onChange={ val => setAttributes( { linkTitle: val } ) } />
					<ToggleControl label={ __( 'Show taxonomy terms', 'post-slider-block' ) } checked={ showTerms } onChange={ val => setAttributes( { showTerms: val } ) } />
					<ToggleControl label={ __( 'Show excerpt', 'post-slider-block' ) } checked={ showExcerpt } onChange={ val => setAttributes( { showExcerpt: val } ) } />
					{ showExcerpt && <RangeControl label={ __( 'Excerpt word count', 'post-slider-block' ) } value={ excerptLength } onChange={ val => setAttributes( { excerptLength: val } ) } min={ 5 } max={ 60 } /> }
					<ToggleControl label={ __( 'Show date & meta', 'post-slider-block' ) } checked={ showMeta } onChange={ val => setAttributes( { showMeta: val } ) } />
					{ showMeta && <ToggleControl label={ __( 'Show author', 'post-slider-block' ) } checked={ showAuthor } onChange={ val => setAttributes( { showAuthor: val } ) } /> }
					<ToggleControl label={ __( 'Show "Read More" link', 'post-slider-block' ) } checked={ showReadMore } onChange={ val => setAttributes( { showReadMore: val } ) } />
					{ showReadMore && <TextControl label={ __( '"Read More" text', 'post-slider-block' ) } value={ readMoreText } onChange={ val => setAttributes( { readMoreText: val } ) } /> }
				</PanelBody>

				<PanelBody title={ __( 'Design & Colours', 'post-slider-block' ) } initialOpen={ false }>
					<p className="psb-label">{ __( 'Accent colour', 'post-slider-block' ) }</p>
					<ColorPicker color={ accentColor } onChange={ val => setAttributes( { accentColor: val } ) } enableAlpha={ false } />
					<p className="psb-label">{ __( 'Text colour', 'post-slider-block' ) }</p>
					<ColorPicker color={ textColor } onChange={ val => setAttributes( { textColor: val } ) } enableAlpha={ false } />
					<p className="psb-label">{ __( 'Background colour', 'post-slider-block' ) }</p>
					<ColorPicker color={ backgroundColor } onChange={ val => setAttributes( { backgroundColor: val } ) } enableAlpha={ false } />
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="psb-editor-preview__inner" style={ { '--psb-accent': accentColor, '--psb-text': textColor, '--psb-bg': backgroundColor, '--psb-height': `${ slideHeight }px`, '--psb-radius': `${ borderRadius }px` } }>
					{ previewPosts.length > 0 ? (
						<div className="psb-editor-preview__slides">
							{ previewPosts.map( ( post, i ) => (
								<div key={ post.id } className={ `psb-editor-preview__slide ${ i === 0 ? 'is-active' : '' }` }>
									<span className="psb-editor-preview__label">{ post.title?.rendered || __( '(No title)', 'post-slider-block' ) }</span>
								</div>
							) ) }
							<div className="psb-editor-preview__info">
								<strong>Post Slider</strong>{ ' · ' }{ numberOfPosts } posts{ ' · ' }{ TRANSITION_OPTIONS.find( t => t.value === transition )?.label } transition
							</div>
						</div>
					) : (
						<div className="psb-editor-preview__placeholder">
							<span className="dashicons dashicons-slides" aria-hidden="true" />
							<p>{ __( 'Post Slider — configure in the sidebar', 'post-slider-block' ) }</p>
						</div>
					) }
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
