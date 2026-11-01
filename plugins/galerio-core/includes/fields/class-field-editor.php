<?php
/**
 * The editor's native fields.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use Galerio\Core\Types\Content_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Shows an item's fields in the pane under the editor, on sites that have moved
 * to native data. A single meta box holds the pane's contents, and the fields
 * themselves are saved with the item through its `galerio_fields` REST property.
 */
final class Field_Editor {

	/**
	 * ID of the meta box.
	 */
	const BOX = 'galerio-fields';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		if ( ! Content_Types::active() ) {
			return;
		}

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'assets' ) );
	}

	/**
	 * The object name of a post type that has fields in the editor.
	 *
	 * @param string $post_type Post type.
	 * @return string Object name, or empty.
	 */
	private static function object_of( $post_type ) {
		$object_name = array_search( $post_type, Registry::OBJECTS, true );

		return false === $object_name || 'attachment' === $object_name ? '' : $object_name;
	}

	/**
	 * Add the meta box to Galerio's post types.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add_box( $post_type ) {
		if ( ! self::object_of( $post_type ) ) {
			return;
		}

		add_meta_box(
			self::BOX,
			__( 'Details', 'galerio-core' ),
			array( __CLASS__, 'render_box' ),
			$post_type,
			'normal',
			'high',
			array( '__block_editor_compatible_meta_box' => true )
		);
	}

	/**
	 * Print the element the fields are drawn in.
	 */
	public static function render_box() {
		echo '<div id="galerio-field-editor"></div>';
	}

	/**
	 * Which items the current user can pick in a relationship: unpublished
	 * ones too, if they can edit that type.
	 *
	 * @param string $post_type Post type.
	 * @return string[] Post statuses.
	 */
	private static function statuses( $post_type ) {
		$type = get_post_type_object( $post_type );

		return $type && current_user_can( $type->cap->edit_posts )
			? array( 'publish', 'future', 'draft', 'pending', 'private' )
			: array( 'publish' );
	}

	/**
	 * The groups and fields a user can edit on an object.
	 *
	 * @param string $object_name Object name.
	 * @return array[] Groups, each with name, label and fields.
	 */
	public static function groups( $object_name ) {
		$groups = array();

		foreach ( galerio_fields()->groups( $object_name ) as $name => $label ) {
			$fields = array();

			foreach ( galerio_fields()->all( $object_name ) as $field ) {
				if ( $name === $field->group && ! $field->value_callback && $field->can_edit() ) {
					$fields[] = array(
						'name'        => $field->name,
						'label'       => $field->label,
						'description' => $field->description,
						'type'        => $field->type,
						'unit'        => $field->unit,
						'multiple'    => $field->multiple,
						'target'      => $field->target ? Registry::OBJECTS[ $field->target ] : '',
						'statuses'    => $field->target ? self::statuses( Registry::OBJECTS[ $field->target ] ) : array(),
						'options'     => $field->options,
						'asksOffline' => $field->asks_offline,
					);
				}
			}

			if ( $fields ) {
				$groups[] = array(
					'name'   => $name,
					'label'  => $label,
					'fields' => $fields,
				);
			}
		}

		return $groups;
	}

	/**
	 * Load the editor's script on an item's edit screen.
	 */
	public static function assets() {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$object_name = $screen ? self::object_of( $screen->post_type ) : '';
		$asset_file  = GALERIO_CORE_DIR . 'build/field-editor.asset.php';

		if ( ! $object_name || ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Formatted text uses WordPress's classic editor, and images its media picker.
		wp_enqueue_editor();
		wp_enqueue_media();

		wp_enqueue_script( 'galerio-field-editor', GALERIO_CORE_URL . 'build/field-editor.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'galerio-field-editor', GALERIO_CORE_URL . 'build/style-field-editor.css', array( 'wp-components' ), $asset['version'] );
		wp_set_script_translations( 'galerio-field-editor', 'galerio-core', GALERIO_CORE_DIR . 'languages' );
		wp_add_inline_script( 'galerio-field-editor', 'window.galerioFieldEditor = ' . wp_json_encode( array( 'groups' => self::groups( $object_name ) ) ) . ';', 'before' );
	}
}
