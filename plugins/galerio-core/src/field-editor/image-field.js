/**
 * One image or a list of them, chosen from the Media Library.
 */
import { __ } from '@wordpress/i18n';
import { BaseControl, Button } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { MediaUpload } from '@wordpress/media-utils';

import OrderedList from './ordered-list';

/**
 * A small preview of an image.
 *
 * @param {Object} props    Props.
 * @param {number} props.id Attachment ID.
 * @return {Element} The preview.
 */
function Thumbnail( { id } ) {
	const media = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecord( 'postType', 'attachment', id ),
		[ id ]
	);
	const src =
		media?.media_details?.sizes?.thumbnail?.source_url ?? media?.source_url;

	return src ? (
		<img
			className="galerio-field-image__preview"
			src={ src }
			alt={ media.alt_text || '' }
		/>
	) : (
		<span className="galerio-field-image__preview">{ `#${ id }` }</span>
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
export default function ImageField( { field, value, onChange } ) {
	const ids = ( Array.isArray( value ) ? value : [ value ] ).filter(
		Boolean
	);
	const save = ( next ) =>
		onChange( field.multiple ? next : ( next[ 0 ] ?? null ) );

	const onSelect = ( media ) => {
		if ( ! field.multiple ) {
			save( [ media.id ] );
			return;
		}

		// Images kept stay where they were, and new ones go at the end.
		const selected = media.map( ( item ) => item.id );
		save( [
			...ids.filter( ( id ) => selected.includes( id ) ),
			...selected.filter( ( id ) => ! ids.includes( id ) ),
		] );
	};

	let label = __( 'Choose images', 'galerio-core' );

	if ( ! field.multiple ) {
		label = ids.length
			? __( 'Replace image', 'galerio-core' )
			: __( 'Choose image', 'galerio-core' );
	}

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `galerio-field-${ field.name }` }
			label={ field.label }
			help={ field.description }
		>
			<div className="galerio-field-image">
				<OrderedList
					className="galerio-field-image__items"
					ids={ ids }
					onChange={ save }
					renderItem={ ( id ) => <Thumbnail id={ id } /> }
				/>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					multiple={ field.multiple ? 'add' : false }
					value={ field.multiple ? ids : ids[ 0 ] }
					title={ field.label }
					onSelect={ onSelect }
					render={ ( { open } ) => (
						<Button
							variant="secondary"
							size="compact"
							onClick={ open }
						>
							{ label }
						</Button>
					) }
				/>
			</div>
		</BaseControl>
	);
}
