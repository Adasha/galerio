<?php
/**
 * Related items in Query Loops.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a core Query Loop show the items a relationship field of the current
 * item points to, such as a creator's works or a collection's exhibitors.
 *
 * The choice is a `galerioRelated` key in the block's query (a field key such
 * as "profile.works"), with `galerioRelatedOrder` for the order: "saved" (as
 * arranged on the item, the default), "random", or "query" for the Query
 * Loop's own order. A loop with nothing related isn't shown at all, heading
 * included, unless it has a No results block with something in it.
 */
final class Related_Query {

	/**
	 * Orders a related Query Loop can use.
	 */
	const ORDERS = array( 'saved', 'random', 'query' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'query_vars' ), 10, 2 );
		add_filter( 'pre_render_block', array( __CLASS__, 'hide_when_empty' ), 10, 2 );
	}

	/**
	 * The relationship fields, from Galerio Core when it's active.
	 *
	 * @return array[] Each with key, label, postType and target (post types).
	 */
	public static function relations() {
		if ( ! function_exists( 'galerio_fields' ) ) {
			return array();
		}

		$types     = \Galerio\Core\Fields\Registry::OBJECTS;
		$relations = array();

		foreach ( galerio_fields()->all() as $field ) {
			if ( $field->public && 'relation' === $field->type && $field->multiple && isset( $types[ $field->object ], $types[ $field->target ] ) ) {
				$relations[] = array(
					'key'      => $field->key,
					'label'    => $field->label,
					'postType' => $types[ $field->object ],
					'target'   => $types[ $field->target ],
				);
			}
		}

		return $relations;
	}

	/**
	 * The sidebar panel's script, with the relationship fields.
	 */
	public static function editor_assets() {
		if ( ! function_exists( 'galerio_expand_names' ) ) {
			return;
		}

		$relations = self::relations();
		foreach ( $relations as $i => $relation ) {
			$relations[ $i ]['label']     = galerio_expand_names( $relation['label'] );
			$type                         = get_post_type_object( $relation['postType'] );
			$relations[ $i ]['typeLabel'] = $type ? $type->labels->singular_name : $relation['postType'];
		}

		wp_enqueue_script(
			'galerio-related-query',
			plugins_url( 'assets/js/related-query.js', GALERIO_BLOCKS_FILE ),
			array( 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			GALERIO_BLOCKS_VERSION,
			true
		);
		wp_set_script_translations( 'galerio-related-query', 'galerio-blocks', GALERIO_BLOCKS_DIR . 'languages' );

		// The Carousel offers the same relationships, through this script.
		$carousel = wp_scripts()->query( 'galerio-carousel-editor-script' );
		if ( $carousel && ! in_array( 'galerio-related-query', $carousel->deps, true ) ) {
			$carousel->deps[] = 'galerio-related-query';
		}

		wp_add_inline_script(
			'galerio-related-query',
			'window.galerioRelatedQuery = ' . wp_json_encode( array( 'relations' => $relations ) ) . ';',
			'before'
		);
	}

	/**
	 * The relationship field a query asks for, if it suits the current item.
	 *
	 * @param array $query The Query Loop's query attribute.
	 * @return array|null The field's data from relations(), or null.
	 */
	public static function relation_for( $query ) {
		$key = isset( $query['galerioRelated'] ) ? (string) $query['galerioRelated'] : '';

		if ( '' === $key ) {
			return null;
		}

		foreach ( self::relations() as $relation ) {
			if ( $key === $relation['key'] ) {
				return $relation;
			}
		}

		return null;
	}

	/**
	 * IDs of the published items related to the current one, in saved order.
	 *
	 * @param array $relation A field from relations().
	 * @param int   $post_id  Current item.
	 * @return int[]
	 */
	public static function related_ids( $relation, $post_id ) {
		if ( ! $post_id || get_post_type( $post_id ) !== $relation['postType'] || ! function_exists( 'galerio_get_related' ) ) {
			return array();
		}

		return wp_list_pluck( galerio_get_related( $relation['key'], $post_id ), 'ID' );
	}

	/**
	 * Turn a related Query Loop's query into one for the related items.
	 *
	 * @param array    $query Query arguments.
	 * @param WP_Block $block The Post Template block inside the Query Loop.
	 * @return array
	 */
	public static function query_vars( $query, $block ) {
		$attrs    = isset( $block->context['query'] ) ? $block->context['query'] : array();
		$relation = self::relation_for( $attrs );

		if ( ! $relation ) {
			return $query;
		}

		$ids   = self::related_ids( $relation, (int) get_the_ID() );
		$order = isset( $attrs['galerioRelatedOrder'] ) && in_array( $attrs['galerioRelatedOrder'], self::ORDERS, true ) ? $attrs['galerioRelatedOrder'] : 'saved';

		$query['post_type']           = $relation['target'];
		$query['post__in']            = $ids ? $ids : array( 0 );
		$query['ignore_sticky_posts'] = true;

		if ( 'saved' === $order ) {
			$query['orderby'] = 'post__in';
		} elseif ( 'random' === $order ) {
			$query['orderby'] = 'rand';
		}

		return $query;
	}

	/**
	 * Leave out a related Query Loop with nothing related, heading and all,
	 * unless it has a No results block with something to say.
	 *
	 * @param string|null $pre   Output so far; null to render as usual.
	 * @param array       $block Parsed block.
	 * @return string|null
	 */
	public static function hide_when_empty( $pre, $block ) {
		if ( null !== $pre || 'core/query' !== $block['blockName'] || empty( $block['attrs']['query']['galerioRelated'] ) ) {
			return $pre;
		}

		$relation = self::relation_for( $block['attrs']['query'] );

		if ( $relation && self::related_ids( $relation, (int) get_the_ID() ) ) {
			return $pre;
		}

		return self::has_message( $block['innerBlocks'] ) ? $pre : '';
	}

	/**
	 * Whether the blocks hold a No results block with blocks inside.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return bool
	 */
	private static function has_message( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'core/query-no-results' === $block['blockName'] ) {
				return ! empty( $block['innerBlocks'] );
			}
			if ( ! empty( $block['innerBlocks'] ) && self::has_message( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}
}
