<?php
/**
 * How a work's date is shown.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A work's date is a year, optionally a last year of a range, and optionally
 * words that stand in for both ("mid-19th century", "600–800 CE"). The years
 * stay numbers so works can be sorted and filtered; the words are only for
 * display.
 */
final class Dates {

	/**
	 * `work.date_label`: the date as shown.
	 *
	 * @param int $post_id Work.
	 * @return string Plain text: the words when there are any, otherwise the
	 *                year or range ("1830–32", "1899–1901"), or empty.
	 */
	public static function label( $post_id ) {
		$words = trim( (string) galerio_get_field( 'work.date_text', $post_id ) );

		if ( '' !== $words ) {
			return $words;
		}

		$from = (int) galerio_get_field( 'work.year', $post_id );
		$to   = (int) galerio_get_field( 'work.year_to', $post_id );

		if ( ! $from ) {
			return '';
		}

		if ( $to <= $from ) {
			return (string) $from;
		}

		// Within a century only the last two digits of the end year are shown.
		$end = $from >= 1000 && intdiv( $from, 100 ) === intdiv( $to, 100 ) ? sprintf( '%02d', $to % 100 ) : (string) $to;

		return $from . '–' . $end;
	}
}
