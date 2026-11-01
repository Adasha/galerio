<?php
/**
 * Plugin Name: Galerio development: native data while Pods is off
 * Description: Development and test sites only. Switching Pods off moves the site to Galerio's native data, so both can be tried without changing settings.
 *
 * @package Galerio
 */

add_filter(
	'galerio_native_data',
	function () {
		// PHPUnit loads Pods itself, whatever the site's active plugins say.
		return ! defined( 'GALERIO_PHPUNIT' ) && ! in_array( 'pods/init.php', (array) get_option( 'active_plugins' ), true );
	}
);
