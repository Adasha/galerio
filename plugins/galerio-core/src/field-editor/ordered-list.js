/**
 * A list of items that keep an order, with buttons to move and remove them.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Move an item in a list.
 *
 * @param {Array}  list List.
 * @param {number} from Index of the item.
 * @param {number} to   Index to move it to.
 * @return {Array} A new list.
 */
function moved( list, from, to ) {
	const next = [ ...list ];
	next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
	return next;
}

/**
 * The list.
 *
 * @param {Object}   props            Props.
 * @param {number[]} props.ids        Item IDs, in order.
 * @param {Object}   props.renderItem Draws an item from its ID.
 * @param {Object}   props.onChange   Called with the new list.
 * @param {string}   props.className  Class of the list.
 * @return {Element} The list.
 */
export default function OrderedList( {
	ids,
	renderItem,
	onChange,
	className,
} ) {
	return (
		<ul className={ className }>
			{ ids.map( ( id, index ) => (
				<li key={ id }>
					{ renderItem( id ) }
					<span className="galerio-field-buttons">
						{ ids.length > 1 && (
							<>
								<Button
									size="small"
									variant="tertiary"
									disabled={ 0 === index }
									onClick={ () =>
										onChange(
											moved( ids, index, index - 1 )
										)
									}
								>
									{ __( 'Up', 'galerio-core' ) }
								</Button>
								<Button
									size="small"
									variant="tertiary"
									disabled={ ids.length - 1 === index }
									onClick={ () =>
										onChange(
											moved( ids, index, index + 1 )
										)
									}
								>
									{ __( 'Down', 'galerio-core' ) }
								</Button>
							</>
						) }
						<Button
							size="small"
							variant="tertiary"
							isDestructive
							onClick={ () =>
								onChange(
									ids.filter( ( other ) => other !== id )
								)
							}
						>
							{ __( 'Remove', 'galerio-core' ) }
						</Button>
					</span>
				</li>
			) ) }
		</ul>
	);
}
