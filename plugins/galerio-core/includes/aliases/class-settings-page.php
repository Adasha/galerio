<?php
/**
 * Content types settings screen.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Aliases;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Galerio content types: the names and addresses of Galerio's
 * content types and taxonomies.
 */
final class Settings_Page {

	/**
	 * Admin page slug.
	 */
	const SLUG = 'galerio-content-types';

	/**
	 * Slugs WordPress uses for its own addresses.
	 *
	 * @var string[]
	 */
	const RESERVED = array( 'author', 'attachment', 'category', 'comments', 'date', 'embed', 'feed', 'page', 'search', 'tag', 'type', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
	}

	/**
	 * The presets, filtered.
	 *
	 * @return array
	 */
	public static function presets() {
		/**
		 * Filters the alias presets offered on the settings screen.
		 *
		 * @param array $presets Presets by slug: label, plus types and taxonomies as stored.
		 */
		return (array) apply_filters( 'galerio_alias_presets', require __DIR__ . '/presets.php' );
	}

	/**
	 * Register the option.
	 */
	public static function register_setting() {
		register_setting(
			self::SLUG,
			Aliases::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Add the page under Settings.
	 */
	public static function add_page() {
		$hook = add_options_page(
			__( 'Galerio content types', 'galerio-core' ),
			__( 'Galerio content types', 'galerio-core' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'enqueue_style' ) );
		}
	}

	/**
	 * Narrow the alias fields, which hold short names and slugs, and let
	 * the tables shrink to fit them. On phones each row stacks, with the
	 * column name above each field, as WordPress's own list tables do.
	 */
	public static function enqueue_style() {
		wp_register_style( 'galerio-aliases', false, array(), GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-aliases' );
		wp_add_inline_style(
			'galerio-aliases',
			'@media screen and (min-width: 783px) {
				.galerio-aliases.widefat {
					width: auto;
				}
				.galerio-aliases input.regular-text {
					width: 16em;
				}
			}
			@media screen and (max-width: 782px) {
				.galerio-aliases thead {
					display: none;
				}
				.galerio-aliases tr,
				.galerio-aliases th,
				.galerio-aliases td {
					display: block;
				}
				.galerio-aliases tbody th {
					font-weight: 600;
				}
				.galerio-aliases td::before {
					content: attr(data-colname);
					display: block;
					margin-bottom: 4px;
				}
				.galerio-aliases input.regular-text {
					width: 100%;
				}
			}'
		);
	}

	/**
	 * Sanitise submitted aliases.
	 *
	 * A slug that clashes with another type's, or with one WordPress uses,
	 * is reported and the previous value kept.
	 *
	 * @param mixed $value Submitted value.
	 * @return array
	 */
	public static function sanitize( $value ) {
		$value    = is_array( $value ) ? $value : array();
		$previous = Aliases::get();
		$clean    = array(
			'types'      => array(),
			'taxonomies' => array(),
			'redirect'   => ! empty( $value['redirect'] ),
		);

		foreach ( Aliases::kinds() as $kind => $keys ) {
			foreach ( array_keys( $keys ) as $key ) {
				foreach ( Aliases::properties( $kind ) as $property ) {
					$submitted = isset( $value[ $kind ][ $key ][ $property ] ) && is_scalar( $value[ $kind ][ $key ][ $property ] )
						? (string) $value[ $kind ][ $key ][ $property ]
						: '';

					$clean[ $kind ][ $key ][ $property ] = in_array( $property, array( 'slug', 'archive' ), true )
						? sanitize_title( $submitted )
						: sanitize_text_field( $submitted );
				}
			}
		}

		// Each address may belong to one type only: a type's archive may share its own single slug.
		$owners = array();

		foreach ( Aliases::kinds() as $kind => $keys ) {
			foreach ( array_keys( $keys ) as $key ) {
				foreach ( array( 'slug', 'archive' ) as $property ) {
					if ( ! in_array( $property, Aliases::properties( $kind ), true ) ) {
						continue;
					}

					$slug  = Aliases::effective( $kind, $key, $property, $clean );
					$owner = $kind . '.' . $key;

					if ( '' === $slug ) {
						continue;
					}

					$problem = '';

					if ( in_array( $slug, self::RESERVED, true ) ) {
						/* translators: %s: URL slug. */
						$problem = sprintf( __( 'The slug "%s" is used by WordPress itself.', 'galerio-core' ), $slug );
					} elseif ( isset( $owners[ $slug ] ) && $owners[ $slug ] !== $owner ) {
						/* translators: %s: URL slug. */
						$problem = sprintf( __( 'The slug "%s" is used by more than one content type.', 'galerio-core' ), $slug );
					}

					if ( $problem ) {
						add_settings_error( Aliases::OPTION, 'galerio-slug-' . $kind . '-' . $key . '-' . $property, $problem . ' ' . __( 'The previous slug was kept.', 'galerio-core' ) );
						$clean[ $kind ][ $key ][ $property ] = $previous[ $kind ][ $key ][ $property ];
						$slug                                = Aliases::effective( $kind, $key, $property, $clean );
					}

					$owners[ $slug ] = $owner;
				}
			}
		}

		return $clean;
	}

	/**
	 * Labels for the rows of the form.
	 *
	 * @return array{types: array<string, string>, taxonomies: array<string, string>}
	 */
	private static function row_labels() {
		return array(
			'types'      => array(
				'work'       => __( 'Works', 'galerio-core' ),
				'profile'    => __( 'Profiles', 'galerio-core' ),
				'collection' => __( 'Collections', 'galerio-core' ),
				'article'    => __( 'Articles', 'galerio-core' ),
			),
			'taxonomies' => array(
				'medium'              => __( 'Medium', 'galerio-core' ),
				'label'               => __( 'Labels', 'galerio-core' ),
				'group'               => __( 'Groups', 'galerio-core' ),
				'role'                => __( 'Roles', 'galerio-core' ),
				'collection_category' => __( 'Collection categories', 'galerio-core' ),
				'article_category'    => __( 'Article categories', 'galerio-core' ),
			),
		);
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$aliases = Aliases::get();
		$rows    = self::row_labels();
		$presets = self::presets();
		$columns = array(
			'singular' => __( 'Name (singular)', 'galerio-core' ),
			'plural'   => __( 'Name (plural)', 'galerio-core' ),
			'slug'     => __( 'Address', 'galerio-core' ),
			'archive'  => __( 'Archive address', 'galerio-core' ),
		);

		wp_enqueue_script( 'galerio-alias-presets', GALERIO_CORE_URL . 'assets/js/alias-presets.js', array(), GALERIO_CORE_VERSION, true );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Rename Galerio\'s content types and taxonomies to suit your site, and change their addresses. Leave a box empty to keep the name or address shown in grey. Your content isn\'t changed.', 'galerio-core' ); ?></p>

			<?php if ( $presets ) : ?>
				<p class="galerio-alias-presets">
					<label for="galerio-alias-preset"><?php esc_html_e( 'Start from a preset:', 'galerio-core' ); ?></label>
					<select id="galerio-alias-preset">
						<option value=""><?php esc_html_e( '— Choose —', 'galerio-core' ); ?></option>
						<?php foreach ( $presets as $slug => $preset ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" data-preset="<?php echo esc_attr( wp_json_encode( $preset ) ); ?>"><?php echo esc_html( isset( $preset['label'] ) ? $preset['label'] : $slug ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="galerio-alias-preset-apply"><?php esc_html_e( 'Fill in', 'galerio-core' ); ?></button>
					<span class="description"><?php esc_html_e( 'Fills in the form below. Nothing changes until you save, and you can edit any value first.', 'galerio-core' ); ?></span>
				</p>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( self::SLUG ); ?>

				<?php
				foreach ( array(
					'types'      => __( 'Content types', 'galerio-core' ),
					'taxonomies' => __( 'Taxonomies', 'galerio-core' ),
				) as $kind => $heading ) :
					?>
					<h2><?php echo esc_html( $heading ); ?></h2>
					<table class="widefat striped galerio-aliases">
						<thead>
							<tr>
								<td></td>
								<?php foreach ( Aliases::properties( $kind ) as $property ) : ?>
									<th scope="col"><?php echo esc_html( $columns[ $property ] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows[ $kind ] as $key => $row_label ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $row_label ); ?></th>
									<?php
									foreach ( Aliases::properties( $kind ) as $property ) :
										$original = Aliases::original( $kind, $key, $property );
										$is_slug  = in_array( $property, array( 'slug', 'archive' ), true );
										$name     = sprintf( '%s[%s][%s][%s]', Aliases::OPTION, $kind, $key, $property );
										?>
										<td data-colname="<?php echo esc_attr( $columns[ $property ] ); ?>">
											<?php if ( $is_slug && '' === $original ) : ?>
												<span class="description"><?php esc_html_e( 'None', 'galerio-core' ); ?></span>
											<?php else : ?>
												<input
													type="text"
													class="regular-text<?php echo $is_slug ? ' code' : ''; ?>"
													name="<?php echo esc_attr( $name ); ?>"
													value="<?php echo esc_attr( $aliases[ $kind ][ $key ][ $property ] ); ?>"
													placeholder="<?php echo esc_attr( $original ); ?>"
													aria-label="<?php echo esc_attr( $row_label . ': ' . $columns[ $property ] ); ?>"
													data-kind="<?php echo esc_attr( $kind ); ?>"
													data-key="<?php echo esc_attr( $key ); ?>"
													data-property="<?php echo esc_attr( $property ); ?>"
												/>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>

				<h2><?php esc_html_e( 'Old addresses', 'galerio-core' ); ?></h2>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Aliases::OPTION ); ?>[redirect]" value="1" <?php checked( $aliases['redirect'] ); ?> />
						<?php esc_html_e( 'Redirect old addresses to the new ones (301), so existing links keep working', 'galerio-core' ); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Turn this off if another plugin already manages your redirects.', 'galerio-core' ); ?></p>
				<?php
				$moved = Aliases::moved();
				if ( $moved ) :
					?>
					<ul>
						<?php foreach ( $moved as $old => $new ) : ?>
							<li><code>/<?php echo esc_html( $old ); ?>/</code> → <code>/<?php echo esc_html( $new ); ?>/</code></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
