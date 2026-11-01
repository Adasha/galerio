/**
 * A relationship: the chosen items, and a search to add more. Unpublished items
 * can be chosen, and say so, by people who can edit them.
 */
import { __, sprintf } from '@wordpress/i18n';
import { ComboboxControl } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

import OrderedList from './ordered-list';

/**
 * An item's name, with its status unless it's published.
 *
 * @param {Object} item Post from the REST API.
 * @return {string} Name.
 */
function itemLabel( item ) {
	const title =
		decodeEntities( item.title?.rendered || '' ) ||
		__( '(no title)', 'galerio-core' );
	const statuses = {
		draft: __( 'draft', 'galerio-core' ),
		pending: __( 'pending', 'galerio-core' ),
		future: __( 'scheduled', 'galerio-core' ),
		private: __( 'private', 'galerio-core' ),
	};

	if ( ! statuses[ item.status ] ) {
		return title;
	}

	return sprintf(
		/* translators: 1: item's title, 2: its status, such as "draft". */
		__( '%1$s (%2$s)', 'galerio-core' ),
		title,
		statuses[ item.status ]
	);
}

/**
 * The field.
 *
 * @param {Object} props          Props.
 * @param {Object} props.field    Field definition.
 * @param {*}      props.value    An ID or a list of IDs.
 * @param {Object} props.onChange Called with the new value.
 * @return {Element} The field.
 */
export default function RelationField( { field, value, onChange } ) {
	const ids = ( Array.isArray( value ) ? value : [ value ] ).filter(
		Boolean
	);
	const [ search, setSearch ] = useState( '' );
	const debouncedSearch = useDebounce( setSearch, 300 );

	const { found, chosen } = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			const shared = {
				status: field.statuses,
				_fields: 'id,title,status',
			};

			return {
				found: getEntityRecords( 'postType', field.target, {
					...shared,
					search,
					per_page: 20,
				} ),
				chosen: ids.length
					? getEntityRecords( 'postType', field.target, {
							...shared,
							include: ids,
							per_page: 100,
						} )
					: [],
			};
		},
		[ field.target, field.statuses, search, ids.join() ]
	);

	const labels = {};
	( chosen || [] ).forEach( ( item ) => {
		labels[ item.id ] = itemLabel( item );
	} );

	const options = ( found || [] )
		.filter( ( item ) => ! ids.includes( item.id ) )
		.map( ( item ) => ( {
			value: String( item.id ),
			label: itemLabel( item ),
		} ) );

	const save = ( next ) =>
		onChange( field.multiple ? next : ( next[ 0 ] ?? null ) );

	return (
		<div className="galerio-field-relation">
			<ComboboxControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ field.label }
				help={ field.description }
				value={ null }
				options={ options }
				onFilterValueChange={ debouncedSearch }
				onChange={ ( next ) => {
					if ( next ) {
						const id = Number( next );
						save( field.multiple ? [ ...ids, id ] : [ id ] );
					}
				} }
			/>
			<OrderedList
				className="galerio-field-relation__items"
				ids={ ids }
				onChange={ save }
				renderItem={ ( id ) => (
					<span className="galerio-field-relation__name">
						{ labels[ id ] ?? `#${ id }` }
					</span>
				) }
			/>
		</div>
	);
}
