<?php
/**
 * Block bindings source for Galerio fields.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use Galerio\Core\Aliases\Names;
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
		// Registered early so other editor scripts, such as the Field block's, can depend on it.
		wp_register_script(
			self::SCRIPT,
			GALERIO_CORE_URL . 'assets/js/field-bindings.js',
			array( 'wp-blocks', 'wp-core-data', 'wp-data', 'wp-date', 'wp-html-entities', 'wp-i18n' ),
			GALERIO_CORE_VERSION,
			true
		);
		wp_set_script_translations( self::SCRIPT, 'galerio-core' );

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
		$post = self::readable_post( isset( $block->context['postId'] ) ? $block->context['postId'] : 0 );

		if ( ! $post || empty( $args['key'] ) || ! is_string( $args['key'] ) ) {
			return null;
		}

		// A show/hide switch that is off empties the block.
		if ( ! self::switched_on( isset( $args['if'] ) ? $args['if'] : '', $post ) ) {
			return '';
		}

		$field = galerio_fields()->resolve( $args['key'], $post );

		if ( ! $field || ! $field->public ) {
			return null;
		}

		$raw = galerio_fields()->value( $field, $post->ID );

		// Leaving out the item the page is about can leave nothing, which empties the block.
		if ( ! empty( $args['omitCurrent'] ) && $field->empty_value() !== $raw ) {
			$raw = self::omit_current( $field, $raw, $post );
			if ( $field->empty_value() === $raw ) {
				return '';
			}
		}

		$value = $field->empty_value() === $raw ? null : self::value_for( $attribute, $field, $raw, $post->ID );

		if ( null === $value || '' === $value ) {
			$value = self::fallback( $args, $attribute, $post, $field );
		}

		if ( null === $value || '' === $value ) {
			return null;
		}

		return self::decorate( $args, $attribute, $value, $post );
	}

	/**
	 * The post a block shows fields of, if the visitor may see it.
	 *
	 * @param int $post_id Post ID from the block's context.
	 * @return \WP_Post|null
	 */
	public static function readable_post( $post_id ) {
		$post = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || post_password_required( $post ) ) {
			return null;
		}

		if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post->ID ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Whether an optional show/hide switch is on.
	 *
	 * @param string   $key  Key of a boolean field, such as "show_lifespan", or empty for none.
	 * @param \WP_Post $post Post.
	 * @return bool True when there is no switch, or it is on.
	 */
	public static function switched_on( $key, $post ) {
		if ( ! $key || ! is_string( $key ) ) {
			return true;
		}

		$field = galerio_fields()->resolve( $key, $post );

		return $field && 'boolean' === $field->type && true === galerio_fields()->value( $field, $post->ID );
	}

	/**
	 * A relation's value without the item the page is about, for
	 * `"omitCurrent": true`: a work card in a list on its creator's profile
	 * needn't name that creator again.
	 *
	 * Only applies in a list, where the block shows another post than the page's.
	 *
	 * @param Field    $field Field.
	 * @param mixed    $raw   Raw, non-empty value.
	 * @param \WP_Post $post  Post the block shows.
	 * @return mixed The value, possibly empty.
	 */
	private static function omit_current( Field $field, $raw, $post ) {
		$current = is_singular() ? get_queried_object_id() : 0;

		if ( 'relation' !== $field->type || ! $current || $current === $post->ID ) {
			return $raw;
		}

		if ( ! $field->multiple ) {
			return (int) $raw === $current ? $field->empty_value() : $raw;
		}

		return array_values(
			array_filter(
				$raw,
				function ( $id ) use ( $current ) {
					return (int) $id !== $current;
				}
			)
		);
	}

	/**
	 * The value to use when the field is empty:
	 *
	 * - `"fallback": "title"` uses the post title, e.g. for a work without a
	 *   definitive title.
	 * - `"fallback": "featured_image"` uses an image field's post's featured
	 *   image, e.g. for a work without a definitive image.
	 *
	 * There is deliberately no excerpt fallback: an excerpt summarises the
	 * main text, so it can't stand in for a field such as a standfirst.
	 *
	 * @param array    $args      Binding arguments.
	 * @param string   $attribute Attribute being filled.
	 * @param \WP_Post $post      Post.
	 * @param Field    $field     Field.
	 * @return mixed Null for none.
	 */
	private static function fallback( array $args, $attribute, $post, Field $field ) {
		$fallback = isset( $args['fallback'] ) ? $args['fallback'] : '';

		if ( 'featured_image' === $fallback && 'image' === $field->type ) {
			// The featured image itself, not Featured_Image's fallback to this field.
			$image = (int) get_post_meta( $post->ID, '_thumbnail_id', true );

			if ( ! $image || 'attachment' !== get_post_type( $image ) ) {
				return null;
			}

			return self::value_for( $attribute, $field, $field->multiple ? array( $image ) : $image, $post->ID );
		}

		if ( 'title' !== $fallback ) {
			return null;
		}

		$title = get_the_title( $post );

		return in_array( $attribute, array( 'alt', 'title' ), true ) ? wp_strip_all_tags( $title ) : wp_kses_post( $title );
	}

	/**
	 * Add the optional prefix, suffix and link to a text value.
	 *
	 * `"prefix"` and `"suffix"` are plain text placed around the value, such as
	 * "b. " before a year. Name tokens such as "{Collection}" in them become
	 * the site's names for its types. `"link": true` links the value to the post.
	 *
	 * @param array    $args      Binding arguments.
	 * @param string   $attribute Attribute being filled.
	 * @param mixed    $value     Value.
	 * @param \WP_Post $post      Post.
	 * @return mixed
	 */
	private static function decorate( array $args, $attribute, $value, $post ) {
		if ( ! in_array( $attribute, array( 'content', 'caption' ), true ) || ! is_string( $value ) ) {
			return $value;
		}

		if ( ! empty( $args['link'] ) && false === stripos( $value, '<a ' ) ) {
			$value = sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_permalink( $post ) ), $value );
		}

		$prefix = isset( $args['prefix'] ) && is_string( $args['prefix'] ) ? esc_html( Names::expand( $args['prefix'] ) ) : '';
		$suffix = isset( $args['suffix'] ) && is_string( $args['suffix'] ) ? esc_html( Names::expand( $args['suffix'] ) ) : '';

		return $prefix . $value . $suffix;
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
		wp_enqueue_script( self::SCRIPT );

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
			'names'    => Names::tokens(),
			/** This filter is documented in includes/fields/class-display.php */
			'services' => apply_filters( 'galerio_link_services', Display::SERVICES ),
		);
	}
}
