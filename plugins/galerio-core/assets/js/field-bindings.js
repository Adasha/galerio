/**
 * Editor side of the `galerio/field` block bindings source: lists Galerio
 * fields in the block bindings panel and previews their values.
 *
 * Field definitions come from PHP in `window.galerioFieldBindings`. Values are
 * read from the `galerio_fields` property of the post's REST record.
 */
( function ( wp, data ) {
	if ( ! wp || ! data ) {
		return;
	}

	const { registerBlockBindingsSource } = wp.blocks;
	const { store: coreStore } = wp.coreData;
	const { dateI18n, getSettings: getDateSettings } = wp.date;
	const { decodeEntities } = wp.htmlEntities;
	const { __ } = wp.i18n;

	/**
	 * Find the Galerio object being edited: from the block's post context, or
	 * from the template when editing a Galerio template in the site editor.
	 *
	 * @param {Function} select  Data store selector.
	 * @param {Object}   context Block context.
	 * @return {string} Object name, or an empty string.
	 */
	function objectFor( select, context ) {
		let postType = context?.postType;

		if ( postType === 'wp_template' || ! postType ) {
			const editor = select( 'core/editor' );
			const slug = editor?.getCurrentPost?.()?.slug || '';
			const match = slug.match( /^(?:single|archive)-([a-z0-9_]+)$/ );
			postType = match ? match[ 1 ] : postType;
		}

		return (
			Object.keys( data.objects ).find(
				( object ) => data.objects[ object ] === postType
			) || ''
		);
	}

	/**
	 * Find a field definition by key.
	 *
	 * @param {string} key Field key, e.g. "work.year".
	 * @return {Object|undefined} Field definition.
	 */
	function fieldFor( key ) {
		const object = String( key ).split( '.' )[ 0 ];
		return ( data.fields[ object ] || [] ).find(
			( field ) => field.key === key
		);
	}

	/**
	 * Preview one value of a field for an attribute.
	 *
	 * @param {Function} select    Data store selector.
	 * @param {Object}   field     Field definition.
	 * @param {*}        value     Single raw value.
	 * @param {string}   attribute Attribute being filled.
	 * @return {*} Preview value, or undefined if it is not available yet.
	 */
	function previewOne( select, field, value, attribute ) {
		const { getEntityRecord } = select( coreStore );

		switch ( field.type ) {
			case 'image': {
				const media = getEntityRecord( 'postType', 'attachment', value );
				if ( attribute === 'id' ) {
					return value;
				}
				if ( ! media ) {
					return undefined;
				}
				if ( attribute === 'url' ) {
					return media.source_url;
				}
				return attribute === 'caption'
					? media.caption?.raw
					: media.alt_text || decodeEntities( media.title?.rendered );
			}

			case 'relation': {
				const post = getEntityRecord( 'postType', field.target, value );
				if ( ! post ) {
					return undefined;
				}
				return attribute === 'url'
					? post.link
					: decodeEntities( post.title?.rendered || '' );
			}

			case 'date':
				return attribute === 'datetime'
					? value
					: dateI18n( getDateSettings().formats.date, value );

			case 'boolean':
				return value ? __( 'Yes', 'galerio-core' ) : __( 'No', 'galerio-core' );

			default:
				return String( value );
		}
	}

	/**
	 * Preview a bound field.
	 *
	 * @param {Function} select    Data store selector.
	 * @param {Object}   context   Block context.
	 * @param {Object}   args      Binding arguments.
	 * @param {string}   attribute Attribute being filled.
	 * @return {*} Preview value.
	 */
	function preview( select, context, args, attribute ) {
		const field = fieldFor( args?.key );

		if ( ! field ) {
			return args?.key;
		}

		// Without a post (e.g. in a template), show the field's name.
		if ( ! context?.postId || ! context?.postType ) {
			return attribute === 'url' || attribute === 'id'
				? undefined
				: field.label;
		}

		const record = select( coreStore ).getEditedEntityRecord(
			'postType',
			context.postType,
			context.postId
		);
		const raw = record?.galerio_fields?.[ field.name ];
		const values = ( field.multiple ? raw || [] : [ raw ] ).filter(
			( value ) => value !== null && value !== undefined && value !== ''
		);

		if ( ! values.length || ( field.type === 'boolean' && ! raw ) ) {
			return attribute === 'url' || attribute === 'id'
				? undefined
				: field.label;
		}

		if ( [ 'url', 'id', 'datetime', 'alt', 'title', 'caption' ].includes( attribute ) ) {
			return previewOne( select, field, values[ 0 ], attribute );
		}

		const items = values
			.map( ( value ) => previewOne( select, field, value, attribute ) )
			.filter( ( value ) => value !== undefined && value !== '' );

		return items.length ? items.join( ', ' ) : field.label;
	}

	registerBlockBindingsSource( {
		name: 'galerio/field',
		usesContext: [ 'postId', 'postType' ],

		getValues( { select, context, bindings } ) {
			const values = {};
			for ( const [ attribute, binding ] of Object.entries( bindings ) ) {
				values[ attribute ] = preview( select, context, binding.args, attribute );
			}
			return values;
		},

		getFieldsList( { select, context } ) {
			const object = objectFor( select, context );

			return ( data.fields[ object ] || [] ).map( ( field ) => ( {
				label: field.label,
				type: 'string',
				args: { key: field.key },
			} ) );
		},
	} );
} )( window.wp, window.galerioFieldBindings );
