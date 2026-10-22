<?php
/**
 * Registers Galerio's Pods field types.
 *
 * @package Galerio\Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add Galerio's field types to the list Pods offers.
 *
 * @param string[] $types Field type identifiers.
 * @return string[]
 */
function galerio_pods_field_types( $types ) {
	$types[] = 'galerio_link';

	return $types;
}
add_filter( 'pods_api_field_types', 'galerio_pods_field_types' );

/**
 * Tell Pods where to find a Galerio field type's class.
 *
 * Pods includes the file only when the type is first used, after Pods itself
 * has loaded, so the class can extend PodsField.
 *
 * @param string $file       File Pods would include.
 * @param string $field_type Field type identifier.
 * @return string
 */
function galerio_pods_field_include( $file, $field_type ) {
	return 'galerio_link' === $field_type ? __DIR__ . '/class-podsfield-galerio-link.php' : $file;
}
add_filter( 'pods_form_field_include', 'galerio_pods_field_include', 10, 2 );
