/**
 * An item's fields, in the pane under the editor.
 *
 * Groups and fields come from `window.galerioFieldEditor`, added by the
 * Field_Editor class. Changes are edits to the item's `galerio_fields` property
 * holding only the fields that changed, so they're saved with the item, count
 * towards its unsaved changes, and never touch fields this person can't edit.
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Panel,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { store as editorStore } from '@wordpress/editor';
import { createRoot } from '@wordpress/element';

import './style.scss';

/**
 * The current item's field values and a way to change them.
 *
 * @return {Object} Values by field name, and setValue( name, value ).
 */
function useFields() {
	const { type, id, saved, edits } = useSelect( ( select ) => {
		const { getCurrentPostId, getCurrentPostType } = select( editorStore );
		const { getEntityRecordEdits, getRawEntityRecord } = select( coreStore );
		const postType = getCurrentPostType();
		const postId = getCurrentPostId();

		return {
			type: postType,
			id: postId,
			saved: getRawEntityRecord( 'postType', postType, postId )
				?.galerio_fields,
			edits: getEntityRecordEdits( 'postType', postType, postId )
				?.galerio_fields,
		};
	}, [] );
	const { editEntityRecord } = useDispatch( coreStore );

	return {
		values: { ...saved, ...edits },
		setValue( name, value ) {
			editEntityRecord( 'postType', type, id, {
				galerio_fields: { ...edits, [ name ]: value },
			} );
		},
	};
}

/**
 * A list of values, one control each, with a way to add and remove them.
 *
 * @param {Object}   props           Props.
 * @param {string}   props.label     Field label.
 * @param {string[]} props.value     Values.
 * @param {Function} props.onChange  Called with the new values.
 * @param {Function} props.Control   Control for one value.
 * @param {string}   props.inputType Input type for the control.
 * @return {Element} The list.
 */
function ListField( { label, value, onChange, Control, inputType } ) {
	const rows = value.length ? value : [ '' ];

	return (
		<fieldset className="galerio-field-list">
			<legend className="galerio-field-list__label">{ label }</legend>
			{ rows.map( ( item, index ) => (
				<div className="galerio-field-list__row" key={ index }>
					<Control
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ label }
						type={ inputType }
						rows={ 1 }
						value={ item }
						onChange={ ( next ) =>
							onChange(
								rows.map( ( old, at ) =>
									at === index ? next : old
								)
							)
						}
					/>
					<Button
						size="small"
						variant="tertiary"
						isDestructive
						disabled={ rows.length === 1 && ! item }
						onClick={ () =>
							onChange( rows.filter( ( _, at ) => at !== index ) )
						}
					>
						{ __( 'Remove', 'galerio-core' ) }
					</Button>
				</div>
			) ) }
			<Button
				size="small"
				variant="secondary"
				onClick={ () => onChange( [ ...rows, '' ] ) }
			>
				{ __( 'Add another', 'galerio-core' ) }
			</Button>
		</fieldset>
	);
}

/**
 * One field's control.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.field    Field definition.
 * @param {*}        props.value    Value.
 * @param {Function} props.onChange Called with the new value.
 * @return {Element} The control.
 */
function Field( { field, value, onChange } ) {
	const common = {
		__next40pxDefaultSize: true,
		__nextHasNoMarginBottom: true,
		label: field.label,
	};

	switch ( field.type ) {
		case 'boolean':
			return (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ field.label }
					checked={ !! value }
					onChange={ onChange }
				/>
			);

		case 'number':
		case 'year':
			return (
				<TextControl
					{ ...common }
					type="number"
					value={ value ?? '' }
					onChange={ ( next ) =>
						onChange( '' === next ? null : Number( next ) )
					}
				/>
			);

		case 'date':
			return (
				<TextControl
					{ ...common }
					type="date"
					value={ value ?? '' }
					onChange={ onChange }
				/>
			);

		case 'choice':
			return (
				<SelectControl
					{ ...common }
					value={ value ?? '' }
					options={ [
						{ value: '', label: __( 'None', 'galerio-core' ) },
						...Object.entries( field.options ).map(
							( [ option, label ] ) => ( {
								value: option,
								label,
							} )
						),
					] }
					onChange={ ( next ) => onChange( next || null ) }
				/>
			);

		case 'textarea':
			return field.multiple ? (
				<ListField
					label={ field.label }
					value={ value ?? [] }
					onChange={ onChange }
					Control={ TextareaControl }
				/>
			) : (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ field.label }
					value={ value ?? '' }
					onChange={ onChange }
				/>
			);

		case 'text':
		case 'url':
		case 'embed':
			return field.multiple ? (
				<ListField
					label={ field.label }
					value={ value ?? [] }
					onChange={ onChange }
					Control={ TextControl }
					inputType={ 'text' === field.type ? 'text' : 'url' }
				/>
			) : (
				<TextControl
					{ ...common }
					type={ 'text' === field.type ? 'text' : 'url' }
					value={ value ?? '' }
					onChange={ onChange }
				/>
			);

		default:
			return (
				<p className="galerio-field-pending">
					<strong>{ field.label }</strong>
					<br />
					{ field.type }
				</p>
			);
	}
}

/**
 * Whether a field takes the whole row rather than a column.
 *
 * @param {Object} field Field definition.
 * @return {boolean} Whether it's wide.
 */
function isWide( field ) {
	return (
		field.multiple || [ 'html', 'textarea', 'link' ].includes( field.type )
	);
}

/**
 * The groups of fields.
 *
 * @return {Element} The panels.
 */
function FieldEditor() {
	const { values, setValue } = useFields();

	return (
		<Panel className="galerio-field-editor">
			{ window.galerioFieldEditor.groups.map( ( group, index ) => (
				<PanelBody
					key={ group.name }
					title={ group.label }
					initialOpen={ 0 === index }
				>
					<div className="galerio-field-editor__grid">
						{ group.fields.map( ( field ) => (
							<div
								key={ field.name }
								className={
									isWide( field )
										? 'galerio-field-editor__field is-wide'
										: 'galerio-field-editor__field'
								}
							>
								<Field
									field={ field }
									value={ values[ field.name ] }
									onChange={ ( next ) =>
										setValue( field.name, next )
									}
								/>
							</div>
						) ) }
					</div>
				</PanelBody>
			) ) }
		</Panel>
	);
}

domReady( () => {
	const element = document.getElementById( 'galerio-field-editor' );

	if ( element && window.galerioFieldEditor ) {
		createRoot( element ).render( <FieldEditor /> );
	}
} );
