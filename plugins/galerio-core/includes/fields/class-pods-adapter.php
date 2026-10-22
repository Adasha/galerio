<?php
/**
 * Pods storage adapter.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes fields defined by the Galerio Pods package.
 */
final class Pods_Adapter implements Adapter {

	/**
	 * Whether Pods is active.
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'pods' );
	}

	/**
	 * Read a field's raw value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @return mixed
	 */
	public function get( Field $field, $object_id ) {
		$pod = $this->pod( $field, $object_id );

		if ( ! $pod ) {
			return $field->empty_value();
		}

		// Without 'output' => 'ids', Pods returns whole post rows for file and relationship fields.
		$value = $field->holds_ids()
			? $pod->field(
				array(
					'name'   => $field->source,
					'output' => 'ids',
				)
			)
			: $pod->field( $field->source );

		return $field->normalise( $value );
	}

	/**
	 * Save a field's value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @param mixed $value     Normalised value.
	 * @return bool
	 */
	public function set( Field $field, $object_id, $value ) {
		$pod = $this->pod( $field, $object_id );

		if ( ! $pod ) {
			return false;
		}

		if ( 'year' === $field->type ) {
			$value = $value ? sprintf( '%04d-01-01', $value ) : '';
		} elseif ( 'boolean' === $field->type ) {
			$value = $value ? 1 : 0;
		} elseif ( ( $field->holds_ids() || 'link' === $field->type ) && ! $field->multiple ) {
			$value = $value ? $value : '';
		}

		return (bool) $pod->save( $field->source, $value );
	}

	/**
	 * Load the pod item a field lives on.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @return \Pods|null
	 */
	private function pod( Field $field, $object_id ) {
		$object_id = absint( $object_id );

		if ( ! $object_id || ! $this->is_available() ) {
			return null;
		}

		$pod = pods( 'attachment' === $field->object ? 'media' : Registry::OBJECTS[ $field->object ], $object_id );

		return ( $pod && $pod->valid() && $pod->exists() ) ? $pod : null;
	}
}
