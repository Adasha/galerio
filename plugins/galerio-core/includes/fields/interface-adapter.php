<?php
/**
 * Storage adapter contract.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes field values in a storage backend.
 *
 * Every adapter must return values in the canonical form produced by
 * Field::normalise(), so templates and blocks never see backend-specific
 * shapes. tests/phpunit/fields/class-adapter-contract-testcase.php holds the
 * tests every adapter must pass.
 */
interface Adapter {

	/**
	 * Whether the backend is usable on this site.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Read a field's raw value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @return mixed Normalised value, or the field's empty value.
	 */
	public function get( Field $field, $object_id );

	/**
	 * Save a field's value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post or attachment ID.
	 * @param mixed $value     Normalised value.
	 * @return bool Whether the value was saved.
	 */
	public function set( Field $field, $object_id, $value );
}
