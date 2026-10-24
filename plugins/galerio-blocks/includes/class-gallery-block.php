<?php
/**
 * The Gallery block.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the `galerio/gallery` block.
 *
 * Its images come from an image-list field of the current item, read
 * through Galerio Core (a collection's or a work's images, by default), or
 * from images chosen in the block. They show as a grid, a masonry wall or
 * one after another (the "linear" layout), with captions and a lightbox.
 * Each image links to its full-size file, so it works without JavaScript.
 */
final class Gallery_Block {

	/**
	 * Aspect ratios a grid can crop to. "auto" keeps each image's own.
	 */
	const RATIOS = array( 'auto', '1', '4/3', '3/4', '3/2', '2/3', '16/9' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Register the block from its compiled metadata.
	 */
	public static function register() {
		$dir = GALERIO_BLOCKS_DIR . 'build/gallery';

		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}

		register_block_type( $dir, array( 'render_callback' => array( __CLASS__, 'render' ) ) );
		wp_set_script_translations( 'galerio-gallery-editor-script', 'galerio-blocks', GALERIO_BLOCKS_DIR . 'languages' );
	}

	/**
	 * Image-list fields, from Galerio Core when it's active.
	 *
	 * @return array[] Each with `key`, `label` and `postType`.
	 */
	public static function fields() {
		if ( ! function_exists( 'galerio_fields' ) ) {
			return array();
		}

		$fields = array();
		$types  = \Galerio\Core\Fields\Registry::OBJECTS;

		foreach ( galerio_fields()->all() as $field ) {
			if ( 'image' === $field->type && $field->multiple && $field->public && isset( $types[ $field->object ] ) ) {
				$fields[] = array(
					'key'      => $field->key,
					'label'    => $field->label,
					'postType' => $types[ $field->object ],
				);
			}
		}

		return $fields;
	}

	/**
	 * Pass the image-list fields to the editor script.
	 */
	public static function editor_data() {
		wp_add_inline_script(
			'galerio-gallery-editor-script',
			'window.galerioGallery = ' . wp_json_encode( array( 'fields' => self::fields() ) ) . ';',
			'before'
		);
	}

	/**
	 * The field a gallery reads: the one chosen, or the current item's first
	 * image list.
	 *
	 * @param string $key       Chosen field key, or empty.
	 * @param string $post_type Current item's post type.
	 * @return string Field key, or empty when there's none.
	 */
	public static function field_for( $key, $post_type ) {
		$fields = self::fields();

		foreach ( $fields as $field ) {
			if ( $key === $field['key'] ) {
				return $key;
			}
		}

		foreach ( $fields as $field ) {
			if ( '' === $key && $post_type === $field['postType'] ) {
				return $field['key'];
			}
		}

		return '';
	}

	/**
	 * The gallery's image IDs, in the order they'll show.
	 *
	 * @param array $attributes Block attributes.
	 * @param int   $post_id    Current item.
	 * @return int[]
	 */
	public static function image_ids( $attributes, $post_id ) {
		$ids = array();

		if ( 'media' === $attributes['source'] ) {
			$ids = (array) $attributes['ids'];
		} elseif ( $post_id ) {
			$key = self::field_for( $attributes['field'], get_post_type( $post_id ) );
			$ids = $key ? (array) galerio_get_field( $key, $post_id ) : array();
		}

		$ids = array_values(
			array_filter(
				array_unique( array_map( 'absint', $ids ) ),
				function ( $id ) {
					return wp_attachment_is_image( $id );
				}
			)
		);

		$shuffle = 'random' === $attributes['order'];

		// Automatic order follows a collection's own "Shuffle images" switch.
		if ( 'auto' === $attributes['order'] && $post_id && 'galerio_collection' === get_post_type( $post_id ) && function_exists( 'galerio_get_field' ) ) {
			$shuffle = (bool) galerio_get_field( 'collection.shuffle_images', $post_id );
		}

		if ( $shuffle ) {
			shuffle( $ids );
		}

		/**
		 * Filters a gallery's images.
		 *
		 * @param int[] $ids        Attachment IDs, in display order.
		 * @param array $attributes Block attributes.
		 * @param int   $post_id    Current item, or 0.
		 */
		return apply_filters( 'galerio_gallery_images', $ids, $attributes, $post_id );
	}

