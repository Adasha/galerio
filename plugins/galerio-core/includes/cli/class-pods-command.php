<?php
/**
 * WP-CLI commands for the Galerio Pods package.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\CLI;

use Galerio\Core\Pods\Importer;
use Galerio\Core\Pods\Package;
use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Import, export and compare the Galerio Pods package.
 *
 * Imports go through Galerio's importer, which repairs the relationships Pods'
 * own importer leaves pointing at the wrong fields.
 */
class Pods_Command {

	/**
	 * Import a Pods package, then repair its relationships.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the Pods package JSON file.
	 *
	 * [--replace]
	 * : Replace existing pods, fields and templates instead of updating them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio pods import wp-content/galerio-data/pods/galerio.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( $args, $assoc_args ) {
		$report = Importer::import( $this->read_package( $args[0] ), (bool) Utils\get_flag_value( $assoc_args, 'replace', false ) );

		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report );
		}

		WP_CLI::log( 'Pods package imported.' );
		$this->print_report( $report );
	}

	/**
	 * Repair bidirectional relationships for an already-imported package.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the Pods package JSON file that was imported.
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio pods repair-relationships wp-content/galerio-data/pods/galerio.json
	 *
	 * @subcommand repair-relationships
	 *
	 * @param array $args Positional arguments.
	 */
	public function repair_relationships( $args ) {
		$this->print_report( Importer::repair( $this->read_package( $args[0] ) ) );
	}

	/**
	 * Export this site's Pods configuration as a package.
	 *
	 * Unless --raw is given, the package is canonical: pods and templates are
	 * sorted by name, IDs are numbered from 1, and values that only describe
	 * this site are left out. Exports of the same configuration from different
	 * sites are then identical, so an export can be compared with the
	 * repository's package using git diff.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : File to write. Without it, the package is printed.
	 *
	 * [--pods=<pods>]
	 * : Comma-separated names of the pods to export. Defaults to all pods.
	 *
	 * [--raw]
	 * : Export exactly what Pods exports, including this site's IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update the repository's package from this site.
	 *     wp galerio pods export wp-content/galerio-data/pods/galerio.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function export( $args, $assoc_args ) {
		$pods    = array_filter( array_map( 'trim', explode( ',', (string) Utils\get_flag_value( $assoc_args, 'pods', '' ) ) ) );
		$package = Package::from_site( $pods );

		if ( is_wp_error( $package ) ) {
			WP_CLI::error( $package );
		}

		if ( ! Utils\get_flag_value( $assoc_args, 'raw', false ) ) {
			$package = Package::canonical( $package );
		}

		$json = Package::encode( $package );

		if ( empty( $args[0] ) ) {
			WP_CLI::line( rtrim( $json ) );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI output file.
		if ( false === file_put_contents( $args[0], $json ) ) {
			WP_CLI::error( sprintf( 'Cannot write %s.', $args[0] ) );
		}

		WP_CLI::success( sprintf( 'Exported %d pods and %d templates to %s.', count( $package['pods'] ), isset( $package['templates'] ) ? count( $package['templates'] ) : 0, $args[0] ) );
	}

	/**
	 * Compare this site's Pods configuration with a package.
	 *
	 * Pods, groups, fields and templates are matched by name, so database IDs
	 * and other values that only describe one site are ignored. Relationships
	 * are compared by the field they pair with.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the Pods package to compare with.
	 *
	 * [--format=<format>]
	 * : Output format. "json" includes full values; the others shorten long ones.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * [--exit-code]
	 * : Exit with status 1 when there are differences, like git diff --exit-code.
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio pods diff wp-content/galerio-data/pods/galerio.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function diff( $args, $assoc_args ) {
		$package = $this->read_package( $args[0] );
		$site    = Package::from_site();

		if ( is_wp_error( $site ) ) {
			WP_CLI::error( $site );
		}

		$format  = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$labels  = array(
			'added'   => 'site only',
			'removed' => 'package only',
			'changed' => 'changed',
		);
		$changes = array();

		foreach ( Package::diff( $package, $site ) as $change ) {
			$changes[] = array(
				'change'  => $labels[ $change['change'] ],
				'item'    => $change['item'],
				'setting' => $change['setting'],
				'package' => 'json' === $format ? $change['from'] : $this->summarise( $change['from'] ),
				'site'    => 'json' === $format ? $change['to'] : $this->summarise( $change['to'] ),
			);
		}

		if ( ! $changes && 'count' !== $format ) {
			WP_CLI::success( 'The site matches the package.' );
		} else {
			Utils\format_items( $format, $changes, array( 'change', 'item', 'setting', 'package', 'site' ) );
		}

		if ( $changes && Utils\get_flag_value( $assoc_args, 'exit-code', false ) ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Shorten a value for tabular output.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function summarise( $value ) {
		if ( null === $value ) {
			return '';
		}

		$text = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );

		if ( false !== strpos( $text, "\n" ) ) {
			return sprintf( '(%d lines)', substr_count( $text, "\n" ) + 1 );
		}

		return strlen( $text ) > 60 ? substr( $text, 0, 57 ) . '...' : $text;
	}

	/**
	 * Decode a package file.
	 *
	 * @param string $file Path to the package.
	 * @return array
	 */
	private function read_package( $file ) {
		$package = Package::from_file( $file );

		if ( is_wp_error( $package ) ) {
			WP_CLI::error( $package );
		}

		return $package;
	}

	/**
	 * Print an import or repair report.
	 *
	 * @param array $report Report from Importer.
	 */
	private function print_report( array $report ) {
		foreach ( $report['warnings'] as $warning ) {
			WP_CLI::warning( $warning );
		}

		WP_CLI::success( sprintf( 'Linked %d relationship fields, cleared %d, removed %d stray entries.', $report['linked'], $report['cleared'], $report['stray'] ) );
		WP_CLI::success( sprintf( 'Normalised %d Pods Template slugs.', $report['renamed'] ) );
	}
}
