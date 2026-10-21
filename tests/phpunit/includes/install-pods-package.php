<?php
/**
 * Installs the Galerio Pods package into the test site.
 *
 * @package Galerio\Tests
 */

/**
 * Import the package, unless its fields are already stored.
 *
 * Pods keeps its definitions as posts, and the test suite deletes every post
 * after each test class, so Galerio_TestCase calls this before each class.
 *
 * @param string $file Path to the Pods package.
 */
function galerio_tests_install_pods_package( $file ) {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test setup.
	if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", '_pods_field' ) ) ) {
		return;
	}

	pods_api()->cache_flush_pods();

	// Import as an administrator, as WP-CLI and the Pods admin do; Pods filters template code otherwise.
	$user = get_current_user_id();
	wp_set_current_user( 1 );
	$report = Galerio\Core\Pods\Importer::import( Galerio\Core\Pods\Package::from_file( $file ) );
	wp_set_current_user( $user );

	if ( is_wp_error( $report ) || $report['warnings'] ) {
		echo 'Could not import the Galerio Pods package.' . PHP_EOL;
		exit( 1 );
	}

	pods_api()->cache_flush_pods();

	// Register the imported post types and taxonomies in this request.
	pods_init()->setup_content_types( true );
}
