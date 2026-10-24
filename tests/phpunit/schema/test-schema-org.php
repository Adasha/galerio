<?php
/**
 * Schema.org data tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Schema\Schema_Org;

/**
 * Structured data for works, profiles and collections.
 *
 * @covers \Galerio\Core\Schema\Schema_Org
 */
class Test_Schema_Org extends Galerio_TestCase {

	/**
	 * A published post of a type.
	 *
	 * @param string $type  Post type.
	 * @param string $title Title.
	 * @return WP_Post
	 */
	private function item( $type, $title ) {
		return get_post(
			self::factory()->post->create(
				array(
					'post_type'   => $type,
					'post_title'  => $title,
					'post_status' => 'publish',
				)
			)
		);
	}

	/**
	 * A medium term.
	 *
	 * @param string $name      Name.
	 * @param int    $parent_id Parent term ID.
	 * @return WP_Term
	 */
	private function medium( $name, $parent_id = 0 ) {
		return get_term(
			self::factory()->term->create(
				array(
					'taxonomy' => Schema_Org::MEDIUM,
					'name'     => $name,
					'parent'   => $parent_id,
				)
			)
		);
	}

	/**
	 * A medium's type: its own setting, its parent's, a guess from the name,
	 * or none.
	 */
	public function test_term_type() {
		$music = $this->medium( 'Sound Art' );
		$field = $this->medium( 'Pieces', $music->term_id );
		$foil  = $this->medium( 'Foil' );

		$this->assertSame( 'AudioObject', Schema_Org::term_type( $field ), 'Guessed from the parent’s name.' );

		update_term_meta( $music->term_id, Schema_Org::TERM_META, 'MusicComposition' );
		$this->assertSame( 'MusicComposition', Schema_Org::term_type( $field ), 'The parent’s setting wins over guesses.' );

		update_term_meta( $field->term_id, Schema_Org::TERM_META, 'MusicRecording' );
		$this->assertSame( 'MusicRecording', Schema_Org::term_type( $field ), 'Its own setting wins.' );

		$this->assertSame( '', Schema_Org::term_type( $foil ), '"Foil" isn’t oil paint.' );
	}

	/**
	 * A work's type follows its medium, and is a general creative work without one.
	 */
	public function test_work_type() {
		$work = $this->item( 'galerio_work', 'Tide Song' );
		$this->assertSame( 'CreativeWork', Schema_Org::work_type( $work ) );

		wp_set_post_terms( $work->ID, array( $this->medium( 'Music' )->term_id ), Schema_Org::MEDIUM );
		$this->assertSame( 'MusicComposition', Schema_Org::work_type( $work ) );

		$data = Schema_Org::data( $work );
		$this->assertSame( 'MusicComposition', $data['@type'] );
		$this->assertArrayNotHasKey( 'artMedium', $data, 'artMedium is for visual art.' );
		$this->assertSame( 'Music', $data['genre'] );
	}

	/**
	 * Visual works carry their medium, and link to their creator.
	 */
	public function test_visual_work() {
		$fields  = galerio_fields();
		$creator = $this->item( 'galerio_profile', 'Ana Morgan' );
		$work    = $this->item( 'galerio_work', 'Low Tide' );

		wp_set_post_terms( $work->ID, array( $this->medium( 'Oil' )->term_id ), Schema_Org::MEDIUM );
		$fields->save( $fields->get( 'work.creator' ), $work->ID, $creator->ID );
		$fields->save( $fields->get( 'work.medium' ), $work->ID, 'Oil on canvas' );
		$fields->save( $fields->get( 'work.year' ), $work->ID, 2019 );

		$data = Schema_Org::data( $work );

		$this->assertSame( 'https://schema.org', $data['@context'] );
		$this->assertSame( 'Painting', $data['@type'] );
		$this->assertSame( 'Oil on canvas', $data['artMedium'] );
		$this->assertSame( '2019', $data['dateCreated'] );
		$this->assertSame( 'Person', $data['creator']['@type'] );
		$this->assertSame( get_permalink( $creator ) . '#profile', $data['creator']['@id'] );
	}

