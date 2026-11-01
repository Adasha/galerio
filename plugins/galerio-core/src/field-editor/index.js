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
import { dispatch, useDispatch, useSelect } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { store as editorStore } from '@wordpress/editor';
import { createRoot } from '@wordpress/element';
import { store as preferencesStore } from '@wordpress/preferences';

import HtmlField from './html-field';
import ImageField from './image-field';
import LinkField from './link-field';
import RelationField from './relation-field';
import './style.scss';

const PREFERENCES = 'galerio/field-editor';

// The pane starts open, leaving part of the editor showing, until someone changes it.
const { setDefaults } = dispatch( preferencesStore );
setDefaults( 'core/edit-post', {
	metaBoxesMainIsOpen: true,
	metaBoxesMainOpenHeight: Math.round( window.innerHeight * 0.55 ),
} );
setDefaults( PREFERENCES, { openGroups: [ 'details' ] } );

/**
 * The current item's field values and a way to change them.
 *
 * @return {Object} Values by field name, and setValue( name, value ).
 */
function useFields() {
	const { type, id, saved, edits } = useSelect( ( select ) => {
		const { getCurrentPostId, getCurrentPostType } = select( editorStore );
		const { getEntityRecordEdits, getRawEntityRecord } =
			select( coreStore );
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
 * @param {Object}   props              Props.
 * @param {string}   props.label        Field label.
 * @param {string}   props.help         Help text.
 * @param {string[]} props.value        Values.
 * @param {Function} props.onChange     Called with the new values.
 * @param {Function} props.Control      Control for one value.
 * @param {Object}   props.controlProps More props for the control.
 * @return {Element} The list.
 */
function ListField( { label, help, value, onChange, Control, controlProps } ) {
	const rows = value.length ? value : [ '' ];

	return (
		<fieldset className="galerio-field-list">
			<legend className="galerio-field-list__label">{ label }</legend>
			{ help && <p className="galerio-field-list__help">{ help }</p> }
			{ rows.map( ( item, index ) => (
				<div className="galerio-field-list__row" key={ index }>
					<Control
						__nextHasNoMarginBottom
						hideLabelFromVision
						label={ label }
						{ ...controlProps }
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
		help: field.description,
	};

	switch ( field.type ) {
		case 'html':
			return (
				<HtmlField
					field={ field }
					value={ value }
					onChange={ onChange }
				/>
			);

		case 'image':
			return (
				<ImageField
					field={ field }
					value={ value }
					onChange={ onChange }
				/>
			);

		case 'relation':
			return (
				<RelationField
					field={ field }
					value={ value }
					onChange={ onChange }
				/>
			);

		case 'link':
			return (
				<LinkField
					field={ field }
					value={ value }
					onChange={ onChange }
				/>
			);

		case 'boolean':
			return (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ field.label }
					help={ field.description }
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
					help={ field.description }
					value={ value ?? [] }
					onChange={ onChange }
					Control={ TextareaControl }
					controlProps={ { rows: 1 } }
				/>
			) : (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ field.label }
					help={ field.description }
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
					help={ field.description }
					value={ value ?? [] }
					onChange={ onChange }
					Control={ TextControl }
					controlProps={ {
						__next40pxDefaultSize: true,
						type: 'text' === field.type ? 'text' : 'url',
					} }
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
			return null;
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
		field.multiple ||
		[ 'html', 'textarea', 'link', 'relation' ].includes( field.type )
	);
}

/**
 * The groups of fields.
 *
 * @return {Element} The panels.
 */
function FieldEditor() {
	const { values, setValue } = useFields();
	const openGroups = useSelect(
		( select ) =>
			select( preferencesStore ).get( PREFERENCES, 'openGroups' ),
		[]
	);
	const { set } = useDispatch( preferencesStore );

	return (
		<Panel className="galerio-field-editor">
			{ window.galerioFieldEditor.groups.map( ( group ) => (
				<PanelBody
					key={ group.name }
					title={ group.label }
					opened={ openGroups.includes( group.name ) }
					onToggle={ () =>
						set(
							PREFERENCES,
							'openGroups',
							openGroups.includes( group.name )
								? openGroups.filter(
										( name ) => name !== group.name
									)
								: [ ...openGroups, group.name ]
						)
					}
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
