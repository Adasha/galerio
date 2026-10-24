<?php
/**
 * Page style tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Styles\Page_Style;

/**
 * A collection's or page's own fonts, colours and CSS.
 *
 * @covers \Galerio\Core\Styles\Page_Style
 */
class Test_Page_Style extends Galerio_TestCase {

	/**
	 * Collection used by most tests.
	 *
	 * @var int
	 */
	private $collection;

	/**
	 * Create a collection.
	 */
	public function set_up() {
		parent::set_up();

		$this->collection = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_collection',
				'post_title' => 'Visions',
			)
		);
	}

	/**
	 * Add a family to the Font Library, with one face.
	 *
	 * @return string The family's slug.
	 */
	private function add_library_font() {
		$family = self::factory()->post->create(
			array(
				'post_type'    => 'wp_font_family',
				'post_title'   => 'Aimstesa',
				'post_name'    => 'aimstesa',
				'post_content' => wp_json_encode(
					array(
						'name'       => 'Aimstesa',
						'slug'       => 'aimstesa',
						'fontFamily' => 'Aimstesa, serif',
					)
				),
			)
		);

		self::factory()->post->create(
			array(
				'post_type'    => 'wp_font_face',
				'post_parent'  => $family,
				'post_content' => wp_json_encode(
					array(
						'fontFamily' => 'Aimstesa',
						'fontWeight' => '400',
						'fontStyle'  => 'normal',
						'src'        => 'https://example.com/fonts/aimstesa.woff2',
					)
				),
			)
		);

		return 'aimstesa';
	}

	/**
	 * Only known fonts, plain colours and CSS without markup are kept.
	 */
	public function test_sanitize() {
		$font  = $this->add_library_font();
		$style = Page_Style::sanitize(
			array(
				'fonts'  => array(
					'heading' => $font,
					'body'    => 'no-such-font',
				),
				'colors' => array(
					'base'      => '#fafaf0',
					'contrast'  => 'red;}body{display:none',
					'Bad Slug!' => '#000',
					'primary'   => 'rgba(200, 10, 10, 0.5)',
				),
				'css'    => 'h2{color:red}</style><script>alert(1)</script>',
				'extra'  => 'dropped',
			)
		);

		$this->assertSame(
			array(
				'fonts'  => array( 'heading' => $font ),
				'colors' => array(
					'base'    => '#fafaf0',
					'primary' => 'rgba(200, 10, 10, 0.5)',
				),
				'css'    => 'h2{color:red}',
			),
			$style
		);
	}

	/**
	 * A style replaces presets on body, then adds its own CSS.
	 */
	public function test_css() {
		$css = Page_Style::css(
			array(
				'fonts'  => array( 'heading' => $this->add_library_font() ),
				'colors' => array( 'base' => '#fafaf0' ),
				'css'    => '.x{color:red}',
			)
		);

		$this->assertStringContainsString( 'body{--wp--preset--font-family--heading:', $css );
		$this->assertStringContainsString( '--wp--preset--color--base:#fafaf0;}', $css );
		$this->assertStringEndsWith( "\n.x{color:red}", $css );
		$this->assertSame( '', Page_Style::css( array() ) );
	}

	/**
	 * Font Library fonts can be used without activating them, and are loaded.
	 */
	public function test_font_library_fonts() {
		$slug = $this->add_library_font();

		$this->assertArrayHasKey( $slug, Page_Style::font_families() );

		$css = Page_Style::css( Page_Style::sanitize( array( 'fonts' => array( 'heading' => $slug ) ) ) );
		$this->assertStringContainsString( '@font-face{font-family:"Aimstesa";src:url("https://example.com/fonts/aimstesa.woff2") format("woff2")', $css );
		$this->assertStringContainsString( '--wp--preset--font-family--heading:Aimstesa, serif;', $css );
	}

	/**
	 * Someone who can't edit CSS can change fonts and colours, but not the CSS.
	 */
	public function test_css_needs_permission() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_post_meta( $this->collection, Page_Style::META, array( 'css' => '.old{}' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		update_post_meta(
			$this->collection,
			Page_Style::META,
			array(
				'colors' => array( 'base' => '#000000' ),
				'css'    => 'body{display:none}',
			)
		);

		$this->assertSame(
			array(
				'colors' => array( 'base' => '#000000' ),
				'css'    => '.old{}',
			),
			get_post_meta( $this->collection, Page_Style::META, true )
		);

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		add_post_meta( $page, Page_Style::META, array( 'css' => 'body{display:none}' ) );
		$this->assertSame( array(), get_post_meta( $page, Page_Style::META, true ) );
	}

	/**
	 * The style is added to its own page only, after the site's styles.
	 */
	public function test_added_to_its_page_only() {
		update_post_meta( $this->collection, Page_Style::META, array( 'colors' => array( 'base' => '#fafaf0' ) ) );
		$other = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );

		$this->go_to( get_permalink( $this->collection ) );
		Page_Style::enqueue();
		$this->assertTrue( wp_style_is( 'galerio-page-style' ) );
		$this->assertStringContainsString( '#fafaf0', implode( '', wp_styles()->get_data( 'galerio-page-style', 'after' ) ) );

		wp_dequeue_style( 'galerio-page-style' );
		wp_deregister_style( 'galerio-page-style' );

		$this->go_to( get_permalink( $other ) );
		Page_Style::enqueue();
		$this->assertFalse( wp_style_is( 'galerio-page-style' ) );
	}

	/**
	 * Collections, articles and pages can have their own style; works can't.
	 */
	public function test_post_types() {
		$this->assertSame( array( 'galerio_collection', 'galerio_article', 'page' ), Page_Style::post_types() );

		// The test suite clears registered meta between tests.
		Page_Style::register_meta();
		$this->assertArrayHasKey( Page_Style::META, get_registered_meta_keys( 'post', 'galerio_article' ) );
		$this->assertArrayNotHasKey( Page_Style::META, get_registered_meta_keys( 'post', 'galerio_work' ) );
	}

	/**
	 * The style waits for the site's styles only when they're registered, as
	 * they are with block themes; classic themes add them later.
	 */
	public function test_dependency_on_global_styles() {
		update_post_meta( $this->collection, Page_Style::META, array( 'colors' => array( 'base' => '#fafaf0' ) ) );
		$this->go_to( get_permalink( $this->collection ) );

		wp_deregister_style( 'global-styles' );
		Page_Style::enqueue();
		$this->assertSame( array(), wp_styles()->registered['galerio-page-style']->deps );
		wp_dequeue_style( 'galerio-page-style' );
		wp_deregister_style( 'galerio-page-style' );

		wp_register_style( 'global-styles', false, array(), '1' );
		Page_Style::enqueue();
		$this->assertSame( array( 'global-styles' ), wp_styles()->registered['galerio-page-style']->deps );
	}

	/**
	 * The editor's preview endpoint returns the same CSS as the page.
	 */
	public function test_preview_endpoint() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/galerio/v1/page-style/css' );
		$request->set_body_params( array( 'style' => array( 'colors' => array( 'base' => '#123456' ) ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '--wp--preset--color--base:#123456', $response->get_data()['css'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}
}
