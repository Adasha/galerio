<?php
/**
 * Field registry.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use InvalidArgumentException;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every logical field and reads values through the active adapter.
 *
 * Use galerio_fields() to get the shared instance. Other plugins and child
 * themes add fields on the `galerio_register_fields` action.
 */
final class Registry {

	/**
	 * Objects that carry fields, and the post type of each.
	 *
	 * @var array<string, string>
	 */
	const OBJECTS = array(
		'work'       => 'galerio_work',
		'profile'    => 'galerio_profile',
		'collection' => 'galerio_collection',
		'article'    => 'galerio_article',
		'attachment' => 'attachment',
	);

	/**
	 * Registered fields by key.
	 *
	 * @var array<string, Field>
	 */
	private $fields = array();

	/**
	 * Storage adapter.
	 *
	 * @var Adapter
	 */
	private $adapter;

	/**
	 * Whether the default fields and the registration hook have run.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Field definitions waiting to be loaded.
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * Create a registry.
	 *
	 * @param Adapter $adapter  Storage adapter.
	 * @param array   $defaults Field definitions to load on first use, keyed by field key.
	 */
	public function __construct( Adapter $adapter, array $defaults = array() ) {
		$this->adapter  = $adapter;
		$this->defaults = $defaults;
	}

	/**
	 * The storage adapter.
	 *
	 * @return Adapter
	 */
	public function adapter() {
		return $this->adapter;
	}

	/**
	 * Register a field, replacing any field with the same key.
	 *
	 * @param string $key  Field key, "<object>.<name>".
	 * @param array  $args Definition. See Field.
	 * @return Field
	 * @throws InvalidArgumentException When the definition is invalid.
	 */
	public function register( $key, array $args ) {
		$this->load();

		$field = new Field( $key, $args );

		if ( ! isset( self::OBJECTS[ $field->object ] ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'Field "%s" belongs to unknown object "%s".', $key, $field->object ) ) );
		}

		$this->fields[ $key ] = $field;

		return $field;
	}

	/**
	 * Remove a field.
	 *
	 * @param string $key Field key.
	 */
	public function unregister( $key ) {
		$this->load();
		unset( $this->fields[ $key ] );
	}

	/**
	 * Get a field by key.
	 *
	 * @param string $key Field key.
	 * @return Field|null
	 */
	public function get( $key ) {
		$this->load();

		return isset( $this->fields[ $key ] ) ? $this->fields[ $key ] : null;
	}

	/**
	 * All fields, optionally limited to one object.
	 *
	 * @param string $object_name Object name, or an empty string for all.
	 * @return array<string, Field>
	 */
	public function all( $object_name = '' ) {
		$this->load();

		if ( ! $object_name ) {
			return $this->fields;
		}

		return array_filter(
			$this->fields,
			function ( Field $field ) use ( $object_name ) {
				return $field->object === $object_name;
			}
		);
	}

	/**
	 * The object a post belongs to.
	 *
	 * @param WP_Post $post Post.
	 * @return string Object name, or an empty string.
	 */
	public function object_of( WP_Post $post ) {
		$object = array_search( $post->post_type, self::OBJECTS, true );

		return false === $object ? '' : $object;
	}

	/**
	 * Find the field a key refers to for a given post.
	 *
	 * A bare name such as "year" is looked up on the post's own object.
	 *
	 * @param string  $key  Field key or bare name.
	 * @param WP_Post $post Post.
	 * @return Field|null Null when the field does not exist or belongs to another object.
	 */
	public function resolve( $key, WP_Post $post ) {
		$object = $this->object_of( $post );

		if ( false === strpos( $key, '.' ) ) {
			$key = $object . '.' . $key;
		}

		$field = $this->get( $key );

		return ( $field && $field->object === $object ) ? $field : null;
	}

	/**
	 * Read a field's raw value.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post ID.
	 * @return mixed
	 */
	public function value( Field $field, $object_id ) {
		if ( ! $this->adapter->is_available() ) {
			return $field->empty_value();
		}

		$value = $field->value_callback
			? call_user_func( $field->value_callback, (int) $object_id, $field )
			: $this->adapter->get( $field, $object_id );

		/**
		 * Filters a field's raw value.
		 *
		 * @param mixed $value     Normalised value.
		 * @param Field $field     Field.
		 * @param int   $object_id Post ID.
		 */
		return apply_filters( 'galerio_field_value', $field->normalise( $value ), $field, $object_id );
	}

	/**
	 * Sanitise and save a field's value. Fields worked out from others can't
	 * be saved.
	 *
	 * @param Field $field     Field.
	 * @param int   $object_id Post ID.
	 * @param mixed $value     Value.
	 * @return bool
	 */
	public function save( Field $field, $object_id, $value ) {
		if ( $field->value_callback ) {
			return false;
		}

		if ( $field->sanitize_callback ) {
			$value = call_user_func( $field->sanitize_callback, $value, $field );
		}

		$sanitizers = array(
			'text'     => 'sanitize_text_field',
			'textarea' => 'sanitize_textarea_field',
			'html'     => 'wp_kses_post',
			'url'      => 'esc_url_raw',
			'embed'    => 'esc_url_raw',
		);

		if ( 'link' === $field->type ) {
			$value = self::sanitize_links( $field, $value );
		} elseif ( isset( $sanitizers[ $field->type ] ) ) {
			$value = is_array( $value ) ? array_map( $sanitizers[ $field->type ], $value ) : call_user_func( $sanitizers[ $field->type ], (string) $value );
		}

		if ( ! $this->adapter->is_available() ) {
			return false;
		}

		return $this->adapter->set( $field, $object_id, $field->normalise( $value ) );
	}

	/**
	 * Sanitise a link field's value, dropping links whose URL isn't safe.
	 *
	 * @param Field $field Link field.
	 * @param mixed $value Value.
	 * @return array|null A list of links, a link, or null.
	 */
	private static function sanitize_links( Field $field, $value ) {
		$links = array();

		foreach ( $field->multiple ? $field->normalise( $value ) : array_filter( array( $field->normalise( $value ) ) ) as $link ) {
			$link['url']   = esc_url_raw( $link['url'] );
			$link['label'] = sanitize_text_field( $link['label'] );

			if ( '' !== $link['url'] ) {
				$links[] = $link;
			}
		}

		if ( $field->multiple ) {
			return $links;
		}

		return $links ? $links[0] : null;
	}

	/**
	 * Load the default fields, then let others register theirs.
	 */
	private function load() {
		if ( $this->loaded ) {
			return;
		}

		$this->loaded = true;

		foreach ( $this->defaults as $key => $args ) {
			$this->register( $key, $args );
		}

		$this->defaults = array();

		/**
		 * Fires once, when the field registry is first used.
		 *
		 * @param Registry $registry Registry to add fields to.
		 */
		do_action( 'galerio_register_fields', $this );
	}
}
