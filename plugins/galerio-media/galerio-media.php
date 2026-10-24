<?php
/**
 * Plugin Name:       Galerio Media
 * Plugin URI:        https://galerio.net
 * Description:       Media for galleries, artists and publishers: folders in the Media Library, shared and per person, with an import from FileBird, and uploads kept private to the people who made them.
 * Version:           0.1.0-alpha.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Adam Shailer
 * Author URI:        https://adasha.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       galerio-media
 * Domain Path:       /languages
 *
 * @package Galerio\Media
 */

defined( 'ABSPATH' ) || exit;

define( 'GALERIO_MEDIA_VERSION', '0.1.0-alpha.1' );
define( 'GALERIO_MEDIA_FILE', __FILE__ );
define( 'GALERIO_MEDIA_DIR', plugin_dir_path( __FILE__ ) );

/*
 * Galerio Media works with or without Galerio Core: folders and upload
 * privacy are about the Media Library itself, whatever the site shows.
 */

require_once GALERIO_MEDIA_DIR . 'includes/class-settings.php';
require_once GALERIO_MEDIA_DIR . 'includes/class-folders.php';
require_once GALERIO_MEDIA_DIR . 'includes/class-own-uploads.php';
require_once GALERIO_MEDIA_DIR . 'includes/class-list-view.php';
require_once GALERIO_MEDIA_DIR . 'includes/class-filebird-import.php';

// Galerio isn't hosted on WordPress.org, so its bundled translations in languages/ are loaded here.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'galerio-media', false, dirname( plugin_basename( GALERIO_MEDIA_FILE ) ) . '/languages' );
	},
	1
);

Galerio\Media\Settings::init();
Galerio\Media\Folders::init();
Galerio\Media\Own_Uploads::init();
Galerio\Media\List_View::init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GALERIO_MEDIA_DIR . 'includes/class-media-command.php';
	WP_CLI::add_command( 'galerio media', Galerio\Media\Media_Command::class );
}
