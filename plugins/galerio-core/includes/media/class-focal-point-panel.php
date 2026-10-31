<?php
/**
 * The Focal point panel in the editor.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Media;

use Galerio\Core\Fields\Featured_Image;

defined( 'ABSPATH' ) || exit;

/**
 * A Focal point panel in the editor's sidebar, for the image that stands
 * for the item (its featured image, or its own image as Featured_Image
 * chooses), so it can be set without going to the Media Library. It changes
 * the image itself, so it's saved straight away, not with the item.
 */
final class Focal_Point_Panel {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'assets' ) );
	}

	/**
	 * The panel, for types with featured images.
	 */
	public static function assets() {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen ? (string) $screen->post_type : '';

		if ( ! $post_type || 'post' !== $screen->base || ! post_type_supports( $post_type, 'thumbnail' ) ) {
			return;
		}

		$fallbacks = Featured_Image::fallbacks();
		$field     = isset( $fallbacks[ $post_type ] ) ? galerio_fields()->get( $fallbacks[ $post_type ] ) : null;

		wp_enqueue_script(
			'galerio-focal-point-panel',
			GALERIO_CORE_URL . 'assets/js/focal-point-panel.js',
			array( 'wp-components', 'wp-core-data', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			GALERIO_CORE_VERSION,
			true
		);
		wp_set_script_translations( 'galerio-focal-point-panel', 'galerio-core', GALERIO_CORE_DIR . 'languages' );

		wp_add_inline_script(
			'galerio-focal-point-panel',
			'window.galerioFocalPoint = ' . wp_json_encode(
				array(
					'meta'         => Focal_Point::META,
					'defaultPoint' => Focal_Point::default_for_type( $post_type ),
					'own'          => $field ? array(
						'name'  => $field->name,
						'meta'  => Featured_Image::USE,
						'optIn' => in_array( $post_type, Featured_Image::OPT_IN, true ),
					) : null,
				)
			) . ';',
			'before'
		);
	}
}
