<?php
/**
 * Plugin Name:       Galerio Core
 * Plugin URI:        https://galerio.net
 * Description:       Data layer for Galerio: a field API over creators, works, collections and articles, used by the Galerio theme and blocks.
 * Version:           0.2.0-alpha
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  pods
 * Author:            Adam Shailer
 * Author URI:        https://adasha.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       galerio-core
 *
 * @package Galerio\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'GALERIO_CORE_VERSION', '0.2.0-alpha' );
define( 'GALERIO_CORE_FILE', __FILE__ );
define( 'GALERIO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GALERIO_CORE_URL', plugin_dir_url( __FILE__ ) );

/*
 * Content types and fields are currently defined by the Pods package in
 * data/pods/galerio.json. This plugin deliberately registers no post types or
 * taxonomies of its own until the native data layer replaces Pods.
 *
 * Templates and blocks read fields through the field API below, never through
 * Pods directly, so that the storage backend can be swapped later.
 */

require_once GALERIO_CORE_DIR . 'includes/fields/class-field.php';
require_once GALERIO_CORE_DIR . 'includes/fields/interface-adapter.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-pods-adapter.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-registry.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-display.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-rest.php';
require_once GALERIO_CORE_DIR . 'includes/fields/functions.php';

Galerio\Core\Fields\Rest::init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GALERIO_CORE_DIR . 'includes/cli/class-pods-command.php';
	WP_CLI::add_command( 'galerio pods', Galerio\Core\CLI\Pods_Command::class );
}
