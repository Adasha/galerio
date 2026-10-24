<?php
/**
 * The Exhibition Dates block.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the `galerio/exhibition-dates` block: a collection's
 * opening and closing dates, with an optional upcoming, on now or past label.
 */
final class Exhibition_Dates_Block {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the block from its compiled metadata.
	 */
	public static function register() {
		$dir = GALERIO_BLOCKS_DIR . 'build/exhibition-dates';

		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}

		register_block_type( $dir, array( 'render_callback' => array( __CLASS__, 'render' ) ) );
		wp_set_script_translations( 'galerio-exhibition-dates-editor-script', 'galerio-blocks', GALERIO_BLOCKS_DIR . 'languages' );
	}

	/**
	 * Render the block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Empty when the collection has no dates.
	 */
	public static function render( $attributes, $content, $block ) {
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$dates   = Exhibitions::dates( $post_id );
		$text    = self::text( $dates['start'], $dates['end'], isset( $attributes['format'] ) ? $attributes['format'] : 'site' );

		if ( '' === $text ) {
			return '';
		}

		$status = ! empty( $attributes['showStatus'] ) ? Exhibitions::status( $post_id ) : '';

		if ( $status ) {
			$text = sprintf(
				'<span class="wp-block-galerio-exhibition-dates__status is-%1$s">%2$s</span> %3$s',
				esc_attr( $status ),
				esc_html( Exhibitions::label( $status ) ),
				$text
			);
		}

		return sprintf( '<p %1$s>%2$s</p>', get_block_wrapper_attributes( $status ? array( 'class' => 'is-' . $status ) : array() ), $text );
	}

	/**
	 * The dates as text with <time> elements.
	 *
	 * @param string $start  "Y-m-d" or empty.
	 * @param string $end    "Y-m-d" or empty.
	 * @param string $format "site" for both dates in the site's format, "short" for a range such as "19 May – 16 June 2023".
	 * @return string HTML, or empty.
	 */
	public static function text( $start, $end, $format ) {
		if ( '' === $start && '' === $end ) {
			return '';
		}

		$site = get_option( 'date_format' );

		if ( '' === $end || $end === $start ) {
			/* translators: %s: the date an exhibition opens. */
			return $end === $start ? self::time( $start, $site ) : sprintf( esc_html__( 'From %s', 'galerio-blocks' ), self::time( $start, $site ) );
		}

		if ( '' === $start ) {
			/* translators: %s: the date an exhibition closes. */
			return sprintf( esc_html__( 'Until %s', 'galerio-blocks' ), self::time( $end, $site ) );
		}

		$from = $site;
		$to   = $site;
		/* translators: 1: opening date, 2: closing date. */
		$range = __( '%1$s – %2$s', 'galerio-blocks' );

		if ( 'short' === $format ) {
			$from = _x( 'j F Y', 'short exhibition date', 'galerio-blocks' );
			$to   = $from;

			if ( substr( $start, 0, 7 ) === substr( $end, 0, 7 ) ) {
				$from = _x( 'j', 'opening day, when an exhibition closes in the same month', 'galerio-blocks' );
				/* translators: 1: opening day, 2: closing date, in the same month. */
				$range = __( '%1$s–%2$s', 'galerio-blocks' );
			} elseif ( substr( $start, 0, 4 ) === substr( $end, 0, 4 ) ) {
				$from = _x( 'j F', 'opening date, when an exhibition closes in the same year', 'galerio-blocks' );
			}
		}

		return sprintf( esc_html( $range ), self::time( $start, $from ), self::time( $end, $to ) );
	}

	/**
	 * A date in a <time> element.
	 *
	 * @param string $date   "Y-m-d".
	 * @param string $format PHP date format.
	 * @return string
	 */
	private static function time( $date, $format ) {
		return sprintf( '<time datetime="%1$s">%2$s</time>', esc_attr( $date ), esc_html( mysql2date( $format, $date ) ) );
	}
}
