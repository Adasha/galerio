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
	 * Issue: one side lists the other, which doesn't list it back.
	 */
	const ONE_WAY = 'one-way';

	/**
	 * Issue: a list names an item that doesn't exist or is of another type.
	 */
	const MISSING = 'missing';

	/**
	 * Issue: a list claims an item whose single-item field names something else,
	 * such as a profile listing a work credited to another creator.
	 */
	const CONFLICT = 'conflict';

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
	 * Find links the two sides disagree about, from the stored rows.
	 *
	 * Reads the database directly, because Pods answers get_post_meta() itself
	 * for relationship fields.
	 *
	 * @return array[] Each with issue, field, id, target and inverse.
	 */
	public static function audit() {
		global $wpdb;

		$fields = array_filter(
			galerio_fields()->all(),
			function ( Field $field ) {
				return 'relation' === $field->type && ! $field->value_callback;
			}
		);
		$lists  = array();
		$known  = array();

		foreach ( $fields as $field ) {
			if ( ! isset( $known[ $field->object ] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft'", Registry::OBJECTS[ $field->object ] ) );

				$known[ $field->object ] = array_fill_keys( array_map( 'intval', $ids ), true );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id", $field->source ) );

			$lists[ $field->key ] = array();

			foreach ( $rows as $row ) {
				$target = absint( $row->meta_value );

				if ( $target && isset( $known[ $field->object ][ (int) $row->post_id ] ) ) {
					$lists[ $field->key ][ (int) $row->post_id ][] = $target;
				}
			}
		}

		$issues = array();

		foreach ( $fields as $field ) {
			$inverse = $field->inverse && isset( $fields[ $field->inverse ] ) ? $fields[ $field->inverse ] : null;

			foreach ( $lists[ $field->key ] as $id => $targets ) {
				foreach ( $targets as $target ) {
					$held  = $inverse && isset( $lists[ $inverse->key ][ $target ] ) ? $lists[ $inverse->key ][ $target ] : array();
					$issue = '';

					if ( empty( $known[ $field->target ][ $target ] ) ) {
						$issue = self::MISSING;
					} elseif ( $inverse && ! in_array( $id, $held, true ) && $target !== $id ) {
						$issue = ! $inverse->multiple && $held ? self::CONFLICT : self::ONE_WAY;
					}

					if ( $issue ) {
						$issues[] = array(
							'issue'   => $issue,
							'field'   => $field->key,
							'id'      => $id,
							'target'  => $target,
							'inverse' => $inverse ? $inverse->key : '',
						);
					}
				}
			}
		}

		return $issues;
	}

	/**
	 * Fix what the audit finds: a link only one side has is added to the other,
	 * a link to something that no longer exists is removed, and a single-item
	 * field wins over a list that disagrees with it.
	 *
	 * @param array[] $issues Issues from audit().
	 */
	public static function repair( array $issues ) {
		foreach ( $issues as $issue ) {
			$field = galerio_fields()->get( $issue['field'] );

			if ( self::ONE_WAY === $issue['issue'] ) {
				$inverse = galerio_fields()->get( $issue['inverse'] );
				$list    = self::list_of( $inverse, $issue['target'] );

				if ( ! $inverse->multiple && $list ) {
					// Another issue already filled this single-item field.
					self::remove( $field, $issue['id'], $issue['target'] );
				} else {
					self::add( $inverse, $field, $issue['target'], $issue['id'] );
				}
			} else {
				self::remove( $field, $issue['id'], $issue['target'] );
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
