<?php
/**
 * Content type alias tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Aliases\Aliases;
use Galerio\Core\Aliases\Settings_Page;

/**
 * Renaming content types and taxonomies, and redirecting their old addresses.
 *
 * @covers \Galerio\Core\Aliases\Aliases
 * @covers \Galerio\Core\Aliases\Settings_Page
 */
class Test_Aliases extends Galerio_TestCase {

	/**
	 * Registration arguments shaped like the ones Pods passes for collections.
	 *
	 * @return array
	 */
	private static function collection_args() {
		return array(
			'label'       => 'Collections',
			'labels'      => array(
				'name'          => 'Collections',
				'singular_name' => 'Collection',
				'add_new_item'  => 'Add New Collection',
				'not_found'     => 'No collections found',
				'search_items'  => 'Search Collections',
				'all_items'     => 'All COLLECTIONS',
				'parent_item'   => 'Parent Collection Category',
			),
			'rewrite'     => array( 'slug' => 'collection' ),
			'has_archive' => 'collections',
		);
	}

	/**
	 * Clear the aliases after each test.
	 */
	public function tear_down() {
		delete_option( Aliases::OPTION );
		delete_option( Aliases::MOVED_OPTION );
		parent::tear_down();
	}

	/**
	 * Save aliases for one post type.
	 *
	 * @param array $collection Collection aliases.
	 */
	private function alias_collections( array $collection ) {
		update_option( Aliases::OPTION, array( 'types' => array( 'collection' => $collection ) ) );
	}

	/**
	 * With no aliases, registration is untouched.
	 */
	public function test_no_aliases_leaves_args_alone() {
		$args = self::collection_args();

		$this->assertSame( $args, Aliases::filter_post_type_args( $args, 'galerio_collection' ) );
	}

	/**
	 * Other plugins' post types are never touched.
	 */
	public function test_other_post_types_are_ignored() {
		$this->alias_collections( array( 'plural' => 'Exhibitions' ) );
		$args = array( 'label' => 'Books' );

		$this->assertSame( $args, Aliases::filter_post_type_args( $args, 'book' ) );
	}

	/**
	 * Names are replaced through the labels, as whole words, in any case.
	 */
	public function test_labels_are_renamed() {
		$this->alias_collections(
			array(
				'singular' => 'Exhibition',
				'plural'   => 'Exhibitions',
			)
		);

		$args = Aliases::filter_post_type_args( self::collection_args(), 'galerio_collection' );

		$this->assertSame( 'Exhibitions', $args['label'] );
		$this->assertSame( 'Exhibitions', $args['labels']['name'] );
		$this->assertSame( 'Exhibitions', $args['labels']['menu_name'] );
		$this->assertSame( 'Exhibition', $args['labels']['singular_name'] );
		$this->assertSame( 'Add New Exhibition', $args['labels']['add_new_item'] );
		$this->assertSame( 'No exhibitions found', $args['labels']['not_found'] );
		$this->assertSame( 'Search Exhibitions', $args['labels']['search_items'] );
		$this->assertSame( 'All EXHIBITIONS', $args['labels']['all_items'] );
		$this->assertSame( 'Parent Exhibition Category', $args['labels']['parent_item'] );
	}

	/**
	 * Slugs move, and an empty alias keeps the registered slug.
	 */
	public function test_slugs_move() {
		$this->alias_collections( array( 'archive' => 'exhibitions' ) );

		$args = Aliases::filter_post_type_args( self::collection_args(), 'galerio_collection' );

		$this->assertSame( 'collection', $args['rewrite']['slug'] );
		$this->assertSame( 'exhibitions', $args['has_archive'] );
	}

	/**
	 * An archive registered as `true` lives at the single slug, and moves with it.
	 */
	public function test_archive_at_single_slug_follows_it() {
		$this->alias_collections( array( 'slug' => 'exhibition' ) );

		$args                = self::collection_args();
		$args['has_archive'] = true;
		$args                = Aliases::filter_post_type_args( $args, 'galerio_collection' );

		$this->assertSame( 'exhibition', $args['has_archive'] );

		// Put the real registration's originals back.
		$registered = get_post_type_object( 'galerio_collection' );
		Aliases::filter_post_type_args( (array) $registered, 'galerio_collection' );
	}

	/**
	 * Taxonomies are renamed and moved the same way.
	 */
	public function test_taxonomies() {
		update_option(
			Aliases::OPTION,
			array(
				'taxonomies' => array(
					'medium' => array(
						'singular' => 'Format',
						'plural'   => 'Formats',
						'slug'     => 'format',
					),
				),
			)
		);

		$args = Aliases::filter_taxonomy_args(
			array(
				'labels'  => array(
					'name'          => 'Mediums',
					'singular_name' => 'Medium',
					'edit_item'     => 'Edit Medium',
				),
				'rewrite' => array( 'slug' => 'medium' ),
			),
			'galerio_medium'
		);

		$this->assertSame( 'Formats', $args['labels']['name'] );
		$this->assertSame( 'Edit Format', $args['labels']['edit_item'] );
		$this->assertSame( 'format', $args['rewrite']['slug'] );
	}

