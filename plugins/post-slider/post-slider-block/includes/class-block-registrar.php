<?php
/**
 * Handles block registration and asset enqueueing.
 */

declare( strict_types=1 );

namespace PostSliderBlock;

class Block_Registrar {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_block' ] );
	}

	public static function register_block(): void {
		// Register the block using block.json metadata.
		register_block_type(
			PSB_PLUGIN_DIR . 'build',
			[
				'render_callback' => [ __CLASS__, 'render_block' ],
			]
		);

		// Make translated strings available to JS.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'post-slider-block-editor-script',
				'post-slider-block',
				PSB_PLUGIN_DIR . 'languages'
			);
		}
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Inner block content (unused).
	 * @return string            Rendered HTML.
	 */
	public static function render_block( array $attributes, string $content ): string {
		$atts = self::parse_attributes( $attributes );
		$posts = self::get_posts( $atts );

		if ( empty( $posts ) ) {
			return '<p class="psb-no-posts">' . esc_html__( 'No posts found.', 'post-slider-block' ) . '</p>';
		}

		// Inline CSS custom properties derived from block attributes.
		$css_vars = self::build_css_vars( $atts );

		$wrapper_attributes = get_block_wrapper_attributes( [
			'class'         => 'psb-slider-wrapper psb-transition-' . esc_attr( $atts['transition'] ),
			'data-autoplay' => $atts['autoplay'] ? 'true' : 'false',
			'data-interval' => (string) absint( $atts['autoplayInterval'] ),
			'data-transition'=> esc_attr( $atts['transition'] ),
			'style'         => $css_vars,
		] );

		ob_start();
		?>
		<div <?php echo $wrapper_attributes; ?>>
			<div class="psb-slider" role="region" aria-label="<?php esc_attr_e( 'Post Slider', 'post-slider-block' ); ?>" aria-roledescription="carousel">
				<div class="psb-track" aria-live="polite">
					<?php foreach ( $posts as $index => $post ) : ?>
						<div
							class="psb-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
							role="group"
							aria-roledescription="slide"
							aria-label="<?php /* translators: 1: current slide, 2: total slides */ printf( esc_attr__( '%1$d of %2$d', 'post-slider-block' ), $index + 1, count( $posts ) ); ?>"
							aria-hidden="<?php echo $index === 0 ? 'false' : 'true'; ?>"
						>
							<?php echo self::render_slide( $post, $atts ); ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $atts['showNavigation'] ) : ?>
				<div class="psb-nav" aria-label="<?php esc_attr_e( 'Slider navigation', 'post-slider-block' ); ?>">
					<button class="psb-nav__btn psb-nav__btn--prev" aria-label="<?php esc_attr_e( 'Previous slide', 'post-slider-block' ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
					</button>
					<button class="psb-nav__btn psb-nav__btn--next" aria-label="<?php esc_attr_e( 'Next slide', 'post-slider-block' ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
					</button>
				</div>
				<?php endif; ?>

				<?php if ( $atts['showDots'] ) : ?>
				<div class="psb-dots" role="tablist" aria-label="<?php esc_attr_e( 'Slide indicators', 'post-slider-block' ); ?>">
					<?php foreach ( $posts as $index => $post ) : ?>
						<button
							class="psb-dot<?php echo $index === 0 ? ' is-active' : ''; ?>"
							role="tab"
							aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
							aria-label="<?php /* translators: slide number */ printf( esc_attr__( 'Go to slide %d', 'post-slider-block' ), $index + 1 ); ?>"
							data-index="<?php echo esc_attr( (string) $index ); ?>"
						></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single slide.
	 */
	private static function render_slide( \WP_Post $post, array $atts ): string {
		$has_thumbnail = $atts['showThumbnail'] && has_post_thumbnail( $post );
		$excerpt       = $atts['showExcerpt'] ? wp_trim_words( get_the_excerpt( $post ), 20 ) : '';

		ob_start();
		?>
		<article class="psb-slide__inner">
			<?php if ( $has_thumbnail ) : ?>
				<div class="psb-slide__image">
					<?php echo get_the_post_thumbnail( $post, $atts['imageSize'], [ 'loading' => 'lazy', 'decoding' => 'async' ] ); ?>
				</div>
			<?php endif; ?>
			<div class="psb-slide__content">
				<?php if ( $atts['showCategory'] ) : ?>
					<div class="psb-slide__meta">
						<?php
						$terms = get_the_terms( $post, 'category' );
						if ( $terms && ! is_wp_error( $terms ) ) {
							echo '<span class="psb-slide__category">' . esc_html( $terms[0]->name ) . '</span>';
						}
						?>
					</div>
				<?php endif; ?>

				<h3 class="psb-slide__title">
					<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
						<?php echo esc_html( get_the_title( $post ) ); ?>
					</a>
				</h3>

				<?php if ( $atts['showDate'] ) : ?>
					<time class="psb-slide__date" datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>">
						<?php echo esc_html( get_the_date( '', $post ) ); ?>
					</time>
				<?php endif; ?>

				<?php if ( $excerpt ) : ?>
					<p class="psb-slide__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<?php if ( $atts['showReadMore'] ) : ?>
					<a class="psb-slide__cta" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
						<?php echo esc_html( $atts['readMoreText'] ?: __( 'Read More', 'post-slider-block' ) ); ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Query posts based on block attributes.
	 *
	 * @return \WP_Post[]
	 */
	private static function get_posts( array $atts ): array {
		$args = [
			'post_type'      => sanitize_key( $atts['postType'] ?: 'post' ),
			'posts_per_page' => absint( $atts['postCount'] ?: 5 ),
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];

		// Taxonomy filter.
		if ( ! empty( $atts['taxonomy'] ) && ! empty( $atts['termIds'] ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => sanitize_key( $atts['taxonomy'] ),
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', (array) $atts['termIds'] ),
				],
			];
		}

		$query = new \WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Build inline CSS custom properties string.
	 */
	private static function build_css_vars( array $atts ): string {
		$vars = [];

		$map = [
			'accentColor'      => '--psb-accent',
			'textColor'        => '--psb-text',
			'backgroundColor'  => '--psb-bg',
			'overlayColor'     => '--psb-overlay',
			'borderRadius'     => '--psb-radius',
			'slideHeight'      => '--psb-height',
		];

		foreach ( $map as $attr => $var ) {
			if ( ! empty( $atts[ $attr ] ) ) {
				$vars[] = esc_attr( $var ) . ':' . esc_attr( $atts[ $attr ] );
			}
		}

		return implode( ';', $vars );
	}

	/**
	 * Merge incoming attributes with safe defaults.
	 */
	private static function parse_attributes( array $attributes ): array {
		$defaults = [
			'postType'        => 'post',
			'postCount'       => 5,
			'taxonomy'        => '',
			'termIds'         => [],
			'transition'      => 'slide',
			'autoplay'        => false,
			'autoplayInterval'=> 4000,
			'showNavigation'  => true,
			'showDots'        => true,
			'showThumbnail'   => true,
			'showExcerpt'     => true,
			'showDate'        => true,
			'showCategory'    => true,
			'showReadMore'    => true,
			'readMoreText'    => '',
			'imageSize'       => 'large',
			'accentColor'     => '',
			'textColor'       => '',
			'backgroundColor' => '',
			'overlayColor'    => '',
			'borderRadius'    => '8px',
			'slideHeight'     => '480px',
		];

		return array_merge( $defaults, $attributes );
	}
}
