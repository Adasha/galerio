<?php
/**
 * Plugin Name:       Galerio Migrate
 * Description:       One-off WP-CLI migration of galerio.net and swansea.art onto Galerio's content model. Delete it once the site has moved.
 * Version:           0.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  pods, galerio-core
 * Author:            Adam Shailer
 * Author URI:        https://adasha.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Galerio\Migrate
 */

defined( 'ABSPATH' ) || exit;

define( 'GALERIO_MIGRATE_DIR', plugin_dir_path( __FILE__ ) );

/*
 * The migration runs from WP-CLI only:
 *
 *     wp galerio migrate content-model --site=<site> --dry-run
 *     wp galerio migrate theme-reset --dry-run
 *
 * Each site's decisions are in sites/<site>.php.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GALERIO_MIGRATE_DIR . 'includes/class-map.php';
	require_once GALERIO_MIGRATE_DIR . 'includes/class-report.php';
	require_once GALERIO_MIGRATE_DIR . 'includes/class-relationships.php';
	require_once GALERIO_MIGRATE_DIR . 'includes/class-site-content.php';
	require_once GALERIO_MIGRATE_DIR . 'includes/class-content-model.php';
	require_once GALERIO_MIGRATE_DIR . 'includes/class-command.php';

	WP_CLI::add_command( 'galerio migrate', Galerio\Migrate\Command::class );
}
