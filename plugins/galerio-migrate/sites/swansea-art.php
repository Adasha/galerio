<?php
/**
 * Decisions for swansea.art.
 *
 * The site swansea.art is Swansea College of Art's fine art site: student, alumni and
 * staff profiles, and exhibitions. Its exhibitions are still in the older
 * `collection` post type, its mediums in `galerio_work_medium`, and its
 * pages carry Pods blocks and Eclecticon page templates. The decisions were
 * agreed with the site's owner on 25 October 2026.
 *
 * @package Galerio\Migrate
 */

defined( 'ABSPATH' ) || exit;

return array(

	/*
	 * Post types whose items move to a Galerio type, keeping their IDs, fields
	 * and URLs (see `aliases`): old => new.
	 */
	'post_types'      => array(
		'collection' => 'galerio_collection',
	),

	/*
	 * Old taxonomies whose terms move to a Galerio taxonomy, as well as the
	 * ones every site has (see Map::TAXONOMIES): old => new.
	 */
	'taxonomies'      => array(
		'galerio_work_medium'   => 'galerio_medium',
		// "Contemporary", on one work and one profile.
		'galerio_work_category' => 'galerio_label',
	),

	// Taxonomies removed with their terms: media_category's terms are on no images.
	'drop_taxonomies' => array( 'media_category' ),

	/*
	 * Fields copied to a new name, as well as Map::RENAMES: pod => old => new.
	 * contributing_profiles left Pods' setup earlier, but four exhibitions still
	 * hold its curators and helpers.
	 */
	'fields'          => array(
		'galerio_collection' => array(
			'contributing_profiles' => 'curators',
		),
	),

	// More of a profile's old link fields to merge into `links`.
	'profile_links'   => array( 'personal_websites' ),

	/*
	 * Old fields removed with their values, as well as the ones every site
	 * loses. Their values are listed in the report first.
	 */
	'retire'          => array(
		// Groups and roles on each user's profile replace it.
		'user.user_group',
		// Now a Label ("Contemporary").
		'galerio_work.work_category',
		// Empty on every work.
		'galerio_work.dealer_website',
		// Pods-era settings: names and slugs are Galerio's aliases, and the
		// one setting in use is carried over in `settings`.
		'galerio_settings.display_work_as',
		'galerio_settings.display_works_as',
		'galerio_settings.rewrite_work_slug',
		'galerio_settings.rewrite_works_slug',
		'galerio_settings.display_profile_as',
		'galerio_settings.display_profiles_as',
		'galerio_settings.rewrite_profile_slug',
		'galerio_settings.rewrite_profiles_slug',
		'galerio_settings.display_collection_as',
		'galerio_settings.display_collections_as',
		'galerio_settings.rewrite_collection_slug',
		'galerio_settings.rewrite_collections_slug',
		'galerio_settings.display_article_as',
		'galerio_settings.display_articles_as',
		'galerio_settings.rewrite_article_slug',
		'galerio_settings.rewrite_articles_slug',
		'galerio_settings.suppress_featured_images',
		'galerio_settings.hide_profile_images',
		'galerio_settings.restrict_media',
	),

	// Works' creators where the old lists disagreed: work => profile. Storeroom
	// Installation is Stella Hubbard-Smith's alone.
	'creators'        => array( 2708 => 4917 ),

	/*
	 * Galerio settings to set: field => value. swansea.art suppressed
	 * featured images, which Galerio asks the other way round.
	 */
	'settings'        => array(
		'show_featured_images' => 0,
	),

	/*
	 * Term names to change once terms are in their new taxonomy: taxonomy =>
	 * old name => new name. Names follow established art-school usage.
	 */
	'rename'          => array(
		'galerio_medium' => array(
			'Ceramic'         => 'Ceramics',
			'Fabric'          => 'Textiles',
			'Textile'         => 'Textiles',
			'Print'           => 'Printmaking',
			'woodblock print' => 'Woodblock',
			'Video'           => 'Moving image',
			'Old clothing'    => 'Clothing',
			'Magazines'       => 'Zines',
			'zines'           => 'Zines',
		),
	),

	// Terms that aren't mediums, moved to Labels: taxonomy => names.
	'labels'          => array(
		'galerio_medium' => array( 'Conceptual', 'Fairytales', 'Intimate objects' ),
	),

	/*
	 * Medium hierarchy: discipline => forms beneath it. Missing terms are
	 * created, so unused ones stay as ready-made choices. A form can sit under
	 * two disciplines (ink paints and draws; clay is fired or modelled); an
	 * existing term goes under the first discipline that lists it, so the one
	 * profile with Clay (a sculptor) keeps it under Sculpture.
	 */
	'medium_tree'     => array(
		'Painting'          => array( 'Acrylic', 'Oil', 'Ink' ),
		'Drawing'           => array( 'Ink' ),
		'Printmaking'       => array( 'Woodblock' ),
		'Sculpture'         => array( 'Metal', 'Wood', 'Clay' ),
		'Ceramics'          => array( 'Clay' ),
		'Textiles'          => array( 'Weaving', 'Clothing' ),
		"Artists' books"    => array( 'Zines' ),
		'Photography'       => array(),
		'Moving image'      => array(),
		'Sound'             => array(),
		'Performance'       => array(),
		'Installation'      => array( 'Light' ),
		'Digital'           => array( 'Creative coding' ),
		'Mixed media'       => array(),
		'Multidisciplinary' => array(),
	),

	/*
	 * Eclecticon page templates => Galerio's ('' = the default for the type).
	 * Project Page and Custom Project Page both had a side panel beside the
	 * content; Custom and Guest Collection were designed in the editor.
	 */
	'page_templates'  => array(
		'template-project.php'                    => 'wp-custom-template-single-item-collection-project',
		'template-custom_project.php'             => 'wp-custom-template-single-item-collection-project',
		'template-custom_collection.php'          => 'wp-custom-template-blank',
		'template-guest_collection.php'           => 'wp-custom-template-blank',
		'template-fullwidth.php'                  => '',
		'template-listing-eclecticon_project.php' => '',
		'template-frontpage.php'                  => '',
	),

	/*
	 * Templates by content type, ahead of the list above: type => old template
	 * ('' for the default) => Galerio's. Eclecticon showed an exhibition on
	 * the default or Fullwidth template as its content alone (the page is
	 * designed in the editor, as Multocular and the summer shows are), which
	 * is Galerio's Blank template.
	 */
	'type_templates'  => array(
		'galerio_collection' => array(
			''                       => 'wp-custom-template-blank',
			'template-fullwidth.php' => 'wp-custom-template-blank',
		),
	),

	// Items on these old templates get the "Site presents…" line at the top of their content.
	'presents'        => array( 'template-guest_collection.php' ),

	// Per-item custom CSS (WP Add Custom CSS) to move into each item's Page style.
	'page_css'        => '_single_add_custom_css',

	/*
	 * Changes to that CSS: item => old text => new. In Eclecticon (a classic
	 * theme) every Group wrapped its blocks in an inner container, so
	 * AMESSWHA's "exhibitor-container > *" styled that wrapper: one box per
	 * exhibitor. Galerio's groups have no wrapper, so the rule styles the
	 * group itself.
	 */
	'css_replace'     => array(
		5553 => array( '.wp-block-group.exhibitor-container > *' => '.wp-block-group.exhibitor-container' ),
	),

	// CSS added to the end of an item's Page style: item => CSS.
	'css_append'      => array(
		// Eclecticon's Custom Collection template framed the page in a grey border.
		3452 => '/* The grey frame of Eclecticon\'s Custom Collection template. */
.wp-block-post-content
{
  border: 16px solid #ececec;
}',
		5553 => '/* Eclecticon\'s tighter spacing in the exhibitor list: Galerio puts 24px between blocks. */
.wp-block-group:has(> .exhibitor-container),
.wp-block-group.exhibit-text
{
  gap: 0.5em;
}

.wp-block-group.exhibitor-container > *
{
  margin-block-start: 0;
}',
	),

	/*
	 * Pages to set to draft: All Profiles (Galerio's profiles archive takes its
	 * address, /profiles/) and Collections (Eclecticon's empty project listing).
	 */
	'drafts'          => array( 2619, 7 ),

	// Menu items to point at a content type's archive: menu item ID => post type.
	'menu_archives'   => array( 3067 => 'galerio_profile' ),

	/*
	 * The classic menu the header shows, turned into a block menu. The site's
	 * older block menu (a 2023 "Main Menu") is out of date and stays unused.
	 */
	'navigation'      => 32,

	/*
	 * Galerio's names and addresses for the site's content types, so links
	 * keep working: exhibitions stay at /exhibition/ and /exhibitions/, and
	 * profile groups at /profile-group/.
	 */
	'aliases'         => array(
		'types'      => array(
			'collection' => array(
				'singular' => 'Exhibition',
				'plural'   => 'Exhibitions',
				'slug'     => 'exhibition',
				'archive'  => 'exhibitions',
			),
		),
		'taxonomies' => array(
			'group' => array(
				'slug' => 'profile-group',
			),
		),
	),
);
