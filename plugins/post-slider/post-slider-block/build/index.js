/**
 * Post Slider Block – compiled editor script.
 *
 * NOTE: This is a pre-compiled stub that will be replaced by
 * `npm run build` output. For development, run `npm install && npm run build`
 * inside the plugin directory. This stub allows the plugin to activate
 * and register the block's server-side render without the Node build step.
 */
( function() {
	var el            = window.wp.element.createElement;
	var registerBlock = window.wp.blocks.registerBlockType;
	var InspectorControls = window.wp.blockEditor.InspectorControls;
	var useBlockProps     = window.wp.blockEditor.useBlockProps;
	var PanelBody         = window.wp.components.PanelBody;
	var SelectControl     = window.wp.components.SelectControl;
	var RangeControl      = window.wp.components.RangeControl;
	var ToggleControl     = window.wp.components.ToggleControl;
	var TextControl       = window.wp.components.TextControl;
	var ColorPicker       = window.wp.components.ColorPicker;
	var useState          = window.wp.element.useState;
	var useEffect         = window.wp.element.useEffect;
	var apiFetch          = window.wp.apiFetch;
	var __               = window.wp.i18n.__;

	var TRANSITIONS = [
		{ label: 'Fade',    value: 'fade'  },
		{ label: 'Slide',   value: 'slide' },
		{ label: 'Zoom In', value: 'zoom'  },
		{ label: 'Flip',    value: 'flip'  },
		{ label: 'Cube',    value: 'cube'  },
		{ label: 'Blur',    value: 'blur'  },
	];

	var ORDER_BY = [
		{ label: 'Date (newest first)', value: 'date'          },
		{ label: 'Date (oldest first)', value: 'date_asc'      },
		{ label: 'Title A→Z',           value: 'title_asc'     },
		{ label: 'Title Z→A',           value: 'title_desc'    },
		{ label: 'Random',              value: 'rand'          },
		{ label: 'Most commented',      value: 'comment_count' },
	];

	function Edit( props ) {
		var attributes   = props.attributes;
		var setAttributes = props.setAttributes;

		var postType          = attributes.postType          || 'post';
		var numberOfPosts     = attributes.numberOfPosts     || 5;
		var taxonomy          = attributes.taxonomy          || '';
		var selectedTerms     = attributes.selectedTerms     || [];
		var orderBy           = attributes.orderBy           || 'date';
		var order             = attributes.order             || 'DESC';
		var transition        = attributes.transition        || 'fade';
		var transitionDuration= attributes.transitionDuration|| 600;
		var autoplay          = attributes.autoplay          || false;
		var autoplayInterval  = attributes.autoplayInterval  || 5000;
		var pauseOnHover      = attributes.pauseOnHover !== false;
		var showNavigation    = attributes.showNavigation !== false;
		var showDots          = attributes.showDots !== false;
		var showFeaturedImage = attributes.showFeaturedImage !== false;
		var imageSize         = attributes.imageSize         || 'large';
		var overlayOpacity    = typeof attributes.overlayOpacity !== 'undefined' ? attributes.overlayOpacity : 0.4;
		var overlayColor      = attributes.overlayColor      || '#000000';
		var showExcerpt       = attributes.showExcerpt !== false;
		var excerptLength     = attributes.excerptLength     || 20;
		var showMeta          = attributes.showMeta !== false;
		var showAuthor        = attributes.showAuthor        || false;
		var showTerms         = attributes.showTerms !== false;
		var showReadMore      = attributes.showReadMore !== false;
		var readMoreText      = attributes.readMoreText      || 'Read More';
		var linkTitle         = attributes.linkTitle !== false;
		var headingTag        = attributes.headingTag        || 'h3';
		var contentPosition   = attributes.contentPosition   || 'left';
		var accentColor       = attributes.accentColor       || '#3b82f6';
		var textColor         = attributes.textColor         || '#ffffff';
		var backgroundColor   = attributes.backgroundColor   || '#1e293b';
		var slideHeight       = attributes.slideHeight       || 520;
		var borderRadius      = attributes.borderRadius      || 12;

		var postTypesState = useState( [] );
		var postTypes      = postTypesState[0];
		var setPostTypes   = postTypesState[1];

		var taxState  = useState( [] );
		var taxList   = taxState[0];
		var setTaxList = taxState[1];

		var termsState = useState( [] );
		var termsList  = termsState[0];
		var setTermsList = termsState[1];

		var previewState = useState( [] );
		var previewPosts = previewState[0];
		var setPreviewPosts = previewState[1];

		useEffect( function() {
			apiFetch( { path: '/post-slider-block/v1/post-types' } )
				.then( function(d) { setPostTypes( d ); } )
				.catch( function() {} );
		}, [] );

		useEffect( function() {
			if ( ! postType ) return;
			apiFetch( { path: '/post-slider-block/v1/taxonomies?post_type=' + postType } )
				.then( function(d) { setTaxList( d ); } )
				.catch( function() {} );
		}, [ postType ] );

		useEffect( function() {
			if ( ! taxonomy ) return;
			apiFetch( { path: '/post-slider-block/v1/terms?taxonomy=' + taxonomy } )
				.then( function(d) { setTermsList( d ); } )
				.catch( function() {} );
		}, [ taxonomy ] );

		useEffect( function() {
			var slug = postType === 'post' ? 'posts' : postType;
			apiFetch( { path: '/wp/v2/' + slug + '?per_page=3&_fields=id,title' } )
				.then( function(d) { setPreviewPosts( d ); } )
				.catch( function() { setPreviewPosts( [] ); } );
		}, [ postType ] );

		function handleOrderBy( val ) {
			var map = { date_asc: { orderBy: 'date', order: 'ASC' }, title_asc: { orderBy: 'title', order: 'ASC' }, title_desc: { orderBy: 'title', order: 'DESC' } };
			if ( map[ val ] ) { setAttributes( map[ val ] ); return; }
			setAttributes( { orderBy: val, order: 'DESC' } );
		}

		var orderByVal = orderBy === 'date' && order === 'ASC' ? 'date_asc'
		              : orderBy === 'title' && order === 'ASC' ? 'title_asc'
		              : orderBy === 'title' && order === 'DESC' ? 'title_desc'
		              : orderBy;

		var blockProps = useBlockProps( { className: 'psb-editor-preview' } );

		return el( window.wp.element.Fragment, null,
			el( InspectorControls, null,
				/* Query */
				el( PanelBody, { title: 'Query', initialOpen: true },
					el( SelectControl, { label: 'Post type', value: postType, options: postTypes, onChange: function(v) { setAttributes( { postType: v, taxonomy: '', selectedTerms: [] } ); } } ),
					el( RangeControl, { label: 'Number of posts', value: numberOfPosts, onChange: function(v) { setAttributes( { numberOfPosts: v } ); }, min: 1, max: 20 } ),
					taxList.length > 0 && el( SelectControl, { label: 'Filter by taxonomy', value: taxonomy, options: [ { label: '— All —', value: '' } ].concat( taxList ), onChange: function(v) { setAttributes( { taxonomy: v, selectedTerms: [] } ); } } ),
					taxonomy && termsList.length > 0 && el( 'div', { className: 'psb-term-checkboxes' },
						el( 'p', { className: 'psb-label' }, 'Filter by term' ),
						termsList.map( function( term ) {
							return el( 'label', { key: term.value, className: 'psb-term-label' },
								el( 'input', { type: 'checkbox', checked: selectedTerms.includes( term.value ), onChange: function(e) {
									setAttributes( { selectedTerms: e.target.checked ? selectedTerms.concat( [ term.value ] ) : selectedTerms.filter( function(t) { return t !== term.value; } ) } );
								} } ), ' ', term.label
							);
						} )
					),
					el( SelectControl, { label: 'Order by', value: orderByVal, options: ORDER_BY, onChange: handleOrderBy } )
				),
				/* Transition */
				el( PanelBody, { title: 'Transition & Autoplay', initialOpen: false },
					el( 'div', { className: 'psb-transition-grid' },
						TRANSITIONS.map( function( opt ) {
							return el( 'button', {
								key: opt.value,
								className: 'psb-transition-btn' + ( transition === opt.value ? ' is-selected' : '' ),
								onClick: function() { setAttributes( { transition: opt.value } ); }
							},
								el( 'span', { className: 'psb-t-icon psb-t-icon--' + opt.value, 'aria-hidden': 'true' } ),
								opt.label
							);
						} )
					),
					el( RangeControl, { label: 'Duration (ms)', value: transitionDuration, onChange: function(v) { setAttributes( { transitionDuration: v } ); }, min: 200, max: 2000, step: 50 } ),
					el( ToggleControl, { label: 'Autoplay', checked: autoplay, onChange: function(v) { setAttributes( { autoplay: v } ); } } ),
					autoplay && el( RangeControl, { label: 'Interval (ms)', value: autoplayInterval, onChange: function(v) { setAttributes( { autoplayInterval: v } ); }, min: 1000, max: 15000, step: 500 } ),
					autoplay && el( ToggleControl, { label: 'Pause on hover', checked: pauseOnHover, onChange: function(v) { setAttributes( { pauseOnHover: v } ); } } )
				),
				/* Layout */
				el( PanelBody, { title: 'Layout', initialOpen: false },
					el( RangeControl, { label: 'Slide height (px)', value: slideHeight, onChange: function(v) { setAttributes( { slideHeight: v } ); }, min: 200, max: 900 } ),
					el( RangeControl, { label: 'Border radius (px)', value: borderRadius, onChange: function(v) { setAttributes( { borderRadius: v } ); }, min: 0, max: 40 } ),
					el( SelectControl, { label: 'Content position', value: contentPosition, options: [ { label: 'Top left', value: 'left' }, { label: 'Top centre', value: 'center' }, { label: 'Top right', value: 'right' }, { label: 'Bottom left', value: 'bottom-left' }, { label: 'Bottom centre', value: 'bottom-center' }, { label: 'Bottom right', value: 'bottom-right' } ], onChange: function(v) { setAttributes( { contentPosition: v } ); } } ),
					el( ToggleControl, { label: 'Show navigation arrows', checked: showNavigation, onChange: function(v) { setAttributes( { showNavigation: v } ); } } ),
					el( ToggleControl, { label: 'Show dot indicators', checked: showDots, onChange: function(v) { setAttributes( { showDots: v } ); } } )
				),
				/* Image */
				el( PanelBody, { title: 'Featured Image', initialOpen: false },
					el( ToggleControl, { label: 'Show featured image', checked: showFeaturedImage, onChange: function(v) { setAttributes( { showFeaturedImage: v } ); } } ),
					showFeaturedImage && el( SelectControl, { label: 'Image size', value: imageSize, options: [ { label: 'Thumbnail', value: 'thumbnail' }, { label: 'Medium', value: 'medium' }, { label: 'Medium Large', value: 'medium_large' }, { label: 'Large', value: 'large' }, { label: 'Full', value: 'full' } ], onChange: function(v) { setAttributes( { imageSize: v } ); } } ),
					showFeaturedImage && el( RangeControl, { label: 'Overlay opacity', value: overlayOpacity, onChange: function(v) { setAttributes( { overlayOpacity: v } ); }, min: 0, max: 1, step: 0.05 } ),
					showFeaturedImage && el( 'p', { className: 'psb-label' }, 'Overlay colour' ),
					showFeaturedImage && el( ColorPicker, { color: overlayColor, onChange: function(v) { setAttributes( { overlayColor: v } ); }, enableAlpha: false } )
				),
				/* Content */
				el( PanelBody, { title: 'Content', initialOpen: false },
					el( SelectControl, { label: 'Heading tag', value: headingTag, options: [ { label: 'H2', value: 'h2' }, { label: 'H3', value: 'h3' }, { label: 'H4', value: 'h4' }, { label: 'H5', value: 'h5' } ], onChange: function(v) { setAttributes( { headingTag: v } ); } } ),
					el( ToggleControl, { label: 'Link title to post', checked: linkTitle, onChange: function(v) { setAttributes( { linkTitle: v } ); } } ),
					el( ToggleControl, { label: 'Show taxonomy terms', checked: showTerms, onChange: function(v) { setAttributes( { showTerms: v } ); } } ),
					el( ToggleControl, { label: 'Show excerpt', checked: showExcerpt, onChange: function(v) { setAttributes( { showExcerpt: v } ); } } ),
					showExcerpt && el( RangeControl, { label: 'Excerpt word count', value: excerptLength, onChange: function(v) { setAttributes( { excerptLength: v } ); }, min: 5, max: 60 } ),
					el( ToggleControl, { label: 'Show date & meta', checked: showMeta, onChange: function(v) { setAttributes( { showMeta: v } ); } } ),
					showMeta && el( ToggleControl, { label: 'Show author', checked: showAuthor, onChange: function(v) { setAttributes( { showAuthor: v } ); } } ),
					el( ToggleControl, { label: 'Show "Read More" link', checked: showReadMore, onChange: function(v) { setAttributes( { showReadMore: v } ); } } ),
					showReadMore && el( TextControl, { label: '"Read More" text', value: readMoreText, onChange: function(v) { setAttributes( { readMoreText: v } ); } } )
				),
				/* Design */
				el( PanelBody, { title: 'Design & Colours', initialOpen: false },
					el( 'p', { className: 'psb-label' }, 'Accent colour' ),
					el( ColorPicker, { color: accentColor, onChange: function(v) { setAttributes( { accentColor: v } ); }, enableAlpha: false } ),
					el( 'p', { className: 'psb-label' }, 'Text colour' ),
					el( ColorPicker, { color: textColor, onChange: function(v) { setAttributes( { textColor: v } ); }, enableAlpha: false } ),
					el( 'p', { className: 'psb-label' }, 'Background colour' ),
					el( ColorPicker, { color: backgroundColor, onChange: function(v) { setAttributes( { backgroundColor: v } ); }, enableAlpha: false } )
				)
			),
			el( 'div', blockProps,
				el( 'div', { className: 'psb-editor-preview__inner', style: { '--psb-accent': accentColor, '--psb-text': textColor, '--psb-bg': backgroundColor, '--psb-height': slideHeight + 'px', '--psb-radius': borderRadius + 'px' } },
					previewPosts.length > 0
						? el( 'div', { className: 'psb-editor-preview__slides' },
							previewPosts.map( function( post, i ) {
								return el( 'div', { key: post.id, className: 'psb-editor-preview__slide' + ( i === 0 ? ' is-active' : '' ) },
									el( 'span', { className: 'psb-editor-preview__label' }, post.title && post.title.rendered ? post.title.rendered : '(No title)' )
								);
							} ),
							el( 'div', { className: 'psb-editor-preview__info' },
								el( 'strong', null, 'Post Slider' ), ' · ', numberOfPosts, ' posts · ',
								( TRANSITIONS.find( function(t) { return t.value === transition; } ) || {} ).label, ' transition'
							)
						  )
						: el( 'div', { className: 'psb-editor-preview__placeholder' },
							el( 'span', { className: 'dashicons dashicons-slides', 'aria-hidden': 'true' } ),
							el( 'p', null, 'Post Slider — configure in the sidebar' )
						  )
				)
			)
		);
	}

	registerBlock( 'post-slider-block/slider', {
		edit: Edit,
		save: function() { return null; },
	} );
} )();
