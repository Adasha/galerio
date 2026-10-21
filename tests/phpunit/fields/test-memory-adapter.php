<?php
/**
 * Reference adapter tests.
 *
 * @package Galerio\Tests
 */

/**
 * Runs the adapter contract against the in-memory adapter.
 *
 * @coversNothing
 */
class Test_Memory_Adapter extends Galerio_Adapter_Contract_TestCase {

	/**
	 * Create the adapter under test.
	 *
	 * @return Galerio_Memory_Adapter
	 */
	protected function make_adapter() {
		return new Galerio_Memory_Adapter();
	}
}
