<?php
/**
 * RSS and Atom feeds of Galerio's content types.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Feeds;

use Galerio\Core\Aliases\Names;
use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Makes the feeds of works, profiles, exhibitions and articles say what a
 * reader would want to know:
 *
 * - each item carries its featured image as `media:content`;
 * - its `dc:creator` (or Atom author) is the work's creators, the article's
 *   authors, the exhibition's curators or the profile itself, not the user
 *   who posted it;
 * - the content starts with its key details (creator, year, medium,
 *   exhibition dates and so on), and leaves out the page's interactive
 *   markup, such as the lightbox button;
 * - an exhibition or article with a standfirst uses it as its description;
 * - the exhibitions feed is ordered by opening date, latest first.
 *
 * The main feed (/feed/) can also include articles: the "Include articles in
 * the main feed" setting on Settings → Reading.
 */
final class Feeds {

	/**
	 * Option: include articles in the main feed.
	 */
	const ARTICLES_OPTION = 'galerio_settings_feed_articles';

	/**
	 * The Media RSS namespace.
	 */
	const MEDIA_NS = 'http://search.yahoo.com/mrss/';

	/**
	 * Fields shown at the top of an item's content, by post type. A field
	 * shown only when the profile allows it names its switch.
	 */
	const DETAILS = array(
		'galerio_work'       => array( 'work.creator', 'work.date_label', 'work.medium', 'work.dimensions', 'work.duration' ),
		'galerio_collection' => array( 'dates', 'collection.location' ),
		'galerio_profile'    => array( 'profile.nationality', 'lifespan' ),
		'galerio_article'    => array( 'article.authors' ),
	);

