<?php
/**
 * Base test case.
 *
 * @package Galerio\Tests
 */

/**
 * Makes sure the Galerio content model exists before each test class runs.
 */
abstract class Galerio_TestCase extends WP_UnitTestCase {

	/**
	 * Install the Pods package.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		galerio_tests_install_pods_package( dirname( __DIR__, 3 ) . '/data/pods/galerio.json' );
	}
}
