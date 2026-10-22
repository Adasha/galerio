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
			$items = self::items( $field, $raw );
			$html  = 'link' === $field->type && $items
				? '<ul class="galerio-links"><li>' . implode( '</li><li>', $items ) . '</li></ul>'
				: implode( self::separator( $field ), $items );
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
	 * Render each of a multiple field's values separately, for list layouts.
	 *
	 * @param Field $field Field.
	 * @param mixed $raw   Normalised raw value; a single value gives one item.
	 * @return string[] Escaped HTML for each non-empty value.
	 */
	public static function items( Field $field, $raw ) {
		$items = array();

		foreach ( $field->multiple ? $raw : array( $raw ) as $value ) {
			$items[] = self::render_one( $field, $value );
		}

		return array_values( array_filter( $items ) );
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

			case 'link':
				return self::link( $value );

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
	 * Services whose links are shown by name when they have no display text,
	 * keyed by host without "www.".
	 *
	 * @var array<string, string>
	 */
	const SERVICES = array(
		'axisweb.org'   => 'Axisweb',
		'bsky.app'      => 'Bluesky',
		'facebook.com'  => 'Facebook',
		'instagram.com' => 'Instagram',
		'linkedin.com'  => 'LinkedIn',
		'threads.net'   => 'Threads',
		'tiktok.com'    => 'TikTok',
		'twitter.com'   => 'X',
		'vimeo.com'     => 'Vimeo',
		'x.com'         => 'X',
		'youtu.be'      => 'YouTube',
		'youtube.com'   => 'YouTube',
	);

	/**
	 * The service a URL belongs to, such as "Instagram".
	 *
	 * @param string $url URL.
	 * @return string Service name, or an empty string for other sites.
	 */
	public static function service( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^(www|m)\./', '', $host );

		/**
		 * Filters the services whose links are shown by name.
		 *
		 * @param array<string, string> $services Service names keyed by host.
		 */
		$services = apply_filters( 'galerio_link_services', self::SERVICES );

		return isset( $services[ $host ] ) ? $services[ $host ] : '';
	}

	/**
	 * The words a link is shown with: its display text, else its service's
	 * name, else its URL without the scheme.
	 *
	 * @param array $link Link with url and label.
	 * @return string Plain text.
	 */
	public static function link_text( array $link ) {
		if ( '' !== $link['label'] ) {
			return $link['label'];
		}

		$service = self::service( $link['url'] );

		return '' !== $service ? $service : self::url_label( $link['url'] );
	}

	/**
	 * Render a link. Offline links show their text without linking.
	 *
	 * @param array $link Link with url, label and offline.
	 * @return string
	 */
	private static function link( array $link ) {
		$service = sanitize_html_class( strtolower( self::service( $link['url'] ) ) );
		$class   = 'galerio-link' . ( $service ? ' galerio-link--' . $service : '' );

		if ( $link['offline'] ) {
			return sprintf( '<span class="%1$s is-offline">%2$s</span>', esc_attr( $class ), esc_html( self::link_text( $link ) ) );
		}

		return sprintf( '<a class="%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $link['url'] ), esc_html( self::link_text( $link ) ) );
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
