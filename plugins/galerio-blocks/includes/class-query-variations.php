<?php
/**
 * Query Loop variations for Galerio's content types.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Adds ready-made Query Loops to the inserter: works, creators, collections,
 * and exhibitions filtered by date (current and upcoming by default).
 *
 * The variations are plain Query Loops with a starting layout, so everything
 * core offers still works. The date filter is a `galerioStatus` key in the
 * block's query, and the works' availability filter (while Galerio Core's
 * catalogue is on) a `galerioAvailability` key. Both are applied here when
 * the page is built.
 */
final class Query_Variations {

	/**
	 * Date filters for exhibitions.
	 */
	const STATUSES = array( 'current-upcoming', 'current', 'upcoming', 'past' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'query_vars' ), 10, 2 );
	}

	/**
	 * The variations script, with the site's names for its content types.
	 */
	public static function editor_assets() {
		if ( ! function_exists( 'galerio_expand_names' ) ) {
			return;
		}

		wp_enqueue_script(
			'galerio-query-variations',
			plugins_url( 'assets/js/query-variations.js', GALERIO_BLOCKS_FILE ),
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			GALERIO_BLOCKS_VERSION,
			true
		);
		wp_set_script_translations( 'galerio-query-variations', 'galerio-blocks', GALERIO_BLOCKS_DIR . 'languages' );

		wp_add_inline_script(
			'galerio-query-variations',
			'window.galerioQueryVariations = ' . wp_json_encode(
				array(
					'names'        => array(
						/* translators: %s: the site's name for a content type, such as "Works". */
						'works'       => sprintf( __( 'Galerio %s', 'galerio-blocks' ), galerio_expand_names( '{Works}' ) ),
						/* translators: %s: the site's name for a content type, such as "Works". */
						'profiles'    => sprintf( __( 'Galerio %s', 'galerio-blocks' ), galerio_expand_names( '{Profiles}' ) ),
						/* translators: %s: the site's name for a content type, such as "Works". */
						'collections' => sprintf( __( 'Galerio %s', 'galerio-blocks' ), galerio_expand_names( '{Collections}' ) ),
						/* translators: %s: the site's name for collections, in lower case, e.g. "exhibitions". */
						'exhibitions' => sprintf( __( 'Galerio current and upcoming %s', 'galerio-blocks' ), galerio_expand_names( '{collections}' ) ),
					),
					'cards'        => self::cards(),
					// The works' availability filter, while the catalogue is on.
					'availability' => self::has_catalogue() ? \Galerio\Core\Catalogue\Catalogue::filters() : (object) array(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * The theme's card template parts for the variations' types, such as
	 * card-work in the Galerio theme, so a new Query Loop shows its items
	 * the same way as their archive does.
	 *
	 * @return array Post type => part slug, for the types that have one.
	 */
	public static function cards() {
		$cards = array();

		foreach ( array( 'galerio_work', 'galerio_profile', 'galerio_collection' ) as $post_type ) {
			$slug = 'card-' . substr( $post_type, strlen( 'galerio_' ) );

			if ( get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' ) ) {
				$cards[ $post_type ] = $slug;
			}
		}

		return $cards;
	}

	/**
	 * Apply an exhibitions Query Loop's date filter, and a works Query
	 * Loop's availability filter.
	 *
	 * @param array    $query Query arguments.
	 * @param WP_Block $block The Post Template block inside the Query Loop.
	 * @return array
	 */
	public static function query_vars( $query, $block ) {
		if ( ! function_exists( 'galerio_fields' ) ) {
			return $query;
		}

		$status       = isset( $block->context['query']['galerioStatus'] ) ? $block->context['query']['galerioStatus'] : '';
		$availability = isset( $block->context['query']['galerioAvailability'] ) ? $block->context['query']['galerioAvailability'] : '';

		if ( in_array( $status, self::STATUSES, true ) ) {
			$query = array_merge( $query, self::status_args( $status ) );
		}

		if ( $availability && self::has_catalogue() ) {
			$args = \Galerio\Core\Catalogue\Catalogue::query_args( $availability );

			if ( $args ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering by availability is the point.
				$query['meta_query'] = empty( $query['meta_query'] )
					? $args['meta_query']
					: array(
						'relation' => 'AND',
						$query['meta_query'],
						$args['meta_query'],
					);
			}
		}

		return $query;
	}

	/**
	 * Whether Galerio Core has a catalogue that is on.
	 *
	 * @return bool
	 */
	private static function has_catalogue() {
		return class_exists( '\\Galerio\\Core\\Catalogue\\Catalogue' ) && \Galerio\Core\Catalogue\Catalogue::enabled();
	}

	/**
	 * Query arguments for collections with a status, ordered by date.
	 *
	 * Current and upcoming collections are ordered by opening date, soonest
	 * first; past ones by closing date, most recent first.
	 *
	 * @param string $status One of STATUSES.
	 * @return array
	 */
	public static function status_args( $status ) {
		$start = galerio_fields()->get( 'collection.start_date' )->source;
		$end   = galerio_fields()->get( 'collection.end_date' )->source;
		$today = Exhibitions::today();

		// Pods stores an empty date as "0000-00-00".
		$empty     = array( '', '0000-00-00' );
		$has_start = array(
			'key'     => $start,
			'value'   => $empty,
			'compare' => 'NOT IN',
		);
		$not_ended = array(
			'relation' => 'OR',
			array(
				'key'     => $end,
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
			array(
				'key'     => $end,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $end,
				'value'   => $empty,
				'compare' => 'IN',
			),
		);
		$started   = array(
			'key'     => $start,
			'value'   => $today,
			'compare' => '<=',
			'type'    => 'DATE',
		);
		$upcoming  = array(
			'key'     => $start,
			'value'   => $today,
			'compare' => '>',
			'type'    => 'DATE',
		);

		switch ( $status ) {
			case 'current':
				$meta = array( $has_start, $started, $not_ended );
				break;
			case 'upcoming':
				$meta = array( $upcoming );
				break;
			case 'past':
				$meta = array(
					array(
						'key'     => $end,
						'value'   => $today,
						'compare' => '<',
						'type'    => 'DATE',
					),
					array(
						'key'     => $end,
						'value'   => $empty,
						'compare' => 'NOT IN',
					),
				);
				break;
			default:
				$meta = array( $has_start, $not_ended );
		}

		return array(
			'post_type'  => 'galerio_collection',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering by date is the point.
			'meta_query' => array_merge( array( 'relation' => 'AND' ), $meta ),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ordering by date.
			'meta_key'   => 'past' === $status ? $end : $start,
			'orderby'    => 'meta_value',
			'order'      => 'past' === $status ? 'DESC' : 'ASC',
		);
	}
}
