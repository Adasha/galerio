<?php
/**
 * WP-CLI commands for Galerio's fields.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\CLI;

use Galerio\Core\Fields\Check;
use Galerio\Core\Fields\Pods_Adapter;
use Galerio\Core\Fields\Reference;
use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * List the fields templates, blocks and code can use, and check their stored data.
 */
class Fields_Command {

	/**
	 * List Galerio's fields.
	 *
	 * ## OPTIONS
	 *
	 * [--object=<object>]
	 * : Only fields of this object.
	 * ---
	 * options:
	 *   - work
	 *   - profile
	 *   - collection
	 *   - article
	 *   - attachment
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format. "markdown" prints the README's field reference.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - markdown
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio fields list --object=work
	 *     wp galerio fields list --format=markdown
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( 'markdown' === $format ) {
			WP_CLI::line( rtrim( Reference::markdown( galerio_fields() ) ) );
			return;
		}

		Utils\format_items(
			$format,
			Reference::rows( galerio_fields(), (string) Utils\get_flag_value( $assoc_args, 'object', '' ) ),
			array( 'key', 'label', 'type', 'notes' )
		);
	}

	/**
	 * Check that the stored fields can be read and kept without Pods.
	 *
	 * Reads only: nothing is changed. It reports field values that read
	 * differently from what's stored, two-way relationships whose sides
	 * disagree, Pods shortcodes and blocks in content, and content in types
	 * and fields Galerio doesn't define. Links to unpublished or missing items
	 * are listed as notes.
	 *
	 * ## OPTIONS
	 *
	 * [--exit-code]
	 * : Exit with status 1 if there are any problems.
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
	 *     wp galerio fields check
	 *     wp galerio fields check --format=json --exit-code
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function check( $args, $assoc_args ) {
		$adapter = new Pods_Adapter();
		$check   = new Check( galerio_fields(), $adapter->is_available() ? $adapter : null );
		$results = $check->run();
		$format  = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( ! $adapter->is_available() ) {
			WP_CLI::warning( "Pods isn't active, so reads through Pods weren't compared." );
		}

		if ( $results ) {
			Utils\format_items( $format, $results, array( 'level', 'check', 'item', 'detail' ) );
		}

		$problems = count( wp_list_filter( $results, array( 'level' => Check::PROBLEM ) ) );

		if ( ! $problems ) {
			WP_CLI::success( 'No problems found.' );
			return;
		}

		WP_CLI::warning( sprintf( '%d problem(s) found.', $problems ) );

		if ( Utils\get_flag_value( $assoc_args, 'exit-code', false ) ) {
			WP_CLI::halt( 1 );
		}
	}
}
