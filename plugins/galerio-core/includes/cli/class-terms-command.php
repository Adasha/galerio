<?php
/**
 * WP-CLI commands for reorganising terms.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\CLI;

use Galerio\Core\Terms\Term_Mover;
use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Reorganise a site's terms: move, merge and tidy.
 */
class Terms_Command {

	/**
	 * Move a term, with the terms under it, to another taxonomy or parent.
	 *
	 * The terms keep their IDs, names, slugs, descriptions and items. Their
	 * addresses change, so the old and new ones are listed for redirects.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : The term's taxonomy, e.g. galerio_group.
	 *
	 * <term>
	 * : The term's ID or slug.
	 *
	 * [--to=<taxonomy>]
	 * : The taxonomy to move it to. Leave out to stay in the same one.
	 *
	 * [--parent=<term>]
	 * : The ID or slug of its new parent, in the taxonomy it moves to. 0 for the top level, which is the default.
	 *
	 * [--dry-run]
	 * : Show what would move without moving it.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Staff and the staff types under it, from Groups to Roles.
	 *     wp galerio terms move galerio_group staff --to=galerio_role --dry-run
	 *
	 *     # Fellows out from under Staff, to the top level.
	 *     wp galerio terms move galerio_role fellows --parent=0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function move( $args, $assoc_args ) {
		list( $from, $which ) = $args;

		$to     = (string) Utils\get_flag_value( $assoc_args, 'to', $from );
		$parent = (string) Utils\get_flag_value( $assoc_args, 'parent', '0' );
		$dry    = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$format = (string) Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( ! taxonomy_exists( $from ) ) {
			WP_CLI::error( sprintf( 'There is no taxonomy "%s".', $from ) );
		}
		if ( $to === $from && ! isset( $assoc_args['parent'] ) ) {
			WP_CLI::error( 'Say where it goes: --to, --parent or both.' );
		}

		$term = $this->find( $which, $from );
		if ( ! $term ) {
			WP_CLI::error( sprintf( 'There is no term "%s" in "%s".', $which, $from ) );
		}

		$parent_id = 0;
		if ( '0' !== $parent ) {
			$parent_term = taxonomy_exists( $to ) ? $this->find( $parent, $to ) : null;
			if ( ! $parent_term ) {
				WP_CLI::error( sprintf( 'There is no term "%s" in "%s".', $parent, $to ) );
			}
			$parent_id = (int) $parent_term->term_id;
		}

		$rows = Term_Mover::move( $term, $to, $parent_id, $dry );
		if ( is_wp_error( $rows ) ) {
			WP_CLI::error( $rows->get_error_message() );
		}

		$fields = array( 'term_id', 'name', 'from', 'to', 'items', 'old_url' );
		if ( ! $dry ) {
			$fields[] = 'new_url';
		}
		Utils\format_items( $format, $rows, $fields );

		if ( $dry ) {
			WP_CLI::log( sprintf( 'Dry run: %s would move. Nothing was changed.', $this->terms( count( $rows ) ) ) );
		} else {
			WP_CLI::success( sprintf( 'Moved %s. Redirect the old addresses to the new ones if they were shared.', $this->terms( count( $rows ) ) ) );
		}
	}

	/**
	 * Merge a term into another in the same taxonomy.
	 *
	 * Its items get the other term, the terms under it move under the other,
	 * and it's deleted. Its address goes, so redirect it if it was shared.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : The taxonomy, e.g. galerio_collection_category.
	 *
	 * <term>
	 * : The ID or slug of the term to merge away.
	 *
	 * <into>
	 * : The ID or slug of the term to keep.
	 *
	 * [--dry-run]
	 * : Show what would happen without changing anything.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio terms merge galerio_collection_category group-project project --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function merge( $args, $assoc_args ) {
		list( $taxonomy, $which, $into_which ) = $args;

		$dry = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			WP_CLI::error( sprintf( 'There is no taxonomy "%s".', $taxonomy ) );
		}
		$from = $this->find( $which, $taxonomy );
		$into = $this->find( $into_which, $taxonomy );
		if ( ! $from || ! $into ) {
			WP_CLI::error( sprintf( 'There is no term "%s" in "%s".', $from ? $into_which : $which, $taxonomy ) );
		}

		$rows = Term_Mover::merge( $from, $into, $dry );
		if ( is_wp_error( $rows ) ) {
			WP_CLI::error( $rows->get_error_message() );
		}

		Utils\format_items( (string) Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, array( 'term_id', 'name', 'into', 'items', 'children', 'old_url', 'new_url' ) );

		if ( $dry ) {
			WP_CLI::log( 'Dry run: nothing was changed.' );
		} else {
			WP_CLI::success( sprintf( 'Merged "%s" into "%s".', $from->name, $into->name ) );
		}
	}

	/**
	 * Drop tags made redundant by a lower term on the same item.
	 *
	 * An item tagged with both a term and a term above it, e.g. "Exhibition"
	 * and "Graduate Exhibition", keeps only the lower one. The upper term's
	 * page still lists it.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : A hierarchical taxonomy, e.g. galerio_collection_category.
	 *
	 * [--dry-run]
	 * : Show what would change without changing it.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio terms tidy galerio_collection_category --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function tidy( $args, $assoc_args ) {
		$dry  = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$rows = Term_Mover::tidy( $args[0], $dry );
		if ( is_wp_error( $rows ) ) {
			WP_CLI::error( $rows->get_error_message() );
		}

		if ( ! $rows ) {
			WP_CLI::success( 'Nothing to tidy.' );
			return;
		}

		Utils\format_items( (string) Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, array( 'id', 'type', 'title', 'removed', 'kept' ) );

		if ( $dry ) {
			WP_CLI::log( sprintf( 'Dry run: %d items would change. Nothing was changed.', count( $rows ) ) );
		} else {
			WP_CLI::success( sprintf( 'Tidied %d items.', count( $rows ) ) );
		}
	}

	/**
	 * "1 term" or "3 terms".
	 *
	 * @param int $count Count.
	 * @return string
	 */
	private function terms( $count ) {
		return 1 === $count ? '1 term' : "$count terms";
	}

	/**
	 * A term by ID or slug.
	 *
	 * @param string $which    ID or slug.
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term|null
	 */
	private function find( $which, $taxonomy ) {
		$term = ctype_digit( $which ) ? get_term( (int) $which, $taxonomy ) : get_term_by( 'slug', $which, $taxonomy );

		return $term instanceof \WP_Term ? $term : null;
	}
}
