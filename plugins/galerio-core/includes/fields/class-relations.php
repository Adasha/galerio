<?php
/**
 * Keeps both sides of two-way relationships in step.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Each item keeps its own list of what it's linked to, in its own order, so
 * the data stays plain post meta that exports, imports and queries can see.
 * Saving one side updates the other here. Plugins that write post meta
 * directly bypass this, which `wp galerio fields check` reports.
 */
final class Relations {

	/**
	 * Update the other side of a relation after one item's list changed.
	 *
	 * @param Field $field   Relation field.
	 * @param int   $id      Item whose list changed.
	 * @param int[] $before  IDs it listed.
	 * @param int[] $after   IDs it lists now.
	 */
	public static function sync( Field $field, $id, array $before, array $after ) {
		$inverse = self::inverse( $field );

		// A revision or the like isn't really part of the relationship.
		if ( ! $inverse || wp_is_post_revision( $id ) || get_post_type( $id ) !== Registry::OBJECTS[ $field->object ] ) {
			return;
		}

		foreach ( array_diff( $before, $after ) as $target ) {
			self::remove( $inverse, $target, $id );
		}

		foreach ( array_diff( $after, $before ) as $target ) {
			if ( (int) $target !== (int) $id && get_post_type( $target ) === Registry::OBJECTS[ $field->target ] ) {
				self::add( $inverse, $field, $target, $id );
			}
		}
	}

	/**
	 * Take a deleted item out of the lists of everything it's linked to.
	 *
	 * @param int $id Item.
	 */
	public static function forget( $id ) {
		$adapter = new Meta_Adapter();

		foreach ( galerio_fields()->all() as $field ) {
			if ( 'relation' === $field->type && get_post_type( $id ) === Registry::OBJECTS[ $field->object ] ) {
				$inverse = self::inverse( $field );

				foreach ( $inverse ? (array) $adapter->get( $field, $id ) : array() as $target ) {
					self::remove( $inverse, $target, $id );
				}
			}
		}
	}

	/**
	 * The field on the other side of a relation.
	 *
	 * @param Field $field Relation field.
	 * @return Field|null
	 */
	private static function inverse( Field $field ) {
		return $field->inverse ? galerio_fields()->get( $field->inverse ) : null;
	}

	/**
	 * The IDs in a relation field, as a list.
	 *
	 * @param Field $field Relation field.
	 * @param int   $id    Item.
	 * @return int[]
	 */
	private static function list_of( Field $field, $id ) {
		return array_values( array_filter( (array) ( new Meta_Adapter() )->get( $field, $id ) ) );
	}

	/**
	 * Add an item to a list, at the end. A field holding one item moves it,
	 * so the item that held it before lets go.
	 *
	 * @param Field $inverse Field to add to.
	 * @param Field $field   The field on the other side.
	 * @param int   $target  Item holding the list.
	 * @param int   $id      Item to add.
	 */
	private static function add( Field $inverse, Field $field, $target, $id ) {
		$list = self::list_of( $inverse, $target );

		if ( in_array( (int) $id, $list, true ) ) {
			return;
		}

		if ( ! $inverse->multiple ) {
			foreach ( $list as $previous ) {
				self::remove( $field, $previous, $target );
			}

			Meta_Adapter::write( $inverse, $target, (int) $id );
			return;
		}

		$list[] = (int) $id;
		Meta_Adapter::write( $inverse, $target, $list );
	}

	/**
	 * Remove an item from a list.
	 *
	 * @param Field $field  Field to remove from.
	 * @param int   $target Item holding the list.
	 * @param int   $id     Item to remove.
	 */
	private static function remove( Field $field, $target, $id ) {
		$list = array_values( array_diff( self::list_of( $field, $target ), array( (int) $id ) ) );

		Meta_Adapter::write( $field, $target, $field->multiple ? $list : ( $list ? $list[0] : null ) );
	}
}
