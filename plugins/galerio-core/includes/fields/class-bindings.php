<?php
/**
 * Block bindings source for Galerio fields.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `galerio/field` block bindings source.
 *
 * Core blocks bind to a field with `{ "source": "galerio/field", "args": { "key": "work.year" } }`.
 * The value depends on the attribute it fills: an image field gives an Image
 * block its URL, alt text and caption, a relation gives a Button its link, and
 * text attributes get the field's display HTML.
 */
final class Bindings {

	/**
	 * Script handle for the editor integration.
	 *
	 * @var string
	 */
	const SCRIPT = 'galerio-field-bindings';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Register the source.
	 */
	public static function register() {
		register_block_bindings_source(
			'galerio/field',
			array(
				'label'              => __( 'Galerio field', 'galerio-core' ),
				'get_value_callback' => array( __CLASS__, 'get_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Resolve a bound attribute's value.
	 *
	 * @param array    $args      Binding arguments; `key` names the field.
	 * @param WP_Block $block     Block being rendered.
	 * @param string   $attribute Attribute being filled.
	 * @return mixed Null leaves the attribute as saved in the block.
	 */
	public static function get_value( array $args, $block, $attribute ) {
		if ( empty( $args['key'] ) || ! is_string( $args['key'] ) || empty( $block->context['postId'] ) ) {
			return null;
		}

		$post = get_post( $block->context['postId'] );

		if ( ! $post || post_password_required( $post ) ) {
			return null;
		}

		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post->ID ) ) {
			return null;
		}

		$field = galerio_fields()->resolve( $args['key'], $post );

		if ( ! $field || ! $field->public ) {
			return null;
		}

		$raw = galerio_fields()->value( $field, $post->ID );

		if ( $field->empty_value() === $raw ) {
			return null;
		}

		$value = self::value_for( $attribute, $field, $raw, $post->ID );

		return ( null === $value || '' === $value ) ? null : $value;
	}

	/**
	 * The value a field supplies to a particular attribute.
	 *
	 * @param string $attribute Attribute name.
	 * @param Field  $field     Field.
	 * @param mixed  $raw       Raw, non-empty value.
	 * @param int    $post_id   Post the value belongs to.
	 * @return mixed
	 */
	private static function value_for( $attribute, Field $field, $raw, $post_id ) {
		$first = $field->multiple ? reset( $raw ) : $raw;

		switch ( $attribute ) {
			case 'id':
				return 'image' === $field->type ? $first : null;

			case 'url':
				if ( 'image' === $field->type ) {
					return wp_get_attachment_image_url( $first, 'full' );
				}
				if ( 'relation' === $field->type ) {
					return 'publish' === get_post_status( $first ) ? get_permalink( $first ) : null;
				}
				if ( 'link' === $field->type ) {
					return $first['offline'] ? null : esc_url_raw( $first['url'] );
				}
				return in_array( $field->type, array( 'url', 'embed' ), true ) ? esc_url_raw( $first ) : null;

			case 'alt':
				return 'image' === $field->type
					? get_post_meta( $first, '_wp_attachment_image_alt', true )
					: self::plain_text( $field, $raw, $post_id );

			case 'title':
				return 'image' === $field->type ? get_the_title( $first ) : self::plain_text( $field, $raw, $post_id );

			case 'caption':
				return 'image' === $field->type ? wp_get_attachment_caption( $first ) : Display::render( $field, $raw, $post_id );

			case 'datetime':
				if ( 'date' === $field->type ) {
					return $first;
				}
				return 'year' === $field->type ? sprintf( '%04d-01-01', $first ) : null;

			case 'text':
				// Button text sits inside a link, so it must not contain links itself.
				if ( 'link' === $field->type ) {
					return esc_html( Display::link_text( $first ) );
				}
				return esc_html( self::plain_text( $field, $raw, $post_id ) );

			case 'linkTarget':
			case 'rel':
				return null;

			default:
				return Display::render( $field, $raw, $post_id );
		}
	}

	/**
	 * A field's display value without markup.
	 *
	 * @param Field $field   Field.
	 * @param mixed $raw     Raw value.
	 * @param int   $post_id Post ID.
	 * @return string
	 */
	private static function plain_text( Field $field, $raw, $post_id ) {
		return trim( html_entity_decode( wp_strip_all_tags( Display::render( $field, $raw, $post_id ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	}

	/**
	 * Load the editor integration: field picker and previews.
	 */
	public static function enqueue() {
		wp_enqueue_script(
			self::SCRIPT,
			GALERIO_CORE_URL . 'assets/js/field-bindings.js',
			array( 'wp-blocks', 'wp-core-data', 'wp-data', 'wp-date', 'wp-html-entities', 'wp-i18n' ),
			GALERIO_CORE_VERSION,
			true
		);

		wp_set_script_translations( self::SCRIPT, 'galerio-core' );

		wp_add_inline_script(
			self::SCRIPT,
			'window.galerioFieldBindings = ' . wp_json_encode( self::editor_data() ) . ';',
			'before'
		);
	}

	/**
	 * Field definitions for the editor.
	 *
	 * @return array
	 */
	private static function editor_data() {
		$fields = array();

		foreach ( galerio_fields()->all() as $field ) {
			if ( ! $field->public ) {
				continue;
			}

			$fields[ $field->object ][] = array(
				'key'      => $field->key,
				'name'     => $field->name,
				'label'    => $field->label,
				'type'     => $field->type,
				'multiple' => $field->multiple,
				'target'   => $field->target ? Registry::OBJECTS[ $field->target ] : '',
			);
		}

		return array(
			'objects'  => Registry::OBJECTS,
			'fields'   => $fields,
			/** This filter is documented in includes/fields/class-display.php */
			'services' => apply_filters( 'galerio_link_services', Display::SERVICES ),
		);
	}
}
