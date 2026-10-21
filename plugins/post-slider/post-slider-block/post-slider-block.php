<?php
/**
 * Plugin Name:       Post Slider Block
 * Plugin URI:        https://github.com/your-org/post-slider-block
 * Description:       A Gutenberg block that displays a configurable slider of the latest posts from any post type or taxonomy, with six transition effects and full block-editor design control.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       post-slider-block
 * Domain Path:       /languages
 *
 * @package PostSliderBlock
 */

declare( strict_types=1 );

namespace PostSliderBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PSB_VERSION',     '1.0.0' );
define( 'PSB_PLUGIN_FILE', __FILE__ );
define( 'PSB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'PSB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/**
 * PSR-4-style autoloader for the PostSliderBlock namespace.
 */
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'PostSliderBlock\\';
	$base_dir = PSB_PLUGIN_DIR . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = $base_dir . 'class-' . strtolower( str_replace( [ '\\', '_' ], [ '-', '-' ], $relative ) ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Bootstrap: register the block and REST routes.
 */
function bootstrap(): void {
	Block::register();
	RestApi::register();
}
add_action( 'init', __NAMESPACE__ . '\\bootstrap' );

/**
 * Load plugin text domain for i18n.
 */
function load_textdomain(): void {
	load_plugin_textdomain(
		'post-slider-block',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', __NAMESPACE__ . '\\load_textdomain' );
