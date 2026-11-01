<?php
/**
 * Galerio's own field definitions.
 *
 * Keys are the names templates and blocks use, and each field's name is also
 * its name in the Pods package (data/pods/galerio.json). New fields must be
 * added to both, and renaming one needs a migration for existing sites' data.
 *
 * Fields are listed in the order the editor shows them. A field's `group` is
 * its box in the editor, and fields without one aren't edited there. `roles`
 * limits who sees and edits it in the editor; administrators always can.
 *
 * @package Galerio\Core
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Works.
	'work.definitive_title'          => array(
		'group' => 'details',
		'label' => __( 'Definitive title', 'galerio-core' ),
	),
	'work.creator'                   => array(
		'group'   => 'details',
		'type'    => 'relation',
		'target'  => 'profile',
		'inverse' => 'profile.works',
		'label'   => __( 'Creator', 'galerio-core' ),
	),
	'work.year'                      => array(
		'group' => 'details',
		'type'  => 'year',
		'label' => __( 'Year', 'galerio-core' ),
	),
	'work.year_to'                   => array(
		'group' => 'details',
		'type'  => 'year',
		'label' => __( 'Year to', 'galerio-core' ),
	),
	'work.date_text'                 => array(
		'group' => 'details',
		'label' => __( 'Date in words', 'galerio-core' ),
	),
	'work.definitive_image'          => array(
		'group' => 'details',
		'type'  => 'image',
		'label' => __( 'Definitive image', 'galerio-core' ),
	),
	'work.description'               => array(
		'group' => 'details',
		'type'  => 'html',
		'label' => __( 'Description', 'galerio-core' ),
	),
	'work.website'                   => array(
		'group' => 'details',
		'type'  => 'link',
		'label' => __( 'Website', 'galerio-core' ),
	),
	'work.images'                    => array(
		'group'    => 'details',
		'type'     => 'image',
		'multiple' => true,
		'label'    => __( 'Images', 'galerio-core' ),
	),
	'work.medium'                    => array(
		'group' => 'metadata',
		'label' => __( 'Medium', 'galerio-core' ),
	),
	'work.dimensions'                => array(
		'group' => 'metadata',
		'label' => __( 'Dimensions', 'galerio-core' ),
	),
	'work.duration'                  => array(
		'group' => 'metadata',
		'label' => __( 'Duration', 'galerio-core' ),
	),
	'work.collections'               => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'collection',
		'inverse'  => 'collection.works',
		'multiple' => true,
		'label'    => '{Collections}',
	),
	'work.articles'                  => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'article',
		'inverse'  => 'article.works',
		'multiple' => true,
		'label'    => '{Articles}',
	),
	'work.related_works'             => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'work',
		'inverse'  => 'work.related_works',
		'multiple' => true,
		/* translators: Keep {works} as it is: it becomes this site's name for works. */
		'label'    => __( 'Related {works}', 'galerio-core' ),
	),
	'work.location'                  => array(
		'group' => 'cataloging',
		'label' => __( 'Location', 'galerio-core' ),
	),
	'work.owner'                     => array(
		'group'  => 'cataloging',
		'public' => false,
		'label'  => __( 'Current owner', 'galerio-core' ),
	),
	'work.valuation'                 => array(
		'group'  => 'cataloging',
		'roles'  => array( 'administrator', 'editor' ),
		'type'   => 'number',
		'public' => false,
		'label'  => __( 'Valuation', 'galerio-core' ),
	),
	'work.valuation_date'            => array(
		'group'  => 'cataloging',
		'roles'  => array( 'administrator', 'editor' ),
		'type'   => 'date',
		'public' => false,
		'label'  => __( 'Valuation date', 'galerio-core' ),
	),
	'work.valued_by'                 => array(
		'group'  => 'cataloging',
		'roles'  => array( 'administrator', 'editor' ),
		'public' => false,
		'label'  => __( 'Valued by', 'galerio-core' ),
	),
	'work.valuation_notes'           => array(
		'group'  => 'cataloging',
		'roles'  => array( 'administrator', 'editor' ),
		'type'   => 'textarea',
		'public' => false,
		'label'  => __( 'Valuation notes', 'galerio-core' ),
	),
	'work.availability'              => array(
		'group'   => 'sales',
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
		'group' => 'sales',
		'type'  => 'number',
		'label' => __( 'Price', 'galerio-core' ),
	),
	'work.price_on_application'      => array(
		'group' => 'sales',
		'type'  => 'boolean',
		'label' => __( 'Price on application', 'galerio-core' ),
	),
	'work.currency'                  => array(
		'group'   => 'sales',
		'type'    => 'choice',
		'options' => \Galerio\Core\Catalogue\Catalogue::currencies(),
		'label'   => __( 'Currency', 'galerio-core' ),
	),
	'work.edition_size'              => array(
		'group' => 'sales',
		'type'  => 'number',
		'label' => __( 'Edition size', 'galerio-core' ),
	),
	'work.editions_available'        => array(
		'group' => 'sales',
		'type'  => 'number',
		'label' => __( 'Editions available', 'galerio-core' ),
	),
	'work.framed'                    => array(
		'group' => 'sales',
		'type'  => 'boolean',
		'label' => __( 'Framed', 'galerio-core' ),
	),
	'work.inventory_number'          => array(
		'group' => 'sales',
		'label' => __( 'Inventory number', 'galerio-core' ),
	),
	'work.available_from'            => array(
		'group' => 'sales',
		'type'  => 'link',
		'label' => __( 'Available from', 'galerio-core' ),
	),
	'work.date_label'                => array(
		'value_callback' => array( \Galerio\Core\Fields\Dates::class, 'label' ),
		'label'          => __( 'Date as shown', 'galerio-core' ),
	),
	'work.price_label'               => array(
		'value_callback' => array( \Galerio\Core\Catalogue\Catalogue::class, 'price_label' ),
		'label'          => __( 'Price as shown', 'galerio-core' ),
	),

	// Creator profiles.
	'profile.portrait'               => array(
		'group' => 'details',
		'type'  => 'image',
		'label' => __( 'Portrait', 'galerio-core' ),
	),
	'profile.show_portrait'          => array(
		'group' => 'details',
		'type'  => 'boolean',
		'label' => __( 'Show portrait', 'galerio-core' ),
	),
	'profile.nationality'            => array(
		'group' => 'details',
		'label' => __( 'Nationality', 'galerio-core' ),
	),
	'profile.show_nationality'       => array(
		'group' => 'details',
		'roles' => array( 'administrator', 'editor', 'author' ),
		'type'  => 'boolean',
		'label' => __( 'Show nationality', 'galerio-core' ),
	),
	'profile.year_of_birth'          => array(
		'group' => 'details',
		'roles' => array( 'administrator', 'editor', 'author' ),
		'type'  => 'year',
		'label' => __( 'Year of birth or formation', 'galerio-core' ),
	),
	'profile.year_of_death'          => array(
		'group' => 'details',
		'roles' => array( 'administrator', 'editor', 'author' ),
		'type'  => 'year',
		'label' => __( 'Year of death or disbanding', 'galerio-core' ),
	),
	'profile.show_lifespan'          => array(
		'group' => 'details',
		'roles' => array( 'administrator', 'editor', 'author' ),
		'type'  => 'boolean',
		'label' => __( 'Show lifespan', 'galerio-core' ),
	),
	'profile.is_collective'          => array(
		'group' => 'details',
		'roles' => array( 'administrator', 'editor', 'author' ),
		'type'  => 'boolean',
		'label' => __( 'Is a collective', 'galerio-core' ),
	),
	'profile.members'                => array(
		'group'    => 'details',
		'roles'    => array( 'administrator', 'editor', 'author' ),
		'type'     => 'relation',
		'target'   => 'profile',
		'inverse'  => 'profile.member_of',
		'multiple' => true,
		'label'    => __( 'Members', 'galerio-core' ),
	),
	'profile.member_of'              => array(
		'group'    => 'details',
		'roles'    => array( 'administrator', 'editor', 'author' ),
		'type'     => 'relation',
		'target'   => 'profile',
		'inverse'  => 'profile.members',
		'multiple' => true,
		'label'    => __( 'Member of', 'galerio-core' ),
	),
	'profile.bio'                    => array(
		'group' => 'biography',
		'type'  => 'html',
		'label' => __( 'Biography', 'galerio-core' ),
	),
	'profile.notes'                  => array(
		'group' => 'biography',
		'type'  => 'html',
		'label' => __( 'Notes', 'galerio-core' ),
	),
	'profile.accolades'              => array(
		'group'    => 'biography',
		'type'     => 'textarea',
		'multiple' => true,
		'label'    => __( 'Accolades', 'galerio-core' ),
	),
	'profile.works'                  => array(
		'group'    => 'relationships',
		'roles'    => array( 'administrator', 'editor', 'author' ),
		'type'     => 'relation',
		'target'   => 'work',
		'inverse'  => 'work.creator',
		'multiple' => true,
		'label'    => '{Works}',
	),
	'profile.collections'            => array(
		'group'    => 'relationships',
		'roles'    => array( 'administrator', 'editor', 'author' ),
		'type'     => 'relation',
		'target'   => 'collection',
		'inverse'  => 'collection.profiles',
		'multiple' => true,
		'label'    => '{Collections}',
	),
	'profile.article_mentions'       => array(
		'group'    => 'relationships',
		'roles'    => array( 'administrator', 'editor', 'author' ),
		'type'     => 'relation',
		'target'   => 'article',
		'inverse'  => 'article.profiles',
		'multiple' => true,
		/* translators: Keep {articles} as it is: it becomes this site's name for articles. */
		'label'    => __( 'Mentioned in {articles}', 'galerio-core' ),
	),
	'profile.related_profiles'       => array(
		'group'    => 'relationships',
		'roles'    => array( 'administrator', 'editor', 'author' ),
		'type'     => 'relation',
		'target'   => 'profile',
		'inverse'  => 'profile.related_profiles',
		'multiple' => true,
		/* translators: Keep {profiles} as it is: it becomes this site's name for profiles. */
		'label'    => __( 'Related {profiles}', 'galerio-core' ),
	),
	'profile.authored_articles'      => array(
		'group'    => 'relationships',
		'roles'    => array( 'administrator', 'editor', 'author' ),
		'type'     => 'relation',
		'target'   => 'article',
		'inverse'  => 'article.authors',
		'multiple' => true,
		/* translators: Keep {articles} as it is: it becomes this site's name for articles. */
		'label'    => __( 'Authored {articles}', 'galerio-core' ),
	),
	'profile.show_links'             => array(
		'group' => 'external_links',
		'type'  => 'boolean',
		'label' => __( 'Show links', 'galerio-core' ),
	),
	'profile.links'                  => array(
		'group'    => 'external_links',
		'type'     => 'link',
		'multiple' => true,
		'label'    => __( 'Links', 'galerio-core' ),
	),
	'profile.represented_by'         => array(
		'group' => 'sales',
		'type'  => 'link',
		'label' => __( 'Represented by', 'galerio-core' ),
	),

	// Collections.
	'collection.standfirst'          => array(
		'group' => 'details',
		'label' => __( 'Standfirst', 'galerio-core' ),
	),
	'collection.location'            => array(
		'group' => 'details',
		'label' => __( 'Location', 'galerio-core' ),
	),
	'collection.start_date'          => array(
		'group' => 'details',
		'type'  => 'date',
		'label' => __( 'Start date', 'galerio-core' ),
	),
	'collection.end_date'            => array(
		'group' => 'details',
		'type'  => 'date',
		'label' => __( 'End date', 'galerio-core' ),
	),
	'collection.website'             => array(
		'group' => 'details',
		'type'  => 'link',
		'label' => __( 'Website', 'galerio-core' ),
	),
	'collection.description'         => array(
		'group' => 'content',
		'type'  => 'html',
		'label' => __( 'Description', 'galerio-core' ),
	),
	'collection.images'              => array(
		'group'    => 'content',
		'type'     => 'image',
		'multiple' => true,
		'label'    => __( 'Images', 'galerio-core' ),
	),
	'collection.shuffle_images'      => array(
		'group' => 'content',
		'type'  => 'boolean',
		'label' => __( 'Shuffle images', 'galerio-core' ),
	),
	'collection.embeds'              => array(
		'group'    => 'content',
		'type'     => 'embed',
		'multiple' => true,
		'label'    => __( 'Embeds', 'galerio-core' ),
	),
	'collection.notes'               => array(
		'group' => 'content',
		'type'  => 'html',
		'label' => __( 'Notes', 'galerio-core' ),
	),
	'collection.profiles'            => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'profile',
		'inverse'  => 'profile.collections',
		'multiple' => true,
		'label'    => '{Profiles}',
	),
	'collection.curators'            => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Curators', 'galerio-core' ),
	),
	'collection.other_contributors'  => array(
		'group'    => 'relationships',
		'multiple' => true,
		'label'    => __( 'Other contributors', 'galerio-core' ),
	),
	'collection.works'               => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'work',
		'inverse'  => 'work.collections',
		'multiple' => true,
		'label'    => '{Works}',
	),
	'collection.articles'            => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'article',
		'inverse'  => 'article.collections',
		'multiple' => true,
		'label'    => '{Articles}',
	),
	'collection.related_collections' => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'collection',
		'inverse'  => 'collection.related_collections',
		'multiple' => true,
		/* translators: Keep {collections} as it is: it becomes this site's name for collections. */
		'label'    => __( 'Related {collections}', 'galerio-core' ),
	),

	// Articles.
	'article.standfirst'             => array(
		'group' => 'details',
		'label' => __( 'Standfirst', 'galerio-core' ),
	),
	'article.authors'                => array(
		'group'    => 'details',
		'type'     => 'relation',
		'target'   => 'profile',
		'inverse'  => 'profile.authored_articles',
		'multiple' => true,
		'label'    => __( 'Authors', 'galerio-core' ),
	),
	'article.profiles'               => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'profile',
		'inverse'  => 'profile.article_mentions',
		'multiple' => true,
		'label'    => '{Profiles}',
	),
	'article.works'                  => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'work',
		'inverse'  => 'work.articles',
		'multiple' => true,
		'label'    => '{Works}',
	),
	'article.collections'            => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'collection',
		'inverse'  => 'collection.articles',
		'multiple' => true,
		'label'    => '{Collections}',
	),
	'article.related_articles'       => array(
		'group'    => 'relationships',
		'type'     => 'relation',
		'target'   => 'article',
		'inverse'  => 'article.related_articles',
		'multiple' => true,
		/* translators: Keep {articles} as it is: it becomes this site's name for articles. */
		'label'    => __( 'Related {articles}', 'galerio-core' ),
	),

	// Media.
	'attachment.year'                => array(
		'group' => 'metadata',
		'type'  => 'year',
		'label' => __( 'Year', 'galerio-core' ),
	),
	'attachment.credit'              => array(
		'group' => 'metadata',
		'label' => __( 'Credit', 'galerio-core' ),
	),
	'attachment.copyright'           => array(
		'group' => 'metadata',
		'label' => __( 'Copyright', 'galerio-core' ),
	),
);
