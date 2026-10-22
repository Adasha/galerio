<?php
/**
 * A single logical field.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Describes one field, independently of where it is stored.
 *
 * Fields are addressed by key, in the form "<object>.<name>" (for example
 * "work.year"). The storage adapter reads the field's `source` name, which
 * defaults to its name, so logical names can differ from storage names.
 */
final class Field {

	/**
	 * Field types and whether each one holds post IDs.
	 *
	 * @var array<string, bool>
	 */
	const TYPES = array(
		'text'     => false,
		'textarea' => false,
		'html'     => false,
		'url'      => false,
		'embed'    => false,
		'date'     => false,
		'year'     => false,
		'boolean'  => false,
		'link'     => false,
		'image'    => true,
		'relation' => true,
	);

	/**
	 * Key, e.g. "work.year".
	 *
	 * @var string
	 */
	public $key;

	/**
	 * Object the field belongs to: work, profile, collection, article or attachment.
	 *
	 * @var string
	 */
	public $object;

	/**
	 * Name within the object, e.g. "year".
	 *
	 * @var string
	 */
	public $name;

	/**
	 * One of the keys of self::TYPES.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Human-readable label.
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Name of the field in the storage backend.
	 *
	 * @var string
	 */
	public $source;

	/**
	 * Whether the field holds a list of values.
	 *
	 * @var bool
	 */
	public $multiple;

	/**
	 * For relations, the object related to.
	 *
	 * @var string
	 */
	public $target;

	/**
	 * Whether the field is readable through the REST API and block bindings.
	 *
	 * @var bool
	 */
	public $public;

	/**
	 * Deprecation notice, or an empty string.
	 *
	 * @var string
	 */
	public $deprecated;

	/**
	 * Optional callback to render the display value: fn( $raw, Field $field, int $object_id ): string.
	 *
	 * @var callable|null
	 */
	public $display_callback;

	/**
	 * Optional callback to sanitise a value before it is saved: fn( $value, Field $field ): mixed.
	 *
	 * @var callable|null
	 */
	public $sanitize_callback;

	/**
	 * Build a field from its definition.
	 *
	 * @param string $key  Field key, "<object>.<name>".
	 * @param array  $args Definition. See the properties of this class.
	 * @throws InvalidArgumentException When the key or type is invalid.
	 */
	public function __construct( $key, array $args ) {
		if ( ! preg_match( '/^([a-z_]+)\.([a-z0-9_]+)$/', $key, $parts ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'Invalid field key "%s".', $key ) ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'type'              => 'text',
				'label'             => $parts[2],
				'source'            => $parts[2],
				'multiple'          => false,
				'target'            => '',
				'public'            => true,
				'deprecated'        => '',
				'display_callback'  => null,
				'sanitize_callback' => null,
			)
		);

		if ( ! isset( self::TYPES[ $args['type'] ] ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'Field "%s" has unknown type "%s".', $key, $args['type'] ) ) );
		}

		if ( 'relation' === $args['type'] && ! $args['target'] ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'Relation field "%s" needs a target.', $key ) ) );
		}

		$this->key               = $key;
		$this->object            = $parts[1];
		$this->name              = $parts[2];
		$this->type              = $args['type'];
		$this->label             = $args['label'];
		$this->source            = $args['source'];
		$this->multiple          = (bool) $args['multiple'];
		$this->target            = $args['target'];
		$this->public            = (bool) $args['public'];
		$this->deprecated        = $args['deprecated'];
		$this->display_callback  = $args['display_callback'];
		$this->sanitize_callback = $args['sanitize_callback'];
	}

	/**
	 * Whether the field's values are post IDs (attachments or related posts).
	 *
	 * @return bool
	 */
	public function holds_ids() {
		return self::TYPES[ $this->type ];
	}

	/**
	 * The value an empty field has.
	 *
	 * @return mixed
	 */
	public function empty_value() {
		if ( $this->multiple ) {
			return array();
		}

		switch ( $this->type ) {
			case 'boolean':
				return false;
			case 'image':
			case 'relation':
				return 0;
			case 'year':
			case 'link':
				return null;
			default:
				return '';
		}
	}

	/**
	 * Coerce a value to the field's canonical raw form.
	 *
	 * Text types become strings, dates "Y-m-d", years integers, booleans
	 * booleans, IDs positive integers and links arrays of url, label and
	 * offline. Multiple fields become lists.
	 *
	 * @param mixed $value Value to normalise.
	 * @return mixed
	 */
	public function normalise( $value ) {
		if ( 'link' === $this->type ) {
			$value = self::link_list( $value );
		}

		if ( $this->multiple ) {
			$values = is_array( $value ) ? $value : ( ( null === $value || '' === $value || false === $value ) ? array() : array( $value ) );
			$values = array_map( array( $this, 'normalise_one' ), $values );

			return array_values(
				array_filter(
					$values,
					function ( $item ) {
						return null !== $item && '' !== $item && 0 !== $item;
					}
				)
			);
		}

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		$value = $this->normalise_one( $value );

		return null === $value ? $this->empty_value() : $value;
	}

	/**
	 * Coerce a single value.
	 *
	 * @param mixed $value Value.
	 * @return mixed Null when the value is empty or invalid.
	 */
	private function normalise_one( $value ) {
		switch ( $this->type ) {
			case 'boolean':
				return (bool) $value && '0' !== $value;

			case 'image':
			case 'relation':
				if ( is_array( $value ) && isset( $value['ID'] ) ) {
					$value = $value['ID'];
				} elseif ( $value instanceof \WP_Post ) {
					$value = $value->ID;
				}
				$id = absint( $value );
				return $id ? $id : null;

			case 'year':
				if ( null === $value || '' === $value || false === $value ) {
					return null;
				}
				$year = (int) substr( (string) $value, 0, 4 );
				return $year > 0 ? $year : null;

			case 'link':
				if ( ! is_array( $value ) || ! isset( $value['url'] ) || ! is_scalar( $value['url'] ) || '' === trim( (string) $value['url'] ) ) {
					return null;
				}
				return array(
					'url'     => trim( (string) $value['url'] ),
					'label'   => isset( $value['label'] ) && is_scalar( $value['label'] ) ? (string) $value['label'] : '',
					'offline' => ! empty( $value['offline'] ),
				);

			case 'date':
				if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) || 0 === strpos( $value, '0000' ) ) {
					return null;
				}
				return substr( $value, 0, 10 );

			default:
				if ( ! is_scalar( $value ) ) {
					return null;
				}
				$value = (string) $value;
				return '' === $value ? null : $value;
		}
	}

	/**
	 * Turn a stored link value into a list of link arrays.
	 *
	 * Accepts JSON as stored by Pods, a single link, a list of links, or a
	 * bare URL.
	 *
	 * @param mixed $value Value.
	 * @return array[]
	 */
	private static function link_list( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array( 'url' => $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		return isset( $value['url'] ) ? array( $value ) : array_values( $value );
	}
}
