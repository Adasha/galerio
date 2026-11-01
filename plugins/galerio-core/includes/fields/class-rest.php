<?php
/**
 * REST exposure of fields.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a `galerio_fields` property to each Galerio post type's REST
 * responses, holding the raw values of its public fields. The block editor
 * reads it for binding previews. Sending the property back with a create or
 * update request saves the fields it names, through the field registry, so it
 * works whichever storage the fields use. Fields worked out from others can't
 * be saved.
 */
final class Rest {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );

		// Check the fields before the post is written, so a bad request changes nothing.
		foreach ( Registry::OBJECTS as $object => $post_type ) {
			add_filter(
				"rest_pre_insert_{$post_type}",
				function ( $prepared, $request ) use ( $object ) {
					$error = self::validate( self::public_fields( $object ), $request['galerio_fields'] ?? array() );
					return $error ? $error : $prepared;
				},
				10,
				2
			);
		}
	}

	/**
	 * An object's public fields.
	 *
	 * @param string $object_name Object name, e.g. "work".
	 * @return Field[]
	 */
	private static function public_fields( $object_name ) {
		return array_filter(
			galerio_fields()->all( $object_name ),
			function ( Field $field ) {
				return $field->public;
			}
		);
	}

	/**
	 * Register the REST field on every object's post type.
	 */
	public static function register() {
		foreach ( Registry::OBJECTS as $object => $post_type ) {
			$fields = self::public_fields( $object );

			if ( ! $fields ) {
				continue;
			}

			register_rest_field(
				$post_type,
				'galerio_fields',
				array(
					'get_callback'    => function ( $data ) use ( $fields ) {
						$values = array();
						foreach ( $fields as $field ) {
							$values[ $field->name ] = self::visible_value( $field, galerio_fields()->value( $field, $data['id'] ), $data['id'] );
						}
						return (object) $values;
					},
					'update_callback' => function ( $values, $post ) use ( $fields ) {
						return self::save( $fields, $values, $post );
					},
					'schema'          => array(
						'description' => __( 'Galerio field values.', 'galerio-core' ),
						'type'        => 'object',
						'context'     => array( 'view', 'edit' ),
						'properties'  => array_combine(
							wp_list_pluck( $fields, 'name' ),
							array_map( array( __CLASS__, 'schema' ), array_values( $fields ) )
						),
					),
				)
			);
		}
	}

	/**
	 * A field's value as the current user may see it. Links to unpublished
	 * items are for people who can edit the item that holds them.
	 *
	 * @param Field $field Field.
	 * @param mixed $value Normalised value.
	 * @param int   $id    Item holding the field.
	 * @return mixed
	 */
	public static function visible_value( Field $field, $value, $id ) {
		if ( ! $field->holds_ids() || current_user_can( 'edit_post', $id ) ) {
			return $value;
		}

		$visible = array_values(
			array_filter(
				(array) $value,
				function ( $target ) {
					return in_array( get_post_status( $target ), array( 'publish', 'inherit' ), true );
				}
			)
		);

		if ( $field->multiple ) {
			return $visible;
		}

		return $visible ? $visible[0] : $field->empty_value();
	}

	/**
	 * Check that a request names only fields that exist and can be saved.
	 *
	 * @param Field[] $fields Public fields of the post's object.
	 * @param mixed   $values Field values by name.
	 * @return \WP_Error|null Null when the request is fine.
	 */
	private static function validate( array $fields, $values ) {
		$by_name = array();
		foreach ( $fields as $field ) {
			$by_name[ $field->name ] = $field;
		}

		foreach ( array_keys( (array) $values ) as $name ) {
			if ( ! isset( $by_name[ $name ] ) || $by_name[ $name ]->value_callback ) {
				return new \WP_Error(
					'galerio_unsaveable_field',
					/* translators: %s: field name. */
					sprintf( __( 'The field %s doesn\'t exist or can\'t be saved.', 'galerio-core' ), $name ),
					array( 'status' => 400 )
				);
			}
		}

		return null;
	}

	/**
	 * Save the fields named in a REST request.
	 *
	 * @param Field[]  $fields Public fields of the post's object.
	 * @param mixed    $values Field values by name, already validated.
	 * @param \WP_Post $post   Post being saved.
	 * @return true|\WP_Error
	 */
	private static function save( array $fields, $values, $post ) {
		$by_name = array();
		foreach ( $fields as $field ) {
			$by_name[ $field->name ] = $field;
		}

		foreach ( (array) $values as $name => $value ) {
			if ( ! isset( $by_name[ $name ] ) || ! galerio_fields()->save( $by_name[ $name ], $post->ID, $value ) ) {
				return new \WP_Error(
					'galerio_field_not_saved',
					/* translators: %s: field name. */
					sprintf( __( 'The field %s could not be saved.', 'galerio-core' ), $name ),
					array( 'status' => 500 )
				);
			}
		}

		return true;
	}

	/**
	 * JSON schema for one field.
	 *
	 * @param Field $field Field.
	 * @return array
	 */
	private static function schema( Field $field ) {
		switch ( $field->type ) {
			case 'boolean':
				$type = 'boolean';
				break;
			case 'year':
				$type = array( 'integer', 'null' );
				break;
			case 'number':
				$type = array( 'number', 'null' );
				break;
			case 'image':
			case 'relation':
				$type = 'integer';
				break;
			default:
				$type = 'string';
		}

		if ( 'choice' === $field->type ) {
			$type = array( 'string', 'null' );
		}

		$item = 'link' === $field->type
			? array(
				'type'       => array( 'object', 'null' ),
				'properties' => array(
					'url'     => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'label'   => array( 'type' => 'string' ),
					'offline' => array( 'type' => 'boolean' ),
				),
			)
			: array( 'type' => $type );

		if ( 'choice' === $field->type ) {
			$item['enum'] = array_merge( array_map( 'strval', array_keys( $field->options ) ), array( null ) );
		}

		$schema = $field->multiple
			? array(
				'type'  => 'array',
				'items' => $item,
			)
			: $item;

		$schema['description'] = $field->label;

		if ( $field->value_callback ) {
			$schema['readonly'] = true;
		}

		return $schema;
	}
}
