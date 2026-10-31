<?php
/**
 * The focal point field in the Media Library.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Media;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * A Focal point field in an image's details, in the Media Library and the
 * media pop-up, and on the image's own edit screen: click the image where
 * it should stay in view, or type how far across and down it is. Left empty,
 * the image keeps the default (centred, or a little above the middle as a
 * profile's image).
 */
final class Focal_Point_Field {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'wp_enqueue_media', array( __CLASS__, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'edit_screen_assets' ) );
	}

	/**
	 * The field, for images.
	 *
	 * @param array   $fields Fields.
	 * @param WP_Post $post   Attachment.
	 * @return array
	 */
	public static function field( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $fields;
		}

		$saved  = Focal_Point::saved( $post->ID );
		$shown  = $saved ?? Focal_Point::CENTRE;
		$name   = 'attachments[' . $post->ID . ']';
		$id     = 'galerio-focal-point-' . $post->ID;
		$across = $saved ? round( $saved['x'] * 100 ) : '';
		$down   = $saved ? round( $saved['y'] * 100 ) : '';

		$html = sprintf(
			'<div class="galerio-focal-point" id="%1$s">
				<div class="galerio-focal-point__image" aria-hidden="true">%2$s<span class="galerio-focal-point__marker" style="left:%3$s%%;top:%4$s%%"></span></div>
				<div class="galerio-focal-point__inputs">
					<label>%5$s <input type="number" min="0" max="100" step="1" name="%6$s[galerio_focal_x]" value="%7$s" placeholder="50" class="galerio-focal-point__x"> %%</label>
					<label>%8$s <input type="number" min="0" max="100" step="1" name="%6$s[galerio_focal_y]" value="%9$s" placeholder="50" class="galerio-focal-point__y"> %%</label>
					<button type="button" class="button-link galerio-focal-point__reset"%10$s>%11$s</button>
				</div>
			</div>',
			esc_attr( $id ),
			wp_get_attachment_image( $post->ID, 'medium', false, array( 'loading' => false ) ),
			esc_attr( (string) round( $shown['x'] * 100, 2 ) ),
			esc_attr( (string) round( $shown['y'] * 100, 2 ) ),
			esc_html__( 'Across', 'galerio-core' ),
			esc_attr( $name ),
			esc_attr( (string) $across ),
			esc_html__( 'Down', 'galerio-core' ),
			esc_attr( (string) $down ),
			$saved ? '' : ' hidden',
			esc_html__( 'Clear', 'galerio-core' )
		);

		$fields['galerio_focal_point'] = array(
			'label' => __( 'Focal point', 'galerio-core' ),
			'input' => 'html',
			'html'  => $html,
			'helps' => __( 'Click the image where it should stay in view when it’s cropped, as in cards, covers and carousels. It holds wherever the image is shown. Left empty, it’s centred, or a little above the middle as a profile’s image.', 'galerio-core' ),
		);

		return $fields;
	}

	/**
	 * Save the field: a point from the two percentages, or none when both
	 * are empty. One left empty is the middle.
	 *
	 * @param array $post       Attachment data.
	 * @param array $attachment Fields sent.
	 * @return array
	 */
	public static function save( $post, $attachment ) {
		if ( ! isset( $attachment['galerio_focal_x'], $attachment['galerio_focal_y'] ) || empty( $post['ID'] ) || ! current_user_can( 'edit_post', $post['ID'] ) ) {
			return $post;
		}

		$across = trim( (string) $attachment['galerio_focal_x'] );
		$down   = trim( (string) $attachment['galerio_focal_y'] );

		if ( '' === $across && '' === $down ) {
			Focal_Point::save( $post['ID'], null );
			return $post;
		}

		Focal_Point::save(
			$post['ID'],
			array(
				'x' => ( is_numeric( $across ) ? (float) $across : 50 ) / 100,
				'y' => ( is_numeric( $down ) ? (float) $down : 50 ) / 100,
			)
		);

		return $post;
	}

	/**
	 * The script and styles, wherever the media pop-up or Media Library is.
	 */
	public static function assets() {
		wp_enqueue_script(
			'galerio-focal-point-field',
			GALERIO_CORE_URL . 'assets/js/focal-point-field.js',
			array( 'jquery' ),
			GALERIO_CORE_VERSION,
			true
		);

		wp_register_style( 'galerio-focal-point-field', false, array(), GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-focal-point-field' );
		wp_add_inline_style(
			'galerio-focal-point-field',
			'.galerio-focal-point__image { position: relative; display: inline-block; max-width: 100%; line-height: 0; cursor: crosshair; }
			.galerio-focal-point__image img { display: block; max-width: 100%; max-height: 240px; width: auto; height: auto; }
			.galerio-focal-point__marker { position: absolute; width: 18px; height: 18px; margin: -11px 0 0 -11px; border: 2px solid #fff; border-radius: 50%; box-shadow: 0 0 0 2px #1e1e1e, inset 0 0 0 2px #1e1e1e; pointer-events: none; }
			.galerio-focal-point__inputs { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; margin-top: 6px; }
			.galerio-focal-point__inputs input[type="number"] { width: 4.5em; }'
		);
	}

	/**
	 * The same on an image's own edit screen.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function edit_screen_assets( $hook_suffix ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( 'post.php' === $hook_suffix && $screen && 'attachment' === $screen->post_type ) {
			self::assets();
		}
	}
}
