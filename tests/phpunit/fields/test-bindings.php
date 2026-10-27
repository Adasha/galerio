<?php
/**
 * Block bindings tests.
 *
 * @package Galerio\Tests
 */

/**
 * Rendering core blocks bound to Galerio fields.
 *
 * @covers \Galerio\Core\Fields\Bindings
 */
class Test_Bindings extends Galerio_TestCase {

	/**
	 * Work used by most tests.
	 *
	 * @var int
	 */
	private $work;

	/**
	 * The work's creator.
	 *
	 * @var int
	 */
	private $profile;

	/**
	 * Create a work with some fields filled in.
	 */
	public function set_up() {
		parent::set_up();

		$fields     = galerio_fields();
		$this->work = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$profile    = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_profile',
				'post_title' => 'Tomos Price',
			)
		);

		$fields->save( $fields->get( 'work.year' ), $this->work, 2018 );
		$fields->save( $fields->get( 'work.definitive_title' ), $this->work, 'Fish & Chips' );
		$fields->save( $fields->get( 'work.creator' ), $this->work, $profile );
		$this->profile = $profile;
		$fields->save( $fields->get( 'work.website' ), $this->work, array( 'url' => 'https://example.org/copperworks' ) );
	}

	/**
	 * Render block markup as part of a post.
	 *
	 * @param string $markup  Block markup.
	 * @param int    $post_id Post to render it in; defaults to the test work.
	 * @return string
	 */
	private function render( $markup, $post_id = 0 ) {
		global $post;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Rendering in a post's context.
		$post = get_post( $post_id ? $post_id : $this->work );
		setup_postdata( $post );

		$html = do_blocks( $markup );

		wp_reset_postdata();

		return $html;
	}

	/**
	 * Block markup with bindings.
	 *
	 * @param string $block    Block name without the "core/" prefix.
	 * @param array  $bindings Attribute => field key.
	 * @param string $inner    Saved inner HTML.
	 * @return string
	 */
	private function block( $block, array $bindings, $inner ) {
		$attrs = array( 'metadata' => array( 'bindings' => array() ) );

		foreach ( $bindings as $attribute => $key ) {
			$attrs['metadata']['bindings'][ $attribute ] = array(
				'source' => 'galerio/field',
				'args'   => array( 'key' => $key ),
			);
		}

		return sprintf( '<!-- wp:%1$s %2$s -->%3$s<!-- /wp:%1$s -->', $block, wp_json_encode( $attrs ), $inner );
	}

	/**
	 * A paragraph shows a field's display value.
	 */
	public function test_paragraph() {
		$html = $this->render( $this->block( 'paragraph', array( 'content' => 'work.year' ), '<p>Year</p>' ) );

		$this->assertSame( '2018', trim( wp_strip_all_tags( $html ) ) );
	}

	/**
	 * A prefix can name a type with a token, which becomes the site's name.
	 */
	public function test_prefix_name_token() {
		update_option( 'galerio_aliases', array( 'types' => array( 'work' => array( 'singular' => 'Artwork' ) ) ) );

		$attrs = array(
			'metadata' => array(
				'bindings' => array(
					'content' => array(
						'source' => 'galerio/field',
						'args'   => array(
							'key'    => 'work.year',
							'prefix' => '{Work} from ',
						),
					),
				),
			),
		);
		$html  = $this->render( '<!-- wp:paragraph ' . wp_json_encode( $attrs ) . ' --><p>Year</p><!-- /wp:paragraph -->' );

		delete_option( 'galerio_aliases' );

		$this->assertSame( 'Artwork from 2018', trim( wp_strip_all_tags( $html ) ) );
	}

	/**
	 * Bare field names resolve against the post's type.
	 */
	public function test_bare_key() {
		$html = $this->render( $this->block( 'paragraph', array( 'content' => 'year' ), '<p>Year</p>' ) );

		$this->assertSame( '2018', trim( wp_strip_all_tags( $html ) ) );
	}

	/**
	 * Text is escaped and relations render as links.
	 */
	public function test_heading_escapes_and_links() {
		$title   = $this->render( $this->block( 'heading', array( 'content' => 'work.definitive_title' ), '<h2 class="wp-block-heading">Title</h2>' ) );
		$creator = $this->render( $this->block( 'paragraph', array( 'content' => 'work.creator' ), '<p>Creator</p>' ) );

		$this->assertStringContainsString( 'Fish &amp; Chips', $title );
		$this->assertMatchesRegularExpression( '#<p[^>]*><a href="[^"]+">Tomos Price</a></p>#', trim( $creator ) );
	}

	/**
	 * `omitCurrent` leaves out the item the page is about, emptying the block
	 * when nothing is left, and changes nothing elsewhere.
	 */
	public function test_omit_current() {
		$markup = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.creator","omitCurrent":true}}}}} --><p>Saved</p><!-- /wp:paragraph -->';

		$this->go_to( get_permalink( $this->profile ) );
		$on_profile = $this->render( $markup );

		$this->go_to( get_permalink( $this->work ) );
		$on_work = $this->render( $markup );

		$this->assertSame( '', trim( wp_strip_all_tags( $on_profile ) ) );
		$this->assertSame( 'Tomos Price', trim( wp_strip_all_tags( $on_work ) ) );
	}

	/**
	 * A button gets its link and plain-text label from fields.
	 */
	public function test_button() {
		$html = $this->render(
			$this->block(
				'button',
				array(
					'url'  => 'work.website',
					'text' => 'work.creator',
				),
				'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Visit</a></div>'
			)
		);

		$this->assertStringContainsString( 'href="https://example.org/copperworks"', $html );
		$this->assertStringContainsString( '>Tomos Price</a>', $html );
		$this->assertSame( 1, substr_count( $html, '<a ' ), 'The label must not contain a link of its own.' );
	}

	/**
	 * An image gets its URL and alt text from an image field.
	 */
	public function test_image() {
		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		update_post_meta( $image, '_wp_attachment_image_alt', 'A field of canola' );
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $this->work, $image );

		$html = $this->render(
			$this->block(
				'image',
				array(
					'url' => 'work.definitive_image',
					'alt' => 'work.definitive_image',
				),
				'<figure class="wp-block-image"><img alt=""/></figure>'
			)
		);

		$this->assertStringContainsString( 'src="' . wp_get_attachment_image_url( $image, 'full' ) . '"', $html );
		$this->assertStringContainsString( 'alt="A field of canola"', $html );
	}

	/**
	 * Saved content is kept when a binding has no value to give.
	 *
	 * @dataProvider data_no_value
	 *
	 * @param string $key Field key.
	 */
	public function test_keeps_saved_content_without_value( $key ) {
		$html = $this->render( $this->block( 'paragraph', array( 'content' => $key ), '<p>Saved</p>' ) );

		$this->assertSame( 'Saved', trim( wp_strip_all_tags( $html ) ) );
	}

	/**
	 * Data for test_keeps_saved_content_without_value.
	 *
	 * @return array[]
	 */
	public function data_no_value() {
		return array(
			'empty field'      => array( 'work.medium' ),
			'unknown field'    => array( 'work.no_such_field' ),
			'other object'     => array( 'profile.nationality' ),
			'non-public field' => array( 'work.owner' ),
		);
	}

	/**
	 * Private fields are never exposed, even when the field exists on the post.
	 */
	public function test_private_field_not_exposed() {
		galerio_fields()->save( galerio_fields()->get( 'work.owner' ), $this->work, 'Secret' );

		$html = $this->render( $this->block( 'paragraph', array( 'content' => 'owner' ), '<p>Saved</p>' ) );

		$this->assertStringNotContainsString( 'Secret', $html );
	}

	/**
	 * Fields of posts the visitor cannot read are not exposed.
	 */
	public function test_unreadable_post() {
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'draft',
			)
		);
		galerio_fields()->save( galerio_fields()->get( 'work.year' ), $draft, 1999 );

		$html = $this->render( $this->block( 'paragraph', array( 'content' => 'year' ), '<p>Saved</p>' ), $draft );

		$this->assertSame( 'Saved', trim( wp_strip_all_tags( $html ) ) );
	}

	/**
	 * The editor receives public field definitions only.
	 */
	public function test_editor_data() {
		do_action( 'enqueue_block_editor_assets' );

		$this->assertTrue( wp_script_is( 'galerio-field-bindings', 'enqueued' ) );

		$before = implode( '', wp_scripts()->get_data( 'galerio-field-bindings', 'before' ) );

		$this->assertStringContainsString( '"work.year"', $before );
		$this->assertStringNotContainsString( 'work.owner', $before );
	}
}
