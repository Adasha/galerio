<?php
/**
 * Link field tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Display;
use Galerio\Core\Fields\Field;

/**
 * The link field type: normalising, display, the Pods field type and bindings.
 *
 * @covers \Galerio\Core\Fields\Field
 * @covers \Galerio\Core\Fields\Display
 * @covers \PodsField_Galerio_link
 */
class Test_Links extends Galerio_TestCase {

	/**
	 * A link as the field API returns it.
	 *
	 * @param string $url     URL.
	 * @param string $label   Display text.
	 * @param bool   $offline Whether the site is offline.
	 * @return array
	 */
	private function link( $url, $label = '', $offline = false ) {
		return array(
			'url'     => $url,
			'label'   => $label,
			'offline' => $offline,
		);
	}

	/**
	 * Stored and submitted values normalise to link arrays.
	 *
	 * @dataProvider data_normalise
	 *
	 * @param bool  $multiple Whether the field holds several links.
	 * @param mixed $value    Input.
	 * @param mixed $expected Normalised value.
	 */
	public function test_normalise( $multiple, $value, $expected ) {
		$field = new Field(
			'profile.test',
			array(
				'type'     => 'link',
				'multiple' => $multiple,
			)
		);

		$this->assertSame( $expected, $field->normalise( $value ) );
	}

	/**
	 * Data for test_normalise.
	 *
	 * @return array[]
	 */
	public function data_normalise() {
		return array(
			'JSON list'                    => array( true, '[{"url":"https://a.org","label":"A"},{"url":"https://b.org"}]', array( $this->link( 'https://a.org', 'A' ), $this->link( 'https://b.org' ) ) ),
			'JSON single'                  => array( false, '{"url":"https://a.org","offline":true}', $this->link( 'https://a.org', '', true ) ),
			'bare URL'                     => array( false, 'https://a.org', $this->link( 'https://a.org' ) ),
			'single for list'              => array( true, array( 'url' => 'https://a.org' ), array( $this->link( 'https://a.org' ) ) ),
			'first of list'                => array( false, array( array( 'url' => 'https://a.org' ), array( 'url' => 'https://b.org' ) ), $this->link( 'https://a.org' ) ),
			'empty single'                 => array( false, '', null ),
			'empty list'                   => array( true, '', array() ),
			'entries without URLs dropped' => array( true, array( array( 'label' => 'No URL' ), array( 'url' => 'https://a.org' ) ), array( $this->link( 'https://a.org' ) ) ),
		);
	}

	/**
	 * Links show their display text, else their service's name, else their address.
	 */
	public function test_link_text() {
		$this->assertSame( 'My site', Display::link_text( $this->link( 'https://instagram.com/me', 'My site' ) ) );
		$this->assertSame( 'Instagram', Display::link_text( $this->link( 'https://www.instagram.com/me' ) ) );
		$this->assertSame( 'YouTube', Display::link_text( $this->link( 'https://m.youtube.com/@me' ) ) );
		$this->assertSame( 'example.org/me', Display::link_text( $this->link( 'https://www.example.org/me/' ) ) );
	}

	/**
	 * Sites can add services.
	 */
	public function test_services_filter() {
		$filter = function ( $services ) {
			$services['artstation.com'] = 'ArtStation';
			return $services;
		};
		add_filter( 'galerio_link_services', $filter );

		$this->assertSame( 'ArtStation', Display::service( 'https://artstation.com/me' ) );

		remove_filter( 'galerio_link_services', $filter );
	}

	/**
	 * Links render with a class per service; offline links aren't linked; several links make a list.
	 */
	public function test_display() {
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		galerio_fields()->save(
			galerio_fields()->get( 'profile.links' ),
			$profile,
			array( $this->link( 'https://instagram.com/me' ), $this->link( 'https://example.org', 'Me & mine' ) )
		);

		$collection = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );
		galerio_fields()->save( galerio_fields()->get( 'collection.website' ), $collection, $this->link( 'https://example.org/show', 'The show', true ) );

