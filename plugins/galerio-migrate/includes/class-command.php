<?php
/**
 * WP-CLI commands.
 *
 * @package Galerio\Migrate
 */

namespace Galerio\Migrate;

use Galerio\Core\Pods\Package;
use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Moves a site onto Galerio's content model.
 */
final class Command {

	/**
	 * Move the site's content from the old Pods model onto Galerio's.
	 *
	 * Imports the Galerio Pods package, copies each old field's values to its
	 * new name, merges link and website fields, rebuilds collective membership,
	 * moves and tidies terms using the site's decisions, then removes what the
	 * package no longer has. Safe to re-run. Back up the database first:
	 * `wp db export`.
	 *
	 * ## OPTIONS
	 *
	 * --site=<site>
	 * : The site's term decisions: a file in sites/, without .php (e.g. galerio-net).
	 *
	 * [--package=<file>]
	 * : The Galerio Pods package. Defaults to the one bundled with this plugin,
	 * or data/pods/galerio.json in a wp-env site.
	 *
	 * [--dry-run]
	 * : Report what would change, without changing anything.
	 *
	 * [--yes]
	 * : Run for real. Required unless --dry-run is given.
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio migrate content-model --site=galerio-net --dry-run
	 *     wp db export before-migration.sql && wp galerio migrate content-model --site=galerio-net --yes
	 *
	 * @subcommand content-model
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function content_model( $args, $assoc_args ) {
		$dry = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! $dry && ! Utils\get_flag_value( $assoc_args, 'yes', false ) ) {
			WP_CLI::error( 'This changes the database. Back it up (wp db export), try --dry-run, then run again with --yes.' );
		}

		$site_file = GALERIO_MIGRATE_DIR . 'sites/' . sanitize_file_name( $assoc_args['site'] ) . '.php';

		if ( ! is_readable( $site_file ) ) {
			WP_CLI::error( "No site decisions at $site_file." );
		}

		$package = Package::from_file( $this->package_file( $assoc_args ) );

		if ( is_wp_error( $package ) ) {
			WP_CLI::error( $package->get_error_message() );
		}

		WP_CLI::log( $dry ? 'Dry run: nothing will be changed.' : 'Migrating.' );

		$package_file = $this->package_file( $assoc_args );
		$report       = ( new Content_Model( $package, require $site_file, $dry ) )->run();

		if ( ! $dry ) {
			// Pods caches its setup for the whole request, so compare in a fresh process.
			$diff                = WP_CLI::runcommand(
				'galerio pods diff ' . escapeshellarg( $package_file ) . ' --format=json',
				array(
					'launch'     => true,
					'return'     => 'all',
					'exit_error' => false,
				)
			);
			$report['remaining'] = (array) json_decode( $diff->stdout, true );
		}

		$this->print_report( $report );

		if ( $dry ) {
			WP_CLI::success( 'Dry run finished.' );
		} elseif ( $report['remaining'] ) {
			WP_CLI::warning( sprintf( 'Migrated, but %d differences from the package remain (listed above).', count( $report['remaining'] ) ) );
		} else {
			WP_CLI::success( 'Migrated. The site matches the package.' );
		}
	}

	/**
	 * Remove a theme's templates, template parts and global styles saved in the Site Editor.
	 *
	 * The theme's own files are used again afterwards. Changes made in the Site
	 * Editor are lost, so back up the database first.
	 *
	 * ## OPTIONS
	 *
	 * [--theme=<theme>]
	 * : Theme slug. Defaults to the active theme.
	 *
	 * [--dry-run]
	 * : List what would be removed.
	 *
	 * [--yes]
	 * : Remove them. Required unless --dry-run is given.
	 *
	 * @subcommand theme-reset
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function theme_reset( $args, $assoc_args ) {
		$dry   = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$theme = isset( $assoc_args['theme'] ) ? $assoc_args['theme'] : get_stylesheet();

		if ( ! $dry && ! Utils\get_flag_value( $assoc_args, 'yes', false ) ) {
			WP_CLI::error( 'This deletes Site Editor changes. Back up the database, try --dry-run, then run again with --yes.' );
		}

		$posts = get_posts(
			array(
				'post_type'      => array( 'wp_template', 'wp_template_part', 'wp_global_styles' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One-off command.
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $theme,
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			WP_CLI::log( sprintf( '%s %s: %s (%s)', $dry ? 'Would remove' : 'Removing', $post->post_type, $post->post_title, $post->post_name ) );

			if ( ! $dry ) {
				wp_delete_post( $post->ID, true );
			}
		}

		WP_CLI::success( sprintf( '%d Site Editor changes %s for %s.', count( $posts ), $dry ? 'would be removed' : 'removed', $theme ) );
	}

	/**
	 * The package file to use.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return string
	 */
	private function package_file( array $assoc_args ) {
		$candidates = isset( $assoc_args['package'] )
			? array( $assoc_args['package'] )
			: array( GALERIO_MIGRATE_DIR . 'package/galerio.json', WP_CONTENT_DIR . '/galerio-data/pods/galerio.json' );

		foreach ( $candidates as $file ) {
			if ( is_readable( $file ) ) {
				return $file;
			}
		}

		WP_CLI::error( 'No Galerio Pods package found. Pass --package=<file>.' );
	}

	/**
	 * Print the counts, notes and remaining differences.
	 *
	 * @param array $report Report from Content_Model::run().
	 */
	private function print_report( array $report ) {
		$rows = array();

		foreach ( $report['counts'] as $step => $outcomes ) {
			foreach ( $outcomes as $outcome => $count ) {
				$rows[] = compact( 'step', 'outcome', 'count' );
			}
		}

		if ( $rows ) {
			Utils\format_items( 'table', $rows, array( 'step', 'outcome', 'count' ) );
		}

		foreach ( $report['notes'] as $step => $notes ) {
			WP_CLI::log( "\n$step:" );
			foreach ( $notes as $note ) {
				WP_CLI::log( "  - $note" );
			}
		}

		if ( $report['remaining'] ) {
			WP_CLI::log( "\nStill different from the package:" );
			Utils\format_items( 'table', $report['remaining'], array( 'change', 'item', 'setting' ) );
		}
	}
}
