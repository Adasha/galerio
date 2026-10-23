<?php
/**
 * WP-CLI commands for Galerio's fields.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\CLI;

use Galerio\Core\Fields\Reference;
use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * List the fields templates, blocks and code can use.
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
}
