<?php
/**
 * Galerio's fields in an image's details.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Media;

use Galerio\Core\Types\Content_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Shows a file's fields in its details, in the Media Library, the media pop-up
 * and its own edit screen, on sites that have moved to native data.
 */
final class Media_Fields {

	/**
	 * Prefix of the fields' names in the form.
	 */
	const PREFIX = 'galerio_field_';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		if ( ! Content_Types::active() ) {
			return;
		}

		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * The fields this user can edit on files.
	 *
	 * @return \Galerio\Core\Fields\Field[]
	 */
	private static function editable() {
		return array_filter(
			galerio_fields()->all( 'attachment' ),
			function ( $field ) {
				return $field->group && ! $field->value_callback && $field->can_edit();
			}
		);
	}

	/**
	 * Add the fields to a file's details.
	 *
	 * @param array    $fields Fields.
	 * @param \WP_Post $post   Attachment.
	 * @return array
	 */
	public static function fields( $fields, $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $fields;
		}

		foreach ( self::editable() as $field ) {
			$fields[ self::PREFIX . $field->name ] = array(
				'label' => $field->label,
				'input' => 'text',
				'value' => (string) galerio_fields()->value( $field, $post->ID ),
				'helps' => $field->description,
			);
		}

		return $fields;
	}

	/**
	 * Save the fields sent with a file's details.
	 *
	 * @param array $post       Attachment data.
	 * @param array $attachment Fields sent.
	 * @return array
	 */
	public static function save( $post, $attachment ) {
		if ( empty( $post['ID'] ) || ! current_user_can( 'edit_post', $post['ID'] ) ) {
			return $post;
		}

		foreach ( self::editable() as $field ) {
			if ( isset( $attachment[ self::PREFIX . $field->name ] ) ) {
				galerio_fields()->save( $field, $post['ID'], $attachment[ self::PREFIX . $field->name ] );
			}
		}

		return $post;
	}
}
