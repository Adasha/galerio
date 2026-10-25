/**
 * Query Loop variations for Galerio's content types: works, creators,
 * collections, and exhibitions filtered by date.
 *
 * Each is a core Query Loop with a starting layout of cards, so it can be
 * changed like any other. The site's names for its content types come from
 * `window.galerioQueryVariations`, added by the Query_Variations class.
 *
 * @param {Object} wp   WordPress's scripts.
 * @param {Object} data Settings from the server.
 */
( function ( wp, data ) {
	if ( ! wp || ! data ) {
		return;
	}

	const { registerBlockVariation } = wp.blocks;
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const EXHIBITIONS = 'galerio/exhibitions';

	/**
	 * A Query Loop's query for a content type.
	 *
	 * @param {string} postType Post type.
	 * @param {Object} extra    Settings that differ from the defaults.
	 * @return {Object} Query.
	 */
	function query( postType, extra ) {
		return Object.assign(
			{
				perPage: 9,
				pages: 0,
				offset: 0,
				postType,
				order: 'desc',
				orderBy: 'date',
				author: '',
				search: '',
				exclude: [],
				sticky: '',
				inherit: false,
			},
			extra
		);
	}

	/**
	 * A grid of cards: the theme's card for the type when it has one (such
	 * as the Galerio theme's card-work), or else image and title, then the
	 * blocks given.
	 *
	 * @param {string}  postType Post type.
	 * @param {Array[]} extra    Inner blocks under the title.
	 * @return {Array[]} Inner blocks of the Query Loop.
	 */
	function cards( postType, extra ) {
		const card = ( data.cards || {} )[ postType ];

		return [
			[
				'core/post-template',
				{ layout: { type: 'grid', columnCount: 3 } },
				card
					? [
							[
								'core/template-part',
								{ slug: card, tagName: 'div' },
							],
						]
					: [
							[
								'core/post-featured-image',
								{ isLink: true, aspectRatio: '4/3' },
							],
							[
								'core/post-title',
								{ isLink: true, level: 3, fontSize: 'medium' },
							],
						].concat( extra ),
			],
			[
				'core/query-no-results',
				{},
				[
					[
						'core/paragraph',
						{
							content: __(
								'Nothing to show yet.',
								'galerio-blocks'
							),
						},
					],
				],
			],
		];
	}

	const variations = [
		{
			name: 'galerio/works',
			title: data.names.works,
			description: __(
				'A grid of works, newest first, with their creators.',
				'galerio-blocks'
			),
			icon: 'art',
			query: query( 'galerio_work' ),
			extra: [
				[
					'core/paragraph',
					{
						metadata: {
							bindings: {
								content: {
									source: 'galerio/field',
									args: { key: 'work.creator' },
								},
							},
						},
					},
				],
			],
		},
		{
			name: 'galerio/profiles',
			title: data.names.profiles,
			description: __( 'A grid of creators, A to Z.', 'galerio-blocks' ),
			icon: 'groups',
			query: query( 'galerio_profile', {
				order: 'asc',
				orderBy: 'title',
			} ),
			extra: [],
		},
		{
			name: 'galerio/collections',
			title: data.names.collections,
			description: __(
				'A grid of collections, newest first, with their dates.',
				'galerio-blocks'
			),
			icon: 'images-alt2',
			query: query( 'galerio_collection' ),
			extra: [ [ 'galerio/exhibition-dates', { format: 'short' } ] ],
		},
		{
			name: EXHIBITIONS,
			title: data.names.exhibitions,
			description: __(
				'Exhibitions that are on now or coming up, soonest first. Can also show only current, upcoming or past ones.',
				'galerio-blocks'
			),
			icon: 'calendar-alt',
			query: query( 'galerio_collection', {
				galerioStatus: 'current-upcoming',
			} ),
			extra: [
				[
					'galerio/exhibition-dates',
					{ format: 'short', showStatus: true },
				],
			],
			// The date filter sets the order.
			controls: [ 'taxQuery', 'search' ],
		},
	];

	variations.forEach( ( variation ) => {
		registerBlockVariation( 'core/query', {
			name: variation.name,
			title: variation.title,
			description: variation.description,
			icon: variation.icon,
			category: 'theme',
			keywords: [ 'galerio' ],
			scope: [ 'inserter', 'block' ],
			attributes: {
				namespace: variation.name,
				query: variation.query,
			},
			innerBlocks: cards( variation.query.postType, variation.extra ),
			isActive: [ 'namespace' ],
			allowedControls: variation.controls || [
				'order',
				'taxQuery',
				'search',
			],
		} );
	} );

	const STATUSES = [
		{
			label: __( 'On now and upcoming', 'galerio-blocks' ),
			value: 'current-upcoming',
		},
		{ label: __( 'On now', 'galerio-blocks' ), value: 'current' },
		{ label: __( 'Upcoming', 'galerio-blocks' ), value: 'upcoming' },
		{
			label: __( 'Past, most recent first', 'galerio-blocks' ),
			value: 'past',
		},
	];

	// The exhibitions variation's date filter, in the sidebar.
	addFilter(
		'editor.BlockEdit',
		'galerio/exhibitions-status',
		createHigherOrderComponent(
			( BlockEdit ) => ( props ) => {
				if (
					props.name !== 'core/query' ||
					props.attributes.namespace !== EXHIBITIONS
				) {
					return el( BlockEdit, props );
				}

				const current = props.attributes.query || {};

				return el(
					Fragment,
					null,
					el( BlockEdit, props ),
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __( 'Dates', 'galerio-blocks' ) },
							el( SelectControl, {
								__next40pxDefaultSize: true,
								__nextHasNoMarginBottom: true,
								label: __( 'Show', 'galerio-blocks' ),
								value:
									current.galerioStatus || 'current-upcoming',
								options: STATUSES,
								help: __(
									'The editor shows them all; the site shows only those that match, by today’s date.',
									'galerio-blocks'
								),
								onChange: ( value ) =>
									props.setAttributes( {
										query: Object.assign( {}, current, {
											galerioStatus: value,
										} ),
									} ),
							} )
						)
					)
				);
			},
			'withGalerioExhibitionsStatus'
		)
	);
} )( window.wp, window.galerioQueryVariations );
