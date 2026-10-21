<?php
/**
 * REST exposure of fields.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a read-only `galerio_fields` property to each Galerio post type's REST
 * responses, holding the raw values of its public fields. The block editor
 * reads it for binding previews.
 */
final class Rest {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the REST field on every object's post type.
	 */
	public static function register() {
		foreach ( Registry::OBJECTS as $object => $post_type ) {
			$fields = array_filter(
				galerio_fields()->all( $object ),
				function ( Field $field ) {
					return $field->public;
				}
			);

			if ( ! $fields ) {
				continue;
			}

			register_rest_field(
				$post_type,
				'galerio_fields',
				array(
					'get_callback' => function ( $data ) use ( $fields ) {
						$values = array();
						foreach ( $fields as $field ) {
							$values[ $field->name ] = galerio_fields()->value( $field, $data['id'] );
						}
						return (object) $values;
					},
					'schema'       => array(
						'description' => __( 'Galerio field values.', 'galerio-core' ),
						'type'        => 'object',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
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
			case 'image':
			case 'relation':
				$type = 'integer';
				break;
			default:
				$type = 'string';
		}

		$schema = $field->multiple
			? array(
				'type'  => 'array',
				'items' => array( 'type' => $type ),
			)
			: array( 'type' => $type );

		$schema['description'] = $field->label;

		return $schema;
	}
}
