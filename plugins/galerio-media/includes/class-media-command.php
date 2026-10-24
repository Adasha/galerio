<?php
/**
 * WP-CLI commands for Galerio Media.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Galerio's media.
 */
final class Media_Command {

	/**
	 * Copy FileBird's folders, and the files in them, into Galerio's folders.
	 *
	 * Shared FileBird folders become shared folders; each person's own become
	 * theirs, and Settings → Media is switched to allow personal folders.
	 * FileBird is left as it was. Safe to run again.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Count what would be imported without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio media import-filebird --dry-run
	 *     wp galerio media import-filebird
	 *
	 * @subcommand import-filebird
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Options.
	 */
	public function import_filebird( $args, $assoc_args ) {
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$report  = FileBird_Import::run( $dry_run );

		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report->get_error_message() );
		}

		$verb = $dry_run ? 'Would import' : 'Imported';

		WP_CLI::log( sprintf( '%s %d folders (%d already there) and filed %d files.', $verb, $report['folders'], $report['reused'], $report['files'] ) );

		if ( $report['people'] ) {
			WP_CLI::log( sprintf( '%d %s own folders%s.', $report['people'], 1 === $report['people'] ? "person's" : "people's", $dry_run ? ' would be kept as personal folders' : ' are kept as personal folders, and personal folders are now on' ) );
		}

		if ( $report['missing'] ) {
			WP_CLI::warning( sprintf( '%d filed items no longer exist and were skipped.', $report['missing'] ) );
		}

		WP_CLI::success( $dry_run ? 'Dry run: nothing changed.' : 'Done. FileBird is unchanged; switch it off once the folders look right.' );
	}
}
