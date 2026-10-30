<?php
/**
 * Small changes to the edit screens of Galerio's content types.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Names the editor's meta box pane after the content type ("Work settings",
 * using the site's name for works), and keeps the accolades fields short.
 */
final class Edit_Screen {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'pane_label' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/**
	 * The current screen's post type when it is one of Galerio's.
	 *
	 * @return string Post type, or empty.
	 */
	private static function post_type() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'post' !== $screen->base || 'attachment' === $screen->post_type ) {
			return '';
		}

		return in_array( $screen->post_type, Registry::OBJECTS, true ) ? $screen->post_type : '';
	}

	/**
	 * Rename the pane under the editor that holds the meta boxes, which
	 * WordPress calls "Meta Boxes", to "Work settings" and the like.
	 */
	public static function pane_label() {
		$post_type = self::post_type();
		$object    = $post_type ? get_post_type_object( $post_type ) : null;

		if ( ! $object ) {
			return;
		}

		/* translators: %s: the site's name for one item of a content type, such as "Work". */
		$label = sprintf( __( '%s settings', 'galerio-core' ), $object->labels->singular_name );

		// In the head, so the filter is in place before the editor first draws the pane.
		wp_register_script( 'galerio-edit-screen', false, array( 'wp-hooks', 'wp-i18n' ), GALERIO_CORE_VERSION, false );
		wp_enqueue_script( 'galerio-edit-screen' );
		wp_add_inline_script(
			'galerio-edit-screen',
			sprintf(
				'wp.hooks.addFilter( "i18n.gettext_default", "galerio/meta-boxes-label", function ( translation, text ) { return "Meta Boxes" === text ? %s : translation; } );',
				wp_json_encode( $label )
			)
		);
	}

	/**
	 * Styles for the meta boxes: accolades start as one line, as each is
	 * usually short, and grow as they're typed in.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function styles( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! self::post_type() ) {
			return;
		}

		wp_register_style( 'galerio-edit-screen', false, array(), GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-edit-screen' );
		wp_add_inline_style(
			'galerio-edit-screen',
			'textarea.pods-form-ui-field.pods-form-ui-field-type-paragraph[name^="pods_meta_accolades"] {
				min-height: 0;
				height: auto;
				field-sizing: content;
				resize: vertical;
			}'
		);
	}
}
