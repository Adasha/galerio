<?php
/**
 * Style variation tests.
 *
 * @package Galerio\Tests
 */

/**
 * The theme's style variations and the child themes' palettes work with
 * Galerio's styles and are readable.
 *
 * @coversNothing
 */
class Test_Style_Variations extends WP_UnitTestCase {

	/**
	 * The colour slugs Galerio's styles use. A palette replaces the parent's
	 * whole list, so it must define all of them.
	 *
	 * @var string[]
	 */
	const COLOUR_SLUGS = array( 'base', 'contrast', 'primary', 'secondary', 'custom-lower-contrast', 'custom-highlight', 'custom-hint', 'custom-lowlight' );

	/**
	 * Text and background pairs the theme's styles put together, with what they're used for.
	 *
	 * @var array[]
	 */
	const PAIRS = array(
		array( 'contrast', 'base', 'body text' ),
		array( 'primary', 'base', 'links' ),
		array( 'secondary', 'base', 'hovered links' ),
		array( 'custom-lower-contrast', 'base', 'captions' ),
		array( 'base', 'custom-lowlight', 'button text' ),
		array( 'base', 'custom-lower-contrast', 'hovered button text' ),
		array( 'custom-lowlight', 'custom-highlight', 'term buttons' ),
		array( 'contrast', 'custom-hint', 'aside text' ),
		array( 'primary', 'custom-hint', 'aside links' ),
	);

	/**
	 * Theme.json files with a palette: the theme's own, its colour variations and the child starter and swansea.art.
	 *
	 * @return array[]
	 */
	public function palette_files() {
		$themes = dirname( __DIR__, 3 ) . '/themes';
		$files  = array(
			'galerio'               => array( $themes . '/galerio/theme.json' ),
			'galerio-child-starter' => array( $themes . '/galerio-child-starter/theme.json' ),
			'swansea-art'           => array( $themes . '/swansea-art/theme.json' ),
		);

		foreach ( glob( $themes . '/galerio/styles/*.json' ) as $file ) {
			$data = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			if ( isset( $data['settings']['color']['palette'] ) ) {
				$files[ basename( $file, '.json' ) ] = array( $file );
			}
		}

		return $files;
	}

	/**
	 * Each variation file has a title and only theme.json keys WordPress reads.
	 */
	public function test_variations_are_valid() {
		$files = glob( dirname( __DIR__, 3 ) . '/themes/galerio/styles/*.json' );
		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$data = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.

			$this->assertIsArray( $data, basename( $file ) );
			$this->assertSame( 3, $data['version'], basename( $file ) );
			$this->assertNotEmpty( $data['title'], basename( $file ) );
			$this->assertSame( array(), array_diff( array_keys( $data ), array( '$schema', 'version', 'title', 'settings', 'styles' ) ), basename( $file ) );
		}
	}

	/**
	 * A palette defines every colour Galerio's styles use, and its text is readable (WCAG AA, 4.5:1).
	 *
	 * @dataProvider palette_files
	 *
	 * @param string $file Theme.json file.
	 */
	public function test_palette( $file ) {
		$data    = json_decode( file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$palette = array_column( $data['settings']['color']['palette'], 'color', 'slug' );

		$this->assertSame( array(), array_diff( self::COLOUR_SLUGS, array_keys( $palette ) ), 'Missing colours.' );

		foreach ( self::PAIRS as list( $text, $background, $use ) ) {
			$this->assertGreaterThanOrEqual(
				4.5,
				self::contrast( $palette[ $text ], $palette[ $background ] ),
				sprintf( 'Contrast of %s (%s on %s).', $use, $text, $background )
			);
		}
	}

	/**
	 * WCAG contrast ratio of two hex colours.
	 *
	 * @param string $a Hex colour.
	 * @param string $b Hex colour.
	 * @return float
	 */
	private static function contrast( $a, $b ) {
		$luminance = function ( $hex ) {
			$channels = array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) );
			$linear   = array_map(
				function ( $channel ) {
					$channel /= 255;
					return $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
				},
				$channels
			);
			return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
		};

		$light = max( $luminance( $a ), $luminance( $b ) );
		$dark  = min( $luminance( $a ), $luminance( $b ) );

		return ( $light + 0.05 ) / ( $dark + 0.05 );
	}
}
