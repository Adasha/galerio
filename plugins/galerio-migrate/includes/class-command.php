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
	 * Turn a classic menu into the block menu the theme's header shows.
	 *
	 * The content model migration runs this in a fresh process, so archive
	 * links get the content types' new addresses.
	 *
	 * ## OPTIONS
	 *
	 * <menu>
	 * : The classic menu's ID.
	 *
	 * @param array $args Positional arguments.
	 */
	public function navigation( $args ) {
		WP_CLI::log( Site_Content::convert_menu( (int) $args[0] ) );
	}

	/**
	 * Remove a theme's templates, template parts and global styles saved in the Site Editor.
	 *
	 * The theme's own files are used again afterwards. Changes made in the Site
	 * Editor are lost, so back up the database first. The one thing kept is
	 * which menu each Navigation block showed: a theme's files can't name a
	 * site's menus, so the theme's version is saved with the same menus chosen.
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

		$menus = array();

		foreach ( $posts as $post ) {
			WP_CLI::log( sprintf( '%s %s: %s (%s)', $dry ? 'Would remove' : 'Removing', $post->post_type, $post->post_title, $post->post_name ) );

			$refs = self::menu_refs( parse_blocks( $post->post_content ) );
			if ( $refs && 'wp_global_styles' !== $post->post_type ) {
				$menus[ $post->post_type ][ $post->post_name ] = $refs;
			}

			if ( ! $dry ) {
				wp_delete_post( $post->ID, true );
			}
		}

		foreach ( $menus as $type => $slugs ) {
			foreach ( $slugs as $slug => $refs ) {
				$this->keep_menus( $theme, $type, $slug, $refs, $dry );
			}
		}

		WP_CLI::success( sprintf( '%d Site Editor changes %s for %s.', count( $posts ), $dry ? 'would be removed' : 'removed', $theme ) );
	}

	/**
	 * Save the theme's version of a template or part with the menus its removed copy showed.
	 *
	 * Navigation blocks are matched in order; ones that already name a menu are left alone.
	 *
	 * @param string $theme Theme slug.
	 * @param string $type  wp_template or wp_template_part.
	 * @param string $slug  Template or part slug.
	 * @param int[]  $refs  Menu (wp_navigation) IDs, in block order.
	 * @param bool   $dry   Report only.
	 */
	private function keep_menus( $theme, $type, $slug, array $refs, $dry ) {
		$template = get_block_template( $theme . '//' . $slug, $type );
		$label    = sprintf( '%s %s (menus %s)', $type, $slug, implode( ', ', $refs ) );

		if ( $dry ) {
			WP_CLI::log( "Would keep the menu choice for $label." );
			return;
		}

		if ( ! $template || 'theme' !== $template->source ) {
			WP_CLI::log( "The theme has no $type $slug, so its menu choice wasn't kept: $label." );
			return;
		}

		$blocks = parse_blocks( $template->content );
		$set    = self::set_menu_refs( $blocks, $refs );

		if ( ! $set ) {
			return;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_name'    => $slug,
				'post_title'   => $template->title,
				'post_status'  => 'publish',
				'post_content' => serialize_blocks( $blocks ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "Couldn't keep the menu choice for $label: " . $id->get_error_message() );
			return;
		}

		wp_set_object_terms( $id, $theme, 'wp_theme' );
		if ( 'wp_template_part' === $type ) {
			wp_set_object_terms( $id, $template->area, 'wp_template_part_area' );
		}

		WP_CLI::log( "Kept the menu choice for $label." );
	}

	/**
	 * The menus Navigation blocks show, in block order.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return int[]
	 */
	private static function menu_refs( array $blocks ) {
		$refs = array();

		foreach ( $blocks as $block ) {
			if ( 'core/navigation' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
				$refs[] = (int) $block['attrs']['ref'];
			}
			$refs = array_merge( $refs, self::menu_refs( $block['innerBlocks'] ) );
		}

		return $refs;
	}

	/**
	 * Give Navigation blocks that name no menu the next of a list of menus.
	 *
	 * @param array[] $blocks Parsed blocks, changed in place.
	 * @param int[]   $refs   Menus to use, in order; used ones are removed.
	 * @return int How many blocks were given a menu.
	 */
	private static function set_menu_refs( array &$blocks, array &$refs ) {
		$set = 0;

		foreach ( $blocks as &$block ) {
			if ( ! $refs ) {
				break;
			}

			if ( 'core/navigation' === $block['blockName'] && empty( $block['attrs']['ref'] ) ) {
				$block['attrs']['ref'] = array_shift( $refs );
				++$set;
			}

			$set += self::set_menu_refs( $block['innerBlocks'], $refs );
		}

		return $set;
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
