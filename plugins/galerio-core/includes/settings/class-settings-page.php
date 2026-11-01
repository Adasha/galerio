<?php
/**
 * The Settings → Galerio page.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Settings;

use Galerio\Core\Catalogue\Catalogue;
use Galerio\Core\Types\Content_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Galerio's site-wide switches, on sites that have moved to native data. Sites
 * on Pods have the Pods settings page of the same name, and both keep the same
 * galerio_settings_{name} options, so saved values carry over either way.
 */
final class Settings_Page {

	/**
	 * Page slug and settings group.
	 */
	const SLUG = 'galerio-settings';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		if ( ! Content_Types::active() ) {
			return;
		}

		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
	}

	/**
	 * The page's sections and their settings.
	 *
	 * @return array[] Sections, each with a title and settings by name.
	 */
	public static function sections() {
		return array(
			'global'    => array(
				'title'    => __( 'Global settings', 'galerio-core' ),
				'settings' => array(
					'show_featured_images' => array(
						'label'       => __( 'Show featured images', 'galerio-core' ),
						'description' => __( 'When off, an item’s own page leaves out its featured image, such as the banner at the top of profiles and articles. Cards, archives and carousels still show featured images.', 'galerio-core' ),
						'type'        => 'switch',
						'default'     => '1',
					),
				),
			),
			'profile'   => array(
				'title'    => __( 'Profile settings', 'galerio-core' ),
				'settings' => array(
					'show_nationality'        => array(
						'label'       => __( 'Show nationality', 'galerio-core' ),
						'description' => __( 'When off, no profile page shows a nationality, whatever the profile’s own “Show nationality” says. The field stays in the editor and can still be used directly.', 'galerio-core' ),
						'type'        => 'switch',
						'default'     => '1',
					),
					'show_birth_death_fields' => array(
						'label'       => __( 'Show birth/death fields', 'galerio-core' ),
						'description' => __( 'When off, no profile page shows years of birth or death, whatever the profile’s own “Show lifespan” says. The fields stay in the editor and can still be used directly.', 'galerio-core' ),
						'type'        => 'switch',
						'default'     => '1',
					),
					'show_portraits'          => array(
						'label'       => __( 'Show portraits', 'galerio-core' ),
						'description' => __( 'When off, profile pages leave out the portrait, whatever the profile’s own “Show portrait” says, including a portrait standing in for the featured image. Cards still show it. The field stays in the editor and can still be used directly.', 'galerio-core' ),
						'type'        => 'switch',
						'default'     => '1',
					),
					'show_collective_fields'  => array(
						'label'       => __( 'Show collective fields', 'galerio-core' ),
						'description' => __( 'When off, profile pages leave out “Is a collective”, “Members” and “Member of”. The fields stay in the editor and can still be used directly.', 'galerio-core' ),
						'type'        => 'switch',
						'default'     => '1',
					),
				),
			),
			'catalogue' => array(
				'title'    => __( 'Catalogue', 'galerio-core' ),
				'settings' => array(
					'catalogue'     => array(
						'label'       => __( 'Catalogue', 'galerio-core' ),
						'description' => __( 'When on, works can show whether they’re for sale, their price, edition and dealer, and who represents each creator. Off for sites that only show work.', 'galerio-core' ),
						'type'        => 'switch',
						'default'     => '0',
					),
					'currency'      => array(
						'label'       => __( 'Currency', 'galerio-core' ),
						'description' => __( 'The currency prices are in, unless a work chooses its own. Valuations are in it too.', 'galerio-core' ),
						'type'        => 'currency',
						'default'     => Catalogue::DEFAULT_CURRENCY,
					),
					'enquiry_email' => array(
						'label'       => __( 'Enquiries email', 'galerio-core' ),
						'description' => __( 'Where enquiries about available works are sent. Leave empty for the site’s administration email. A work sold through a dealer links to them instead, and nothing is kept on the site.', 'galerio-core' ),
						'type'        => 'email',
						'default'     => '',
					),
				),
			),
		);
	}

	/**
	 * Register the options, sections and fields.
	 */
	public static function register() {
		foreach ( self::sections() as $section => $definition ) {
			add_settings_section( $section, $definition['title'], '__return_null', self::SLUG );

			foreach ( $definition['settings'] as $name => $setting ) {
				$option = Features::PREFIX . $name;

				register_setting(
					self::SLUG,
					$option,
					array(
						'type'              => 'string',
						'sanitize_callback' => function ( $value ) use ( $setting ) {
							return self::sanitize( $value, $setting['type'] );
						},
					)
				);

				add_settings_field(
					$option,
					$setting['label'],
					array( __CLASS__, 'render_field' ),
					self::SLUG,
					$section,
					array(
						'label_for' => $option,
						'option'    => $option,
						'setting'   => $setting,
					)
				);
			}
		}
	}

	/**
	 * Clean a submitted value.
	 *
	 * @param mixed  $value Value. A switch that's off isn't submitted at all.
	 * @param string $type  Setting type.
	 * @return string
	 */
	public static function sanitize( $value, $type ) {
		switch ( $type ) {
			case 'switch':
				return empty( $value ) ? '0' : '1';

			case 'currency':
				return is_string( $value ) && isset( Catalogue::currencies()[ $value ] ) ? $value : Catalogue::DEFAULT_CURRENCY;

			default:
				return sanitize_email( (string) $value );
		}
	}

	/**
	 * Add the page under Settings.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Galerio settings', 'galerio-core' ),
			__( 'Galerio', 'galerio-core' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Print one setting.
	 *
	 * @param array $args The option's name, and its setting.
	 */
	public static function render_field( $args ) {
		$option  = $args['option'];
		$setting = $args['setting'];
		$value   = get_option( $option, null );
		$value   = null === $value || '' === $value ? $setting['default'] : (string) $value;

		if ( 'switch' === $setting['type'] ) {
			printf(
				'<label><input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s> %3$s</label>',
				esc_attr( $option ),
				checked( '1', $value, false ),
				esc_html__( 'On', 'galerio-core' )
			);
		} elseif ( 'currency' === $setting['type'] ) {
			printf( '<select id="%1$s" name="%1$s">', esc_attr( $option ) );

			foreach ( Catalogue::currencies() as $code => $name ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $code ), selected( $code, $value, false ), esc_html( $name ) );
			}

			echo '</select>';
		} else {
			printf( '<input type="email" class="regular-text" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $option ), esc_attr( $value ) );
		}

		printf( '<p class="description">%s</p>', esc_html( $setting['description'] ) );
	}

	/**
	 * Print the page.
	 */
	public static function render_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SLUG );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
