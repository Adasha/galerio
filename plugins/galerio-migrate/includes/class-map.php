<?php
/**
 * What moves where.
 *
 * @package Galerio\Migrate
 */

namespace Galerio\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * The old model's names and the new model's, from the agreed content model
 * proposal, as they apply to galerio.net and swansea.art.
 */
final class Map {

	/**
	 * Pods pod => Galerio field registry object.
	 *
	 * @var array<string, string>
	 */
	const OBJECTS = array(
		'galerio_work'       => 'work',
		'galerio_profile'    => 'profile',
		'galerio_collection' => 'collection',
		'galerio_article'    => 'article',
		'media'              => 'attachment',
	);

	/**
	 * Fields copied unchanged to a new name: pod => old name => new name.
	 *
	 * @var array<string, array<string, string>>
	 */
	const RENAMES = array(
		'galerio_work'       => array(
			'work_creator'       => 'creator',
			'year_created'       => 'year',
			'principal_image'    => 'definitive_image',
			'additional_images'  => 'images',
			'work_medium'        => 'medium',
			'work_dimensions'    => 'dimensions',
			'work_duration'      => 'duration',
			'work_location'      => 'location',
			'current_work_owner' => 'owner',
			'in_collections'     => 'collections',
			'in_articles'        => 'articles',
		),
		'galerio_profile'    => array(
			'profile_image'       => 'portrait',
			'show_profile_image'  => 'show_portrait',
			'profile_nationality' => 'nationality',
			'profile_bio'         => 'bio',
			'hidden_bio'          => 'notes',
			'featured_works'      => 'works',
			'in_collections'      => 'collections',
			'in_articles'         => 'article_mentions',
			'show_external_links' => 'show_links',
		),
		'galerio_collection' => array(
			'opening_date'       => 'start_date',
			'closing_date'       => 'end_date',
			'collection_images'  => 'images',
			'random_image_order' => 'shuffle_images',
			'media_embed_urls'   => 'embeds',
			'featured_profiles'  => 'profiles',
			'featured_works'     => 'works',
			'in_articles'        => 'articles',
		),
		'galerio_article'    => array(
			'article_author'       => 'authors',
			'featured_profiles'    => 'profiles',
			'featured_works'       => 'works',
			'featured_collections' => 'collections',
		),
		'media'              => array(
			'creator' => 'credit',
		),
	);

	/**
	 * A profile's link fields, in the order they join `links`.
	 *
	 * @var string[]
	 */
	const PROFILE_LINKS = array(
		'personal_website',
		'other_websites',
		'personal_instagram',
		'personal_bluesky',
		'personal_facebook',
		'personal_tiktok',
		'personal_twitter',
		'personal_vimeo',
		'personal_youtube',
		'personal_axisweb',
	);

	/**
	 * Old taxonomies and the taxonomy their terms move to.
	 *
	 * @var array<string, string>
	 */
	const TAXONOMIES = array(
		'galerio_profile_group' => 'galerio_group',
		'galerio_style'         => 'galerio_label',
		'galerio_genre'         => 'galerio_label',
		'galerio_form'          => 'galerio_medium',
	);

	/**
	 * A user's link to their profile: old user meta => new.
	 *
	 * @var array<string, string>
	 */
	const USER_FIELDS = array(
		'profile_page' => 'profile',
	);
}
