<?php
/**
 * Plugin Name:       Query Carousel Block
 * Plugin URI:        https://example.com/query-carousel-block
 * Description:       A Gutenberg block that wraps the core Query Loop block in a fully accessible, zero-dependency Embla Carousel.
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Version:           1.0.0
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       query-carousel-block
 *
 * @package Custom\Blocks\QueryCarousel
 */

declare( strict_types=1 );

namespace Custom\Blocks\QueryCarousel;

// Abort if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the block using metadata from block.json.
 * The block's view script is only enqueued when the block is rendered on the page,
 * handled automatically by the `viewScript` field in block.json.
 *
 * @return void
 */
function register_block(): void {
	register_block_type_from_metadata( __DIR__ . '/build' );
}
add_action( 'init', __NAMESPACE__ . '\\register_block' );

/**
 * Filters the list of allowed transition effects available in the editor SelectControl.
 *
 * Third-party developers can hook into this filter to register additional
 * transition effects. Each item must be an associative array with 'label'
 * and 'value' keys. The 'value' maps to a CSS class and Embla plugin key.
 *
 * Example usage in a child plugin:
 *   add_filter( 'query_carousel_transition_effects', function( array $effects ): array {
 *       $effects[] = [ 'label' => 'Flip', 'value' => 'flip' ];
 *       return $effects;
 *   } );
 *
 * @param array<int, array{label: string, value: string}> $effects Registered effects.
 * @return array<int, array{label: string, value: string}>
 */
function get_transition_effects( array $effects ): array {
	return $effects;
}

/**
 * Passes the PHP-side transition effects to the editor JS via localized script data.
 * This bridges the PHP filter system with the JS block editor controls.
 *
 * @return void
 */
function localize_editor_data(): void {
	// Only run if the block editor assets are already enqueued.
	if ( ! wp_script_is( 'custom-query-carousel-editor-script', 'registered' ) ) {
		return;
	}

	/**
	 * Filter the available transition effects.
	 * Defaults include 'slide' and 'fade'.
	 *
	 * @param array<int, array{label: string, value: string}> $effects
	 */
	$transition_effects = apply_filters(
		'query_carousel_transition_effects',
		[
			[ 'label' => __( 'Slide', 'query-carousel-block' ), 'value' => 'slide' ],
			[ 'label' => __( 'Fade', 'query-carousel-block' ), 'value' => 'fade' ],
		]
	);

	wp_localize_script(
		'custom-query-carousel-editor-script',
		'queryCarouselData',
		[
			'transitionEffects' => $transition_effects,
			'i18n'              => [
				'panelTitle'       => __( 'Carousel Settings', 'query-carousel-block' ),
				'autoplayLabel'    => __( 'Autoplay', 'query-carousel-block' ),
				'autoplayHelp'     => __( 'Automatically advance slides.', 'query-carousel-block' ),
				'autoplaySpeed'    => __( 'Autoplay Speed (ms)', 'query-carousel-block' ),
				'loopLabel'        => __( 'Loop', 'query-carousel-block' ),
				'loopHelp'         => __( 'Loop slides back to the beginning.', 'query-carousel-block' ),
				'transitionLabel'  => __( 'Transition Effect', 'query-carousel-block' ),
				'arrowsLabel'      => __( 'Show Navigation Arrows', 'query-carousel-block' ),
				'dotsLabel'        => __( 'Show Pagination Dots', 'query-carousel-block' ),
				'slidesToScroll'   => __( 'Slides to Scroll', 'query-carousel-block' ),
			],
		]
	);
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\localize_editor_data' );