	/**
	 * The people an item is by, for `dc:creator`, by post type.
	 */
	const CREATORS = array(
		'galerio_work'       => 'work.creator',
		'galerio_collection' => 'collection.curators',
		'galerio_article'    => 'article.authors',
	);

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_action( 'rss2_ns', array( __CLASS__, 'namespace' ) );
		add_action( 'atom_ns', array( __CLASS__, 'namespace' ) );
		add_action( 'rss2_item', array( __CLASS__, 'media' ) );
		add_action( 'atom_entry', array( __CLASS__, 'media' ) );
		add_filter( 'the_author', array( __CLASS__, 'author' ) );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'description' ) );
		add_filter( 'the_content_feed', array( __CLASS__, 'content' ), 20 );
	}

	/**
	 * Whether the main feed includes articles.
	 *
	 * @return bool
	 */
	public static function articles_in_main_feed() {
		return (bool) get_option( self::ARTICLES_OPTION, false );
	}

	/**
	 * The current feed item, when it's one of Galerio's types.
	 *
	 * @return WP_Post|null
	 */
	private static function item() {
		if ( ! is_feed() ) {
			return null;
		}

		$post = get_post();

		return $post && isset( self::DETAILS[ $post->post_type ] ) ? $post : null;
	}

	/**
	 * Articles in the main feed, when chosen, and the exhibitions feed in
	 * order of opening date.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function query( WP_Query $query ) {
		if ( ! $query->is_main_query() || ! $query->is_feed() || $query->is_comment_feed() ) {
			return;
		}

		// The main feed: WordPress doesn't count it as the home page, so it's
		// the feed that isn't an archive's, a single item's or a search's.
		$main = ! $query->is_archive() && ! $query->is_singular() && ! $query->is_search() && ! $query->get( 'post_type' );

		if ( $main && self::articles_in_main_feed() && post_type_exists( 'galerio_article' ) ) {
			$query->set( 'post_type', array( 'post', 'galerio_article' ) );
			return;
		}

		if ( $query->is_post_type_archive( 'galerio_collection' ) && function_exists( 'galerio_fields' ) ) {
			$start = galerio_fields()->get( 'collection.start_date' );

			if ( $start ) {
				// Collections without a start date come last, newest first.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Ordering by date is the point.
				$query->set(
					'meta_query',
					array(
						'relation'      => 'OR',
						'galerio_start' => array(
							'key'     => $start->source,
							'compare' => 'EXISTS',
							'type'    => 'DATE',
						),
						array(
							'key'     => $start->source,
							'compare' => 'NOT EXISTS',
						),
					)
				);
				$query->set(
					'orderby',
					array(
						'galerio_start' => 'DESC',
						'date'          => 'DESC',
					)
				);
			}
		}
	}

	/**
	 * The Media RSS namespace, for the items' images.
	 */
	public static function namespace() {
		echo 'xmlns:media="' . esc_url( self::MEDIA_NS ) . '"' . "\n";
	}

	/**
	 * The item's featured image, as `media:content` with a thumbnail.
	 */
	public static function media() {
		$post = get_post();
		$id   = $post ? get_post_thumbnail_id( $post ) : 0;
		$full = $id ? wp_get_attachment_image_src( $id, 'large' ) : false;

		if ( ! $full ) {
			return;
		}

		$thumbnail = wp_get_attachment_image_src( $id, 'medium' );
		$alt       = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );

		printf(
			"\t\t" . '<media:content url="%1$s" medium="image" type="%2$s" width="%3$d" height="%4$d">' . "\n",
			esc_url( $full[0] ),
			esc_attr( (string) get_post_mime_type( $id ) ),
			(int) $full[1],
			(int) $full[2]
		);
		if ( '' !== $alt ) {
			printf( "\t\t\t" . '<media:description type="plain"><![CDATA[%s]]></media:description>' . "\n", esc_html( $alt ) );
		}
		if ( $thumbnail ) {
			printf(
				"\t\t\t" . '<media:thumbnail url="%1$s" width="%2$d" height="%3$d" />' . "\n",
				esc_url( $thumbnail[0] ),
				(int) $thumbnail[1],
				(int) $thumbnail[2]
			);
		}
		echo "\t\t</media:content>\n";
	}

	/**
	 * Who an item is by: its creators, authors or curators, or the profile
	 * itself. Without any, the site, rather than the user who posted it.
	 *
	 * @param string $author The post's author's name.
	 * @return string
	 */
	public static function author( $author ) {
		$post = self::item();

		if ( ! $post ) {
			return $author;
		}

		if ( 'galerio_profile' === $post->post_type ) {
			return get_the_title( $post );
		}

		$names = array();
		if ( isset( self::CREATORS[ $post->post_type ] ) && function_exists( 'galerio_get_related' ) ) {
			foreach ( galerio_get_related( self::CREATORS[ $post->post_type ], $post ) as $person ) {
				$names[] = get_the_title( $person );
			}
		}

		return $names ? wp_sprintf( '%l', $names ) : get_bloginfo( 'name' );
	}

	/**
	 * An exhibition's or article's standfirst as its description.
	 *
	 * @param string $excerpt The excerpt.
	 * @return string
	 */
	public static function description( $excerpt ) {
		$post = self::item();

		if ( ! $post || ! in_array( $post->post_type, array( 'galerio_collection', 'galerio_article' ), true ) ) {
			return $excerpt;
		}

		$standfirst = trim( wp_strip_all_tags( (string) galerio_get_field( 'standfirst', $post ) ) );

		return '' === $standfirst ? $excerpt : esc_html( $standfirst );
	}

	/**
	 * The item's key details, then its content without the page's
	 * interactive markup.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function content( $content ) {
		$content = self::clean( $content );
		$post    = self::item();

		return $post ? self::details( $post ) . $content : $content;
	}

	/**
	 * Content without what only works on the page: the image lightbox's
	 * button, and the Interactivity API's `data-wp-*` attributes.
	 *
	 * @param string $html Content.
	 * @return string
	 */
	public static function clean( $html ) {
		if ( false === strpos( $html, 'data-wp-' ) && false === strpos( $html, 'lightbox-trigger' ) ) {
			return $html;
		}

		$html = (string) preg_replace( '#<button\b[^>]*\blightbox-trigger\b[^>]*>.*?</button>#s', '', $html );

		$tags = new WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			foreach ( (array) $tags->get_attribute_names_with_prefix( 'data-wp-' ) as $name ) {
				$tags->remove_attribute( $name );
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * The item's key details, as lines of "Label: value".
	 *
	 * @param WP_Post $post Item.
	 * @return string HTML, or empty when it has none.
	 */
	public static function details( WP_Post $post ) {
		if ( ! function_exists( 'galerio_get_field' ) || ! isset( self::DETAILS[ $post->post_type ] ) ) {
			return '';
		}

		$lines = array();
		foreach ( self::DETAILS[ $post->post_type ] as $key ) {
			$line = self::detail( $key, $post );
			if ( $line ) {
				$lines[] = sprintf( '<strong>%1$s:</strong> %2$s', esc_html( $line[0] ), $line[1] );
			}
		}

		return $lines ? '<p class="galerio-feed-details">' . implode( '<br />', $lines ) . "</p>\n" : '';
	}

	/**
	 * One detail's label and value.
	 *
	 * @param string  $key  Field key, or "dates" or "lifespan".
	 * @param WP_Post $post Item.
	 * @return array|null Label, and value as HTML; null when there's nothing to show.
	 */
	private static function detail( $key, WP_Post $post ) {
		if ( 'dates' === $key ) {
			return self::dates( $post );
		}

		if ( 'lifespan' === $key ) {
			return self::lifespan( $post );
		}

		if ( 'profile.nationality' === $key && ! galerio_get_field( 'profile.show_nationality', $post ) ) {
			return null;
		}

		$field = galerio_fields()->get( $key );
		$value = $field ? galerio_get_field( $key, $post, 'display' ) : '';

		if ( 'work.medium' === $key && '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			// The mediums the work is filed under, when it has no medium text.
			$terms = get_the_terms( $post, 'galerio_medium' );
			$value = is_array( $terms ) ? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) : '';
		}

		if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			return null;
		}

		// The date as shown is labelled plainly, as the year was.
		$label = 'work.date_label' === $key ? __( 'Date', 'galerio-core' ) : Names::expand( $field->label );

		return array( $label, $value );
	}

	/**
	 * An exhibition's dates.
	 *
	 * @param WP_Post $post Collection.
	 * @return array|null
	 */
	private static function dates( WP_Post $post ) {
		$format = get_option( 'date_format' );
		$dates  = array();

		foreach ( array( 'start_date', 'end_date' ) as $name ) {
			$date = (string) galerio_get_field( 'collection.' . $name, $post );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && '0000-00-00' !== $date ) {
				$dates[ $name ] = esc_html( mysql2date( $format, $date ) );
			}
		}

		if ( ! $dates ) {
			return null;
		}

		if ( isset( $dates['start_date'], $dates['end_date'] ) ) {
			/* translators: 1: opening date, 2: closing date. */
			$text = sprintf( __( '%1$s – %2$s', 'galerio-core' ), $dates['start_date'], $dates['end_date'] );
		} elseif ( isset( $dates['start_date'] ) ) {
			/* translators: %s: the date an exhibition opens. */
			$text = sprintf( __( 'From %s', 'galerio-core' ), $dates['start_date'] );
		} else {
			/* translators: %s: the date an exhibition closes. */
			$text = sprintf( __( 'Until %s', 'galerio-core' ), $dates['end_date'] );
		}

		return array( __( 'Dates', 'galerio-core' ), $text );
	}

	/**
	 * A profile's years, when it shows them.
	 *
	 * @param WP_Post $post Profile.
	 * @return array|null
	 */
	private static function lifespan( WP_Post $post ) {
		if ( ! galerio_get_field( 'profile.show_lifespan', $post ) ) {
			return null;
		}

		$born = galerio_get_field( 'profile.year_of_birth', $post );
		$died = galerio_get_field( 'profile.year_of_death', $post );

		if ( ! $born && ! $died ) {
			return null;
		}

		if ( ! $died ) {
			// The field's own label, which suits people and collectives: "Born/Formed".
			return array( Names::expand( galerio_fields()->get( 'profile.year_of_birth' )->label ), esc_html( (string) $born ) );
		}

		/* translators: 1: year of birth or forming, 2: year of death or disbanding. */
		$text = sprintf( __( '%1$s–%2$s', 'galerio-core' ), $born ? $born : '?', $died );

		return array( __( 'Years', 'galerio-core' ), esc_html( $text ) );
	}
}
