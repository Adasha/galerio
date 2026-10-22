<?php
/**
 * The Field block.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the `galerio/field-display` block.
 *
 * Block bindings suit single values in core blocks. This block covers what
 * they can't: formatted text such as a biography, lists such as accolades or
 * related works, and sections that should disappear when empty. It shows the
 * same display HTML as galerio_get_field( $key, $post, 'display' ).
 */
final class Field_Block {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the block from its metadata.
	 */
	public static function register() {
		register_block_type(
			GALERIO_CORE_DIR . 'blocks/field-display',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Empty when the field is empty, private, switched off or unreadable.
	 */
	public static function render( $attributes, $content, $block ) {
		$post_id = isset( $block->context['postId'] ) ? $block->context['postId'] : get_the_ID();
		$post    = Bindings::readable_post( $post_id );

		if ( ! $post || empty( $attributes['key'] ) || ! Bindings::switched_on( $attributes['showIf'], $post ) ) {
			return '';
		}

		$field = galerio_fields()->resolve( $attributes['key'], $post );

		if ( ! $field || ! $field->public ) {
			return '';
		}

		$raw = galerio_fields()->value( $field, $post->ID );

		if ( $field->empty_value() === $raw ) {
			return '';
		}

		if ( 'list' === $attributes['layout'] ) {
			$items = Display::items( $field, $raw );
			$html  = $items ? '<ul class="wp-block-galerio-field-display__list"><li>' . implode( '</li><li>', $items ) . '</li></ul>' : '';
		} else {
			$html = Display::render( $field, $raw, $post->ID );
		}

		if ( '' === trim( $html ) ) {
			return '';
		}

		$label = '' !== $attributes['label']
			? sprintf( '<h4 class="wp-block-galerio-field-display__label">%s</h4>', esc_html( $attributes['label'] ) )
			: '';

		return sprintf(
			'<div %1$s>%2$s<div class="wp-block-galerio-field-display__value">%3$s</div></div>',
			get_block_wrapper_attributes( array( 'class' => 'is-type-' . $field->type ) ),
			$label,
			$html
		);
	}
}
