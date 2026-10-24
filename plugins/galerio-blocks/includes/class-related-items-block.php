<?php
/**
 * The Related Items block.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

use WP_Block;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the `galerio/related-items` block.
 *
 * It lists the published items a relationship field of the current item
 * points to (a creator's works, a collection's exhibitors, a work's
 * collections), as a grid or a list of cards with an image, the title and an
 * optional detail such as a work's year. Fields are read through Galerio
 * Core, so the block shows nothing without it.
 */
final class Related_Items_Block {

	/**
	 * Aspect ratios the card images can crop to. "auto" keeps each image's own.
	 */
	const RATIOS = array( 'auto', '1', '4/3', '3/4', '3/2', '2/3', '16/9' );

	/**
	 * Field types that make a short detail line.
	 */
	const DETAIL_TYPES = array( 'text', 'year', 'date', 'number', 'relation' );

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
		$dir = GALERIO_BLOCKS_DIR . 'build/related-items';

		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}

		register_block_type( $dir, array( 'render_callback' => array( __CLASS__, 'render' ) ) );
		wp_set_script_translations( 'galerio-related-items-editor-script', 'galerio-blocks', GALERIO_BLOCKS_DIR . 'languages' );
	}

	/**
	 * The relationship fields, and the fields each related type can show as
	 * a detail, from Galerio Core when it's active.
	 *
	 * @return array With `relations` (key, label, postType, target) and
	 *               `details` (post type => list of key and label).
	 */
	public static function fields() {
		$data = array(
			'relations' => array(),
			'details'   => array(),
		);

		if ( ! function_exists( 'galerio_fields' ) ) {
			return $data;
		}

		$types = \Galerio\Core\Fields\Registry::OBJECTS;

		foreach ( galerio_fields()->all() as $field ) {
			if ( ! $field->public || ! isset( $types[ $field->object ] ) ) {
				continue;
			}

			if ( 'relation' === $field->type && $field->multiple && isset( $types[ $field->target ] ) ) {
				$data['relations'][] = array(
					'key'      => $field->key,
					'label'    => $field->label,
					'postType' => $types[ $field->object ],
					'target'   => $types[ $field->target ],
				);
			} elseif ( in_array( $field->type, self::DETAIL_TYPES, true ) && ! $field->multiple ) {
				$data['details'][ $types[ $field->object ] ][] = array(
					'key'   => $field->key,
					'label' => $field->label,
				);
			}
		}

		return $data;
	}

	/**
	 * Pass the fields to the editor script, with name tokens expanded.
	 */
	public static function editor_data() {
		$data = self::fields();

		foreach ( $data['relations'] as $i => $relation ) {
			$data['relations'][ $i ]['label'] = self::expand( $relation['label'] );
		}
		foreach ( $data['details'] as $type => $fields ) {
			foreach ( $fields as $i => $field ) {
				$data['details'][ $type ][ $i ]['label'] = self::expand( $field['label'] );
			}
		}

		wp_add_inline_script(
			'galerio-related-items-editor-script',
			'window.galerioRelatedItems = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * The relationship field a block reads: the one chosen, or the current
	 * item's first.
	 *
	 * @param string $key       Chosen field key, or empty.
	 * @param string $post_type Current item's post type.
	 * @return array|null The field's data from fields(), or null.
	 */
	public static function relation_for( $key, $post_type ) {
		$relations = self::fields()['relations'];

		foreach ( $relations as $relation ) {
			if ( $key === $relation['key'] && $post_type === $relation['postType'] ) {
				return $relation;
			}
		}

		foreach ( $relations as $relation ) {
			if ( '' === $key && $post_type === $relation['postType'] ) {
				return $relation;
			}
		}

		return null;
	}

	/**
	 * Render the block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Empty when there's nothing related.
	 */
	public static function render( $attributes, $content, $block ) {
		$attributes = wp_parse_args( $attributes, self::defaults() );
		$post_id    = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$relation   = $post_id ? self::relation_for( $attributes['field'], get_post_type( $post_id ) ) : null;

		if ( ! $relation || ! function_exists( 'galerio_get_related' ) ) {
			return '';
		}

		$items = self::items( galerio_get_related( $relation['key'], $post_id ), $attributes );

		if ( ! $items ) {
			return '';
		}

		$layout  = 'list' === $attributes['layout'] ? 'list' : 'grid';
		$columns = 'list' === $layout ? 1 : max( 1, min( 6, (int) $attributes['columns'] ) );
		$ratio   = in_array( $attributes['aspectRatio'], self::RATIOS, true ) ? $attributes['aspectRatio'] : '4/3';
		$style   = '--galerio-related-columns:' . $columns . ';' . ( 'auto' !== $ratio ? '--galerio-related-ratio:' . $ratio . ';' : '' );
		$cards   = '';

		foreach ( $items as $item ) {
			$cards .= self::card( $item, $attributes, $columns );
		}

		$heading = trim( self::expand( str_replace( '{field}', $relation['label'], (string) $attributes['heading'] ) ) );
		$level   = max( 2, min( 6, (int) $attributes['headingLevel'] ) );

		return sprintf(
			'<section %1$s>%2$s<ul class="wp-block-galerio-related-items__items">%3$s</ul></section>',
			get_block_wrapper_attributes(
				array(
					'class' => 'is-layout-' . $layout . ( $attributes['showImage'] && 'auto' !== $ratio ? ' is-cropped' : '' ),
					'style' => $style,
				)
			),
			'' !== $heading ? sprintf( '<h%1$d class="wp-block-galerio-related-items__heading">%2$s</h%1$d>', $level, esc_html( $heading ) ) : '',
			$cards
		);
	}

	/**
	 * The items to show, in order and up to the limit.
	 *
	 * @param WP_Post[] $items      Related items, in saved order.
	 * @param array     $attributes Block attributes.
	 * @return WP_Post[]
	 */
	private static function items( $items, $attributes ) {
		if ( 'title' === $attributes['order'] ) {
			usort(
				$items,
				function ( $a, $b ) {
					return strcasecmp( get_the_title( $a ), get_the_title( $b ) );
				}
			);
		} elseif ( 'random' === $attributes['order'] ) {
			shuffle( $items );
		}

		$limit = (int) $attributes['limit'];

		return $limit > 0 ? array_slice( $items, 0, $limit ) : $items;
	}

	/**
	 * One card.
	 *
	 * @param WP_Post $item       Related item.
	 * @param array   $attributes Block attributes.
	 * @param int     $columns    Columns in view.
	 * @return string
	 */
	private static function card( WP_Post $item, $attributes, $columns ) {
		$url   = get_permalink( $item );
		$title = get_the_title( $item );
		$image = '';

		if ( $attributes['showImage'] && has_post_thumbnail( $item ) ) {
			$image = sprintf(
				'<a class="wp-block-galerio-related-items__image" href="%1$s" tabindex="-1" aria-hidden="true">%2$s</a>',
				esc_url( $url ),
				get_the_post_thumbnail(
					$item,
					'medium_large',
					array(
						'alt'   => '',
						'sizes' => 1 === $columns ? '(max-width: 781px) 100vw, 33vw' : sprintf( '(max-width: 781px) 50vw, %dvw', ceil( 100 / $columns ) ),
					)
				)
			);
		}

		$detail = self::detail( $item, $attributes['detail'] );

		return sprintf(
			'<li class="wp-block-galerio-related-items__item">%1$s<p class="wp-block-galerio-related-items__title"><a href="%2$s">%3$s</a></p>%4$s</li>',
			$image,
			esc_url( $url ),
			wp_kses_post( $title ),
			$detail ? '<p class="wp-block-galerio-related-items__detail">' . $detail . '</p>' : ''
		);
	}

	/**
	 * A card's detail line: a field of the related item, as plain text.
	 *
	 * @param WP_Post $item Related item.
	 * @param string  $key  Field key, or empty for none.
	 * @return string Escaped text, or empty.
	 */
	private static function detail( WP_Post $item, $key ) {
		if ( '' === $key ) {
			return '';
		}

		$field = galerio_fields()->resolve( $key, $item );

		if ( ! $field || ! $field->public || ! in_array( $field->type, self::DETAIL_TYPES, true ) ) {
			return '';
		}

		if ( 'relation' === $field->type ) {
			$related = galerio_get_field( $field->key, $item );
			return $related && 'publish' === get_post_status( $related ) ? esc_html( get_the_title( $related ) ) : '';
		}

		return esc_html( wp_strip_all_tags( (string) galerio_get_field( $field->key, $item, 'display' ) ) );
	}

	/**
	 * Put the site's type names into a text, when Galerio Core can.
	 *
	 * @param string $text Text with tokens such as "{Works}".
	 * @return string
	 */
	private static function expand( $text ) {
		return function_exists( 'galerio_expand_names' ) ? galerio_expand_names( $text ) : $text;
	}

	/**
	 * The attributes' defaults, from the block's metadata.
	 *
	 * @return array
	 */
	private static function defaults() {
		$defaults = array();
		$type     = \WP_Block_Type_Registry::get_instance()->get_registered( 'galerio/related-items' );

		foreach ( $type ? $type->attributes : array() as $name => $schema ) {
			if ( array_key_exists( 'default', $schema ) ) {
				$defaults[ $name ] = $schema['default'];
			}
		}

		return $defaults;
	}
}
