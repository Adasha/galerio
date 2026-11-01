<?php
/**
 * Post meta storage adapter.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes fields as plain post meta, without Pods.
 *
 * Values are stored as Pods stores them, so a site can move between the two
 * with nothing to convert: one row per value in list order, dates as "Y-m-d",
 * years as "Y-01-01", booleans as 1 or 0 and links as JSON.
 */
final class Meta_Adapter implements Adapter {

	/**
	 * Whether the backend is usable. Post meta always is.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Read a field's raw value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @return mixed
	 */
	public function get( Field $field, $object_id ) {
		if ( $field->multiple && 'link' !== $field->type ) {
			return $field->normalise( get_post_meta( $object_id, $field->source, false ) );
		}

		$value = get_post_meta( $object_id, $field->source, true );

		return $field->normalise( '' === $value ? null : $value );
	}

	/**
	 * Save a field's value, and the other side of a two-way relationship.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @param mixed $value     Normalised value.
	 * @return bool
	 */
	public function set( Field $field, $object_id, $value ) {
		$object_id = absint( $object_id );

		if ( ! $object_id || ! get_post( $object_id ) ) {
			return false;
		}

		$value = $field->normalise( $value );

		if ( 'relation' === $field->type ) {
			$old = (array) $this->get( $field, $object_id );
			self::write( $field, $object_id, $value );
			Relations::sync( $field, $object_id, array_filter( $old ), array_filter( (array) $value ) );

			return true;
		}

		self::write( $field, $object_id, $value );

		return true;
	}

	/**
	 * Replace a field's stored rows, without touching anything else.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @param mixed $value     Normalised value.
	 */
	public static function write( Field $field, $object_id, $value ) {
		delete_post_meta( $object_id, $field->source );

		foreach ( self::rows( $field, $value ) as $row ) {
			add_post_meta( $object_id, $field->source, wp_slash( $row ) );
		}
	}

	/**
	 * The rows that store a value.
	 *
	 * @param Field $field Field.
	 * @param mixed $value Normalised value.
	 * @return string[]
	 */
	private static function rows( Field $field, $value ) {
		if ( 'link' === $field->type ) {
			return $value ? array( wp_json_encode( $value ) ) : array();
		}

		if ( 'boolean' === $field->type ) {
			return array( $value ? '1' : '0' );
		}

		if ( $field->multiple ) {
			return array_map( 'strval', $value );
		}

		if ( null === $value || '' === $value || 0 === $value ) {
			return array();
		}

		return array( 'year' === $field->type ? sprintf( '%04d-01-01', $value ) : (string) $value );
	}
}
