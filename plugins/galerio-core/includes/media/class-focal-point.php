<?php
/**
 * Image focal points.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Media;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Each image can have a focal point: the part of it that stays in view when
 * the image is cropped to fill a box, such as a card, a cover or a carousel
 * slide. It's kept with the image, so it holds wherever the image is shown,
 * whichever item it stands for.
 *
 * An image without one is centred, except where it stands for a profile:
 * a portrait is usually a face, so it's kept a little above the middle.
 *
 * It's applied as `object-position` to images built by WordPress's
 * attachment image functions, unless the markup already sets its own (a
 * Cover block with its own focal point), and to Cover blocks that show the
 * featured image, as their focal point, unless they have one of their own.
 */
final class Focal_Point {

	/**
	 * Attachment meta: the focal point, as `x` and `y` from 0 (left, top) to
	 * 1 (right, bottom).
	 */
	const META = 'galerio_focal_point';

	/**
	 * The middle of an image.
	 */
	const CENTRE = array(
		'x' => 0.5,
		'y' => 0.5,
	);

	/**
	 * Items whose images are being shown, innermost last.
	 *
	 * @var int[]
	 */
	private static $items = array();

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'begin_fetch_post_thumbnail_html', array( __CLASS__, 'enter' ) );
		add_action( 'end_fetch_post_thumbnail_html', array( __CLASS__, 'leave' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'image_attributes' ), 10, 2 );
		add_filter( 'render_block_data', array( __CLASS__, 'cover' ) );
	}

	/**
	 * Register the meta, editable through the REST API by those who can edit
	 * the image.
	 */
	public static function register_meta() {
		register_post_meta(
			'attachment',
			self::META,
			array(
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'properties'           => array(
							'x' => array(
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 1,
							),
							'y' => array(
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 1,
							),
						),
						'additionalProperties' => false,
					),
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * A focal point with both coordinates from 0 to 1, or null.
	 *
	 * @param mixed $value Value.
	 * @return array{x: float, y: float}|null
	 */
	public static function sanitize( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['x'], $value['y'] ) || ! is_numeric( $value['x'] ) || ! is_numeric( $value['y'] ) ) {
			return null;
		}

		return array(
			'x' => round( min( 1, max( 0, (float) $value['x'] ) ), 4 ),
			'y' => round( min( 1, max( 0, (float) $value['y'] ) ), 4 ),
		);
	}

	/**
	 * An image's own focal point.
	 *
	 * @param int $image_id Attachment ID.
	 * @return array{x: float, y: float}|null Null when it has none.
	 */
	public static function saved( $image_id ) {
		return $image_id ? self::sanitize( get_post_meta( $image_id, self::META, true ) ) : null;
	}

	/**
	 * Save an image's focal point, or clear it with null.
	 *
	 * @param int        $image_id Attachment ID.
	 * @param array|null $point    Focal point.
	 */
	public static function save( $image_id, $point ) {
		$point = self::sanitize( $point );

		if ( $point ) {
			update_post_meta( $image_id, self::META, $point );
		} else {
			delete_post_meta( $image_id, self::META );
		}
	}

	/**
	 * Where an image without a focal point is kept in view, for an item.
	 *
	 * @param int|WP_Post|null $item Item the image stands for, if any.
	 * @return array{x: float, y: float}
	 */
	public static function default_for( $item = null ) {
		return self::default_for_type( $item ? (string) get_post_type( $item ) : '' );
	}

	/**
	 * Where an image without a focal point is kept in view, for an item of
	 * a type.
	 *
	 * @param string $post_type Type of the item the image stands for, or empty.
	 * @return array{x: float, y: float}
	 */
	public static function default_for_type( $post_type ) {
		$point = 'galerio_profile' === $post_type ? array(
			'x' => 0.5,
			'y' => 0.3,
		) : self::CENTRE;

		/**
		 * Filters where an image without a focal point is kept in view.
		 *
		 * @param array{x: float, y: float} $point     From 0 (left, top) to 1 (right, bottom).
		 * @param string                    $post_type Type of the item the image stands for, or empty.
		 */
		return self::sanitize( apply_filters( 'galerio_focal_point_default', $point, (string) $post_type ) ) ?? self::CENTRE;
	}

	/**
	 * An image's focal point: its own, or the default for the item.
	 *
	 * @param int              $image_id Attachment ID.
	 * @param int|WP_Post|null $item     Item the image stands for, if any.
	 * @return array{x: float, y: float}
	 */
	public static function get( $image_id, $item = null ) {
		return self::saved( $image_id ) ?? self::default_for( $item );
	}

	/**
	 * A focal point as a CSS position, such as "50% 30%".
	 *
	 * @param array{x: float, y: float} $point Focal point.
	 * @return string
	 */
	public static function css( array $point ) {
		return round( $point['x'] * 100, 2 ) . '% ' . round( $point['y'] * 100, 2 ) . '%';
	}

	/**
	 * Note the item whose image is about to be shown.
	 *
	 * @param int $item_id Item.
	 */
	public static function enter( $item_id ) {
		self::$items[] = (int) $item_id;
	}

	/**
	 * Done showing the innermost item's image.
	 */
	public static function leave() {
		array_pop( self::$items );
	}

	/**
	 * The item whose image is being shown, if known.
	 *
	 * @return int
	 */
	private static function current_item() {
		return self::$items ? (int) end( self::$items ) : 0;
	}

	/**
	 * Keep an image's focal point in view. A centred one is left alone, as
	 * that's where images are cropped anyway.
	 *
	 * @param array<string, string> $attr       Image attributes.
	 * @param WP_Post               $attachment Image.
	 * @return array<string, string>
	 */
	public static function image_attributes( $attr, $attachment ) {
		$style = isset( $attr['style'] ) ? (string) $attr['style'] : '';

		if ( false !== stripos( $style, 'object-position' ) ) {
			return $attr;
		}

		$point = self::get( $attachment->ID, self::current_item() );
		if ( self::CENTRE === $point ) {
			return $attr;
		}

		$style         = rtrim( trim( $style ), ';' );
		$attr['style'] = ( '' === $style ? '' : $style . ';' ) . 'object-position:' . self::css( $point ) . ';';

		return $attr;
	}

	/**
	 * A Cover block showing the featured image, without a focal point of its
	 * own, takes the image's. That also places a fixed or repeated background.
	 *
	 * @param array $block Parsed block.
	 * @return array
	 */
	public static function cover( $block ) {
		if ( 'core/cover' !== ( $block['blockName'] ?? '' ) || empty( $block['attrs']['useFeaturedImage'] ) || isset( $block['attrs']['focalPoint'] ) ) {
			return $block;
		}

		// The Cover block shows the featured image of the post in the loop.
		$item     = get_post();
		$image_id = $item ? get_post_thumbnail_id( $item ) : 0;

		if ( ! $image_id ) {
			return $block;
		}

		$point = self::get( $image_id, $item );
		if ( self::CENTRE !== $point ) {
			$block['attrs']['focalPoint'] = $point;
		}

		return $block;
	}
}
