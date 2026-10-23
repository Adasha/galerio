<?php
/**
 * Alias presets: starting values for common kinds of site.
 *
 * A preset only fills in the settings form. Every name and slug can still be
 * changed before or after saving. Types and taxonomies a preset leaves out
 * keep their registered names. Filter with `galerio_alias_presets`.
 *
 * @package Galerio\Core
 */

defined( 'ABSPATH' ) || exit;

return array(
	'gallery'      => array(
		'label'      => __( 'Gallery', 'galerio-core' ),
		'types'      => array(
			'work'       => array(
				'singular' => __( 'Artwork', 'galerio-core' ),
				'plural'   => __( 'Artworks', 'galerio-core' ),
				'slug'     => _x( 'artwork', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'artworks', 'URL slug', 'galerio-core' ),
			),
			'profile'    => array(
				'singular' => __( 'Artist', 'galerio-core' ),
				'plural'   => __( 'Artists', 'galerio-core' ),
				'slug'     => _x( 'artist', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'artists', 'URL slug', 'galerio-core' ),
			),
			'collection' => array(
				'singular' => __( 'Exhibition', 'galerio-core' ),
				'plural'   => __( 'Exhibitions', 'galerio-core' ),
				'slug'     => _x( 'exhibition', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'exhibitions', 'URL slug', 'galerio-core' ),
			),
			'article'    => array(
				'singular' => __( 'News story', 'galerio-core' ),
				'plural'   => __( 'News', 'galerio-core' ),
				'slug'     => _x( 'news', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'news', 'URL slug', 'galerio-core' ),
			),
		),
		'taxonomies' => array(
			'collection_category' => array(
				'singular' => __( 'Exhibition type', 'galerio-core' ),
				'plural'   => __( 'Exhibition types', 'galerio-core' ),
				'slug'     => _x( 'exhibition-type', 'URL slug', 'galerio-core' ),
			),
			'article_category'    => array(
				'singular' => __( 'News category', 'galerio-core' ),
				'plural'   => __( 'News categories', 'galerio-core' ),
				'slug'     => _x( 'news-category', 'URL slug', 'galerio-core' ),
			),
		),
	),
	'record_label' => array(
		'label'      => __( 'Record label', 'galerio-core' ),
		'types'      => array(
			'work'       => array(
				'singular' => __( 'Release', 'galerio-core' ),
				'plural'   => __( 'Releases', 'galerio-core' ),
				'slug'     => _x( 'release', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'releases', 'URL slug', 'galerio-core' ),
			),
			'profile'    => array(
				'singular' => __( 'Artist', 'galerio-core' ),
				'plural'   => __( 'Artists', 'galerio-core' ),
				'slug'     => _x( 'artist', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'artists', 'URL slug', 'galerio-core' ),
			),
			'collection' => array(
				'singular' => __( 'Album', 'galerio-core' ),
				'plural'   => __( 'Albums', 'galerio-core' ),
				'slug'     => _x( 'album', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'albums', 'URL slug', 'galerio-core' ),
			),
			'article'    => array(
				'singular' => __( 'News story', 'galerio-core' ),
				'plural'   => __( 'News', 'galerio-core' ),
				'slug'     => _x( 'news', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'news', 'URL slug', 'galerio-core' ),
			),
		),
		'taxonomies' => array(
			'medium'              => array(
				'singular' => __( 'Format', 'galerio-core' ),
				'plural'   => __( 'Formats', 'galerio-core' ),
				'slug'     => _x( 'format', 'URL slug', 'galerio-core' ),
			),
			'label'               => array(
				'singular' => __( 'Genre', 'galerio-core' ),
				'plural'   => __( 'Genres', 'galerio-core' ),
				'slug'     => _x( 'genre', 'URL slug', 'galerio-core' ),
			),
			'collection_category' => array(
				'singular' => __( 'Album type', 'galerio-core' ),
				'plural'   => __( 'Album types', 'galerio-core' ),
				'slug'     => _x( 'album-type', 'URL slug', 'galerio-core' ),
			),
			'article_category'    => array(
				'singular' => __( 'News category', 'galerio-core' ),
				'plural'   => __( 'News categories', 'galerio-core' ),
				'slug'     => _x( 'news-category', 'URL slug', 'galerio-core' ),
			),
		),
	),
	'publisher'    => array(
		'label'      => __( 'Publisher', 'galerio-core' ),
		'types'      => array(
			'work'       => array(
				'singular' => __( 'Book', 'galerio-core' ),
				'plural'   => __( 'Books', 'galerio-core' ),
				'slug'     => _x( 'book', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'books', 'URL slug', 'galerio-core' ),
			),
			'profile'    => array(
				'singular' => __( 'Author', 'galerio-core' ),
				'plural'   => __( 'Authors', 'galerio-core' ),
				'slug'     => _x( 'authors', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'authors', 'URL slug', 'galerio-core' ),
			),
			'collection' => array(
				'singular' => __( 'Series', 'galerio-core' ),
				'plural'   => __( 'Series', 'galerio-core' ),
				'slug'     => _x( 'series', 'URL slug', 'galerio-core' ),
				'archive'  => _x( 'series', 'URL slug', 'galerio-core' ),
			),
		),
		'taxonomies' => array(
			'medium'              => array(
				'singular' => __( 'Format', 'galerio-core' ),
				'plural'   => __( 'Formats', 'galerio-core' ),
				'slug'     => _x( 'format', 'URL slug', 'galerio-core' ),
			),
			'label'               => array(
				'singular' => __( 'Genre', 'galerio-core' ),
				'plural'   => __( 'Genres', 'galerio-core' ),
				'slug'     => _x( 'genre', 'URL slug', 'galerio-core' ),
			),
			'collection_category' => array(
				'singular' => __( 'Series type', 'galerio-core' ),
				'plural'   => __( 'Series types', 'galerio-core' ),
				'slug'     => _x( 'series-type', 'URL slug', 'galerio-core' ),
			),
		),
	),
);
