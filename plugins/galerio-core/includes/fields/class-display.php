<?php
/**
 * Display formatting for field values.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Turns raw field values into escaped HTML.
 */
final class Display {

	/**
	 * Render a field's value for output.
	 *
	 * @param Field $field     Field.
	 * @param mixed $raw       Normalised raw value.
	 * @param int   $object_id Post ID the value belongs to.
	 * @return string Escaped HTML, or an empty string.
	 */
	public static function render( Field $field, $raw, $object_id ) {
		if ( $field->display_callback ) {
			$html = (string) call_user_func( $field->display_callback, $raw, $field, $object_id );
		} elseif ( $field->multiple ) {
			$items = array();
			foreach ( $raw as $value ) {
				$items[] = self::render_one( $field, $value );
			}
			$html = implode( self::separator( $field ), array_filter( $items ) );
		} else {
			$html = self::render_one( $field, $raw );
		}

		/**
		 * Filters a field's display HTML.
		 *
		 * @param string $html      Escaped HTML.
		 * @param Field  $field     Field.
		 * @param mixed  $raw       Raw value.
		 * @param int    $object_id Post ID.
		 */
		return (string) apply_filters( 'galerio_field_display', $html, $field, $raw, $object_id );
	}

	/**
	 * Render one value.
	 *
	 * @param Field $field Field.
	 * @param mixed $value Single normalised value.
	 * @return string
	 */
	private static function render_one( Field $field, $value ) {
		if ( $field->empty_value() === $value && 'boolean' !== $field->type ) {
			return '';
		}

		switch ( $field->type ) {
			case 'textarea':
				return wpautop( esc_html( $value ) );

			case 'html':
				return wp_kses_post( wpautop( $value ) );

			case 'url':
				return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $value ), esc_html( self::url_label( $value ) ) );

			case 'embed':
				$embed = wp_oembed_get( $value );
				return $embed ? $embed : sprintf( '<a href="%1$s">%1$s</a>', esc_url( $value ) );

			case 'date':
				return esc_html( mysql2date( get_option( 'date_format' ), $value ) );

			case 'year':
				return esc_html( (string) $value );

			case 'boolean':
				return $value ? esc_html__( 'Yes', 'galerio-core' ) : esc_html__( 'No', 'galerio-core' );

			case 'image':
				return wp_get_attachment_image( $value, 'large' );

			case 'relation':
				if ( 'publish' !== get_post_status( $value ) ) {
					return '';
				}
				return sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_permalink( $value ) ), esc_html( get_the_title( $value ) ) );

			default:
				return esc_html( $value );
		}
	}

	/**
	 * Markup placed between the values of a multiple field.
	 *
	 * @param Field $field Field.
	 * @return string
	 */
	private static function separator( Field $field ) {
		return in_array( $field->type, array( 'relation', 'url', 'text', 'year' ), true ) ? ', ' : "\n";
	}

	/**
	 * A short label for a URL: its host and path, without the scheme.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function url_label( $url ) {
		return untrailingslashit( preg_replace( '#^https?://(www\.)?#i', '', $url ) );
	}
}
