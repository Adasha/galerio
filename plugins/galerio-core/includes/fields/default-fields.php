<?php
/**
 * Galerio's own field definitions.
 *
 * Keys are the logical names templates and blocks use. `source` is the field's
 * name in the Pods package (data/pods/galerio.json) where the two differ. New
 * fields must also be added to the package, and changes must stay compatible
 * with existing sites' data.
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
	'work.principal_image'           => array(
		'type'  => 'image',
		'label' => __( 'Principal image', 'galerio-core' ),
	),
	'work.additional_images'         => array(
		'type'     => 'image',
		'multiple' => true,
		'label'    => __( 'Additional images', 'galerio-core' ),
	),
	'work.description'               => array(
		'type'       => 'html',
		'label'      => __( 'Description', 'galerio-core' ),
		'deprecated' => __( 'Write the description in the post content instead.', 'galerio-core' ),
	),
	'work.medium'                    => array(
		'source' => 'work_medium',
		'label'  => __( 'Medium', 'galerio-core' ),
	),
	'work.categorisation'            => array(
		'source' => 'work_categorisation',
		'label'  => __( 'Categorisation', 'galerio-core' ),
	),
	'work.dimensions'                => array(
		'source' => 'work_dimensions',
		'label'  => __( 'Dimensions', 'galerio-core' ),
	),
	'work.duration'                  => array(
		'source' => 'work_duration',
		'label'  => __( 'Duration', 'galerio-core' ),
	),
	'work.location'                  => array(
		'source' => 'work_location',
		'label'  => __( 'Location', 'galerio-core' ),
	),
	'work.website_name'              => array(
		'label' => __( 'Website name', 'galerio-core' ),
	),
	'work.website_url'               => array(
		'type'  => 'url',
		'label' => __( 'Website URL', 'galerio-core' ),
	),
	'work.in_collections'            => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		'label'    => __( 'In collections', 'galerio-core' ),
	),
	'work.in_articles'               => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		'label'    => __( 'In articles', 'galerio-core' ),
	),
	'work.related_works'             => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		'label'    => __( 'Related works', 'galerio-core' ),
	),

	// Creator profiles.
	'profile.image'                  => array(
		'type'   => 'image',
		'source' => 'profile_image',
		'label'  => __( 'Profile image', 'galerio-core' ),
	),
	'profile.show_image'             => array(
		'type'   => 'boolean',
		'source' => 'show_profile_image',
		'label'  => __( 'Show profile image', 'galerio-core' ),
	),
	'profile.nationality'            => array(
		'source' => 'profile_nationality',
		'label'  => __( 'Nationality', 'galerio-core' ),
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
	'profile.collective_members'     => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Collective members', 'galerio-core' ),
	),
	'profile.bio'                    => array(
		'type'   => 'html',
		'source' => 'profile_bio',
		'label'  => __( 'Biography', 'galerio-core' ),
	),
	'profile.hidden_bio'             => array(
		'type'   => 'html',
		'public' => false,
		'label'  => __( 'Private biography', 'galerio-core' ),
	),
	'profile.accolades'              => array(
		'type'     => 'textarea',
		'multiple' => true,
		'label'    => __( 'Accolades', 'galerio-core' ),
	),
	'profile.featured_works'         => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		'label'    => __( 'Featured works', 'galerio-core' ),
	),
	'profile.in_collections'         => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		'label'    => __( 'In collections', 'galerio-core' ),
	),
	'profile.article_mentions'       => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		'label'    => __( 'Mentioned in articles', 'galerio-core' ),
	),
	'profile.related_profiles'       => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Related profiles', 'galerio-core' ),
	),
	'profile.authored_articles'      => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		'label'    => __( 'Authored articles', 'galerio-core' ),
	),
	'profile.show_external_links'    => array(
		'type'  => 'boolean',
		'label' => __( 'Show external links', 'galerio-core' ),
	),
	'profile.website'                => array(
		'type'   => 'url',
		'source' => 'personal_website',
		'label'  => __( 'Website', 'galerio-core' ),
	),
	'profile.axisweb'                => array(
		'type'   => 'url',
		'source' => 'personal_axisweb',
		'label'  => __( 'Axisweb', 'galerio-core' ),
	),
	'profile.instagram'              => array(
		'type'   => 'url',
		'source' => 'personal_instagram',
		'label'  => __( 'Instagram', 'galerio-core' ),
	),
	'profile.youtube'                => array(
		'type'   => 'url',
		'source' => 'personal_youtube',
		'label'  => __( 'YouTube', 'galerio-core' ),
	),
	'profile.vimeo'                  => array(
		'type'   => 'url',
		'source' => 'personal_vimeo',
		'label'  => __( 'Vimeo', 'galerio-core' ),
	),
	'profile.twitter'                => array(
		'type'   => 'url',
		'source' => 'personal_twitter',
		'label'  => __( 'X (Twitter)', 'galerio-core' ),
	),
	'profile.bluesky'                => array(
		'type'   => 'url',
		'source' => 'personal_bluesky',
		'label'  => __( 'Bluesky', 'galerio-core' ),
	),
	'profile.facebook'               => array(
		'type'   => 'url',
		'source' => 'personal_facebook',
		'label'  => __( 'Facebook', 'galerio-core' ),
	),
	'profile.tiktok'                 => array(
		'type'   => 'url',
		'source' => 'personal_tiktok',
		'label'  => __( 'TikTok', 'galerio-core' ),
	),
	'profile.other_websites'         => array(
		'type'     => 'url',
		'multiple' => true,
		'label'    => __( 'Other websites', 'galerio-core' ),
	),

	// Collections.
	'collection.standfirst'          => array(
		'label' => __( 'Standfirst', 'galerio-core' ),
	),
	'collection.location'            => array(
		'label' => __( 'Location', 'galerio-core' ),
	),
	'collection.opening_date'        => array(
		'type'  => 'date',
		'label' => __( 'Opening date', 'galerio-core' ),
	),
	'collection.closing_date'        => array(
		'type'  => 'date',
		'label' => __( 'Closing date', 'galerio-core' ),
	),
	'collection.website_name'        => array(
		'label' => __( 'Website name', 'galerio-core' ),
	),
	'collection.website_url'         => array(
		'type'  => 'url',
		'label' => __( 'Website URL', 'galerio-core' ),
	),
	'collection.dead_link'           => array(
		'type'  => 'boolean',
		'label' => __( 'Website no longer available', 'galerio-core' ),
	),
	'collection.description'         => array(
		'type'  => 'html',
		'label' => __( 'Description', 'galerio-core' ),
	),
	'collection.managed_media'       => array(
		'type'  => 'boolean',
		'label' => __( 'Managed media', 'galerio-core' ),
	),
	'collection.images'              => array(
		'type'     => 'image',
		'multiple' => true,
		'source'   => 'collection_images',
		'label'    => __( 'Images', 'galerio-core' ),
	),
	'collection.random_image_order'  => array(
		'type'  => 'boolean',
		'label' => __( 'Random image order', 'galerio-core' ),
	),
	'collection.media_embeds'        => array(
		'type'     => 'embed',
		'multiple' => true,
		'source'   => 'media_embed_urls',
		'label'    => __( 'Media embeds', 'galerio-core' ),
	),
	'collection.notes'               => array(
		'type'   => 'html',
		'public' => false,
		'label'  => __( 'Notes', 'galerio-core' ),
	),
	'collection.featured_profiles'   => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Featured profiles', 'galerio-core' ),
	),
	'collection.featured_works'      => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		'label'    => __( 'Featured works', 'galerio-core' ),
	),
	'collection.in_articles'         => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		'label'    => __( 'In articles', 'galerio-core' ),
	),
	'collection.related_collections' => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		'label'    => __( 'Related collections', 'galerio-core' ),
	),

	// Articles.
	'article.standfirst'             => array(
		'label' => __( 'Standfirst', 'galerio-core' ),
	),
	'article.authors'                => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'source'   => 'article_author',
		'label'    => __( 'Authors', 'galerio-core' ),
	),
	'article.featured_profiles'      => array(
		'type'     => 'relation',
		'target'   => 'profile',
		'multiple' => true,
		'label'    => __( 'Featured profiles', 'galerio-core' ),
	),
	'article.featured_works'         => array(
		'type'     => 'relation',
		'target'   => 'work',
		'multiple' => true,
		'label'    => __( 'Featured works', 'galerio-core' ),
	),
	'article.featured_collections'   => array(
		'type'     => 'relation',
		'target'   => 'collection',
		'multiple' => true,
		'label'    => __( 'Featured collections', 'galerio-core' ),
	),
	'article.related_articles'       => array(
		'type'     => 'relation',
		'target'   => 'article',
		'multiple' => true,
		'label'    => __( 'Related articles', 'galerio-core' ),
	),

	// Media.
	'attachment.year'                => array(
		'type'  => 'year',
		'label' => __( 'Year', 'galerio-core' ),
	),
	'attachment.creator'             => array(
		'label' => __( 'Creator', 'galerio-core' ),
	),
	'attachment.copyright'           => array(
		'label' => __( 'Copyright', 'galerio-core' ),
	),
);
