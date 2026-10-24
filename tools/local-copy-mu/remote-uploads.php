<?php
/**
 * Plugin Name: Remote uploads (local copies only)
 * Description: Serves a local copy's media from the live site, so its images needn't be downloaded. Set GALERIO_REMOTE_UPLOADS in the wp-env config to the live site's uploads URL.
 *
 * @package Galerio\Tools
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'GALERIO_REMOTE_UPLOADS' ) && GALERIO_REMOTE_UPLOADS ) {
	add_filter(
		'upload_dir',
		function ( $uploads ) {
			$remote = untrailingslashit( GALERIO_REMOTE_UPLOADS );

			$uploads['baseurl'] = $remote;
			$uploads['url']     = $remote . $uploads['subdir'];

			return $uploads;
		}
	);
}