	/**
	 * A profile leaves out what it hides on the site.
	 */
	public function test_profile_respects_its_switches() {
		$fields  = galerio_fields();
		$profile = $this->item( 'galerio_profile', 'Tomos Price' );

		$fields->save( $fields->get( 'profile.nationality' ), $profile->ID, 'Welsh' );
		$fields->save( $fields->get( 'profile.year_of_birth' ), $profile->ID, 1980 );
		$fields->save( $fields->get( 'profile.links' ), $profile->ID, array( array( 'url' => 'https://example.com/tomos' ) ) );
		$fields->save( $fields->get( 'profile.show_nationality' ), $profile->ID, false );
		$fields->save( $fields->get( 'profile.show_lifespan' ), $profile->ID, false );
		$fields->save( $fields->get( 'profile.show_links' ), $profile->ID, false );

		$data = Schema_Org::data( $profile );

		$this->assertSame( 'Person', $data['@type'] );
		$this->assertArrayNotHasKey( 'nationality', $data );
		$this->assertArrayNotHasKey( 'birthDate', $data );
		$this->assertArrayNotHasKey( 'sameAs', $data );

		$fields->save( $fields->get( 'profile.show_lifespan' ), $profile->ID, true );
		$this->assertSame( '1980', Schema_Org::data( $profile )['birthDate'] );
	}

	/**
	 * A collective is an organisation, listing its published members.
	 */
	public function test_collective() {
		$fields     = galerio_fields();
		$collective = $this->item( 'galerio_profile', 'Kiln Collective' );
		$member     = $this->item( 'galerio_profile', 'Sian Rees' );
		$draft      = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_profile',
				'post_status' => 'draft',
			)
		);

		$fields->save( $fields->get( 'profile.is_collective' ), $collective->ID, true );
		$fields->save( $fields->get( 'profile.members' ), $collective->ID, array( $member->ID, $draft ) );

		$data = Schema_Org::data( $collective );

		$this->assertSame( 'Organization', $data['@type'] );
		$this->assertCount( 1, $data['member'], 'Unpublished members are left out.' );
		$this->assertSame( 'Sian Rees', $data['member'][0]['name'] );
	}

	/**
	 * A collection with dates is an exhibition; without, a collection.
	 */
	public function test_collection() {
		$fields     = galerio_fields();
		$collection = $this->item( 'galerio_collection', 'Shorelines' );
		$work       = $this->item( 'galerio_work', 'Low Tide' );

		$fields->save( $fields->get( 'collection.works' ), $collection->ID, array( $work->ID ) );
		$this->assertSame( 'Collection', Schema_Org::data( $collection )['@type'] );
		$this->assertSame( 'Low Tide', Schema_Org::data( $collection )['hasPart'][0]['name'] );

		$fields->save( $fields->get( 'collection.start_date' ), $collection->ID, '2026-10-08' );
		$fields->save( $fields->get( 'collection.location' ), $collection->ID, 'Main Gallery' );

		$data = Schema_Org::data( $collection );
		$this->assertSame( 'ExhibitionEvent', $data['@type'] );
		$this->assertSame( '2026-10-08', $data['startDate'] );
		$this->assertArrayNotHasKey( 'endDate', $data, 'Unknown values are left out.' );
		$this->assertSame( 'Main Gallery', $data['location']['name'] );
		$this->assertSame( 'Low Tide', $data['workFeatured'][0]['name'] );
	}

	/**
	 * The data is printed on single pages, and can be switched off.
	 */
	public function test_output() {
		$work = $this->item( 'galerio_work', 'Low Tide' );
		$this->go_to( get_permalink( $work ) );

		ob_start();
		Schema_Org::output();
		$html = ob_get_clean();
		$this->assertStringStartsWith( '<script type="application/ld+json">', $html );
		$this->assertSame( 'Low Tide', json_decode( preg_replace( '#</?script[^>]*>#', '', $html ), true )['name'] );

		add_filter( 'galerio_schema_enabled', '__return_false' );
		ob_start();
		Schema_Org::output();
		$this->assertSame( '', ob_get_clean() );
		remove_filter( 'galerio_schema_enabled', '__return_false' );

		$this->go_to( home_url( '/' ) );
		ob_start();
		Schema_Org::output();
		$this->assertSame( '', ob_get_clean(), 'Nothing on archives and the home page.' );
	}
}
