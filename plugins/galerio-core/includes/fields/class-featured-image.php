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
 * Decides which image stands for an item wherever the site shows a featured
 * image: cards, archives, carousels, feeds, links shared on social media and
 * Schema.org data. An item can have two: its featured image, which may
 * interpret it, and its own image, which records it (a work's definitive
 * image, a collection's first image, a profile's portrait).
 *
 * - Works, collections and articles: with one of the two, that one is used.
 *   With both, the item's "Use the … instead" setting chooses, and the
 *   featured image is used until it's turned on, as WordPress does.
 * - Profiles: the portrait is used only when the profile turns the setting
 *   on, even when it's the only image, and only while the profile shows its
 *   portrait (Show portrait, and the site's Show portraits).
 *
 * The work's own page shows its definitive image as the artwork either way,
 * through its template's binding. The REST API's `featured_media` reports
 * only a featured image that was actually set, so the editor tells the truth.
 */
final class Featured_Image {

	/**
	 * Post meta: whether the item uses its own image rather than its
	 * featured image.
	 */
	const USE = 'galerio_use_own_image';

	/**
	 * Post meta of Galerio Core 0.5 and earlier: whether the item used its own
	 * image while it had no featured image. Read only for profiles that turned
	 * their portrait on, so they keep showing it.
	 */
	const LEGACY = 'galerio_image_fallback';

	/**
	 * Post types whose own image is used only when chosen.
	 */
	const OPT_IN = array( 'galerio_profile' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'post_thumbnail_id', array( __CLASS__, 'thumbnail_id' ), 10, 2 );
		add_filter( 'default_post_metadata', array( __CLASS__, 'fallback' ), 10, 4 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );

		foreach ( array_keys( self::fallbacks() ) as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", array( __CLASS__, 'rest_featured_media' ), 10, 2 );
		}
	}

	/**
	 * The image field each post type has as its own image.
	 *
	 * @return array<string, string> Post type => field key.
	 */
	public static function fallbacks() {
		/**
		 * Filters the image field each post type has as its own image, used
		 * in place of the featured image as the item's settings choose. List
		 * fields use their first image.
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
	 * The featured image as stored, without the choice applied.
	 *
	 * @param int $post_id Item.
	 * @return int Attachment ID, or 0.
	 */
	public static function stored( $post_id ) {
		return (int) get_metadata_raw( 'post', $post_id, '_thumbnail_id', true );
	}

	/**
	 * The item's own image, when it has one it may show.
	 *
	 * @param int $post_id Item.
	 * @return int Attachment ID, or 0.
	 */
	public static function own( $post_id ) {
		$post_type = get_post_type( $post_id );
		$fallbacks = self::fallbacks();
		$field     = isset( $fallbacks[ $post_type ] ) ? galerio_fields()->get( $fallbacks[ $post_type ] ) : null;

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
	 * Whether the item chose its own image over its featured image.
	 *
	 * @param int $post_id Item.
	 * @return bool
	 */
	public static function prefers_own( $post_id ) {
		$saved = get_metadata_raw( 'post', $post_id, self::USE, true );

		if ( null !== $saved ) {
			return (bool) $saved;
		}

		// A profile that turned its portrait on under the old setting, which
		// applied while it had no featured image, keeps it in that case.
		return in_array( get_post_type( $post_id ), self::OPT_IN, true )
			&& (bool) get_metadata_raw( 'post', $post_id, self::LEGACY, true )
			&& ! self::stored( $post_id );
	}

	/**
	 * The image that stands for the item, by the rules above.
	 *
	 * @param int $post_id Item.
	 * @return int Attachment ID, or 0 for none.
	 */
	public static function image( $post_id ) {
		$featured = self::stored( $post_id );
		$own      = self::own( $post_id );

		if ( $own && ( self::prefers_own( $post_id ) || ( ! $featured && ! in_array( get_post_type( $post_id ), self::OPT_IN, true ) ) ) ) {
			return $own;
		}

		return $featured;
	}

	/**
	 * The featured image, as the item's choice has it, wherever WordPress
	 * reads it (get_post_thumbnail_id(), has_post_thumbnail() and the
	 * blocks and templates that use them).
	 *
	 * Stored values aren't touched, so saving a featured image works as
	 * usual, and the editor reads what was set.
	 *
	 * @param int|false    $thumbnail_id The stored featured image.
	 * @param WP_Post|null $post         Item.
	 * @return int|false
	 */
	public static function thumbnail_id( $thumbnail_id, $post ) {
		if ( ! $post || ! isset( self::fallbacks()[ $post->post_type ] ) ) {
			return $thumbnail_id;
		}

		$image = self::image( $post->ID );

		return $image ? $image : $thumbnail_id;
	}

	/**
	 * For code that reads `_thumbnail_id` itself: the item's image while no
	 * featured image is stored.
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
			$response->data['featured_media'] = self::stored( $post->ID );
		}

		return $response;
	}

	/**
	 * Register the per-item setting, editable in the editor.
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
					'default'       => false,
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
					'name'     => $field->name,
					'field'    => Names::expand( $field->label ),
					'multiple' => (bool) $field->multiple,
					'optIn'    => in_array( $screen->post_type, self::OPT_IN, true ),
					'isWork'   => 'galerio_work' === $screen->post_type,
				)
			) . ';',
			'before'
		);
	}
}
