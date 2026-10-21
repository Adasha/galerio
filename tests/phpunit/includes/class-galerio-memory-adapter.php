<?php
/**
 * In-memory storage adapter for tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Adapter;
use Galerio\Core\Fields\Field;

/**
 * Keeps values in memory. The simplest correct adapter: it checks that the
 * contract tests themselves are sound, and backs the registry tests.
 */
class Galerio_Memory_Adapter implements Adapter {

	/**
	 * Values by post ID and field key.
	 *
	 * @var array
	 */
	public $values = array();

	/**
	 * Always available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Read a value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post ID.
	 * @return mixed
	 */
	public function get( Field $field, $object_id ) {
		return isset( $this->values[ $object_id ][ $field->key ] ) ? $this->values[ $object_id ][ $field->key ] : $field->empty_value();
	}

	/**
	 * Save a value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post ID.
	 * @param mixed $value     Value.
	 * @return bool
	 */
	public function set( Field $field, $object_id, $value ) {
		if ( ! get_post( $object_id ) ) {
			return false;
		}

		$this->values[ $object_id ][ $field->key ] = $field->normalise( $value );

		return true;
	}
}
