<?php
/**
 * Plugin Name:       Galerio Blocks
 * Plugin URI:        https://galerio.net
 * Description:       Blocks for galleries, artists and publishers: a carousel, a gallery, exhibition dates and related items, for exhibitions, works and creators.
 * Version:           0.4.2-alpha.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Adam Shailer
 * Author URI:        https://adasha.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       galerio-blocks
 * Domain Path:       /languages
 *
 * @package Galerio\Blocks
 */

defined( 'ABSPATH' ) || exit;

define( 'GALERIO_BLOCKS_VERSION', '0.4.2-alpha.1' );
define( 'GALERIO_BLOCKS_FILE', __FILE__ );
define( 'GALERIO_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );

/*
 * The blocks work with or without Galerio Core: they read posts, terms and
 * featured images through WordPress itself, so they can show any content type.
 * The scripts and styles are compiled into build/ by `npm run build`.
 */

require_once GALERIO_BLOCKS_DIR . 'includes/class-carousel-block.php';
require_once GALERIO_BLOCKS_DIR . 'includes/class-gallery-block.php';
require_once GALERIO_BLOCKS_DIR . 'includes/class-exhibitions.php';
require_once GALERIO_BLOCKS_DIR . 'includes/class-exhibition-dates-block.php';
require_once GALERIO_BLOCKS_DIR . 'includes/class-query-variations.php';
require_once GALERIO_BLOCKS_DIR . 'includes/class-related-query.php';
require_once GALERIO_BLOCKS_DIR . 'includes/class-query-rows.php';
require_once GALERIO_BLOCKS_DIR . 'includes/class-loop-covers.php';

// Galerio isn't hosted on WordPress.org, so its bundled translations in languages/ are loaded here.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'galerio-blocks', false, dirname( plugin_basename( GALERIO_BLOCKS_FILE ) ) . '/languages' );
	},
	1
);

Galerio\Blocks\Carousel_Block::init();
Galerio\Blocks\Gallery_Block::init();
Galerio\Blocks\Exhibition_Dates_Block::init();
Galerio\Blocks\Query_Variations::init();
Galerio\Blocks\Related_Query::init();
Galerio\Blocks\Query_Rows::init();
Galerio\Blocks\Loop_Covers::init();
