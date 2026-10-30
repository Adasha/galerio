<?php
/**
 * Catalogue tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Catalogue\Catalogue;
use Galerio\Core\Schema\Schema_Org;

/**
 * Works for sale: availability, price, edition and dealer, behind the
 * catalogue switch.
 *
 * @covers \Galerio\Core\Catalogue\Catalogue
 */
class Test_Catalogue extends Galerio_TestCase {

	/**
	 * Turn the catalogue on, as most tests need it.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'galerio_settings_catalogue', '1' );
	}

	/**
	 * Leave the settings as they were.
	 */
	public function tear_down() {
		delete_option( 'galerio_settings_catalogue' );
		delete_option( 'galerio_settings_currency' );
		wp_dequeue_style( 'galerio-catalogue' );
		parent::tear_down();
	}

	/**
	 * A published work with catalogue values.
	 *
	 * @param array $values Field name => value.
	 * @return int Work ID.
	 */
	private function work( array $values ) {
		$work = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'publish',
			)
		);

		foreach ( $values as $name => $value ) {
			galerio_fields()->save( galerio_fields()->get( "work.$name" ), $work, $value );
		}

		return $work;
	}

	/**
	 * A choice must be one of its options.
	 */
	public function test_choice_values() {
		$field = galerio_fields()->get( 'work.availability' );

		$this->assertSame( 'sold', $field->normalise( 'sold' ) );
		$this->assertSame( '', $field->normalise( 'gone' ) );
		$this->assertSame( '', $field->normalise( null ) );
		$this->assertSame( 'On loan', Galerio\Core\Fields\Display::render( $field, 'on_loan', 0 ) );
	}

	/**
	 * While the catalogue is off, its fields read as empty, though their
	 * values are kept, and the editor leaves out the Sales boxes.
	 */
	public function test_off() {
		$work = $this->work(
			array(
				'availability'     => 'available',
				'price'            => 900,
				'inventory_number' => 'SA-1',
			)
		);
		update_option( 'galerio_settings_catalogue', '0' );

		$this->assertSame( '', galerio_get_field( 'work.availability', $work ) );
		$this->assertNull( galerio_get_field( 'work.price', $work ) );
		$this->assertSame( '', galerio_get_field( 'work.price_label', $work ) );
		$this->assertSame( '', galerio_get_field( 'work.inventory_number', $work ) );
		$this->assertNotContains( 'galerio-availability-available', get_post_class( '', $work ) );

		$groups = Catalogue::editor_groups( array( array( 'name' => 'details_group' ), array( 'name' => 'sales_group' ) ), 'post_type', 'galerio_work' );
		$this->assertSame( array( 'details_group' ), wp_list_pluck( $groups, 'name' ) );

		delete_option( 'galerio_settings_catalogue' );
		$this->assertFalse( Catalogue::enabled(), 'The catalogue is off until a site turns it on.' );

		update_option( 'galerio_settings_catalogue', '1' );
		$this->assertSame( 'available', galerio_get_field( 'work.availability', $work ) );
		$this->assertCount( 2, Catalogue::editor_groups( array( array( 'name' => 'details_group' ), array( 'name' => 'sales_group' ) ), 'post_type', 'galerio_work' ) );
	}

	/**
	 * The price shows while the work is for sale, in its currency.
	 */
	public function test_price() {
		$work = $this->work(
			array(
				'availability' => 'available',
				'price'        => 1250,
			)
		);

		$this->assertSame( 1250.0, galerio_get_field( 'work.price', $work ) );
		$this->assertSame( '£1,250', galerio_get_field( 'work.price_label', $work ) );

		update_option( 'galerio_settings_currency', 'EUR' );
		$this->assertSame( '€1,250', galerio_get_field( 'work.price_label', $work ) );

		galerio_fields()->save( galerio_fields()->get( 'work.currency' ), $work, 'USD' );
		galerio_fields()->save( galerio_fields()->get( 'work.price' ), $work, 99.5 );
		$this->assertSame( '$99.50', galerio_get_field( 'work.price_label', $work ) );

		galerio_fields()->save( galerio_fields()->get( 'work.availability' ), $work, 'sold' );
		$this->assertNull( galerio_get_field( 'work.price', $work ), 'A sold work keeps its price private.' );
		$this->assertSame( '', galerio_get_field( 'work.price_label', $work ) );
	}

	/**
	 * Price on application shows instead of the price, which stays private.
	 */
	public function test_price_on_application() {
		$work = $this->work(
			array(
				'availability'         => 'reserved',
				'price'                => 5000,
				'price_on_application' => true,
			)
		);

		$this->assertNull( galerio_get_field( 'work.price', $work ) );
		$this->assertSame( 'Price on application', galerio_get_field( 'work.price_label', $work ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/galerio_work/$work" ) );
		$this->assertNull( ( (array) $response->get_data()['galerio_fields'] )['price'] );

		$without_price = $this->work(
			array(
				'availability'         => 'available',
				'price_on_application' => true,
			)
		);
		$this->assertSame( 'Price on application', galerio_get_field( 'work.price_label', $without_price ) );
		$this->assertFalse( galerio_fields()->save( galerio_fields()->get( 'work.price_label' ), $without_price, 'Free' ), 'The label is read-only.' );
	}

	/**
	 * An edition shows its size and what's left, and reads as sold when
	 * none is left.
	 */
	public function test_edition() {
		$work = $this->work(
			array(
				'availability'       => 'available',
				'edition_size'       => 10,
				'editions_available' => 3,
			)
		);

		$this->assertSame( 'Edition of 10, 3 available', galerio_get_field( 'work.edition_size', $work, 'display' ) );

		galerio_fields()->save( galerio_fields()->get( 'work.editions_available' ), $work, 0 );
		$this->assertSame( 'sold', galerio_get_field( 'work.availability', $work ) );
		$this->assertSame( 'Edition of 10', galerio_get_field( 'work.edition_size', $work, 'display' ) );

		galerio_fields()->save( galerio_fields()->get( 'work.editions_available' ), $work, null );
		$this->assertSame( 'available', galerio_get_field( 'work.availability', $work ), 'An edition with nothing said about what is left stays available.' );
	}

	/**
	 * Available from falls back to the creator's dealer, and goes when the
	 * work isn't for sale.
	 */
	public function test_dealer() {
		$creator = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_profile',
				'post_status' => 'publish',
			)
		);
		galerio_fields()->save(
			galerio_fields()->get( 'profile.represented_by' ),
			$creator,
			array(
				'url'   => 'https://gallery.example',
				'label' => 'Gallery',
			)
		);

		$work = $this->work(
			array(
				'availability' => 'available',
				'creator'      => $creator,
			)
		);
		$this->assertSame( 'https://gallery.example', galerio_get_field( 'work.available_from', $work )['url'] );

		galerio_fields()->save( galerio_fields()->get( 'work.available_from' ), $work, array( 'url' => 'https://shop.example' ) );
		$this->assertSame( 'https://shop.example', galerio_get_field( 'work.available_from', $work )['url'] );

		galerio_fields()->save( galerio_fields()->get( 'work.availability' ), $work, 'not_for_sale' );
		$this->assertNull( galerio_get_field( 'work.available_from', $work ) );
	}

	/**
	 * Works in Query Loops get a class for their availability, and sold or
	 * reserved ones a marker.
	 */
	public function test_cards() {
		$sold      = $this->work( array( 'availability' => 'sold' ) );
		$available = $this->work( array( 'availability' => 'available' ) );

		$this->assertContains( 'galerio-availability-sold', get_post_class( '', $sold ) );

		$list = sprintf(
			'<ul class="wp-block-post-template"><li class="%1$s"></li><li class="%2$s"></li></ul>',
			implode( ' ', get_post_class( 'wp-block-post', $sold ) ),
			implode( ' ', get_post_class( 'wp-block-post', $available ) )
		);
		$html = Catalogue::markers( $list );

		$this->assertSame( 1, substr_count( $html, '<span class="galerio-availability-marker ' ) );
		$this->assertStringContainsString( '>Sold</span>', $html );
		$this->assertTrue( wp_style_is( 'galerio-catalogue' ) );
	}

	/**
	 * Query Loops can show only available or only sold works, counting an
	 * edition with none left as sold.
	 */
	public function test_query_filter() {
		$available = $this->work( array( 'availability' => 'available' ) );
		$edition   = $this->work(
			array(
				'availability'       => 'available',
				'edition_size'       => 5,
				'editions_available' => 2,
			)
		);
		$sold_out  = $this->work(
			array(
				'availability'       => 'available',
				'edition_size'       => 5,
				'editions_available' => 0,
			)
		);
		$sold      = $this->work( array( 'availability' => 'sold' ) );
		$this->work( array( 'availability' => 'on_loan' ) );
		$this->work( array() );

		$ids = function ( $filter ) {
			$query = new WP_Query(
				array_merge(
					array(
						'post_type'      => 'galerio_work',
						'fields'         => 'ids',
						'posts_per_page' => -1,
					),
					Catalogue::query_args( $filter )
				)
			);
			sort( $query->posts );
			return $query->posts;
		};

		$this->assertSame( array( $available, $edition ), $ids( 'available' ) );
		$this->assertSame( array( $sold_out, $sold ), $ids( 'sold' ) );

		$this->assertSame( array(), Catalogue::query_args( 'everything' ) );
		update_option( 'galerio_settings_catalogue', '0' );
		$this->assertSame( array(), Catalogue::query_args( 'sold' ), 'No filter while the catalogue is off.' );
	}

	/**
	 * A works Query Loop with the availability filter shows only matching
	 * works, and marks the sold ones.
	 */
	public function test_query_loop() {
		$sold = $this->work( array( 'availability' => 'sold' ) );
		$this->work( array( 'availability' => 'available' ) );
		wp_update_post(
			array(
				'ID'         => $sold,
				'post_title' => 'Gone',
			)
		);

		$markup = '<!-- wp:query {"queryId":3,"query":{"perPage":10,"postType":"galerio_work","inherit":false,"galerioAvailability":"sold"},"namespace":"galerio/works"} -->'
			. '<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->';
		$html   = do_blocks( $markup );

		$this->assertSame( 1, substr_count( $html, 'wp-block-post-title' ) );
		$this->assertStringContainsString( '>Gone<', $html );
		$this->assertStringContainsString( 'galerio-availability-marker--sold', $html );
	}

	/**
	 * A work for sale has an Offer in its Schema.org data, with its dealer
	 * as the seller.
	 */
	public function test_schema() {
		$work = $this->work(
			array(
				'availability'     => 'reserved',
				'price'            => 400,
				'inventory_number' => 'SA-0123',
				'available_from'   => array(
					'url'   => 'https://shop.example',
					'label' => 'The Shop',
				),
			)
		);
		$data = Schema_Org::data( get_post( $work ) );

		$this->assertSame( 'SA-0123', $data['identifier'] );
		$this->assertSame( 'Offer', $data['offers']['@type'] );
		$this->assertEquals( 400, $data['offers']['price'] );
		$this->assertSame( 'GBP', $data['offers']['priceCurrency'] );
		$this->assertSame( 'https://schema.org/Reserved', $data['offers']['availability'] );
		$this->assertSame( 'The Shop', $data['offers']['seller']['name'] );

		galerio_fields()->save( galerio_fields()->get( 'work.price_on_application' ), $work, true );
		$this->assertArrayNotHasKey( 'offers', Schema_Org::data( get_post( $work ) ), 'No offer without a price.' );
	}
}
