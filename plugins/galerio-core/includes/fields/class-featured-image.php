<?php
/**
 * Featured image fallback for works.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Makes a work without a featured image use its definitive image.
 *
 * The definitive image is the work's single true representation, and the
 * featured image may interpret it, so they are separate fields. Most works
 * only need one, so the featured image falls back to the definitive image.
 */
final class Featured_Image {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'default_post_metadata', array( __CLASS__, 'fallback' ), 10, 4 );
	}

	/**
	 * Supply a work's definitive image when it has no featured image.
	 *
	 * Runs only when no _thumbnail_id is stored, so a featured image set by
	 * hand always wins.
	 *
	 * @param mixed  $value     Default value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether one value was requested.
	 * @return mixed
	 */
	public static function fallback( $value, $object_id, $meta_key, $single ) {
		if ( '_thumbnail_id' !== $meta_key || 'galerio_work' !== get_post_type( $object_id ) ) {
			return $value;
		}

		$field = galerio_fields()->get( 'work.definitive_image' );
		$image = $field ? galerio_fields()->value( $field, $object_id ) : 0;

		if ( ! $image ) {
			return $value;
		}

		return $single ? $image : array( $image );
	}
}
