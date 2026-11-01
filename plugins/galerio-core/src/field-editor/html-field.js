/**
 * Formatted text, in WordPress's classic editor, stored as that editor stores
 * it: paragraphs as blank lines, which the site turns back into paragraphs.
 */
import { BaseControl } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Run a function once the pane holds the field. WordPress moves meta boxes into
 * the pane after loading, and an editor started before that would break, as
 * moving its frame empties it.
 *
 * @param {Element} element Element in the meta box.
 * @param {Object}  run     Function to run.
 * @return {Object} Cancels the wait.
 */
function whenInPane( element, run ) {
	let frame;

	const check = () => {
		if ( element.closest( '.edit-post-meta-boxes-area' ) ) {
			run();
		} else {
			frame = window.requestAnimationFrame( check );
		}
	};

	check();

	return () => window.cancelAnimationFrame( frame );
}

/**
 * The field.
 *
 * @param {Object} props          Props.
 * @param {Object} props.field    Field definition.
 * @param {string} props.value    Value.
 * @param {Object} props.onChange Called with the new value.
 * @return {Element} The field.
 */
export default function HtmlField( { field, value, onChange } ) {
	const id = `galerio-field-${ field.name }`;
	const textarea = useRef();
	const latest = useRef( { value, onChange } );

	latest.current.onChange = onChange;

	useEffect( () => {
		const { oldEditor } = window.wp;
		let started = false;

		const cancel = whenInPane( textarea.current, () => {
			started = true;
			oldEditor.initialize( id, {
				quicktags: true,
				mediaButtons: true,
				tinymce: {
					wpautop: true,
					toolbar1:
						'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
					setup( editor ) {
						editor.on( 'input change undo redo', () => {
							const next = oldEditor.removep(
								editor.getContent()
							);

							if ( next !== latest.current.value ) {
								latest.current.value = next;
								latest.current.onChange( next );
							}
						} );
					},
				},
			} );
			textarea.current.addEventListener( 'input', ( event ) => {
				latest.current.value = event.target.value;
				latest.current.onChange( event.target.value );
			} );
		} );

		return () => {
			cancel();

			if ( started ) {
				oldEditor.remove( id );
			}
		};
	}, [ id ] );

	// A change from elsewhere, such as the editor's Undo.
	useEffect( () => {
		const next = value ?? '';

		if ( next !== ( latest.current.value ?? '' ) ) {
			latest.current.value = next;

			const editor = window.tinymce?.get( id );

			if ( editor ) {
				editor.setContent( window.wp.oldEditor.autop( next ) );
			} else if ( textarea.current ) {
				textarea.current.value = next;
			}
		}
	}, [ id, value ] );

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ id }
			label={ field.label }
			help={ field.description }
		>
			<textarea
				id={ id }
				ref={ textarea }
				rows={ 8 }
				className="galerio-field-html"
				defaultValue={ value ?? '' }
			/>
		</BaseControl>
	);
}
