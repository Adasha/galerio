<?php
/**
 * The content model migration.
 *
 * @package Galerio\Migrate
 */

namespace Galerio\Migrate;

use Galerio\Core\Fields\Field;
use Galerio\Core\Pods\Importer;
use Galerio\Core\Pods\Package;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Moves a site's content from the old Pods model onto Galerio's, in the
 * steps of the content model proposal.
 *
 * Every step can be re-run: a value already in its new place is left alone,
 * and a new field that already holds something different is reported, never
 * overwritten. In a dry run nothing is written and each step reports what it
 * would do.
 */
final class Content_Model {

	/**
	 * Report nothing is written.
	 *
	 * @var bool
	 */
	private $dry;

	/**
	 * The package to move onto.
	 *
	 * @var array
	 */
	private $package;

	/**
	 * The site's term decisions, from sites/<site>.php.
	 *
	 * @var array
	 */
	private $site;

	/**
	 * What this run did.
	 *
	 * @var Report
	 */
	private $report;

	/**
	 * Fields to keep that the package doesn't have, saved before the import, each with its group.
	 *
	 * @var array[]
	 */
	private $kept = array();

	/**
	 * Whether terms have moved to their new taxonomies yet.
	 *
	 * @var bool
	 */
	private $terms_moved = false;

	/**
	 * Set up a run.
	 *
	 * @param array $package The Galerio Pods package.
	 * @param array $site    The site's term decisions.
	 * @param bool  $dry     Report without writing.
	 */
	public function __construct( array $package, array $site, $dry ) {
		$this->package = $package;
		$this->site    = $site + array(
			'post_types'      => array(),
			'taxonomies'      => array(),
			'drop_taxonomies' => array(),
			'fields'          => array(),
			'profile_links'   => array(),
			'retire'          => array(),
			'settings'        => array(),
			'rename'          => array(),
			'labels'          => array(),
			'medium_tree'     => array(),
			'categorisation'  => array(),
			'infer_medium'    => array(),
			'forms'           => array(),
		);
		$this->dry     = (bool) $dry;
		$this->report  = new Report();
	}

	/**
	 * Run every step.
	 *
	 * @return array{counts: array, notes: array, remaining: array} The report. The command fills in `remaining`.
	 */
	public function run() {
		$this->move_post_types();
		$this->import_package();
		$this->regroup_fields();
		$this->copy_fields();
		$this->merge_links();
		$this->merge_websites();
		$this->check_managed_media();
		$this->rebuild_membership();
		$this->copy_user_fields();
		$this->move_terms();
		$this->apply_settings();
		$this->clean_up();

		( new Relationships( $this->report, $this->dry ) )->rebuild();
		( new Site_Content( $this->site, $this->report, $this->dry ) )->run();

		return array(
			'counts'    => $this->report->counts,
			'notes'     => $this->report->notes,
			'remaining' => array(),
		);
	}

	/**
	 * Step 1: move items of an old post type to their Galerio type, keeping
	 * their IDs and fields. Menu items and blocks that name the old type
	 * (Query Loops, Carousels) follow.
	 */
	private function move_post_types() {
		global $wpdb;

		foreach ( $this->site['post_types'] as $old => $new ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off migration.
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $old ) );

			if ( ! $count ) {
				$this->count( 'Move post types', 'already done' );
			} else {
				$this->count( 'Move post types', $this->dry ? 'would move' : 'moved', $count );
				$this->note( 'Move post types', "$count $old items → $new." );

				if ( ! $this->dry ) {
					$wpdb->update( $wpdb->posts, array( 'post_type' => $new ), array( 'post_type' => $old ) );
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = '_menu_item_object' AND meta_value = %s", $new, $old ) );
					wp_cache_flush();
				}
			}

			// Blocks that query the old type, e.g. a Carousel of the latest exhibitions.
			$needle = '"postType":"' . $old . '"';
			$posts  = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type <> 'revision'", '%' . $wpdb->esc_like( $needle ) . '%' ) );

