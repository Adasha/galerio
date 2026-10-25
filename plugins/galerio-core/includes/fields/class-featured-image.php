<?php
/**
 * Featured image fallback.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use Galerio\Core\Aliases\Names;
use WP_Post;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Makes an item without a featured image use one of its own images instead:
 * a work's definitive image, a profile's portrait (when the profile shows
 * it), a collection's first image, and an article's first image once
 * articles have images.
 *
 * The featured image may interpret the work while the definitive image is
 * its single true representation, so they are separate fields; most items
 * only need one, so the featured image falls back to the other. Each item
 * can turn the fallback on or off; the theme shows a plain colour without
 * an image. Profiles start with it off, so a portrait is never shown large
 * unless someone chooses it; works, collections and articles start with it on.
 *
 * The fallback applies wherever the site reads a featured image, but not in
 * the REST API's `featured_media`, so the editor shows only an image that
 * was actually set.
 */
final class Featured_Image {

	/**
	 * Post meta: whether the item uses its own image when it has no featured image.
	 */
	const USE = 'galerio_image_fallback';

	/**
	 * Post types whose fallback starts off.
	 */
	const OFF_BY_DEFAULT = array( 'galerio_profile' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'default_post_metadata', array( __CLASS__, 'fallback' ), 10, 4 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );

		foreach ( array_keys( self::fallbacks() ) as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", array( __CLASS__, 'rest_featured_media' ), 10, 2 );
		}
	}

	/**
	 * The image field each post type falls back to.
	 *
	 * @return array<string, string> Post type => field key.
	 */
	public static function fallbacks() {
		/**
		 * Filters the image field each post type uses when it has no featured
		 * image. List fields use their first image.
		 *
		 * @param array<string, string> $fallbacks Post type => field key.
		 */
		return (array) apply_filters(
			'galerio_featured_image_fallbacks',
			array(
				'galerio_work'       => 'work.definitive_image',
				'galerio_profile'    => 'profile.portrait',
				'galerio_collection' => 'collection.images',
				'galerio_article'    => 'article.images',
			)
		);
	}

	/**
	 * The image an item falls back to, if any.
	 *
	 * @param int $post_id Item.
	 * @return int Attachment ID, or 0.
	 */
	public static function image( $post_id ) {
		$post_type = get_post_type( $post_id );
		$fallbacks = self::fallbacks();

		if ( ! isset( $fallbacks[ $post_type ] ) || ! self::used( $post_id ) ) {
			return 0;
		}

		$field = galerio_fields()->get( $fallbacks[ $post_type ] );

		if ( ! $field ) {
			return 0;
		}

		// A profile that hides its portrait on the site doesn't show it as an image either.
		if ( 'galerio_profile' === $post_type && ! galerio_get_field( 'profile.show_portrait', $post_id ) ) {
			return 0;
		}

		$value = galerio_fields()->value( $field, $post_id );
		$image = is_array( $value ) ? reset( $value ) : $value;

		return $image ? (int) $image : 0;
	}

	/**
	 * Whether an item uses the fallback: its own choice, or its type's default.
	 *
	 * @param int $post_id Item.
	 * @return bool
	 */
	public static function used( $post_id ) {
		$saved = get_metadata_raw( 'post', $post_id, self::USE, true );

		if ( null === $saved ) {
			return ! in_array( get_post_type( $post_id ), self::OFF_BY_DEFAULT, true );
		}

		return (bool) $saved;
	}

	/**
	 * Supply the fallback image when an item has no featured image.
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
		if ( '_thumbnail_id' !== $meta_key || ! isset( self::fallbacks()[ get_post_type( $object_id ) ] ) ) {
			return $value;
		}

		$image = self::image( $object_id );

		if ( ! $image ) {
			return $value;
		}

		return $single ? $image : array( $image );
	}

	/**
	 * Report only a featured image that was actually set to the REST API, so
	 * the editor's Featured image panel tells the truth.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Item.
	 * @return WP_REST_Response
	 */
	public static function rest_featured_media( $response, $post ) {
		if ( isset( $response->data['featured_media'] ) ) {
			$response->data['featured_media'] = (int) get_metadata_raw( 'post', $post->ID, '_thumbnail_id', true );
		}

		return $response;
	}

	/**
	 * Register the per-item switch, editable in the editor.
	 */
	public static function register_meta() {
		foreach ( array_keys( self::fallbacks() ) as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			// The editor only loads meta of types that support custom fields.
			add_post_type_support( $post_type, 'custom-fields' );

			register_post_meta(
				$post_type,
				self::USE,
				array(
					'type'          => 'boolean',
					'single'        => true,
					'default'       => ! in_array( $post_type, self::OFF_BY_DEFAULT, true ),
					'show_in_rest'  => true,
					'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * The switch under the editor's Featured image panel.
	 */
	public static function editor_assets() {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$fallbacks = self::fallbacks();

		if ( ! $screen || ! isset( $fallbacks[ $screen->post_type ] ) ) {
			return;
		}

		$field = galerio_fields()->get( $fallbacks[ $screen->post_type ] );
		if ( ! $field ) {
			return;
		}

		wp_enqueue_script(
			'galerio-featured-image',
			GALERIO_CORE_URL . 'assets/js/featured-image.js',
			array( 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			GALERIO_CORE_VERSION,
			true
		);
		wp_set_script_translations( 'galerio-featured-image', 'galerio-core', GALERIO_CORE_DIR . 'languages' );

		wp_add_inline_script(
			'galerio-featured-image',
			'window.galerioFeaturedImage = ' . wp_json_encode(
				array(
					'meta'     => self::USE,
					'field'    => Names::expand( $field->label ),
					'multiple' => (bool) $field->multiple,
				)
			) . ';',
			'before'
		);
	}
}