	/**
	 * Render the block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Empty when there are no images.
	 */
	public static function render( $attributes, $content, $block ) {
		$attributes = wp_parse_args( $attributes, self::defaults() );
		$post_id    = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$ids        = self::image_ids( $attributes, $post_id );

		if ( ! $ids ) {
			return self::in_editor() ? '<p>' . esc_html__( 'No images to show yet.', 'galerio-blocks' ) . '</p>' : '';
		}

		$layout  = in_array( $attributes['layout'], array( 'grid', 'masonry', 'linear' ), true ) ? $attributes['layout'] : 'grid';
		$columns = 'linear' === $layout ? 1 : max( 1, min( 8, (int) $attributes['columns'] ) );
		$ratio   = in_array( $attributes['aspectRatio'], self::RATIOS, true ) ? $attributes['aspectRatio'] : '1';
		$style   = '--galerio-gallery-columns:' . $columns . ';';

		if ( 'grid' === $layout && 'auto' !== $ratio ) {
			$style .= '--galerio-gallery-ratio:' . $ratio . ';';
		}

		$items = '';
		foreach ( $ids as $id ) {
			$items .= self::item( $id, $columns, $attributes );
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'is-layout-' . $layout . ( 'grid' === $layout && 'auto' !== $ratio ? ' is-cropped' : '' ) . ( $attributes['lightbox'] ? ' has-lightbox' : '' ),
				'style' => $style,
			)
		);

		$labels = array(
			'dialog' => __( 'Image viewer', 'galerio-blocks' ),
			'close'  => __( 'Close', 'galerio-blocks' ),
			'prev'   => __( 'Previous image', 'galerio-blocks' ),
			'next'   => __( 'Next image', 'galerio-blocks' ),
			/* translators: 1: image number, 2: number of images. */
			'count'  => __( '%1$d of %2$d', 'galerio-blocks' ),
		);

		return sprintf(
			'<div %1$s%2$s><ul class="wp-block-galerio-gallery__items">%3$s</ul></div>',
			$wrapper,
			$attributes['lightbox'] ? ' data-galerio-lightbox="' . esc_attr( wp_json_encode( $labels ) ) . '"' : '',
			$items
		);
	}

	/**
	 * One image.
	 *
	 * @param int   $id         Attachment ID.
	 * @param int   $columns    Columns in view.
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private static function item( $id, $columns, $attributes ) {
		$image = wp_get_attachment_image(
			$id,
			$attributes['imageSize'],
			false,
			array(
				'class' => 'wp-block-galerio-gallery__image',
				'sizes' => 1 === $columns ? '100vw' : sprintf( '(max-width: 781px) 50vw, %dvw', ceil( 100 / $columns ) ),
			)
		);

		if ( ! $image ) {
			return '';
		}

		$full    = wp_get_attachment_image_src( $id, 'full' );
		$caption = self::caption( $id, $attributes['caption'] );

		if ( $full ) {
			$image = sprintf(
				'<a class="wp-block-galerio-gallery__link" href="%1$s" data-width="%2$d" data-height="%3$d">%4$s</a>',
				esc_url( $full[0] ),
				(int) $full[1],
				(int) $full[2],
				$image
			);
		}

		return sprintf(
			'<li class="wp-block-galerio-gallery__item"><figure class="wp-block-galerio-gallery__figure">%1$s%2$s</figure></li>',
			$image,
			$caption ? '<figcaption class="wp-block-galerio-gallery__caption">' . $caption . '</figcaption>' : ''
		);
	}

	/**
	 * An image's caption: its caption, or its title and caption.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $kind "none", "caption" or "title".
	 * @return string HTML, or empty.
	 */
	private static function caption( $id, $kind ) {
		if ( 'none' === $kind ) {
			return '';
		}

		$caption = wp_kses_post( wp_get_attachment_caption( $id ) );

		if ( 'title' === $kind ) {
			$title   = esc_html( get_the_title( $id ) );
			$caption = $title ? '<strong class="wp-block-galerio-gallery__title">' . $title . '</strong>' . ( $caption ? ' ' . $caption : '' ) : $caption;
		}

		return trim( $caption );
	}

	/**
	 * The attributes' defaults, from the block's metadata.
	 *
	 * @return array
	 */
	private static function defaults() {
		$defaults = array();
		$type     = \WP_Block_Type_Registry::get_instance()->get_registered( 'galerio/gallery' );

		foreach ( $type ? $type->attributes : array() as $name => $schema ) {
			if ( array_key_exists( 'default', $schema ) ) {
				$defaults[ $name ] = $schema['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Whether the block is being previewed in the editor.
	 *
	 * @return bool
	 */
	private static function in_editor() {
		return wp_is_serving_rest_request() && current_user_can( 'edit_posts' );
	}
}
