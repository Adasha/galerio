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
	 * Counts by step and outcome: step => outcome => count.
	 *
	 * @var array<string, array<string, int>>
	 */
	private $counts = array();

	/**
	 * Things for a person to look at, by step.
	 *
	 * @var array<string, string[]>
	 */
	private $notes = array();

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
			'rename'         => array(),
			'medium_tree'    => array(),
			'categorisation' => array(),
			'infer_medium'   => array(),
			'forms'          => array(),
		);
		$this->dry     = (bool) $dry;
	}

	/**
	 * Run every step.
	 *
	 * @return array{counts: array, notes: array, remaining: array} The report. The command fills in `remaining`.
	 */
	public function run() {
		$this->import_package();
		$this->copy_fields();
		$this->merge_links();
		$this->merge_websites();
		$this->check_managed_media();
		$this->rebuild_membership();
		$this->copy_user_fields();
		$this->move_terms();
		$this->clean_up();

		return array(
			'counts'    => $this->counts,
			'notes'     => $this->notes,
			'remaining' => array(),
		);
	}

	/**
	 * Step 2: import the new package alongside the old fields.
	 */
	private function import_package() {
		if ( $this->dry ) {
			$this->count( 'Import package', 'would import' );
			return;
		}

		$report = Importer::import( $this->package );

		if ( is_wp_error( $report ) ) {
			\WP_CLI::error( $report->get_error_message() );
		}

		$this->count( 'Import package', 'imported' );
		$this->refresh_pods();
	}

	/**
	 * Step 3: copy each old field's values to its new name.
	 */
	private function copy_fields() {
		foreach ( Map::RENAMES as $pod => $renames ) {
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

			foreach ( Map::PROFILE_LINKS as $old ) {
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
		foreach ( Map::TAXONOMIES as $old => $new ) {
			foreach ( $this->terms( $old ) as $term ) {
				$parent_name = 'galerio_form' === $old && isset( $this->site['forms'][ $term->name ] ) ? $this->site['forms'][ $term->name ] : '';
				$this->move_term( 'Move terms', $term, $new, $term->name, $parent_name );
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

		foreach ( $this->site['medium_tree'] as $discipline => $forms ) {
			$existing = array_filter(
				$forms,
				function ( $form ) {
					return (bool) $this->find_term( $form, 'galerio_medium' );
				}
			);

			foreach ( $existing as $form ) {
				$this->move_term( 'Medium hierarchy', $this->find_term( $form, 'galerio_medium' ), 'galerio_medium', $form, $discipline );
			}
		}

		$this->categorise();
		$this->infer_mediums();
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

		if ( 'field' === $kind && ! in_array( $name, $retired, true ) ) {
			$this->note( $step, "Field $name isn't in the package or the migration's map, so it was kept." );
			return;
		}

		if ( 'pod' === $kind && ! isset( Map::TAXONOMIES[ $name ] ) ) {
			$this->note( $step, "Pod $name isn't in the package or the migration's map, so it was kept." );
			return;
		}

		$this->count( $step, ( $this->dry ? 'would remove ' : 'removed ' ) . $kind );

		if ( $this->dry ) {
			return;
		}

		switch ( $kind ) {
			case 'pod':
				$this->ensure_taxonomy( $name );
				foreach ( $this->terms( $name ) as $term ) {
					wp_delete_term( $term->term_id, $name );
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

		foreach ( Map::RENAMES as $pod => $renames ) {
			foreach ( array_keys( $renames ) as $old ) {
				$fields[] = "$pod.$old";
			}
		}

		foreach ( Map::PROFILE_LINKS as $old ) {
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
		if ( $field->empty_value() === $value ) {
			return;
		}

		$raw     = self::raw( $id, $field->source );
		$current = $field->normalise( 'link' === $field->type ? self::first( $raw ) : $raw );

		// Pods keeps relationships two-way, so copying one side may already have
		// added items here: keep the old list's order and append what Pods added.
		if ( 'relation' === $field->type && $field->multiple ) {
			$value = array_values( array_unique( array_merge( $value, $current ) ) );
		}

		if ( $current === $value ) {
			$this->count( $step, 'already done' );
			return;
		}

		if ( $field->empty_value() !== $current ) {
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration over taxonomies that may not be registered.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY t.term_id", $taxonomy ) );

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

		foreach ( array_unique( Map::TAXONOMIES ) as $taxonomy ) {
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration; includes drafts, private items and attachments.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft' ORDER BY ID", 'media' === $pod ? 'attachment' : $pod ) ) );
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
		if ( ! isset( $this->counts[ $step ][ $outcome ] ) ) {
			$this->counts[ $step ][ $outcome ] = 0;
		}
		$this->counts[ $step ][ $outcome ] += $by;
	}

	/**
	 * Note something for a person to look at.
	 *
	 * @param string $step Step.
	 * @param string $text Note.
	 */
	private function note( $step, $text ) {
		$this->notes[ $step ][] = $text;
	}
}