	/**
	 * The registered Pods types pick up the aliases, so the rest of WordPress sees them.
	 */
	public function test_registered_type_is_aliased() {
		$this->assertSame( 'collection', Aliases::original( 'types', 'collection', 'slug' ) );
		$this->assertSame( 'collections', Aliases::original( 'types', 'collection', 'archive' ) );

		$this->alias_collections( array( 'plural' => 'Exhibitions' ) );
		$args = Aliases::filter_post_type_args( (array) get_post_type_object( 'galerio_collection' ), 'galerio_collection' );

		$this->assertSame( 'Exhibitions', $args['label'] );
	}

	/**
	 * Saving new slugs records the old ones for redirecting.
	 */
	public function test_saving_records_moved_slugs() {
		$this->alias_collections(
			array(
				'slug'    => 'exhibition',
				'archive' => 'exhibitions',
			)
		);

		$this->assertSame(
			array(
				'collection'  => 'exhibition',
				'collections' => 'exhibitions',
			),
			Aliases::moved()
		);

		// Renaming back stops the original addresses redirecting, and redirects the aliases instead.
		update_option( Aliases::OPTION, array() );

		$this->assertSame(
			array(
				'exhibition'  => 'collection',
				'exhibitions' => 'collections',
			),
			Aliases::moved()
		);
	}

	/**
	 * A slug renamed twice redirects straight to the latest one.
	 */
	public function test_move_collapses_chains() {
		$moved = Aliases::move( array(), 'collection', 'exhibition' );
		$moved = Aliases::move( $moved, 'exhibition', 'show' );

		$this->assertSame(
			array(
				'collection' => 'show',
				'exhibition' => 'show',
			),
			$moved
		);
	}

	/**
	 * Paths under an old slug move, keeping the rest of the path.
	 */
	public function test_moved_path() {
		$moved = array(
			'collection'          => 'exhibition',
			'collection-category' => 'exhibition-type',
		);

		$this->assertSame( 'exhibition', Aliases::moved_path( 'collection', $moved ) );
		$this->assertSame( 'exhibition/shorelines/page/2', Aliases::moved_path( 'collection/shorelines/page/2', $moved ) );
		$this->assertSame( 'exhibition-type/ceramics', Aliases::moved_path( 'collection-category/ceramics', $moved ) );
		$this->assertNull( Aliases::moved_path( 'collections', $moved ) );
		$this->assertNull( Aliases::moved_path( 'about', $moved ) );
	}

	/**
	 * Redirects are on until switched off.
	 */
	public function test_redirect_setting() {
		$this->assertTrue( Aliases::get()['redirect'] );

		update_option( Aliases::OPTION, array( 'redirect' => false ) );

		$this->assertFalse( Aliases::get()['redirect'] );
	}

	/**
	 * Submitted values are cleaned: names as text, slugs as slugs.
	 */
	public function test_sanitize() {
		$clean = Settings_Page::sanitize(
			array(
				'types'    => array(
					'collection' => array(
						'singular' => ' <b>Exhibition</b> ',
						'slug'     => 'My Exhibitions!',
					),
				),
				'redirect' => '1',
			)
		);

		$this->assertSame( 'Exhibition', $clean['types']['collection']['singular'] );
		$this->assertSame( 'my-exhibitions', $clean['types']['collection']['slug'] );
		$this->assertSame( '', $clean['types']['work']['plural'] );
		$this->assertTrue( $clean['redirect'] );
		$this->assertFalse( Settings_Page::sanitize( array() )['redirect'] );
	}

	/**
	 * Clashing and reserved slugs are refused, keeping the previous value.
	 */
	public function test_sanitize_refuses_clashing_slugs() {
		$this->alias_collections( array( 'slug' => 'exhibition' ) );

		$clean = Settings_Page::sanitize(
			array(
				'types' => array(
					'collection' => array( 'slug' => 'work' ),
					'profile'    => array( 'slug' => 'author' ),
					'article'    => array(
						'slug'    => 'news',
						'archive' => 'news',
					),
				),
			)
		);

		$this->assertSame( 'exhibition', $clean['types']['collection']['slug'], 'Another type\'s slug is refused.' );
		$this->assertSame( '', $clean['types']['profile']['slug'], 'A WordPress slug is refused.' );
		$this->assertSame( 'news', $clean['types']['article']['archive'], 'A type may share its own slug with its archive.' );
		$this->assertCount( 2, get_settings_errors( Aliases::OPTION ) );
	}

	/**
	 * Every preset only names known types, taxonomies and properties.
	 */
	public function test_presets_are_well_formed() {
		foreach ( Settings_Page::presets() as $slug => $preset ) {
			$this->assertNotEmpty( $preset['label'], $slug );

			foreach ( Aliases::kinds() as $kind => $keys ) {
				foreach ( isset( $preset[ $kind ] ) ? $preset[ $kind ] : array() as $key => $values ) {
					$this->assertArrayHasKey( $key, $keys, "$slug: $kind.$key" );
					$this->assertSame( array(), array_diff( array_keys( $values ), Aliases::properties( $kind ) ), "$slug: $kind.$key" );
				}
			}

			$this->assertSame( Settings_Page::sanitize( $preset )['types'], Aliases::get( $preset )['types'], "$slug is valid as saved." );
		}
	}
}
