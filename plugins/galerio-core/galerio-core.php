<?php
/**
 * Plugin Name:       Galerio Core
 * Plugin URI:        https://galerio.net
 * Description:       Data layer for Galerio: a field API over creators, works, collections and articles, used by the Galerio theme and blocks.
 * Version:           0.8.2-alpha.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  pods
 * Author:            Adam Shailer
 * Author URI:        https://adasha.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       galerio-core
 * Domain Path:       /languages
 *
 * @package Galerio\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'GALERIO_CORE_VERSION', '0.8.2-alpha.1' );
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
 *
 * A site can rename the types and move their addresses (Settings → Galerio
 * content types). The aliases are applied while the types are registered.
 */

require_once GALERIO_CORE_DIR . 'includes/fields/class-field.php';
require_once GALERIO_CORE_DIR . 'includes/fields/interface-adapter.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-pods-adapter.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-registry.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-display.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-dates.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-rest.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-bindings.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-featured-image.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-field-block.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-edit-screen.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-reference.php';
require_once GALERIO_CORE_DIR . 'includes/fields/class-check.php';
require_once GALERIO_CORE_DIR . 'includes/fields/functions.php';
require_once GALERIO_CORE_DIR . 'includes/pods/field-types.php';
require_once GALERIO_CORE_DIR . 'includes/pods/class-package.php';
require_once GALERIO_CORE_DIR . 'includes/pods/class-importer.php';
require_once GALERIO_CORE_DIR . 'includes/pods/class-status-notice.php';
require_once GALERIO_CORE_DIR . 'includes/aliases/class-aliases.php';
require_once GALERIO_CORE_DIR . 'includes/aliases/class-names.php';
require_once GALERIO_CORE_DIR . 'includes/aliases/class-settings-page.php';
require_once GALERIO_CORE_DIR . 'includes/media/class-focal-point.php';
require_once GALERIO_CORE_DIR . 'includes/media/class-focal-point-field.php';
require_once GALERIO_CORE_DIR . 'includes/styles/class-page-style.php';
require_once GALERIO_CORE_DIR . 'includes/schema/class-schema-org.php';
require_once GALERIO_CORE_DIR . 'includes/roles/class-type-capabilities.php';
require_once GALERIO_CORE_DIR . 'includes/settings/class-features.php';
require_once GALERIO_CORE_DIR . 'includes/catalogue/class-catalogue.php';
require_once GALERIO_CORE_DIR . 'includes/catalogue/class-enquiries.php';
require_once GALERIO_CORE_DIR . 'includes/archives/class-archives.php';
require_once GALERIO_CORE_DIR . 'includes/feeds/class-feeds.php';
require_once GALERIO_CORE_DIR . 'includes/feeds/class-sitemaps.php';
require_once GALERIO_CORE_DIR . 'includes/feeds/class-reading-settings.php';
require_once GALERIO_CORE_DIR . 'includes/terms/class-term-mover.php';

// Galerio isn't hosted on WordPress.org, so its bundled translations in languages/ are loaded here.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'galerio-core', false, dirname( plugin_basename( GALERIO_CORE_FILE ) ) . '/languages' );
	},
	1
);

Galerio\Core\Fields\Rest::init();
Galerio\Core\Fields\Bindings::init();
Galerio\Core\Fields\Featured_Image::init();
Galerio\Core\Fields\Field_Block::init();
Galerio\Core\Fields\Edit_Screen::init();
Galerio\Core\Aliases\Aliases::init();
Galerio\Core\Aliases\Settings_Page::init();
Galerio\Core\Pods\Status_Notice::init();
Galerio\Core\Media\Focal_Point::init();
Galerio\Core\Media\Focal_Point_Field::init();
Galerio\Core\Styles\Page_Style::init();
Galerio\Core\Schema\Schema_Org::init();
Galerio\Core\Roles\Type_Capabilities::init();
Galerio\Core\Settings\Features::init();
Galerio\Core\Catalogue\Catalogue::init();
Galerio\Core\Catalogue\Enquiries::init();
Galerio\Core\Archives\Archives::init();
Galerio\Core\Feeds\Feeds::init();
Galerio\Core\Feeds\Sitemaps::init();
Galerio\Core\Feeds\Reading_Settings::init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GALERIO_CORE_DIR . 'includes/cli/class-pods-command.php';
	WP_CLI::add_command( 'galerio pods', Galerio\Core\CLI\Pods_Command::class );

	require_once GALERIO_CORE_DIR . 'includes/cli/class-fields-command.php';
	WP_CLI::add_command( 'galerio fields', Galerio\Core\CLI\Fields_Command::class );

	require_once GALERIO_CORE_DIR . 'includes/cli/class-terms-command.php';
	WP_CLI::add_command( 'galerio terms', Galerio\Core\CLI\Terms_Command::class );
}
