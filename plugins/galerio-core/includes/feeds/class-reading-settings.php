<?php
/**
 * Galerio's settings on Settings → Reading.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Feeds;

defined( 'ABSPATH' ) || exit;

/**
 * A "Galerio feeds and sitemap" section on Settings → Reading, next to
 * WordPress's own feed and search engine settings: whether the main feed
 * includes articles, and what the sitemap leaves out.
 *
 * The options keep the galerio_settings_ prefix of Settings → Galerio, so
 * they carry over when that page is Galerio's own.
 */
final class Reading_Settings {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the settings and their section.
	 */
	public static function register() {
		register_setting(
			'reading',
			Feeds::ARTICLES_OPTION,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			'reading',
			Sitemaps::OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( Sitemaps::class, 'sanitize' ),
			)
		);

		add_settings_section(
			'galerio_feeds',
			__( 'Galerio feeds and sitemap', 'galerio-core' ),
			'__return_false',
			'reading'
		);
		add_settings_field(
			'galerio_feed_articles',
			__( 'Main feed', 'galerio-core' ),
			array( __CLASS__, 'articles_field' ),
			'reading',
			'galerio_feeds'
		);
		add_settings_field(
			'galerio_sitemap_exclude',
			__( 'Sitemap', 'galerio-core' ),
			array( __CLASS__, 'sitemap_field' ),
			'reading',
			'galerio_feeds'
		);
	}

	/**
	 * Whether the main feed includes articles.
	 */
	public static function articles_field() {
		$type = get_post_type_object( 'galerio_article' );

		if ( ! $type ) {
			esc_html_e( 'Articles aren’t set up on this site.', 'galerio-core' );
			return;
		}
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( Feeds::ARTICLES_OPTION ); ?>" value="1" <?php checked( Feeds::articles_in_main_feed() ); ?> />
			<?php
			/* translators: %s: the site's name for articles, e.g. "News stories". */
			echo esc_html( sprintf( __( 'Include %s in the main feed', 'galerio-core' ), $type->labels->name ) );
			?>
		</label>
		<p class="description">
			<?php
			printf(
				/* translators: 1: the main feed's address, 2: the address of the feed of articles alone. */
				esc_html__( 'Without this, the main feed (%1$s) lists posts only. Either way, they have a feed of their own: %2$s', 'galerio-core' ),
				esc_html( get_feed_link() ),
				'<a href="' . esc_url( get_post_type_archive_feed_link( 'galerio_article' ) ) . '">' . esc_html( get_post_type_archive_feed_link( 'galerio_article' ) ) . '</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * What the sitemap leaves out.
	 */
	public static function sitemap_field() {
		$excluded = Sitemaps::excluded();
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Leave out of the sitemap', 'galerio-core' ); ?></legend>
			<p><?php esc_html_e( 'Leave out of the sitemap:', 'galerio-core' ); ?></p>
			<?php foreach ( Sitemaps::choices() as $key => $label ) : ?>
				<label style="display: block; margin: 0.25em 0;">
					<input type="checkbox" name="<?php echo esc_attr( Sitemaps::OPTION ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $excluded, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
			<input type="hidden" name="<?php echo esc_attr( Sitemaps::OPTION ); ?>[]" value="" />
		</fieldset>
		<p class="description">
			<?php
			if ( '0' === (string) get_option( 'blog_public' ) ) {
				esc_html_e( 'The sitemap is off while search engines are discouraged from indexing this site.', 'galerio-core' );
			} else {
				printf(
					/* translators: %s: the sitemap's address, as a link. */
					esc_html__( 'What’s left out can still be found on the site; search engines just aren’t pointed to it. The sitemap: %s', 'galerio-core' ),
					'<a href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '">' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</a>'
				);
			}
			?>
		</p>
		<?php
	}
}