			foreach ( $posts as $post ) {
				$this->count( 'Move post types', $this->dry ? 'would repoint blocks' : 'repointed blocks' );
				$this->note( 'Move post types', sprintf( '%s (#%d): blocks showing %s now show %s.', $post->post_title, $post->ID, $old, $new ) );

				if ( ! $this->dry ) {
					$wpdb->update( $wpdb->posts, array( 'post_content' => str_replace( $needle, '"postType":"' . $new . '"', $post->post_content ) ), array( 'ID' => $post->ID ) );
					clean_post_cache( (int) $post->ID );
				}
			}
			// phpcs:enable
		}
	}

	/**
	 * Set Galerio settings the site's decisions name.
	 */
	private function apply_settings() {
		foreach ( $this->site['settings'] as $name => $value ) {
			$current = get_option( 'galerio_settings_' . $name, null );

			if ( null !== $current && (string) $current === (string) $value ) {
				$this->count( 'Settings', 'already done' );
				continue;
			}

			$this->count( 'Settings', $this->dry ? 'would set' : 'set' );
			$this->note( 'Settings', "$name → $value." );

			if ( ! $this->dry ) {
				pods( 'galerio_settings' )->save( $name, $value );
			}
		}
	}

	/**
	 * Fields copied to a new name: Map::RENAMES and the site's own.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function renames() {
		$renames = Map::RENAMES;

		foreach ( $this->site['fields'] as $pod => $fields ) {
			$renames[ $pod ] = ( isset( $renames[ $pod ] ) ? $renames[ $pod ] : array() ) + $fields;
		}

		return $renames;
	}

	/**
	 * A profile's old link fields, in the order they join `links`.
	 *
	 * @return string[]
	 */
	private function profile_links() {
		return array_merge( Map::PROFILE_LINKS, $this->site['profile_links'] );
	}

	/**
	 * Old taxonomies and the taxonomy their terms move to: Map::TAXONOMIES and the site's own.
	 *
	 * @return array<string, string>
	 */
	private function taxonomies() {
		return Map::TAXONOMIES + $this->site['taxonomies'];
	}

	/**
	 * Step 2: import the new package alongside the old fields.
	 */
	private function import_package() {
		if ( $this->dry ) {
			$this->count( 'Import package', 'would import' );
			return;
		}

		$this->save_kept_fields();

		$report = Importer::import( $this->package );

		if ( is_wp_error( $report ) ) {
			\WP_CLI::error( $report->get_error_message() );
		}

		$this->count( 'Import package', 'imported' );
		$this->refresh_pods();
		$this->restore_kept_fields();
	}

	/**
	 * Save the records of fields the site keeps that the package doesn't have,
	 * such as swansea.art's review checklist: importing drops a group the
	 * package doesn't name, with its fields. Read from the database, since
	 * loading them through Pods leaves its cache in a state the import trips over.
	 */
	private function save_kept_fields() {
		$retired = $this->retired_fields();
		$names   = $this->package_fields();

		foreach ( array_keys( $names ) as $pod_name ) {
			$pod = $this->config_id( 'pod', $pod_name );

			if ( ! $pod || isset( $this->site['post_types'][ $pod_name ] ) ) {
				continue;
			}

			foreach ( get_posts(
				array(
					'post_type'      => '_pods_field',
					'post_parent'    => $pod,
					'post_status'    => 'any',
					'posts_per_page' => -1,
				)
			) as $field ) {
				if ( in_array( $field->post_name, $names[ $pod_name ], true ) || in_array( "$pod_name.{$field->post_name}", $retired, true ) ) {
					continue;
				}

				$group = get_post( (int) get_post_meta( $field->ID, 'group', true ) );

				$this->kept[] = array(
					'field' => $this->config_record( $field ),
					'group' => $group ? $this->config_record( $group ) : null,
				);
			}
		}

		pods_api()->cache_flush_pods();
	}

	/**
	 * A Pods configuration post and its settings.
	 *
	 * @param \WP_Post $post Post.
	 * @return array{post: array, meta: array}
	 */
	private function config_record( $post ) {
		$meta = array();

		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			$meta[ $key ] = array_map( 'maybe_unserialize', $values );
		}

		return array(
			'post' => $post->to_array(),
			'meta' => $meta,
		);
	}

	/**
	 * Recreate kept fields the import dropped, and their group, with their old IDs.
	 */
	private function restore_kept_fields() {
		$restored = false;

		foreach ( $this->kept as $kept ) {
			if ( get_post( $kept['field']['post']['ID'] ) ) {
				continue;
			}

			if ( $kept['group'] && ! get_post( $kept['group']['post']['ID'] ) ) {
				$this->insert_record( $kept['group'] );
			}

			$this->insert_record( $kept['field'] );
			$restored = true;

			$this->count( 'Import package', 'kept site fields restored' );
			$this->note( 'Import package', sprintf( "Kept %s, which the package doesn't have.", $kept['field']['post']['post_name'] ) );
		}

		if ( $restored ) {
			$this->refresh_pods();
		}
	}

	/**
	 * Insert a saved Pods configuration post with its old ID and settings.
	 *
	 * @param array $record Record, from config_record().
	 */
	private function insert_record( array $record ) {
		$post              = $record['post'];
		$post['import_id'] = $post['ID'];
		unset( $post['ID'], $post['guid'] );

		$id = wp_insert_post( wp_slash( $post ), true );

		if ( is_wp_error( $id ) ) {
			$this->note( 'Import package', sprintf( "Couldn't restore %s: %s", $post['post_name'], $id->get_error_message() ) );
			return;
		}

		foreach ( $record['meta'] as $key => $values ) {
			foreach ( $values as $value ) {
				add_post_meta( $id, $key, wp_slash( $value ) );
			}
		}
	}

	/**
	 * The package's field names, by pod.
	 *
	 * @return array<string, string[]>
	 */
	private function package_fields() {
		$names = array();

		foreach ( $this->package['pods'] as $pod ) {
			$names[ $pod['name'] ] = array();

			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $group ) {
				foreach ( isset( $group['fields'] ) ? $group['fields'] : array() as $field ) {
					$names[ $pod['name'] ][] = $field['name'];
				}
			}
		}

		return $names;
	}

	/**
	 * Put each field the package has into the package's group for it.
	 *
	 * Importing updates a field that already exists by name but leaves it in
	 * its old group, and Pods deletes a group's fields with the group, so
	 * without this the clean-up would take fields such as a collection's
	 * description with the old groups.
	 */
	private function regroup_fields() {
		$step = 'Field groups';

		foreach ( $this->package['pods'] as $pod ) {
			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $group ) {
				$group_id = $this->config_id( 'group', $pod['name'] . '/' . $group['name'] );

				foreach ( isset( $group['fields'] ) ? $group['fields'] : array() as $field ) {
					$field_id = $this->config_id( 'field', $pod['name'] . '.' . $field['name'] );

					if ( ! $field_id ) {
						continue;
					}

					$current = (int) get_post_meta( $field_id, 'group', true );

					if ( $group_id && $current === $group_id ) {
						$this->count( $step, 'already done' );
						continue;
					}

					// A dry run hasn't imported the package's groups, so it compares names.
					if ( ! $group_id && get_post_field( 'post_name', $current ) === $group['name'] ) {
						continue;
					}

					$this->count( $step, $this->dry ? 'would move' : 'moved' );

					if ( ! $this->dry && $group_id ) {
						update_post_meta( $field_id, 'group', $group_id );
					}
				}
			}
		}

		if ( ! $this->dry ) {
			$this->refresh_pods();
		}
	}

	/**
	 * Step 3: copy each old field's values to its new name.
	 */
	private function copy_fields() {
		foreach ( $this->renames() as $pod => $renames ) {
			foreach ( $this->items( $pod ) as $id ) {
				foreach ( $renames as $old => $new ) {
					$field = $this->field( $pod, $new );
					$this->put( 'Copy fields', $field, $id, $field->normalise( self::raw( $id, $old ) ), "$pod #$id $old → $new" );
				}
			}
		}
	}

	/**
	 * Step 4a: merge a profile's link fields into `links`.
	 */
	private function merge_links() {
		$field = $this->field( 'galerio_profile', 'links' );

		foreach ( $this->items( 'galerio_profile' ) as $id ) {
			$links = array();

			foreach ( $this->profile_links() as $old ) {
				foreach ( (array) self::raw( $id, $old ) as $url ) {
					$url = trim( (string) $url );

					if ( '' !== $url && ! isset( $links[ $url ] ) ) {
						$links[ $url ] = array(
							'url'   => $url,
							'label' => '',
						);
					}
				}
			}

			$this->put( 'Merge profile links', $field, $id, $field->normalise( array_values( $links ) ), "profile #$id links" );
		}
	}

	/**
	 * Step 4b: merge a work's or collection's website name, URL and dead-link flag into `website`.
	 */
	private function merge_websites() {
		foreach ( array( 'galerio_work', 'galerio_collection' ) as $pod ) {
			$field = $this->field( $pod, 'website' );

			foreach ( $this->items( $pod ) as $id ) {
				$url = trim( (string) self::first( self::raw( $id, 'website_url' ) ) );

				if ( '' === $url ) {
					continue;
				}

				$link = array(
					'url'     => $url,
					'label'   => (string) self::first( self::raw( $id, 'website_name' ) ),
					'offline' => 'galerio_collection' === $pod && (bool) self::first( self::raw( $id, 'dead_link' ) ),
				);

				$this->put( 'Merge websites', $field, $id, $field->normalise( $link ), "$pod #$id website" );
			}
		}
	}

	/**
	 * Step 4c: list collections whose images were hidden by managed_media,
	 * since the template now lays out any images a collection has.
	 */
	private function check_managed_media() {
		foreach ( $this->items( 'galerio_collection' ) as $id ) {
			if ( ! self::first( self::raw( $id, 'managed_media' ) ) && self::raw( $id, 'collection_images' ) ) {
				$this->note( 'Managed media', sprintf( '%s (#%d) had managed media off but has images; they will now be shown.', get_post_field( 'post_title', $id ), $id ) );
			}
		}
	}

	/**
	 * Step 5: rebuild collective membership from collective_members.
	 */
	private function rebuild_membership() {
		$members   = $this->field( 'galerio_profile', 'members' );
		$member_of = $this->field( 'galerio_profile', 'member_of' );
		$expected  = array();

		foreach ( $this->items( 'galerio_profile' ) as $id ) {
			$ids = $members->normalise( self::raw( $id, 'collective_members' ) );

			if ( ! $ids ) {
				continue;
			}

			$this->put( 'Rebuild membership', $members, $id, $ids, "profile #$id members" );

			foreach ( $ids as $member ) {
				$expected[ $member ][] = $id;
			}

			$related = array_intersect( $ids, $members->normalise( self::raw( $id, 'related_profiles' ) ) );
			if ( $related ) {
				$this->note( 'Rebuild membership', sprintf( '%s (#%d) lists members %s in related profiles too; check whether they were added by hand.', get_post_field( 'post_title', $id ), $id, implode( ', ', $related ) ) );
			}
		}

		// Pods pairs members with member_of; this makes sure, without dropping memberships added by hand.
		foreach ( $expected as $id => $collectives ) {
			$current = $member_of->normalise( self::raw( $id, 'member_of' ) );
			$union   = array_values( array_unique( array_merge( $current, $collectives ) ) );

			if ( $union === $current ) {
				$this->count( 'Rebuild membership', 'already done' );
				continue;
			}

			$this->write( 'Rebuild membership', 'written', $member_of, $id, $union );
		}
	}

	/**
	 * Copy user fields, such as the link from a user to their profile.
	 */
	private function copy_user_fields() {
		global $wpdb;

		foreach ( Map::USER_FIELDS as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration, reading raw values past Pods' filters.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''", $old ) );

			foreach ( $rows as $row ) {
				$value   = absint( is_numeric( $row->meta_value ) ? $row->meta_value : self::first( maybe_unserialize( $row->meta_value ) ) );
				$current = absint( get_user_meta( (int) $row->user_id, $new, true ) );

				if ( ! $value || $current === $value ) {
					$this->count( 'Copy user fields', $value ? 'already done' : 'empty' );
					continue;
				}

				if ( $current ) {
					$this->note( 'Copy user fields', sprintf( 'User #%d: %s is already set to %d; left alone.', $row->user_id, $new, $current ) );
					continue;
				}

				$this->count( 'Copy user fields', $this->dry ? 'would write' : 'written' );

				if ( ! $this->dry ) {
					pods( 'user', (int) $row->user_id )->save( $new, $value );
				}
			}
		}
	}

	/**
	 * Step 6: move terms to their new taxonomies, then apply the site's renames,
	 * medium hierarchy, categorisation and inferred mediums.
	 */
	private function move_terms() {
		foreach ( $this->taxonomies() as $old => $new ) {
			foreach ( $this->terms( $old ) as $term ) {
				$parent_name = 'galerio_form' === $old && isset( $this->site['forms'][ $term->name ] ) ? $this->site['forms'][ $term->name ] : '';
				$this->move_term( 'Move terms', $term, $new, $term->name, $parent_name );
			}
		}

		$this->terms_moved = true;

		foreach ( $this->site['drop_taxonomies'] as $taxonomy ) {
			foreach ( $this->terms( $taxonomy ) as $term ) {
				$this->count( 'Drop taxonomies', $this->dry ? 'would delete' : 'deleted' );
				$this->note( 'Drop taxonomies', sprintf( '%s "%s" deleted (%d items).', $taxonomy, $term->name, $term->count ) );

				if ( ! $this->dry ) {
					$this->ensure_taxonomy( $taxonomy );
					wp_delete_term( $term->term_id, $taxonomy );
				}
			}
		}

		foreach ( $this->site['rename'] as $taxonomy => $renames ) {
			foreach ( $this->terms( $taxonomy ) as $term ) {
				if ( ! array_key_exists( $term->name, $renames ) ) {
					continue;
				}

				if ( null === $renames[ $term->name ] ) {
					$this->count( 'Rename terms', $this->dry ? 'would delete' : 'deleted' );
					$this->note( 'Rename terms', sprintf( '%s "%s" deleted (%d items).', $taxonomy, $term->name, $term->count ) );

					if ( ! $this->dry ) {
						wp_delete_term( $term->term_id, $taxonomy );
					}
					continue;
				}

				$this->move_term( 'Rename terms', $term, $taxonomy, $renames[ $term->name ], '' );
			}
		}

		foreach ( $this->site['labels'] as $taxonomy => $names ) {
			foreach ( $names as $name ) {
				$term = $this->find_term( $name, $taxonomy );

				if ( $term ) {
					$this->move_term( 'Labels', $term, 'galerio_label', $term->name, '' );
				}
			}
		}

		$this->medium_tree();

		$this->categorise();
		$this->infer_mediums();
	}

	/**
	 * Arrange mediums as disciplines with forms beneath them, creating the
	 * ones that are missing, so unused forms are there to choose. A form can
	 * sit under more than one discipline; an existing top-level term goes
	 * under the first discipline that lists it.
	 */
	private function medium_tree() {
		$step     = 'Medium hierarchy';
		$taxonomy = 'galerio_medium';
		$tree     = $this->site['medium_tree'];
		$claimed  = array();

		foreach ( $tree as $discipline => $forms ) {
			$discipline_id = $this->tree_term( $step, $discipline, 0, '' );

			foreach ( $forms as $form ) {
				if ( $discipline_id > 0 && $this->find_child( $form, $taxonomy, $discipline_id ) ) {
					$this->count( $step, 'already done' );
					continue;
				}

				$loose = $this->find_child( $form, $taxonomy, 0 );

				if ( $loose && ! isset( $tree[ $loose->name ] ) && ! isset( $claimed[ $loose->term_id ] ) ) {
					$claimed[ $loose->term_id ] = true;
					$this->count( $step, $this->dry ? 'would move' : 'moved' );
					$this->note( $step, sprintf( '"%s" → under "%s" (%d items).', $loose->name, $discipline, $loose->count ) );

					if ( ! $this->dry ) {
						wp_update_term( $loose->term_id, $taxonomy, array( 'parent' => $discipline_id ) );
					}
					continue;
				}

				$this->tree_term( $step, $form, $discipline_id, $discipline );
			}
		}
	}

	/**
	 * A medium term under a parent, created if need be.
	 *
	 * @param string $step        Step.
	 * @param string $name        Name.
	 * @param int    $parent_id   Parent's ID, 0 for the top level, or -1 for a parent a dry run hasn't made.
	 * @param string $parent_name Parent's name, for the report.
	 * @return int The term's ID, or -1 in a dry run when it doesn't exist.
	 */
	private function tree_term( $step, $name, $parent_id, $parent_name ) {
		$term = $parent_id >= 0 ? $this->find_child( $name, 'galerio_medium', $parent_id ) : null;

		if ( $term ) {
			return (int) $term->term_id;
		}

		$this->count( $step, $this->dry ? 'would create' : 'created' );
		$this->note( $step, $parent_name ? "\"$name\" created under \"$parent_name\"." : "\"$name\" created." );

		if ( $this->dry ) {
			return -1;
		}

		$this->ensure_taxonomy( 'galerio_medium' );
		$created = wp_insert_term( $name, 'galerio_medium', array( 'parent' => $parent_id ) );

		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	/**
	 * A term by name under a given parent.
	 *
	 * @param string $name      Name.
	 * @param string $taxonomy  Taxonomy.
	 * @param int    $parent_id Parent's ID, 0 for the top level.
	 * @return \WP_Term|null
	 */
	private function find_child( $name, $taxonomy, $parent_id ) {
		foreach ( $this->terms( $taxonomy ) as $term ) {
			if ( (int) $term->parent === (int) $parent_id && 0 === strcasecmp( $term->name, $name ) ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Turn work_categorisation values into Labels.
	 */
	private function categorise() {
		foreach ( $this->items( 'galerio_work' ) as $id ) {
			foreach ( explode( ',', (string) self::first( self::raw( $id, 'work_categorisation' ) ) ) as $value ) {
				$value = trim( $value );

				if ( '' === $value ) {
					continue;
				}

				$label = isset( $this->site['categorisation'][ $value ] ) ? $this->site['categorisation'][ $value ] : $value;
				$this->tag( 'Categorisation', $id, $label, 'galerio_label', '' );
			}
		}
	}

	/**
	 * Give works with no medium terms a medium from their free-text medium.
	 */
	private function infer_mediums() {
		foreach ( $this->items( 'galerio_work' ) as $id ) {
			if ( $this->has_terms( $id, 'galerio_medium' ) ) {
				continue;
			}

			$text = (string) self::first( self::raw( $id, 'medium' ) );
			$text = '' !== $text ? $text : (string) self::first( self::raw( $id, 'work_medium' ) );

			foreach ( $this->site['infer_medium'] as $pattern => $medium ) {
				if ( '' !== $text && preg_match( $pattern, $text ) ) {
					$this->tag( 'Inferred mediums', $id, $medium, 'galerio_medium', '' );
					$this->note( 'Inferred mediums', sprintf( '%s (#%d): "%s" → %s.', get_post_field( 'post_title', $id ), $id, $text, $medium ) );
				}
			}
		}
	}

	/**
	 * Steps 7 and 8: remove what the package no longer has (old fields once
	 * their values are copied, old taxonomies, Pods Templates, stale settings).
	 */
	private function clean_up() {
		$retired  = $this->retired_fields();
		$removals = array();

		// Retired fields go explicitly, values included: Pods can delete a field
		// along with its group and leave the values behind.
		foreach ( $retired as $name ) {
			$this->remove_field( $name );
		}

		foreach ( Package::diff( Package::canonical( $this->package ), Package::canonical( $this->site_package() ) ) as $change ) {
			list( $kind, $name ) = array_pad( explode( ' ', $change['item'], 2 ), 2, '' );

			if ( 'settings' === $kind ) {
				continue;
			}

			if ( 'added' === $change['change'] && '' === $change['setting'] ) {
				$removals[ $kind ][] = $name;
				continue;
			}

			if ( 'added' === $change['change'] ) {
				$this->remove_setting( $kind, $name, $change['setting'] );
				continue;
			}

			if ( ! $this->dry ) {
				$this->note( 'Clean up', sprintf( '%s %s %s differs from the package.', $change['item'], $change['setting'], $change['change'] ) );
			}
		}

		foreach ( array( 'field', 'group', 'pod', 'template' ) as $kind ) {
			foreach ( isset( $removals[ $kind ] ) ? $removals[ $kind ] : array() as $name ) {
				$this->remove_item( $kind, $name, $retired );
			}
		}

		if ( ! $this->dry ) {
			$this->refresh_pods();
		}
	}

	/**
	 * Remove a pod, group, field or template the package doesn't have.
	 *
	 * @param string   $kind    pod, group, field or template.
	 * @param string   $name    Its name: "pod", "pod/group", "pod.field" or "template".
	 * @param string[] $retired Fields known to be copied or retired, as "pod.field".
	 */
	private function remove_item( $kind, $name, array $retired ) {
		$api  = pods_api();
		$step = 'Clean up';

		$old_types = $this->site['post_types'];
		$old_pods  = $this->taxonomies() + array_flip( $this->site['drop_taxonomies'] ) + $old_types;

		if ( 'field' === $kind && ! in_array( $name, $retired, true ) ) {
			// A moved post type's fields go with its pod; their values now belong to the new type's fields.
			if ( ! isset( $old_types[ strtok( $name, '.' ) ] ) ) {
				$this->note( $step, "Field $name isn't in the package or the migration's map, so it was kept." );
			}
			return;
		}

		if ( 'pod' === $kind && ! isset( $old_pods[ $name ] ) ) {
			$this->note( $step, "Pod $name isn't in the package or the migration's map, so it was kept." );
			return;
		}

		// Pods deletes a group's fields with it, so a group holding fields that are kept stays too.
		if ( 'group' === $kind ) {
			$kept = $this->kept_fields( $name, $retired );

			if ( $kept ) {
				$this->note( $step, sprintf( 'Group %s was kept for its fields %s.', $name, implode( ', ', $kept ) ) );
				return;
			}
		}

		$this->count( $step, ( $this->dry ? 'would remove ' : 'removed ' ) . $kind );

		if ( $this->dry ) {
			return;
		}

		switch ( $kind ) {
			case 'pod':
				// A post type's items have moved already; a taxonomy's leftover terms go with it.
				if ( ! isset( $old_types[ $name ] ) ) {
					$this->ensure_taxonomy( $name );
					foreach ( $this->terms( $name ) as $term ) {
						wp_delete_term( $term->term_id, $name );
					}
				}
				$api->delete_pod( array( 'name' => $name ) );
				break;

			case 'group':
				$id = $this->config_id( 'group', $name );
				if ( $id ) {
					$api->delete_group( array( 'id' => $id ) );
				}
				break;

			case 'field':
				$this->remove_field( $name );
				break;

			case 'template':
				$template = $api->load_template( array( 'name' => $name ) );
				if ( $template ) {
					$api->delete_template( array( 'id' => (int) $template['id'] ) );
				}
				break;
		}
	}

	/**
	 * The fields of a group that neither the package has nor the migration retires.
	 *
	 * @param string   $name    Group, as "pod/group".
	 * @param string[] $retired Fields known to be copied or retired, as "pod.field".
	 * @return string[] Field names.
	 */
	private function kept_fields( $name, array $retired ) {
		list( $pod ) = explode( '/', $name, 2 );

		$id = $this->config_id( 'group', $name );

		if ( ! $id || isset( $this->site['post_types'][ $pod ] ) ) {
			return array();
		}

		$in_package = array();
		foreach ( $this->package['pods'] as $package_pod ) {
			if ( $package_pod['name'] !== $pod ) {
				continue;
			}
			foreach ( isset( $package_pod['groups'] ) ? $package_pod['groups'] : array() as $group ) {
				foreach ( isset( $group['fields'] ) ? $group['fields'] : array() as $field ) {
					$in_package[] = $field['name'];
				}
			}
		}

		$kept = array();
		foreach ( get_posts(
			array(
				'post_type'      => '_pods_field',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => 'group', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration.
				'meta_value'     => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-off migration.
			)
		) as $field ) {
			if ( ! in_array( $field->post_name, $in_package, true ) && ! in_array( "$pod.{$field->post_name}", $retired, true ) ) {
				$kept[] = $field->post_name;
			}
		}

		return $kept;
	}

	/**
	 * Remove a retired field and its values, if there are any left.
	 *
	 * @param string $name Field, as "pod.field".
	 */
	private function remove_field( $name ) {
		list( $pod, $field ) = explode( '.', $name, 2 );

		$exists = (bool) $this->config_id( 'field', $name );
		$values = $this->count_values( $pod, $field );

		if ( ! $exists && ! $values ) {
			return;
		}

		$this->count( 'Clean up', $this->dry ? 'would remove field' : 'removed field' );
		$this->count( 'Clean up', $this->dry ? 'old values to delete' : 'old values deleted', $values );

		if ( $values && in_array( $name, $this->site['retire'], true ) ) {
			$this->note_values( $pod, $field );
		}

		// Settings pods keep their values in options.
		if ( 'galerio_settings' === $pod && ! $this->dry ) {
			delete_option( "{$pod}_{$field}" );
		}

		if ( $this->dry ) {
			return;
		}

		if ( $exists ) {
			pods_api()->delete_field(
				array(
					'pod'  => $pod,
					'name' => $field,
				)
			);
		}

		$this->delete_values( $pod, $field );
	}

	/**
	 * List a retired field's values in the report, so nothing goes unseen.
	 *
	 * @param string $pod   Pod.
	 * @param string $field Field.
	 */
	private function note_values( $pod, $field ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off migration.
		if ( 'user' === $pod ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.user_login AS item, m.meta_value AS value FROM {$wpdb->usermeta} m JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE m.meta_key = %s AND m.meta_value <> '' ORDER BY u.user_login", $field ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT CONCAT(p.post_title, ' (#', p.ID, ')') AS item, m.meta_value AS value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = %s AND m.meta_key = %s AND m.meta_value <> '' ORDER BY p.ID", 'media' === $pod ? 'attachment' : $pod, $field ) );
		}
		// phpcs:enable

		foreach ( $rows as $row ) {
			$value = $row->value;

			// A term ID, as a relationship to a taxonomy stores it.
			if ( is_numeric( $value ) ) {
				$term  = get_term( (int) $value );
				$value = $term && ! is_wp_error( $term ) ? $term->name : $value;
			}

			$this->note( 'Retired values', sprintf( '%s.%s: %s: %s', $pod, $field, $row->item, wp_trim_words( (string) $value, 12 ) ) );
		}
	}

	/**
	 * How many stored values a field has.
	 *
	 * @param string $pod   Pod.
	 * @param string $field Field.
	 * @return int
	 */
	private function count_values( $pod, $field ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off migration.
		if ( 'user' === $pod ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s)", $field, '_pods_' . $field ) );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key IN (%s, %s)", 'media' === $pod ? 'attachment' : $pod, $field, '_pods_' . $field ) );
		// phpcs:enable
	}

	/**
	 * Remove a setting the package no longer has from a pod, group or field.
	 *
	 * @param string $kind    pod, group or field.
	 * @param string $name    Its name.
	 * @param string $setting Setting.
	 */
	private function remove_setting( $kind, $name, $setting ) {
		$this->count( 'Stale settings', $this->dry ? 'would remove' : 'removed' );

		if ( $this->dry ) {
			return;
		}

		$id = $this->config_id( $kind, $name );

		if ( $id ) {
			delete_post_meta( $id, $setting );
		}
	}

	/**
	 * The post ID Pods stores a pod's, group's or field's settings on.
	 *
	 * @param string $kind pod, group or field.
	 * @param string $name Its name.
	 * @return int
	 */
	private function config_id( $kind, $name ) {
		$api = pods_api();

		switch ( $kind ) {
			case 'pod':
				$object = $api->load_pod( array( 'name' => $name ), false );
				break;
			case 'group':
				list( $pod, $group ) = explode( '/', $name, 2 );
				$object              = $api->load_group(
					array(
						'pod'  => $pod,
						'name' => $group,
					)
				);
				break;
			case 'field':
				list( $pod, $field ) = explode( '.', $name, 2 );
				$object              = $api->load_field(
					array(
						'pod'  => $pod,
						'name' => $field,
					)
				);
				break;
			default:
				$object = null;
		}

		return $object ? (int) $object['id'] : 0;
	}

	/**
	 * Delete a removed field's stored values.
	 *
	 * @param string $pod   Pod.
	 * @param string $field Field.
	 */
	private function delete_values( $pod, $field ) {
		global $wpdb;

		if ( 'user' === $pod ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s)", $field, '_pods_' . $field ) );
			return;
		}

		$ids = $this->items( $pod );

		if ( ! $ids ) {
			return;
		}

		$in = implode( ',', array_map( 'absint', $ids ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off migration; $in is a list of integers.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in) AND meta_key IN (%s, %s)", $field, '_pods_' . $field ) );
		// phpcs:enable
	}

	/**
	 * The old fields the migration copies or retires, as "pod.field".
	 *
	 * @return string[]
	 */
	private function retired_fields() {
		$fields = array(
			'galerio_work.website_name',
			'galerio_work.website_url',
			'galerio_work.work_categorisation',
			'galerio_collection.website_name',
			'galerio_collection.website_url',
			'galerio_collection.dead_link',
			'galerio_collection.managed_media',
			'galerio_profile.collective_members',
			'galerio_profile.checklist_advice_txt',
			'galerio_profile.links_advice_txt',
			'user.profile_page',
		);

		$fields = array_merge( $fields, $this->site['retire'] );

		foreach ( $this->renames() as $pod => $renames ) {
			foreach ( array_keys( $renames ) as $old ) {
				$fields[] = "$pod.$old";
			}
		}

		foreach ( $this->profile_links() as $old ) {
			$fields[] = "galerio_profile.$old";
		}

		return $fields;
	}

	/**
	 * The site's Pods setup, as a package.
	 *
	 * @return array
	 */
	private function site_package() {
		$package = Package::from_site();

		if ( is_wp_error( $package ) ) {
			\WP_CLI::error( $package->get_error_message() );
		}

		return $package;
	}

	/**
	 * Copy a value into a new field unless it's already there, or the field holds something else.
	 *
	 * @param string $step  Step.
	 * @param Field  $field New field.
	 * @param int    $id    Post ID.
	 * @param mixed  $value Normalised value.
	 * @param string $what  Description for the report.
	 */
	private function put( $step, Field $field, $id, $value, $what ) {
		$raw     = self::raw( $id, $field->source );
		$current = $field->normalise( 'link' === $field->type ? self::first( $raw ) : $raw );

		// Links to deleted items are dropped, from the old list and the new.
		if ( $field->holds_ids() ) {
			$kept = self::existing( $value );

			if ( $kept !== $value ) {
				$this->count( $step, 'links to deleted items dropped', count( (array) $value ) - count( (array) $kept ) );
				$value = $kept;
			}

			$current = self::existing( $current );
		}

		if ( $field->empty_value() === $value ) {
			return;
		}

		// Pods keeps relationships two-way, so copying one side may already have
		// added items here, in its own order: keep the old list's order and append
		// what Pods added. Nothing in the list is lost, so this is never a conflict.
		$merge = 'relation' === $field->type && $field->multiple;

		if ( $merge ) {
			$value = array_values( array_unique( array_merge( $value, $current ) ) );
		}

		if ( $current === $value ) {
			$this->count( $step, 'already done' );
			return;
		}

		if ( ! $merge && $field->empty_value() !== $current ) {
			$this->note( $step, "$what: the new field already holds something different, so it was left alone." );
			$this->count( $step, 'conflicts' );
			return;
		}

		$this->write( $step, 'copied', $field, $id, $value );
	}

	/**
	 * Write a value, or count it in a dry run.
	 *
	 * @param string $step    Step.
	 * @param string $outcome Outcome to count.
	 * @param Field  $field   Field.
	 * @param int    $id      Post ID.
	 * @param mixed  $value   Value.
	 */
	private function write( $step, $outcome, Field $field, $id, $value ) {
		$this->count( $step, $this->dry ? 'would write' : $outcome );

		if ( ! $this->dry ) {
			galerio_fields()->save( $field, $id, $value );
		}
	}

	/**
	 * Move a term's posts to a term of a given name (and parent) in a taxonomy, then delete it.
	 *
	 * A term renamed within its own taxonomy keeps its ID; a term whose new
	 * name exists already is merged into it.
	 *
	 * @param string   $step     Step.
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Target taxonomy.
	 * @param string   $name     Target name.
	 * @param string   $parent_name Target parent's name, or empty for the top level.
	 */
	private function move_term( $step, $term, $taxonomy, $name, $parent_name ) {
		$target    = $this->find_term( $name, $taxonomy );
		$parent_id = $parent_name ? $this->ensure_term( $parent_name, $taxonomy, 0 ) : 0;

		if ( $target && (int) $target->term_id === (int) $term->term_id && $target->name === $name && (int) $target->parent === $parent_id ) {
			$this->count( $step, 'already done' );
			return;
		}

		$this->count( $step, $this->dry ? 'would move' : 'moved' );
		$this->note( $step, sprintf( '%s "%s" → %s "%s"%s (%d items).', $term->taxonomy, $term->name, $taxonomy, $name, $parent_name ? " under \"$parent_name\"" : '', $term->count ) );

		if ( $this->dry ) {
			return;
		}

		$this->ensure_taxonomy( $taxonomy );
		$this->ensure_taxonomy( $term->taxonomy );

		$in_place = $target ? (int) $target->term_id === (int) $term->term_id : $term->taxonomy === $taxonomy;

		if ( $in_place ) {
			wp_update_term(
				$term->term_id,
				$taxonomy,
				array(
					'name'   => $name,
					'slug'   => sanitize_title( $name ),
					'parent' => $parent_id,
				)
			);
			return;
		}

		// Nothing of that name in the new taxonomy yet: the term moves whole,
		// keeping its ID (which fields, menus and links may hold) and its parent,
		// which moves with it.
		if ( ! $target ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration; WordPress has no API to move a term between taxonomies.
			$wpdb->update(
				$wpdb->term_taxonomy,
				array(
					'taxonomy' => $taxonomy,
					'parent'   => $parent_name ? $parent_id : (int) $term->parent,
				),
				array( 'term_taxonomy_id' => $term->term_taxonomy_id )
			);
			clean_term_cache( $term->term_id, '', false );
			delete_option( "{$term->taxonomy}_children" );
			delete_option( "{$taxonomy}_children" );

			if ( $name !== $term->name ) {
				wp_update_term( $term->term_id, $taxonomy, array( 'name' => $name ) );
			}
			return;
		}

		$target_id = $this->ensure_term( $name, $taxonomy, $parent_id );

		foreach ( $this->term_objects( $term ) as $object_id ) {
			wp_set_object_terms( $object_id, array( $target_id ), $taxonomy, true );
		}

		wp_delete_term( $term->term_id, $term->taxonomy );
	}

	/**
	 * Add a term (created if need be) to a post.
	 *
	 * @param string $step     Step.
	 * @param int    $id       Post ID.
	 * @param string $name     Term name.
	 * @param string $taxonomy Taxonomy.
	 * @param string $parent_name Parent's name, for a new term.
	 */
	private function tag( $step, $id, $name, $taxonomy, $parent_name ) {
		$term = $this->find_term( $name, $taxonomy );

		if ( $term && in_array( (int) $id, $this->term_objects( $term ), true ) ) {
			$this->count( $step, 'already done' );
			return;
		}

		$this->count( $step, $this->dry ? 'would add' : 'added' );

		if ( ! $this->dry ) {
			$parent_id = $parent_name ? $this->ensure_term( $parent_name, $taxonomy, 0 ) : 0;
			wp_set_object_terms( $id, array( $this->ensure_term( $name, $taxonomy, $parent_id ) ), $taxonomy, true );
		}
	}

	/**
	 * A term by exact name in a taxonomy, read past registration.
	 *
	 * @param string $name     Name.
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term|null
	 */
	private function find_term( $name, $taxonomy ) {
		foreach ( $this->terms( $taxonomy ) as $term ) {
			if ( $term->name === $name ) {
				return $term;
			}
		}

		// Term names compare without case in MySQL, so a case-only rename finds the same term.
		foreach ( $this->terms( $taxonomy ) as $term ) {
			if ( 0 === strcasecmp( $term->name, $name ) ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * A term's ID, creating the term if need be.
	 *
	 * @param string $name      Name.
	 * @param string $taxonomy  Taxonomy.
	 * @param int    $parent_id Parent for a new term, or for an existing one at the top level.
	 * @return int 0 in a dry run when the term doesn't exist.
	 */
	private function ensure_term( $name, $taxonomy, $parent_id ) {
		$term = $this->find_term( $name, $taxonomy );

		if ( $term && $term->name === $name ) {
			if ( $parent_id && (int) $term->parent !== $parent_id && ! $this->dry ) {
				wp_update_term( $term->term_id, $taxonomy, array( 'parent' => $parent_id ) );
			}
			return (int) $term->term_id;
		}

		if ( $this->dry ) {
			$this->note( 'New terms', "$taxonomy \"$name\" would be created." );
			return 0;
		}

		$this->ensure_taxonomy( $taxonomy );
		$created = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent_id ) );

		if ( is_wp_error( $created ) ) {
			$existing = $created->get_error_data( 'term_exists' );
			return $existing ? (int) $existing : 0;
		}

		$this->note( 'New terms', "$taxonomy \"$name\" created." );

		return (int) $created['term_id'];
	}

	/**
	 * A taxonomy's terms, read straight from the database so taxonomies Pods
	 * hasn't registered in this request still count.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return \WP_Term[]
	 */
	private function terms( $taxonomy ) {
		global $wpdb;

		$taxonomies = array( $taxonomy );

		// A dry run hasn't moved old taxonomies' terms, so once past that step it counts them as their new taxonomy's.
		if ( $this->dry && $this->terms_moved ) {
			$taxonomies = array_merge( $taxonomies, array_keys( $this->taxonomies(), $taxonomy, true ) );
		}

		$in = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off migration over taxonomies that may not be registered; $in is placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy IN ($in) ORDER BY t.term_id", $taxonomies ) );

		return array_map(
			function ( $row ) {
				return new \WP_Term( $row );
			},
			$rows
		);
	}

	/**
	 * Whether a post has any terms in a taxonomy.
	 *
	 * @param int    $id       Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	private function has_terms( $id, $taxonomy ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = %s", $id, $taxonomy ) );
	}

	/**
	 * The posts a term is on.
	 *
	 * @param \WP_Term $term Term.
	 * @return int[]
	 */
	private function term_objects( $term ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $term->term_taxonomy_id ) ) );
	}

	/**
	 * Register a taxonomy for this request if Pods hasn't yet, so terms can be written to it.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	private function ensure_taxonomy( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array(),
				array(
					'hierarchical' => true,
					'public'       => false,
					'rewrite'      => false,
				)
			);
		}
	}

	/**
	 * Make Pods load the setup just imported or changed, so this run can use it.
	 */
	private function refresh_pods() {
		pods_api()->cache_flush_pods();

		if ( function_exists( 'pods_init' ) ) {
			pods_init()->setup_content_types( true );
		}

		foreach ( array_unique( $this->taxonomies() ) as $taxonomy ) {
			$this->ensure_taxonomy( $taxonomy );
		}
	}

	/**
	 * A new field from the registry.
	 *
	 * @param string $pod  Pod.
	 * @param string $name Field name.
	 * @return Field
	 */
	private function field( $pod, $name ) {
		$field = galerio_fields()->get( Map::OBJECTS[ $pod ] . '.' . $name );

		if ( ! $field ) {
			\WP_CLI::error( sprintf( 'Galerio Core has no field %s.%s.', Map::OBJECTS[ $pod ], $name ) );
		}

		return $field;
	}

	/**
	 * Every item of a pod, whatever its status.
	 *
	 * @param string $pod Pod.
	 * @return int[]
	 */
	private function items( $pod ) {
		global $wpdb;

		if ( 'user' === $pod ) {
			return array();
		}

		$types = array( 'media' === $pod ? 'attachment' : $pod );

		// A dry run hasn't moved old post types' items, so it counts them as their new type's.
		if ( $this->dry ) {
			$types = array_merge( $types, array_keys( $this->site['post_types'], $pod, true ) );
		}

		$in = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off migration; includes drafts, private items and attachments. $in is placeholders.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status <> 'auto-draft' ORDER BY ID", $types ) ) );
	}

	/**
	 * A field's stored value, read past Pods' filters.
	 *
	 * Relationship and file fields come from Pods' ordered ID list when there
	 * is one; other fields are every stored value, in order.
	 *
	 * @param int    $id  Post ID.
	 * @param string $key Field name.
	 * @return array
	 */
	private static function raw( $id, $key ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off migration, reading raw values past Pods' filters.
		$ordered = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $id, '_pods_' . $key ) );

		if ( null !== $ordered ) {
			$ids = maybe_unserialize( $ordered );

			if ( is_array( $ids ) ) {
				return array_values( array_filter( array_map( 'absint', $ids ) ) );
			}
		}

		$values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $id, $key ) );
		// phpcs:enable

		return array_values(
			array_filter(
				$values,
				function ( $value ) {
					return null !== $value && '' !== $value;
				}
			)
		);
	}

	/**
	 * Keep only IDs of posts that exist: a list, or a single ID (0 when it doesn't).
	 *
	 * @param int|int[] $ids IDs.
	 * @return int|int[]
	 */
	private static function existing( $ids ) {
		if ( ! is_array( $ids ) ) {
			return $ids && get_post_status( (int) $ids ) ? $ids : 0;
		}

		return array_values(
			array_filter(
				$ids,
				function ( $id ) {
					return (bool) get_post_status( (int) $id );
				}
			)
		);
	}

	/**
	 * The first of a list of values.
	 *
	 * @param mixed $values Values.
	 * @return mixed Null when empty.
	 */
	private static function first( $values ) {
		$values = (array) $values;
		return $values ? reset( $values ) : null;
	}

	/**
	 * Count an outcome.
	 *
	 * @param string $step    Step.
	 * @param string $outcome Outcome.
	 * @param int    $by      Amount.
	 */
	private function count( $step, $outcome, $by = 1 ) {
		$this->report->count( $step, $outcome, $by );
	}

	/**
	 * Note something for a person to look at.
	 *
	 * @param string $step Step.
	 * @param string $text Note.
	 */
	private function note( $step, $text ) {
		$this->report->note( $step, $text );
	}
}
