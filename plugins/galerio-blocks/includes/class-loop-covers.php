<?php
/**
 * Covers with the featured image in Query Loops.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Loads loop-covers.js in the editor. It keeps a Cover that uses the featured
 * image, inside a Query Loop, from setting its `isDark` from each item's
 * image in turn, which redrew the loop without end.
 */
final class Loop_Covers {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
	}

	/**
	 * The editor script.
	 */
	public static function editor_assets() {
		wp_enqueue_script(
			'galerio-loop-covers',
			plugins_url( 'assets/js/loop-covers.js', GALERIO_BLOCKS_FILE ),
			array( 'wp-compose', 'wp-data', 'wp-element', 'wp-hooks' ),
			GALERIO_BLOCKS_VERSION,
			true
		);
	}
}
