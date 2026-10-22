<?php
/**
 * Plugin Name: Galerio development: quiet Pods' early translation notice
 * Description: Development and test sites only. Stops WordPress reporting that Pods loads its translations too early under WP-CLI, which it does in its own command setup. Delete once Pods fixes it.
 *
 * Mounted by wp-env (.wp-env.json, .wp-env.test.json); never shipped.
 *
 * @package Galerio\Tools
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

add_filter(
	'doing_it_wrong_trigger_error',
	function ( $trigger, $function_name, $message ) {
		if ( '_load_textdomain_just_in_time' === $function_name && false !== strpos( $message, '<code>pods</code>' ) ) {
			return false;
		}

		return $trigger;
	},
	10,
	3
);
