<?php
/**
 * Exhibition dates and status.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * When a collection is on, from its start and end dates in Galerio Core.
 *
 * A collection without a start date has no status. One without an end date
 * stays on from its start.
 */
final class Exhibitions {

	/**
	 * A collection's dates, as "Y-m-d" or empty.
	 *
	 * @param int $post_id Collection.
	 * @return array With `start` and `end`.
	 */
	public static function dates( $post_id ) {
		if ( ! function_exists( 'galerio_get_field' ) || 'galerio_collection' !== get_post_type( $post_id ) ) {
			return array(
				'start' => '',
				'end'   => '',
			);
		}

		return array(
			'start' => (string) galerio_get_field( 'collection.start_date', $post_id ),
			'end'   => (string) galerio_get_field( 'collection.end_date', $post_id ),
		);
	}

	/**
	 * Today, in the site's time zone.
	 *
	 * @return string "Y-m-d".
	 */
	public static function today() {
		/**
		 * Filters the date exhibition statuses are worked out for.
		 *
		 * @param string $today "Y-m-d".
		 */
		return (string) apply_filters( 'galerio_exhibition_today', wp_date( 'Y-m-d' ) );
	}

	/**
	 * A collection's status.
	 *
	 * @param int $post_id Collection.
	 * @return string "upcoming", "current", "past", or empty without a start date.
	 */
	public static function status( $post_id ) {
		$dates = self::dates( $post_id );
		$today = self::today();

		if ( '' === $dates['start'] ) {
			return '';
		}

		if ( $dates['start'] > $today ) {
			return 'upcoming';
		}

		return '' !== $dates['end'] && $dates['end'] < $today ? 'past' : 'current';
	}

	/**
	 * A status's label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function label( $status ) {
		$labels = array(
			'upcoming' => _x( 'Upcoming', 'exhibition status', 'galerio-blocks' ),
			'current'  => _x( 'On now', 'exhibition status', 'galerio-blocks' ),
			'past'     => _x( 'Past', 'exhibition status', 'galerio-blocks' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
	}
}
