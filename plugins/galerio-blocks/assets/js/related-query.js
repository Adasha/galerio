/**
 * The Query Loop's "Related items" panel: show the items a relationship field
 * of the current item points to, such as a creator's works, in the order
 * arranged on the item, at random, or in the Query Loop's own order.
 *
 * The panel appears where there is a current item: in single templates, and
 * in the content of a type with relationships. The relationships come from
 * `window.galerioRelatedQuery`, added by the Related_Query class.
 *
 * @param {Object} wp   WordPress's scripts.
 * @param {Object} data Settings from the server.
 */
( function ( wp, data ) {
	if ( ! wp || ! data || ! data.relations.length ) {
		return;
	}

	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const ORDERS = [
		{
			label: __( 'As arranged on the item', 'galerio-blocks' ),
			value: 'saved',
		},
		{ label: __( 'Random', 'galerio-blocks' ), value: 'random' },
		{
			label: __( 'Use the Query Loop’s order', 'galerio-blocks' ),
			value: 'query',
		},
	];

	/**
	 * The relationships to offer, given what is being edited: the item's own
	 * type's, the single template's type's, or, in a custom template that
	 * could be for any type, all of them. None elsewhere.
	 *
	 * @return {Object[]} Relationships.
	 */
	function useRelations() {
		const { postType, slug } = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			const post = editor && editor.getCurrentPost();
			return {
				postType: editor && editor.getCurrentPostType(),
				slug: ( post && post.slug ) || '',
			};
		}, [] );

		let type = postType;
		if ( 'wp_template' === postType ) {
			const single = slug.match( /^single-(.+)$/ );
			if ( single ) {
				type = single[ 1 ];
			} else if ( slug.startsWith( 'wp-custom-template-' ) ) {
				return data.relations;
			}
		}

		return data.relations.filter(
			( relation ) => relation.postType === type
		);
	}

	addFilter(
		'editor.BlockEdit',
		'galerio/related-query',
		createHigherOrderComponent(
			( BlockEdit ) => ( props ) =>
				props.name === 'core/query'
					? el( WithPanel, { BlockEdit, props } )
					: el( BlockEdit, props ),
			'withGalerioRelatedQuery'
		)
	);

	/**
	 * A Query Loop with the panel, where it has relationships to offer.
	 *
	 * @param {Object}                        root           Component props.
	 * @param {import("react").ComponentType} root.BlockEdit The Query Loop's own editor.
	 * @param {Object}                        root.props     Its props.
	 * @return {Element} Element.
	 */
	function WithPanel( { BlockEdit, props } ) {
		const relations = useRelations();
		const current = props.attributes.query || {};
		const chosen = current.galerioRelated || '';

		if ( ! relations.length && ! chosen ) {
			return el( BlockEdit, props );
		}

		const options = relations.slice();
		if (
			chosen &&
			! options.some( ( relation ) => relation.key === chosen )
		) {
			const known = data.relations.find(
				( relation ) => relation.key === chosen
			);
			options.push( known || { key: chosen, label: chosen } );
		}

		// In a template for any type, say which type each is for.
		const types = new Set(
			options.map( ( relation ) => relation.postType )
		);
		const label = ( relation ) =>
			types.size > 1 && relation.typeLabel
				? relation.typeLabel + ': ' + relation.label
				: relation.label;

		const update = ( changes ) => {
			const query = Object.assign( {}, current, changes );
			Object.keys( changes ).forEach( ( key ) => {
				if ( undefined === changes[ key ] ) {
					delete query[ key ];
				}
			} );
			props.setAttributes( { query } );
		};

		return el(
			Fragment,
			null,
			el( BlockEdit, props ),
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{
						title: __( 'Related items', 'galerio-blocks' ),
						initialOpen: false,
					},
					el( SelectControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Show', 'galerio-blocks' ),
						value: chosen,
						options: [
							{
								label: __(
									'Not related (normal query)',
									'galerio-blocks'
								),
								value: '',
							},
						].concat(
							options.map( ( relation ) => ( {
								label: label( relation ),
								value: relation.key,
							} ) )
						),
						help: chosen
							? __(
									'The editor previews recent items; the site shows the ones related to the item. With none, the whole Query Loop is hidden, unless its No results block has something to say.',
									'galerio-blocks'
								)
							: __(
									'Show the items this item is linked to, such as a creator’s works.',
									'galerio-blocks'
								),
						onChange: ( value ) => {
							const relation = data.relations.find(
								( item ) => item.key === value
							);
							update(
								relation
									? {
											galerioRelated: value,
											postType: relation.target,
											inherit: false,
										}
									: {
											galerioRelated: undefined,
											galerioRelatedOrder: undefined,
										}
							);
						},
					} ),
					chosen &&
						el( SelectControl, {
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
							label: __( 'Order', 'galerio-blocks' ),
							value: current.galerioRelatedOrder || 'saved',
							options: ORDERS,
							onChange: ( value ) =>
								update( {
									galerioRelatedOrder:
										'saved' === value ? undefined : value,
								} ),
						} )
				)
			)
		);
	}
} )( window.wp, window.galerioRelatedQuery );
