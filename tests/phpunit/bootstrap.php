<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite with Pods, Galerio Core and
 * Galerio Blocks (whose blocks need `npm run build` first).
 *
 * Run through `npm run test:php`, which executes inside the wp-env test
 * environment where the WordPress test library is mounted at /wordpress-phpunit.
 *
 * @package Galerio\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test bootstrap.

$repo     = dirname( __DIR__, 2 );
$tests_wp = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/wordpress-phpunit';

if ( ! file_exists( $tests_wp . '/includes/functions.php' ) ) {
	echo "The WordPress test library was not found in {$tests_wp}. Run the tests with `npm run test:php`." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $repo . '/vendor/yoast/phpunit-polyfills' );

require_once $tests_wp . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () use ( $repo ) {
		require WP_CONTENT_DIR . '/plugins/pods/init.php';
		require $repo . '/plugins/galerio-core/galerio-core.php';
		require $repo . '/plugins/galerio-blocks/galerio-blocks.php';
	}
);

require $tests_wp . '/includes/bootstrap.php';

require_once __DIR__ . '/includes/install-pods-package.php';
require_once __DIR__ . '/includes/class-galerio-testcase.php';
require_once __DIR__ . '/includes/class-galerio-memory-adapter.php';
require_once __DIR__ . '/fields/class-adapter-contract-testcase.php';
