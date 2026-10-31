<?php
/**
 * Galerio's own field definitions.
 *
 * Keys are the names templates and blocks use, and each field's name is also
 * its name in the Pods package (data/pods/galerio.json). New fields must be
 * added to both, and renaming one needs a migration for existing sites' data.
 *
 * @package Galerio\Core
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Works.
	'work.definitive_title'          => array(
		'label' => __( 'Definitive title', 'galerio-core' ),
	),
	'work.creator'                   => array(
		'type'   => 'relation',
		'target' => 'profile',
		'label'  => __( 'Creator', 'galerio-core' ),
	),
	'work.year'                      => array(
		'type'  => 'year',
		'label' => __( 'Year', 'galerio-core' ),
	),
	'work.year_to'                   => array(
		'type'  => 'year',
		'label' => __( 'Year to', 'galerio-core' ),
	),
	'work.date_text'                 => array(
		'label' => __( 'Date in words', 'galerio-core' ),
	),
	'work.date_label'                => array(
		'value_callback' => array( \Galerio\Core\Fields\Dates::class, 'label' ),
		'label'          => __( 'Date as shown', 'galerio-core' ),
	),
	'work.definitive_image'          => array(
		'type'  => 'image',
		'label' => __( 'Definitive image', 'galerio-core' ),
	),
	'work.images'                    => array(
		'type'     => 'image',
		'multiple' => true,
		'label'    => __( 'Images', 'galerio-core' ),
	),
	'work.description'               => array(
		'type'  => 'html',
		'label' => __( 'Description', 'galerio-core' ),
	),
	'work.medium'                    => array(
		'label' => __( 'Medium', 'galerio-core' ),
	),
	'work.dimensions'                => array(
		'label' => __( 'Dimensions', 'galerio-core' ),
	),
	'work.duration'                  => array(
		'label' => __( 'Duration', 'galerio-core' ),
	),
	'work.location'                  => array(
		'label' => __( 'Location', 'galerio-core' ),
	),
	'work.owner'                     => array(
		'public' => false,
		'label'  => __( 'Current owner', 'galerio-core' ),
	),
	'work.valuation'                 => array(
		'type'   => 'number',
		'public' => false,
		'label'  => __( 'Valuation', 'galerio-core' ),
	),
	'work.valuation_date'            => array(
		'type'   => 'date',
		'public' => false,
		'label'  => __( 'Valuation date', 'galerio-core' ),
	),
	'work.valued_by'                 => array(
		'public' => false,
		'label'  => __( 'Valued by', 'galerio-core' ),
	),
	'work.valuation_notes'           => array(
		'type'   => 'textarea',
		'public' => false,
		'label'  => __( 'Valuation notes', 'galerio-core' ),
	),
	'work.availability'              => array(
		'type'    => 'choice',
		'options' => array(
			'available'    => __( 'Available', 'galerio-core' ),
			'reserved'     => __( 'Reserved', 'galerio-core' ),
			'sold'         => __( 'Sold', 'galerio-core' ),
			'on_loan'      => __( 'On loan', 'galerio-core' ),
			'not_for_sale' => __( 'Not for sale', 'galerio-core' ),
		),
		'label'   => __( 'Availability', 'galerio-core' ),
	),
	'work.price'                     => array(
		'type'  => 'number',
		'label' => __( 'Price', 'galerio-core' ),
	),
	'work.price_label'               => array(
		'value_callback' => array( \Galerio\Core\Catalogue\Catalogue::class, 'price_label' ),
		'label'          => __( 'Price as shown', 'galerio-core' ),
	),
	'work.price_on_application'      => array(
		'type'  => 'boolean',
		'label' => __( 'Price on application', 'galerio-core' ),
	),
	'work.currency'                  => array(
		'type'    => 'choice',
		'options' => \Galerio\Core\Catalogue\Catalogue::currencies(),
		'label'   => __( 'Currency', 'galerio-core' ),
	),
	'work.edition_size'              => array(
		'type'  => 'number',
		'label' => __( 'Edition size', 'galerio-core' ),
	),
	'work.editions_available'        => array(
		'type'  => 'number',
		'label' => __( 'Editions available', 'galerio-core' ),
	),
	'work.framed'                    => array(
		'type'  => 'boolean',
		'label' => __( 'Framed', 'galerio-core' ),
	),
	'work.inventory_number'          => array(
		'label' => __( 'Inventory number', 'galerio-core' ),
	),
	'work.available_from'            => array(
		'type'  => 'link',
		'label' => __( 'Available from', 'galerio-core' ),
	),
	'work.website'                   => array(
		'type'  => 'link',
		'label' => __( 'Website', 'galerio-core' ),
	),
	'work.collections'               => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		'label'    => '{Collections}',
	),
	'work.articles'                  => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		'label'    => '{Articles}',
	),
	'work.related_works'             => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		/* translators: Keep {works} as it is: it becomes this site's name for works. */
		'label'    => __( 'Related {works}', 'galerio-core' ),
	),

	// Creator profiles.
	'profile.portrait'               => array(
		'type'  => 'image',
		'label' => __( 'Portrait', 'galerio-core' ),
	),
	'profile.show_portrait'          => array(
		'type'  => 'boolean',
		'label' => __( 'Show portrait', 'galerio-core' ),
	),
	'profile.nationality'            => array(
		'label' => __( 'Nationality', 'galerio-core' ),
	),
	'profile.show_nationality'       => array(
		'type'  => 'boolean',
		'label' => __( 'Show nationality', 'galerio-core' ),
	),
	'profile.year_of_birth'          => array(
		'type'  => 'year',
		'label' => __( 'Year of birth or formation', 'galerio-core' ),
	),
	'profile.year_of_death'          => array(
		'type'  => 'year',
		'label' => __( 'Year of death or disbanding', 'galerio-core' ),
	),
	'profile.show_lifespan'          => array(
		'type'  => 'boolean',
		'label' => __( 'Show lifespan', 'galerio-core' ),
	),
	'profile.is_collective'          => array(
		'type'  => 'boolean',
		'label' => __( 'Is a collective', 'galerio-core' ),
	),
	'profile.members'                => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Members', 'galerio-core' ),
	),
	'profile.member_of'              => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Member of', 'galerio-core' ),
	),
	'profile.bio'                    => array(
		'type'  => 'html',
		'label' => __( 'Biography', 'galerio-core' ),
	),
	'profile.notes'                  => array(
		'type'  => 'html',
		'label' => __( 'Notes', 'galerio-core' ),
	),
	'profile.accolades'              => array(
		'type'     => 'textarea',
		'multiple' => true,
		'label'    => __( 'Accolades', 'galerio-core' ),
	),
	'profile.works'                  => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		'label'    => '{Works}',
	),
	'profile.collections'            => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		'label'    => '{Collections}',
	),
	'profile.article_mentions'       => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		/* translators: Keep {articles} as it is: it becomes this site's name for articles. */
		'label'    => __( 'Mentioned in {articles}', 'galerio-core' ),
	),
	'profile.authored_articles'      => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		/* translators: Keep {articles} as it is: it becomes this site's name for articles. */
		'label'    => __( 'Authored {articles}', 'galerio-core' ),
	),
	'profile.related_profiles'       => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		/* translators: Keep {profiles} as it is: it becomes this site's name for profiles. */
		'label'    => __( 'Related {profiles}', 'galerio-core' ),
	),
	'profile.show_links'             => array(
		'type'  => 'boolean',
		'label' => __( 'Show links', 'galerio-core' ),
	),
	'profile.links'                  => array(
		'type'     => 'link',
		'multiple' => true,
		'label'    => __( 'Links', 'galerio-core' ),
	),
	'profile.represented_by'         => array(
		'type'  => 'link',
		'label' => __( 'Represented by', 'galerio-core' ),
	),

	// Collections.
	'collection.standfirst'          => array(
		'label' => __( 'Standfirst', 'galerio-core' ),
	),
	'collection.description'         => array(
		'type'  => 'html',
		'label' => __( 'Description', 'galerio-core' ),
	),
	'collection.location'            => array(
		'label' => __( 'Location', 'galerio-core' ),
	),
	'collection.start_date'          => array(
		'type'  => 'date',
		'label' => __( 'Start date', 'galerio-core' ),
	),
	'collection.end_date'            => array(
		'type'  => 'date',
		'label' => __( 'End date', 'galerio-core' ),
	),
	'collection.website'             => array(
		'type'  => 'link',
		'label' => __( 'Website', 'galerio-core' ),
	),
	'collection.images'              => array(
		'type'     => 'image',
		'multiple' => true,
		'label'    => __( 'Images', 'galerio-core' ),
	),
	'collection.shuffle_images'      => array(
		'type'  => 'boolean',
		'label' => __( 'Shuffle images', 'galerio-core' ),
	),
	'collection.embeds'              => array(
		'type'     => 'embed',
		'multiple' => true,
		'label'    => __( 'Embeds', 'galerio-core' ),
	),
	'collection.notes'               => array(
		'type'  => 'html',
		'label' => __( 'Notes', 'galerio-core' ),
	),
	'collection.profiles'            => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => '{Profiles}',
	),
	'collection.curators'            => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Curators', 'galerio-core' ),
	),
	'collection.other_contributors'  => array(
		'multiple' => true,
		'label'    => __( 'Other contributors', 'galerio-core' ),
	),
	'collection.works'               => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		'label'    => '{Works}',
	),
	'collection.articles'            => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		'label'    => '{Articles}',
	),
	'collection.related_collections' => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		/* translators: Keep {collections} as it is: it becomes this site's name for collections. */
		'label'    => __( 'Related {collections}', 'galerio-core' ),
	),

	// Articles.
	'article.standfirst'             => array(
		'label' => __( 'Standfirst', 'galerio-core' ),
	),
	'article.authors'                => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Authors', 'galerio-core' ),
	),
	'article.profiles'               => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => '{Profiles}',
	),
	'article.works'                  => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		'label'    => '{Works}',
	),
	'article.collections'            => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		'label'    => '{Collections}',
	),
	'article.related_articles'       => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		/* translators: Keep {articles} as it is: it becomes this site's name for articles. */
		'label'    => __( 'Related {articles}', 'galerio-core' ),
	),

	// Media.
	'attachment.year'                => array(
		'type'  => 'year',
		'label' => __( 'Year', 'galerio-core' ),
	),
	'attachment.credit'              => array(
		'label' => __( 'Credit', 'galerio-core' ),
	),
	'attachment.copyright'           => array(
		'label' => __( 'Copyright', 'galerio-core' ),
	),
);
