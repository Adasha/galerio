<?php
/**
 * Moving, merging and tidying terms.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Terms;

use WP_Error;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Reorganises a site's terms: moves a term and its children, keeping their
 * IDs and items (e.g. staff types from Groups to Roles), merges one term into
 * another, and drops tags made redundant by a lower term on the same item.
 */
class Term_Mover {

	/**
	 * Plan or make a move.
	 *
	 * The terms keep their IDs, names, slugs, descriptions, term meta and
	 * items. The moved term goes under the new parent (0 for the top level);
	 * the terms under it keep their places beneath it.
	 *
	 * @param WP_Term $term      The term to move.
	 * @param string  $taxonomy  The taxonomy to move it to (its own for a new parent only).
	 * @param int     $parent_id The new parent's term ID in that taxonomy, or 0.
	 * @param bool    $dry_run   Only report what would happen.
	 * @return array|WP_Error One row per term moved: term_id, name, from, to,
	 *                        items, old_url and new_url (empty on a dry run).
	 */
	public static function move( WP_Term $term, $taxonomy, $parent_id = 0, $dry_run = false ) {
		$parent_id = (int) $parent_id;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'galerio_terms_taxonomy', sprintf( 'There is no taxonomy "%s".', $taxonomy ) );
		}

		$terms = array_merge( array( $term ), self::descendants( $term ) );
		$ids   = wp_list_pluck( $terms, 'term_id' );

		if ( $parent_id ) {
			$parent = get_term( $parent_id, $taxonomy );
			if ( ! $parent instanceof WP_Term ) {
				return new WP_Error( 'galerio_terms_parent', sprintf( 'There is no term %d in "%s".', $parent_id, $taxonomy ) );
			}
			if ( in_array( $parent_id, array_map( 'intval', $ids ), true ) ) {
				return new WP_Error( 'galerio_terms_parent', 'A term can\'t go under itself or a term under it.' );
			}
			if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
				return new WP_Error( 'galerio_terms_parent', sprintf( '"%s" has no parents.', $taxonomy ) );
			}
		}

		$across = $taxonomy !== $term->taxonomy;

		if ( $across ) {
			$problem = self::check_across( $terms, $taxonomy );
			if ( is_wp_error( $problem ) ) {
				return $problem;
			}
		}

		$rows = array();
		foreach ( $terms as $item ) {
			$link   = get_term_link( $item );
			$rows[] = array(
				'term_id' => (int) $item->term_id,
				'name'    => $item->name,
				'from'    => $item->taxonomy,
				'to'      => $taxonomy,
				'items'   => self::count_objects( $item ),
				'old_url' => is_wp_error( $link ) ? '' : $link,
				'new_url' => '',
			);
		}

		if ( $dry_run ) {
			return $rows;
		}

		if ( $across ) {
			self::move_across( $terms, $term, $taxonomy, $parent_id );
		} else {
			$result = wp_update_term( $term->term_id, $taxonomy, array( 'parent' => $parent_id ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		foreach ( $rows as $i => $row ) {
			$link                  = get_term_link( (int) $row['term_id'], $taxonomy );
			$rows[ $i ]['new_url'] = is_wp_error( $link ) ? '' : $link;
		}

		return $rows;
	}

	/**
	 * Plan or make a merge: one term's items go to another in the same
	 * taxonomy, the terms under it move under the other, and it's deleted.
	 *
	 * @param WP_Term $from    The term to merge away.
	 * @param WP_Term $into    The term to keep.
	 * @param bool    $dry_run Only report what would happen.
	 * @return array|WP_Error One row: term_id, name, into, items, children,
	 *                        old_url and new_url.
	 */
	public static function merge( WP_Term $from, WP_Term $into, $dry_run = false ) {
		if ( $from->taxonomy !== $into->taxonomy ) {
			return new WP_Error( 'galerio_terms_merge', 'Both terms must be in the same taxonomy. Move one across first.' );
		}

		$below = self::descendants( $from );
		if ( (int) $from->term_id === (int) $into->term_id || in_array( (int) $into->term_id, array_map( 'intval', wp_list_pluck( $below, 'term_id' ) ), true ) ) {
			return new WP_Error( 'galerio_terms_merge', 'A term can\'t merge into itself or a term under it.' );
		}

		$children = array_filter(
			$below,
			function ( $child ) use ( $from ) {
				return (int) $child->parent === (int) $from->term_id;
			}
		);

		$old  = get_term_link( $from );
		$new  = get_term_link( $into );
		$rows = array(
			array(
				'term_id'  => (int) $from->term_id,
				'name'     => $from->name,
				'into'     => $into->name,
				'items'    => self::count_objects( $from ),
				'children' => implode( ', ', wp_list_pluck( $children, 'name' ) ),
				'old_url'  => is_wp_error( $old ) ? '' : $old,
				'new_url'  => is_wp_error( $new ) ? '' : $new,
			),
		);

		if ( $dry_run ) {
			return $rows;
		}

		foreach ( self::objects( array( $from ) ) as $ids ) {
			foreach ( $ids as $id ) {
				wp_set_object_terms( $id, array( (int) $into->term_id ), $into->taxonomy, true );
			}
		}

		foreach ( $children as $child ) {
			wp_update_term( $child->term_id, $child->taxonomy, array( 'parent' => (int) $into->term_id ) );
		}

		$deleted = wp_delete_term( $from->term_id, $from->taxonomy );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return $rows;
	}

	/**
	 * Plan or make a tidy: an item tagged with a term and with a term above it
	 * keeps only the lower one, e.g. "Graduate Exhibition" without
	 * "Exhibition". The upper term's page still lists the item.
	 *
	 * @param string $taxonomy A hierarchical taxonomy.
	 * @param bool   $dry_run  Only report what would happen.
	 * @return array|WP_Error One row per item changed: id, type, title,
	 *                        removed and kept.
	 */
	public static function tidy( $taxonomy, $dry_run = false ) {
		global $wpdb;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'galerio_terms_taxonomy', sprintf( 'There is no taxonomy "%s".', $taxonomy ) );
		}
		if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Every item's terms in one query, drafts and private items included.
		$pairs = $wpdb->get_results( $wpdb->prepare( "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = %s ORDER BY tr.object_id", $taxonomy ) );

		$by_object = array();
		foreach ( $pairs as $pair ) {
			$by_object[ (int) $pair->object_id ][] = (int) $pair->term_id;
		}

		$rows = array();
		foreach ( $by_object as $object_id => $term_ids ) {
			if ( count( $term_ids ) < 2 ) {
				continue;
			}

			$above = array();
			foreach ( $term_ids as $term_id ) {
				$above = array_merge( $above, array_map( 'intval', get_ancestors( $term_id, $taxonomy, 'taxonomy' ) ) );
			}
			$remove = array_values( array_intersect( $term_ids, $above ) );
			if ( ! $remove ) {
				continue;
			}

			$names  = function ( $ids ) use ( $taxonomy ) {
				$list = array();
				foreach ( $ids as $id ) {
					$list[] = get_term( $id, $taxonomy )->name;
				}
				return implode( ', ', $list );
			};
			$rows[] = array(
				'id'      => $object_id,
				'type'    => get_post_type( $object_id ),
				'title'   => get_the_title( $object_id ),
				'removed' => $names( $remove ),
				'kept'    => $names( array_diff( $term_ids, $remove ) ),
			);

			if ( ! $dry_run ) {
				wp_remove_object_terms( $object_id, $remove, $taxonomy );
			}
		}

		return $rows;
	}

	/**
	 * The terms under a term, each parent before its children.
	 *
	 * @param WP_Term $term Term.
	 * @return WP_Term[]
	 */
	public static function descendants( WP_Term $term ) {
		if ( ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
			return array();
		}

		$found = array();
		$queue = array( (int) $term->term_id );

		while ( $queue ) {
			$children = get_terms(
				array(
					'taxonomy'   => $term->taxonomy,
					'parent'     => array_shift( $queue ),
					'hide_empty' => false,
					'orderby'    => 'name',
				)
			);
			foreach ( is_array( $children ) ? $children : array() as $child ) {
				$found[] = $child;
				$queue[] = (int) $child->term_id;
			}
		}

		return $found;
	}

	/**
	 * Refuse a move to another taxonomy that would clash or lose tags.
	 *
	 * @param WP_Term[] $terms    The terms that would move.
	 * @param string    $taxonomy The taxonomy they'd move to.
	 * @return true|WP_Error
	 */
	private static function check_across( array $terms, $taxonomy ) {
		$problems = array();

		foreach ( $terms as $item ) {
			$same = get_term_by( 'slug', $item->slug, $taxonomy );
			if ( $same ) {
				$problems[] = sprintf( '"%s" has a term with the slug "%s" already (ID %d).', $taxonomy, $item->slug, $same->term_id );
			}
		}

		if ( count( $terms ) > 1 && ! is_taxonomy_hierarchical( $taxonomy ) ) {
			$problems[] = sprintf( '"%s" has no parents, so the terms under "%s" can\'t go with it. Move them first.', $taxonomy, $terms[0]->name );
		}

		$covered = get_taxonomy( $taxonomy )->object_type;
		foreach ( self::object_types( $terms ) as $type ) {
			if ( ! in_array( $type, $covered, true ) ) {
				$problems[] = sprintf( 'Some items are of the type "%s", which "%s" isn\'t used for.', $type, $taxonomy );
			}
		}

		return $problems ? new WP_Error( 'galerio_terms_clash', implode( ' ', $problems ) ) : true;
	}

	/**
	 * Move terms to another taxonomy in place.
	 *
	 * WordPress has no API for this. Changing the taxonomy of each term's
	 * term_taxonomy row keeps the term's ID, its items and the parent links
	 * between the moved terms.
	 *
	 * @param WP_Term[] $terms     The term and the terms under it.
	 * @param WP_Term   $top       The term being moved.
	 * @param string    $taxonomy  The taxonomy to move them to.
	 * @param int       $parent_id The moved term's new parent.
	 */
	private static function move_across( array $terms, WP_Term $top, $taxonomy, $parent_id ) {
		global $wpdb;

		$objects = self::objects( $terms );

		foreach ( $terms as $item ) {
			$data = array( 'taxonomy' => $taxonomy );
			if ( (int) $item->term_id === (int) $top->term_id ) {
				$data['parent'] = $parent_id;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- WordPress has no API to move a term between taxonomies.
			$wpdb->update( $wpdb->term_taxonomy, $data, array( 'term_taxonomy_id' => $item->term_taxonomy_id ) );
		}

		foreach ( $terms as $item ) {
			clean_term_cache( (int) $item->term_id, '', false );
		}
		clean_taxonomy_cache( $top->taxonomy );
		clean_taxonomy_cache( $taxonomy );

		foreach ( $objects as $type => $ids ) {
			clean_object_term_cache( $ids, $type );
		}
	}

	/**
	 * The items tagged with any of the terms, by post type.
	 *
	 * @param WP_Term[] $terms Terms.
	 * @return array<string,int[]>
	 */
	private static function objects( array $terms ) {
		global $wpdb;

		$tt_ids = array_map( 'intval', wp_list_pluck( $terms, 'term_taxonomy_id' ) );
		$in     = implode( ',', $tt_ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The IDs are integers.
		$rows = $wpdb->get_results( "SELECT DISTINCT tr.object_id, p.post_type FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tr.term_taxonomy_id IN ($in)" );

		$by_type = array();
		foreach ( $rows as $row ) {
			$by_type[ $row->post_type ? $row->post_type : 'post' ][] = (int) $row->object_id;
		}

		return $by_type;
	}

	/**
	 * How many items have a term, drafts and private items included.
	 *
	 * @param WP_Term $term Term.
	 * @return int
	 */
	private static function count_objects( WP_Term $term ) {
		return (int) array_sum( array_map( 'count', self::objects( array( $term ) ) ) );
	}

	/**
	 * The post types of the items tagged with any of the terms.
	 *
	 * @param WP_Term[] $terms Terms.
	 * @return string[]
	 */
	private static function object_types( array $terms ) {
		return array_keys( self::objects( $terms ) );
	}
}