		$this->assertSame(
			'<ul class="galerio-links"><li><a class="galerio-link galerio-link--instagram" href="https://instagram.com/me">Instagram</a></li><li><a class="galerio-link" href="https://example.org">Me &amp; mine</a></li></ul>',
			galerio_get_field( 'links', $profile, 'display' )
		);
		$this->assertSame( '<span class="galerio-link is-offline">The show</span>', galerio_get_field( 'website', $collection, 'display' ) );
	}

	/**
	 * The Pods field type sanitises submitted links and stores them as JSON.
	 */
	public function test_pods_field_type_saves_json() {
		$type = PodsForm::field_loader( 'galerio_link' );

		$this->assertInstanceOf( 'PodsField_Galerio_link', $type );

		$submitted = array(
			array(
				'url'   => 'https://example.org',
				'label' => '<b>Bold</b>',
			),
			array(
				'url'   => 'javascript:alert(1)',
				'label' => 'Unsafe',
			),
			array(
				'url'   => '',
				'label' => 'Empty row',
			),
		);

		$this->assertSame( '[{"url":"https:\/\/example.org","label":"Bold","offline":false}]', $type->pre_save( $submitted, null, 'links', array( 'galerio_link_multiple' => 1 ) ) );
		$this->assertSame( '{"url":"https:\/\/example.org","label":"Bold","offline":false}', $type->pre_save( $submitted, null, 'website', array() ) );
		$this->assertSame( '', $type->pre_save( array(), null, 'website', array() ) );
	}

	/**
	 * Pods Templates show links with the same words as the field API.
	 */
	public function test_pods_field_type_display() {
		$type = PodsForm::field_loader( 'galerio_link' );

		$this->assertSame( '<a href="https://instagram.com/me">Instagram</a>', $type->display( '{"url":"https://instagram.com/me"}' ) );
		$this->assertSame( 'The show', $type->display( '{"url":"https://example.org","label":"The show","offline":true}' ) );
	}

	/**
	 * The editing form escapes values and shows the offline box only when asked.
	 */
	public function test_pods_field_type_input() {
		$type  = PodsForm::field_loader( 'galerio_link' );
		$value = '{"url":"https://example.org/?a=1&b=2","label":"\"Quoted\""}';

		ob_start();
		$type->input( 'pods_meta_website', $value, array( 'galerio_link_offline' => 1 ) );
		$with_offline = ob_get_clean();

		// Pods meta boxes pass the prefix separately from the field name.
		ob_start();
		$type->input(
			'links',
			$value,
			array(
				'galerio_link_multiple' => 1,
				'name_prefix'           => 'pods_meta_',
			)
		);
		$multiple = ob_get_clean();

		$this->assertStringContainsString( 'value="https://example.org/?a=1&amp;b=2"', $with_offline );
		$this->assertStringContainsString( 'value="&quot;Quoted&quot;"', $with_offline );
		$this->assertStringContainsString( 'name="pods_meta_website[offline]"', $with_offline );
		$this->assertStringNotContainsString( '[offline]', $multiple );
		$this->assertStringContainsString( 'name="pods_meta_links[0][url]"', $multiple );
		$this->assertStringContainsString( 'galerio-links__add', $multiple );
	}

	/**
	 * A Button bound to a link gets its URL and its text.
	 */
	public function test_button_binding() {
		$work = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		galerio_fields()->save( galerio_fields()->get( 'work.website' ), $work, $this->link( 'https://vimeo.com/123' ) );

		$binding = array(
			'source' => 'galerio/field',
			'args'   => array( 'key' => 'website' ),
		);
		$markup  = sprintf(
			'<!-- wp:button %s --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Visit</a></div><!-- /wp:button -->',
			wp_json_encode(
				array(
					'metadata' => array(
						'bindings' => array(
							'url'  => $binding,
							'text' => $binding,
						),
					),
				)
			)
		);

		$this->go_to( get_permalink( $work ) );
		the_post();
		$html = do_blocks( $markup );

		$this->assertStringContainsString( 'href="https://vimeo.com/123"', $html );
		$this->assertStringContainsString( '>Vimeo</a>', $html );
	}

	/**
	 * REST describes links as objects.
	 */
	public function test_rest_schema() {
		$response = rest_do_request( new WP_REST_Request( 'OPTIONS', '/wp/v2/galerio_profile' ) );
		$schema   = $response->get_data()['schema']['properties']['galerio_fields']['properties'];

		$this->assertSame( 'array', $schema['links']['type'] );
		$this->assertSame( array( 'url', 'label', 'offline' ), array_keys( $schema['links']['items']['properties'] ) );
	}
}
