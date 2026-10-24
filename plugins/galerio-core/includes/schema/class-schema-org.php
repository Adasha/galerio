<?php
/**
 * Schema.org structured data for works, profiles and collections.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Schema;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Describes Galerio's content to search engines as JSON-LD.
 *
 * - A work is the creative work type its medium suggests, since works aren't
 *   only visual: a painting, a photograph, a piece of music, a film. Each
 *   medium term can set the type, and terms inherit their parent's.
 * - A profile is a Person, or an Organization for collectives. Anything the
 *   profile hides (portrait, nationality, lifespan, links) is left out.
 * - A collection with dates is an ExhibitionEvent, otherwise a Collection.
 */
final class Schema_Org {

	/**
	 * Term meta holding a medium's Schema.org type.
	 */
	const TERM_META = 'galerio_schema_type';

	/**
	 * Medium taxonomy.
	 */
	const MEDIUM = 'galerio_medium';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_term_meta' ), 20 );
		add_action( self::MEDIUM . '_add_form_fields', array( __CLASS__, 'add_term_field' ) );
		add_action( self::MEDIUM . '_edit_form_fields', array( __CLASS__, 'edit_term_field' ) );
		add_action( 'created_' . self::MEDIUM, array( __CLASS__, 'save_term_field' ) );
		add_action( 'edited_' . self::MEDIUM, array( __CLASS__, 'save_term_field' ) );
	}

	/**
	 * The creative work types a medium can choose, with their labels.
	 *
	 * @return array<string, string>
	 */
	public static function types() {
		/**
		 * Filters the Schema.org types a medium can give its works.
		 *
		 * @param array<string, string> $types Type => label.
		 */
		return apply_filters(
			'galerio_schema_work_types',
			array(
				'CreativeWork'     => __( 'Creative work (general)', 'galerio-core' ),
				'VisualArtwork'    => __( 'Visual artwork', 'galerio-core' ),
				'Painting'         => __( 'Painting', 'galerio-core' ),
				'Drawing'          => __( 'Drawing', 'galerio-core' ),
				'Photograph'       => __( 'Photograph', 'galerio-core' ),
				'Sculpture'        => __( 'Sculpture', 'galerio-core' ),
				'MusicComposition' => __( 'Music composition', 'galerio-core' ),
				'MusicRecording'   => __( 'Music recording', 'galerio-core' ),
				'AudioObject'      => __( 'Sound or audio work', 'galerio-core' ),
				'Book'             => __( 'Book', 'galerio-core' ),
				'Movie'            => __( 'Film', 'galerio-core' ),
				'VideoObject'      => __( 'Video', 'galerio-core' ),
			)
		);
	}

	/**
	 * Types guessed from a medium's slug when it doesn't set one.
	 *
	 * @return array<string, string> Pattern => type, tried in order.
	 */
	private static function defaults() {
		return array(
			'/paint|(^|-)oil|acrylic|watercolou?r|gouache|tempera|fresco/' => 'Painting',
			'/draw|charcoal|pencil|graphite|pastel|(^|-)ink(-|$)/' => 'Drawing',
			'/photo/'                        => 'Photograph',
			'/sculpt|bronze|carv/'           => 'Sculpture',
			'/recording|album/'              => 'MusicRecording',
			'/music|composition|song/'       => 'MusicComposition',
			'/sound|audio|radio/'            => 'AudioObject',
			'/^book|artists-book/'           => 'Book',
			'/film|cinema/'                  => 'Movie',
			'/video|moving-image|animation/' => 'VideoObject',
			'/print|ceramic|textile|install|collage|mixed-media|visual|craft|glass|jewel|illustrat/' => 'VisualArtwork',
		);
	}

	/**
	 * Register the medium term meta.
	 */
	public static function register_term_meta() {
		register_term_meta(
			self::MEDIUM,
			self::TERM_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					return isset( self::types()[ $value ] ) ? $value : '';
				},
			)
		);
	}

	/**
	 * The type a medium term gives its works: its own, an ancestor's, or one
	 * guessed from the slugs.
	 *
	 * @param WP_Term $term Medium term.
	 * @return string Empty when nothing applies.
	 */
	public static function term_type( WP_Term $term ) {
		$chain = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, self::MEDIUM, 'taxonomy' ) );

		foreach ( $chain as $id ) {
			$type = get_term_meta( $id, self::TERM_META, true );
			if ( $type && isset( self::types()[ $type ] ) ) {
				return $type;
			}
		}

		foreach ( $chain as $id ) {
			$slug = get_term( $id, self::MEDIUM )->slug;
			foreach ( self::defaults() as $pattern => $type ) {
				if ( preg_match( $pattern, $slug ) ) {
					return $type;
				}
			}
		}

		return '';
	}

	/**
	 * A work's type, from the first of its mediums that gives one.
	 *
	 * @param WP_Post $post Work.
	 * @return string
	 */
	public static function work_type( WP_Post $post ) {
		$terms = get_the_terms( $post, self::MEDIUM );

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$type = self::term_type( $term );
			if ( $type ) {
				return $type;
			}
		}

		return 'CreativeWork';
	}

	/**
	 * The structured data for a post, or null when it has none.
	 *
	 * @param WP_Post $post Post.
	 * @return array|null
	 */
	public static function data( WP_Post $post ) {
		switch ( $post->post_type ) {
			case 'galerio_work':
				$data = self::work( $post );
				break;
			case 'galerio_profile':
				$data = self::profile( $post );
				break;
			case 'galerio_collection':
				$data = self::collection( $post );
				break;
			default:
				$data = null;
		}

		/**
		 * Filters a post's Schema.org data. Return null to leave it out.
		 *
		 * @param array|null $data Data, without @context.
		 * @param WP_Post    $post Post.
		 */
		$data = apply_filters( 'galerio_schema_data', $data, $post );

		return $data ? array_merge( array( '@context' => 'https://schema.org' ), self::clean( $data ) ) : null;
	}

	/**
	 * Print the current page's data.
	 */
	public static function output() {
		/**
		 * Filters whether Galerio prints Schema.org data, for sites whose SEO
		 * plugin already describes this content.
		 *
		 * @param bool $enabled Whether to print it.
		 */
		if ( ! is_singular() || ! apply_filters( 'galerio_schema_enabled', true ) ) {
			return;
		}

		$data = self::data( get_queried_object() );

		if ( $data ) {
			printf( "<script type=\"application/ld+json\">%s</script>\n", wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) );
		}
	}

	/**
	 * A work.
	 *
	 * @param WP_Post $post Work.
	 * @return array
	 */
	private static function work( WP_Post $post ) {
		$type    = self::work_type( $post );
		$title   = galerio_get_field( 'work.definitive_title', $post );
		$creator = galerio_get_field( 'work.creator', $post );
		$year    = galerio_get_field( 'work.year', $post );
		$mediums = wp_get_post_terms( $post->ID, self::MEDIUM, array( 'fields' => 'names' ) );
		$labels  = wp_get_post_terms( $post->ID, 'galerio_label', array( 'fields' => 'names' ) );

		$data = array(
			'@type'       => $type,
			'@id'         => get_permalink( $post ) . '#work',
			'name'        => $title ? $title : get_the_title( $post ),
			'url'         => get_permalink( $post ),
			'image'       => self::image( get_post_thumbnail_id( $post ) ),
			'creator'     => $creator ? self::reference( $creator ) : null,
			'dateCreated' => $year ? (string) $year : null,
			'description' => self::summary( galerio_get_field( 'work.description', $post ) ),
			'keywords'    => is_array( $labels ) && $labels ? implode( ', ', $labels ) : null,
			'genre'       => is_array( $mediums ) && $mediums ? implode( ', ', $mediums ) : null,
		);

		if ( in_array( $type, array( 'VisualArtwork', 'Painting', 'Drawing', 'Photograph', 'Sculpture' ), true ) ) {
			$medium = galerio_get_field( 'work.medium', $post );

			$data['artMedium'] = $medium ? $medium : $data['genre'];
			unset( $data['genre'] );
		}

		if ( in_array( $type, array( 'MusicRecording', 'AudioObject', 'VideoObject', 'Movie' ), true ) && galerio_get_field( 'work.duration', $post ) ) {
			$data['timeRequired'] = galerio_get_field( 'work.duration', $post );
		}

		return $data;
	}

	/**
	 * A profile.
	 *
	 * @param WP_Post $post Profile.
	 * @return array
	 */
	private static function profile( WP_Post $post ) {
		$group = galerio_get_field( 'profile.is_collective', $post ) || galerio_get_field( 'profile.members', $post );
		$links = galerio_get_field( 'profile.show_links', $post ) ? galerio_get_field( 'profile.links', $post ) : array();

		$data = array(
			'@type'       => $group ? 'Organization' : 'Person',
			'@id'         => get_permalink( $post ) . '#profile',
			'name'        => get_the_title( $post ),
			'url'         => get_permalink( $post ),
			'image'       => galerio_get_field( 'profile.show_portrait', $post ) ? self::image( galerio_get_field( 'profile.portrait', $post ) ) : null,
			'description' => self::summary( galerio_get_field( 'profile.bio', $post ) ),
			'sameAs'      => array_values(
				array_map(
					function ( $link ) {
						return $link['url'];
					},
					array_filter(
						(array) $links,
						function ( $link ) {
							return empty( $link['offline'] );
						}
					)
				)
			),
			'memberOf'    => self::references( galerio_get_field( 'profile.member_of', $post ) ),
		);

		if ( $group ) {
			$data['member'] = self::references( galerio_get_field( 'profile.members', $post ) );
		} else {
			if ( galerio_get_field( 'profile.show_nationality', $post ) ) {
				$data['nationality'] = galerio_get_field( 'profile.nationality', $post );
			}
			if ( galerio_get_field( 'profile.show_lifespan', $post ) ) {
				$born  = galerio_get_field( 'profile.year_of_birth', $post );
				$died  = galerio_get_field( 'profile.year_of_death', $post );
				$data += array(
					'birthDate' => $born ? (string) $born : null,
					'deathDate' => $died ? (string) $died : null,
				);
			}
		}

		return $data;
	}

	/**
	 * A collection: an exhibition when it has dates.
	 *
	 * @param WP_Post $post Collection.
	 * @return array
	 */
	private static function collection( WP_Post $post ) {
		$start    = galerio_get_field( 'collection.start_date', $post );
		$end      = galerio_get_field( 'collection.end_date', $post );
		$location = galerio_get_field( 'collection.location', $post );
		$works    = array_slice( (array) galerio_get_field( 'collection.works', $post ), 0, 50 );
		$summary  = galerio_get_field( 'collection.standfirst', $post );

		$data = array(
			'@type'       => $start ? 'ExhibitionEvent' : 'Collection',
			'@id'         => get_permalink( $post ) . '#collection',
			'name'        => get_the_title( $post ),
			'url'         => get_permalink( $post ),
			'image'       => self::image( get_post_thumbnail_id( $post ) ),
			'description' => $summary ? $summary : self::summary( galerio_get_field( 'collection.description', $post ) ),
		);

		if ( $start ) {
			$data += array(
				'startDate'    => $start,
				'endDate'      => $end ? $end : null,
				'location'     => array(
					'@type' => 'Place',
					'name'  => $location ? $location : get_bloginfo( 'name' ),
				),
				'organizer'    => array(
					'@type' => 'Organization',
					'name'  => get_bloginfo( 'name' ),
					'url'   => home_url( '/' ),
				),
				'workFeatured' => self::references( $works ),
			);
		} else {
			$data['hasPart'] = self::references( $works );
		}

		return $data;
	}

	/**
	 * A short reference to another Galerio item: its type, name and address.
	 *
	 * @param int $id Post ID.
	 * @return array|null Null unless published.
	 */
	private static function reference( $id ) {
		$post = get_post( $id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		switch ( $post->post_type ) {
			case 'galerio_profile':
				$group = galerio_get_field( 'profile.is_collective', $post ) || galerio_get_field( 'profile.members', $post );
				$type  = $group ? 'Organization' : 'Person';
				$hash  = '#profile';
				break;
			case 'galerio_work':
				$type = self::work_type( $post );
				$hash = '#work';
				break;
			default:
				$type = 'CreativeWork';
				$hash = '';
		}

		return array(
			'@type' => $type,
			'@id'   => get_permalink( $post ) . $hash,
			'name'  => get_the_title( $post ),
			'url'   => get_permalink( $post ),
		);
	}

	/**
	 * References to several items, leaving out unpublished ones.
	 *
	 * @param int[]|int $ids Post IDs.
	 * @return array[]
	 */
	private static function references( $ids ) {
		return array_values( array_filter( array_map( array( __CLASS__, 'reference' ), (array) $ids ) ) );
	}

	/**
	 * An image's address, at a size search engines accept.
	 *
	 * @param int $id Attachment ID.
	 * @return string|null
	 */
	private static function image( $id ) {
		$url = $id ? wp_get_attachment_image_url( $id, 'large' ) : false;

		return $url ? $url : null;
	}

	/**
	 * Plain text from formatted text, cut to about 60 words.
	 *
	 * @param string $html Formatted text.
	 * @return string|null
	 */
	private static function summary( $html ) {
		$text = trim( wp_trim_words( wp_strip_all_tags( (string) $html ), 60 ) );

		return '' === $text ? null : html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Drop empty values, so the data says only what's known.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private static function clean( $data ) {
		return array_filter(
			$data,
			function ( $value ) {
				return null !== $value && '' !== $value && array() !== $value;
			}
		);
	}

	/**
	 * The type setting on the Add Medium form.
	 */
	public static function add_term_field() {
		?>
		<div class="form-field">
			<label for="galerio-schema-type"><?php esc_html_e( 'Kind of work, for search engines', 'galerio-core' ); ?></label>
			<?php self::type_select( '' ); ?>
			<p><?php esc_html_e( 'How works in this medium are described to search engines. Leave it to follow the parent medium, or Galerio’s guess from the name.', 'galerio-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * The type setting on the Edit Medium form.
	 *
	 * @param WP_Term $term Term being edited.
	 */
	public static function edit_term_field( $term ) {
		$guess = self::term_type( $term );
		$own   = get_term_meta( $term->term_id, self::TERM_META, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="galerio-schema-type"><?php esc_html_e( 'Kind of work, for search engines', 'galerio-core' ); ?></label></th>
			<td>
				<?php self::type_select( $own ); ?>
				<p class="description">
					<?php
					if ( ! $own && $guess ) {
						/* translators: %s: a kind of work, such as "Painting". */
						printf( esc_html__( 'Currently: %s, from the parent medium or the name.', 'galerio-core' ), esc_html( self::types()[ $guess ] ) );
					} else {
						esc_html_e( 'How works in this medium are described to search engines. Media inside this one follow it unless they set their own.', 'galerio-core' );
					}
					?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * The type dropdown.
	 *
	 * @param string $value Selected type.
	 */
	private static function type_select( $value ) {
		wp_nonce_field( 'galerio_schema_type', 'galerio_schema_type_nonce' );
		echo '<select name="galerio_schema_type" id="galerio-schema-type">';
		printf( '<option value="">%s</option>', esc_html__( 'Automatic', 'galerio-core' ) );
		foreach ( self::types() as $type => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $type ), selected( $value, $type, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Save the type setting from the medium forms.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_term_field( $term_id ) {
		if ( ! isset( $_POST['galerio_schema_type_nonce'], $_POST['galerio_schema_type'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['galerio_schema_type_nonce'] ), 'galerio_schema_type' )
			|| ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$type = sanitize_text_field( wp_unslash( $_POST['galerio_schema_type'] ) );

		if ( isset( self::types()[ $type ] ) ) {
			update_term_meta( $term_id, self::TERM_META, $type );
		} else {
			delete_term_meta( $term_id, self::TERM_META );
		}
	}
}
