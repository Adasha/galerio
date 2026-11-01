/**
 * One link or a list of them: an address and optional display text.
 */
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

const EMPTY = { url: '', label: '', offline: false };

/**
 * The field.
 *
 * @param {Object} props          Props.
 * @param {Object} props.field    Field definition.
 * @param {*}      props.value    A link or a list of links.
 * @param {Object} props.onChange Called with the new value.
 * @return {Element} The field.
 */
export default function LinkField( { field, value, onChange } ) {
	const links = ( Array.isArray( value ) ? value : [ value ] ).filter(
		Boolean
	);
	const rows = links.length ? links : [ EMPTY ];

	const save = ( next ) =>
		onChange( field.multiple ? next : ( next[ 0 ] ?? null ) );
	const update = ( index, change ) =>
		save(
			rows.map( ( link, at ) =>
				at === index ? { ...link, ...change } : link
			)
		);

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ `galerio-field-${ field.name }` }
			label={ field.label }
			help={ field.description }
		>
			<div className="galerio-field-links">
				{ rows.map( ( link, index ) => (
					<div className="galerio-field-links__row" key={ index }>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'URL', 'galerio-core' ) }
							type="url"
							value={ link.url }
							onChange={ ( url ) => update( index, { url } ) }
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Display text', 'galerio-core' ) }
							value={ link.label }
							onChange={ ( label ) => update( index, { label } ) }
						/>
						{ field.asksOffline && (
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Offline', 'galerio-core' ) }
								checked={ !! link.offline }
								onChange={ ( offline ) =>
									update( index, { offline } )
								}
							/>
						) }
						{ field.multiple && (
							<Button
								size="small"
								variant="tertiary"
								isDestructive
								disabled={ rows.length === 1 && ! link.url }
								onClick={ () =>
									save(
										rows.filter( ( _, at ) => at !== index )
									)
								}
							>
								{ __( 'Remove', 'galerio-core' ) }
							</Button>
						) }
					</div>
				) ) }
				{ field.multiple && (
					<Button
						size="small"
						variant="secondary"
						onClick={ () => save( [ ...rows, EMPTY ] ) }
					>
						{ __( 'Add link', 'galerio-core' ) }
					</Button>
				) }
			</div>
		</BaseControl>
	);
}
