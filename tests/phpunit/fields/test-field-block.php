<?php
/**
 * Field block and binding argument tests.
 *
 * @package Galerio\Tests
 */

/**
 * The Field block, and the optional arguments of the galerio/field binding.
 *
 * @covers \Galerio\Core\Fields\Field_Block
 * @covers \Galerio\Core\Fields\Bindings
 */
class Test_Field_Block extends Galerio_TestCase {

	/**
	 * Profile used by most tests.
	 *
	 * @var int
	 */
	private $profile;

	/**
	 * Create a profile with some fields filled in.
	 */
	public function set_up() {
		parent::set_up();

		$fields        = galerio_fields();
		$this->profile = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_profile',
				'post_title' => 'Ana Morgan',
			)
		);

		$fields->save( $fields->get( 'profile.bio' ), $this->profile, '<p>Paints the <em>coast</em>.</p>' );
		$fields->save( $fields->get( 'profile.accolades' ), $this->profile, array( 'First prize', 'Residency' ) );
		$fields->save( $fields->get( 'profile.year_of_birth' ), $this->profile, 1978 );
		$fields->save( $fields->get( 'profile.show_lifespan' ), $this->profile, true );
		$fields->save( $fields->get( 'profile.show_links' ), $this->profile, false );
		$fields->save( $fields->get( 'profile.links' ), $this->profile, array( array( 'url' => 'https://instagram.com/me' ) ) );

		$fields->register(
			'profile.private_test',
			array(
				'type'   => 'html',
				'public' => false,
			)
		);
	}

	/**
	 * Remove the test's private field.
	 */
	public function tear_down() {
		galerio_fields()->unregister( 'profile.private_test' );
		parent::tear_down();
	}

	/**
	 * Render block markup in the profile's context.
	 *
	 * @param string $markup Block markup.
	 * @return string
	 */
	private function render( $markup ) {
		$this->go_to( get_permalink( $this->profile ) );
		the_post();

		return do_blocks( $markup );
	}

	/**
	 * Field block markup.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private function field_block( array $attributes ) {
		return sprintf( '<!-- wp:galerio/field-display %s /-->', wp_json_encode( $attributes ) );
	}

	/**
	 * Paragraph markup bound to a field.
	 *
	 * @param array $args Binding arguments.
	 * @return string
	 */
	private function bound_paragraph( array $args ) {
		return sprintf(
			'<!-- wp:paragraph %s --><p>Saved</p><!-- /wp:paragraph -->',
			wp_json_encode(
				array(
					'metadata' => array(
						'bindings' => array(
							'content' => array(
								'source' => 'galerio/field',
								'args'   => $args,
							),
						),
					),
				)
			)
		);
	}

	/**
	 * Formatted text renders whole, with an optional heading.
	 */
	public function test_renders_formatted_text_with_heading() {
		$html = $this->render(
			$this->field_block(
				array(
					'key'   => 'bio',
					'label' => 'About',
				)
			)
		);

		$this->assertStringContainsString( '<h4 class="wp-block-galerio-field-display__label">About</h4>', $html );
		$this->assertStringContainsString( '<p>Paints the <em>coast</em>.</p>', $html );
		$this->assertStringContainsString( 'is-type-html', $html );
	}

	/**
	 * The list layout puts each value on its own line.
	 */
	public function test_list_layout() {
		$html = $this->render(
			$this->field_block(
				array(
					'key'    => 'profile.accolades',
					'layout' => 'list',
				)
			)
		);

		$this->assertSame( 2, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( 'First prize', $html );
	}

	/**
	 * The block disappears entirely when it has nothing to show.
	 *
	 * @dataProvider data_hidden
	 *
	 * @param array $attributes Block attributes.
	 */
	public function test_hidden( array $attributes ) {
		$this->assertSame( '', trim( $this->render( $this->field_block( $attributes ) ) ) );
	}

	/**
	 * Data for test_hidden.
	 *
	 * @return array[]
	 */
	public function data_hidden() {
		return array(
			'empty field'     => array(
				array(
					'key'   => 'profile.nationality',
					'label' => 'Nationality',
				),
			),
			'switch off'      => array(
				array(
					'key'    => 'profile.links',
					'showIf' => 'show_links',
				),
			),
			'private field'   => array( array( 'key' => 'profile.private_test' ) ),
			'unknown field'   => array( array( 'key' => 'profile.no_such_field' ) ),
			'no field chosen' => array( array( 'label' => 'Heading only' ) ),
		);
	}

	/**
	 * Prefixes and suffixes are added around non-empty values only.
	 */
	public function test_binding_prefix_and_suffix() {
		$html = $this->render(
			$this->bound_paragraph(
				array(
					'key'    => 'year_of_birth',
					'prefix' => 'b. ',
					'suffix' => ' <!>',
				)
			)
		);

		$this->assertStringContainsString( '>b. 1978 &lt;!&gt;</p>', $html );
	}

	/**
	 * A switch that is off empties a bound block; one that is on leaves it alone.
	 */
	public function test_binding_switch() {
		$off = $this->render(
			$this->bound_paragraph(
				array(
					'key' => 'links',
					'if'  => 'show_links',
				)
			)
		);
		$on  = $this->render(
			$this->bound_paragraph(
				array(
					'key' => 'year_of_birth',
					'if'  => 'show_lifespan',
				)
			)
		);

		$this->assertMatchesRegularExpression( '#<p[^>]*></p>#', $off );
		$this->assertStringContainsString( '1978', $on );
	}

	/**
	 * An empty field can fall back to the post excerpt.
	 */
	public function test_binding_excerpt_fallback() {
		$article = self::factory()->post->create(
			array(
				'post_type'    => 'galerio_article',
				'post_excerpt' => 'A short summary.',
			)
		);
		$this->go_to( get_permalink( $article ) );
		the_post();

		$html = do_blocks(
			$this->bound_paragraph(
				array(
					'key'      => 'standfirst',
					'fallback' => 'excerpt',
				)
			)
		);

		$this->assertStringContainsString( 'A short summary.', $html );
	}

	/**
	 * An empty field can fall back to the post title, and values can link to the post.
	 */
	public function test_binding_fallback_and_link() {
		$work = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_work',
				'post_title' => 'Low Tide',
			)
		);
		$this->go_to( get_permalink( $work ) );
		the_post();

		$html = do_blocks(
			$this->bound_paragraph(
				array(
					'key'      => 'definitive_title',
					'fallback' => 'title',
					'link'     => true,
				)
			)
		);

		$this->assertStringContainsString( sprintf( '<a href="%s">Low Tide</a>', get_permalink( $work ) ), $html );
	}
}
