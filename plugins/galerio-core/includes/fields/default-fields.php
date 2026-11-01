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
		'description' => __( 'The definitive name for the work, if one exists, otherwise the post title is used.', 'galerio-core' ),
		'group'       => 'details',
		'label'       => __( 'Definitive title', 'galerio-core' ),
	),
	'work.creator'                   => array(
		'description' => __( 'Connect the profile of the work\'s creator. Add a new creator profile if one doesn\'t exist.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'relation',
		'target'      => 'profile',
		'inverse'     => 'profile.works',
		'label'       => __( 'Creator', 'galerio-core' ),
	),
	'work.year'                      => array(
		'description' => __( 'The year the work was completed, or the first year of a range.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'year',
		'label'       => __( 'Year', 'galerio-core' ),
	),
	'work.year_to'                   => array(
		'description' => __( 'The last year of a range, if the work was made over several years or the date is uncertain. Leave empty for a single year.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'year',
		'label'       => __( 'Year to', 'galerio-core' ),
	),
	'work.date_text'                 => array(
		'description' => __( 'Overrides how the date is shown, e.g. "mid-19th century" or "600–800 CE". Leave empty to show the year or range.', 'galerio-core' ),
		'group'       => 'details',
		'label'       => __( 'Date in words', 'galerio-core' ),
	),
	'work.definitive_image'          => array(
		'description' => __( 'The work\'s single true representation. The featured image falls back to it when empty.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'image',
		'label'       => __( 'Definitive image', 'galerio-core' ),
	),
	'work.description'               => array(
		'description' => __( 'A description for templates to place, apart from the main text.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'html',
		'label'       => __( 'Description', 'galerio-core' ),
	),
	'work.website'                   => array(
		'description' => __( 'An external website with more about the work.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'link',
		'label'       => __( 'Website', 'galerio-core' ),
	),
	'work.images'                    => array(
		'description' => __( 'Additional images of the work.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'image',
		'multiple'    => true,
		'label'       => __( 'Images', 'galerio-core' ),
	),
	'work.medium'                    => array(
		'description' => __( 'Text description of the work medium(s).', 'galerio-core' ),
		'group'       => 'metadata',
		'label'       => __( 'Medium', 'galerio-core' ),
	),
	'work.dimensions'                => array(
		'description' => __( 'The absolute size of the work, if physical.', 'galerio-core' ),
		'group'       => 'metadata',
		'label'       => __( 'Dimensions', 'galerio-core' ),
	),
	'work.duration'                  => array(
		'description' => __( 'The duration of the work, if relevant.', 'galerio-core' ),
		'group'       => 'metadata',
		'label'       => __( 'Duration', 'galerio-core' ),
	),
	'work.collections'               => array(
		'description' => __( 'List the collections in which this work has been featured.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'collection',
		'inverse'     => 'collection.works',
		'multiple'    => true,
		'label'       => '{Collections}',
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
		'description' => __( 'Other works that may be related to this one.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'work',
		'inverse'     => 'work.related_works',
		'multiple'    => true,
		/* translators: Keep {works} as it is: it becomes this site's name for works. */
		'label'       => __( 'Related {works}', 'galerio-core' ),
	),
	'work.location'                  => array(
		'description' => __( 'Where the work is currently held.', 'galerio-core' ),
		'group'       => 'cataloging',
		'label'       => __( 'Location', 'galerio-core' ),
	),
	'work.owner'                     => array(
		'description' => __( 'Private: who owns the work now. Not shown on the site.', 'galerio-core' ),
		'group'       => 'cataloging',
		'public'      => false,
		'label'       => __( 'Current owner', 'galerio-core' ),
	),
	'work.valuation'                 => array(
		'description' => __( 'Private: the work\'s value, in the site\'s currency. Seen by editors and administrators only.', 'galerio-core' ),
		'group'       => 'cataloging',
		'roles'       => array( 'administrator', 'editor' ),
		'type'        => 'number',
		'public'      => false,
		'label'       => __( 'Valuation', 'galerio-core' ),
	),
	'work.valuation_date'            => array(
		'description' => __( 'Private: when the work was valued.', 'galerio-core' ),
		'group'       => 'cataloging',
		'roles'       => array( 'administrator', 'editor' ),
		'type'        => 'date',
		'public'      => false,
		'label'       => __( 'Valuation date', 'galerio-core' ),
	),
	'work.valued_by'                 => array(
		'description' => __( 'Private: who valued the work.', 'galerio-core' ),
		'group'       => 'cataloging',
		'roles'       => array( 'administrator', 'editor' ),
		'public'      => false,
		'label'       => __( 'Valued by', 'galerio-core' ),
	),
	'work.valuation_notes'           => array(
		'description' => __( 'Private: anything else about the valuation, such as its purpose or the documents behind it.', 'galerio-core' ),
		'group'       => 'cataloging',
		'roles'       => array( 'administrator', 'editor' ),
		'type'        => 'textarea',
		'public'      => false,
		'label'       => __( 'Valuation notes', 'galerio-core' ),
	),
	'work.availability'              => array(
		'description' => __( 'Whether the work can be bought. Leave empty to say nothing. A work in an edition with none left available shows as Sold.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'choice',
		'options'     => array(
			'available'    => __( 'Available', 'galerio-core' ),
			'reserved'     => __( 'Reserved', 'galerio-core' ),
			'sold'         => __( 'Sold', 'galerio-core' ),
			'on_loan'      => __( 'On loan', 'galerio-core' ),
			'not_for_sale' => __( 'Not for sale', 'galerio-core' ),
		),
		'label'       => __( 'Availability', 'galerio-core' ),
	),
	'work.price'                     => array(
		'description' => __( 'Shown while the work is Available or Reserved, in the work’s currency.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'number',
		'label'       => __( 'Price', 'galerio-core' ),
	),
	'work.price_on_application'      => array(
		'description' => __( 'Shows “Price on application” instead of the price, which then stays private.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'boolean',
		'label'       => __( 'Price on application', 'galerio-core' ),
	),
	'work.currency'                  => array(
		'description' => __( 'Leave empty for the site’s currency (Settings → Galerio).', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'choice',
		'options'     => \Galerio\Core\Catalogue\Catalogue::currencies(),
		'label'       => __( 'Currency', 'galerio-core' ),
	),
	'work.edition_size'              => array(
		'description' => __( 'For a work in an edition, such as a print: how many there are. Shown as “Edition of 10”.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'number',
		'label'       => __( 'Edition size', 'galerio-core' ),
	),
	'work.editions_available'        => array(
		'description' => __( 'How many of the edition are left. At 0 the work shows as Sold.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'number',
		'label'       => __( 'Editions available', 'galerio-core' ),
	),
	'work.framed'                    => array(
		'description' => __( 'Whether the work is sold framed.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'boolean',
		'label'       => __( 'Framed', 'galerio-core' ),
	),
	'work.inventory_number'          => array(
		'description' => __( 'Your reference for the work, shown on its page so buyers can quote it.', 'galerio-core' ),
		'group'       => 'sales',
		'label'       => __( 'Inventory number', 'galerio-core' ),
	),
	'work.available_from'            => array(
		'description' => __( 'A dealer or shop selling the work. Leave empty to use the creator’s “Represented by”, if any.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'link',
		'label'       => __( 'Available from', 'galerio-core' ),
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
		'description' => __( 'Only valid if enabled site-wide.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'boolean',
		'label'       => __( 'Show portrait', 'galerio-core' ),
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
		'description' => __( 'Year of birth of the artist (or year collective was formed).', 'galerio-core' ),
		'group'       => 'details',
		'roles'       => array( 'administrator', 'editor', 'author' ),
		'type'        => 'year',
		'label'       => __( 'Year of birth or formation', 'galerio-core' ),
	),
	'profile.year_of_death'          => array(
		'description' => __( 'Year of death of the artist (or year collective was disbanded).', 'galerio-core' ),
		'group'       => 'details',
		'roles'       => array( 'administrator', 'editor', 'author' ),
		'type'        => 'year',
		'label'       => __( 'Year of death or disbanding', 'galerio-core' ),
	),
	'profile.show_lifespan'          => array(
		'description' => __( 'Whether to display the year of birth/death (if specified).', 'galerio-core' ),
		'group'       => 'details',
		'roles'       => array( 'administrator', 'editor', 'author' ),
		'type'        => 'boolean',
		'label'       => __( 'Show lifespan', 'galerio-core' ),
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
		'description' => __( 'Collectives this profile belongs to.', 'galerio-core' ),
		'group'       => 'details',
		'roles'       => array( 'administrator', 'editor', 'author' ),
		'type'        => 'relation',
		'target'      => 'profile',
		'inverse'     => 'profile.members',
		'multiple'    => true,
		'label'       => __( 'Member of', 'galerio-core' ),
	),
	'profile.bio'                    => array(
		'group' => 'biography',
		'type'  => 'html',
		'label' => __( 'Biography', 'galerio-core' ),
	),
	'profile.notes'                  => array(
		'description' => __( 'Additional content to display at the end, e.g. credits.', 'galerio-core' ),
		'group'       => 'biography',
		'type'        => 'html',
		'label'       => __( 'Notes', 'galerio-core' ),
	),
	'profile.accolades'              => array(
		'description' => __( 'Awards, prizes etc. Read-only for contributors.', 'galerio-core' ),
		'group'       => 'biography',
		'type'        => 'textarea',
		'multiple'    => true,
		'label'       => __( 'Accolades', 'galerio-core' ),
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
		'description' => __( 'Websites and social media. Display text is optional: recognised services show their name, others their address.', 'galerio-core' ),
		'group'       => 'external_links',
		'type'        => 'link',
		'multiple'    => true,
		'label'       => __( 'Links', 'galerio-core' ),
	),
	'profile.represented_by'         => array(
		'description' => __( 'A gallery or dealer that sells this creator’s work. Their available works link to it, unless a work names its own dealer.', 'galerio-core' ),
		'group'       => 'sales',
		'type'        => 'link',
		'label'       => __( 'Represented by', 'galerio-core' ),
	),

	// Collections.
	'collection.standfirst'          => array(
		'description' => __( 'A short line below the title that draws the reader in, like a subtitle. Shown only when set.', 'galerio-core' ),
		'group'       => 'details',
		'label'       => __( 'Standfirst', 'galerio-core' ),
	),
	'collection.location'            => array(
		'description' => __( 'Where the collection is located.', 'galerio-core' ),
		'group'       => 'details',
		'label'       => __( 'Location', 'galerio-core' ),
	),
	'collection.start_date'          => array(
		'description' => __( 'When the collection opened.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'date',
		'label'       => __( 'Start date', 'galerio-core' ),
	),
	'collection.end_date'            => array(
		'description' => __( 'When the collection closed.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'date',
		'label'       => __( 'End date', 'galerio-core' ),
	),
	'collection.website'             => array(
		'description'  => __( 'An external website for the collection. Tick "offline" when the site no longer exists.', 'galerio-core' ),
		'asks_offline' => true,
		'group'        => 'details',
		'type'         => 'link',
		'label'        => __( 'Website', 'galerio-core' ),
	),
	'collection.description'         => array(
		'description' => __( 'Describe the collection in as much detail as you like.', 'galerio-core' ),
		'group'       => 'content',
		'type'        => 'html',
		'label'       => __( 'Description', 'galerio-core' ),
	),
	'collection.images'              => array(
		'description' => __( 'Images the collection page lays out as a gallery, in this order. Leave empty to show only the main text.', 'galerio-core' ),
		'group'       => 'content',
		'type'        => 'image',
		'multiple'    => true,
		'label'       => __( 'Images', 'galerio-core' ),
	),
	'collection.shuffle_images'      => array(
		'description' => __( 'Randomise the order of images?', 'galerio-core' ),
		'group'       => 'content',
		'type'        => 'boolean',
		'label'       => __( 'Shuffle images', 'galerio-core' ),
	),
	'collection.embeds'              => array(
		'group'    => 'content',
		'type'     => 'embed',
		'multiple' => true,
		'label'    => __( 'Embeds', 'galerio-core' ),
	),
	'collection.notes'               => array(
		'description' => __( 'Additional content to display at the end, e.g. credits.', 'galerio-core' ),
		'group'       => 'content',
		'type'        => 'html',
		'label'       => __( 'Notes', 'galerio-core' ),
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
		'description' => __( 'Profiles of the people who curated or helped put the collection together.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'profile',
		'multiple'    => true,
		'label'       => __( 'Curators', 'galerio-core' ),
	),
	'collection.other_contributors'  => array(
		'description' => __( 'Contributors without profile pages, one per line.', 'galerio-core' ),
		'group'       => 'relationships',
		'multiple'    => true,
		'label'       => __( 'Other contributors', 'galerio-core' ),
	),
	'collection.works'               => array(
		'description' => __( 'List of works featured in this collection.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'work',
		'inverse'     => 'work.collections',
		'multiple'    => true,
		'label'       => '{Works}',
	),
	'collection.articles'            => array(
		'description' => __( 'List of articles referrng to this collection.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'article',
		'inverse'     => 'article.collections',
		'multiple'    => true,
		'label'       => '{Articles}',
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
		'description' => __( 'A short line below the title that draws the reader in, like a subtitle. Shown only when set.', 'galerio-core' ),
		'group'       => 'details',
		'label'       => __( 'Standfirst', 'galerio-core' ),
	),
	'article.authors'                => array(
		'description' => __( 'The author(s) of the article, if different from the page creator.', 'galerio-core' ),
		'group'       => 'details',
		'type'        => 'relation',
		'target'      => 'profile',
		'inverse'     => 'profile.authored_articles',
		'multiple'    => true,
		'label'       => __( 'Authors', 'galerio-core' ),
	),
	'article.profiles'               => array(
		'description' => __( 'Profiles featured in this article.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'profile',
		'inverse'     => 'profile.article_mentions',
		'multiple'    => true,
		'label'       => '{Profiles}',
	),
	'article.works'                  => array(
		'description' => __( 'Works featured in this article.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'work',
		'inverse'     => 'work.articles',
		'multiple'    => true,
		'label'       => '{Works}',
	),
	'article.collections'            => array(
		'description' => __( 'Collections featured in this article.', 'galerio-core' ),
		'group'       => 'relationships',
		'type'        => 'relation',
		'target'      => 'collection',
		'inverse'     => 'collection.articles',
		'multiple'    => true,
		'label'       => '{Collections}',
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
		'description' => __( 'Who made it, for a creator without a profile.', 'galerio-core' ),
		'group'       => 'metadata',
		'label'       => __( 'Credit', 'galerio-core' ),
	),
	'attachment.copyright'           => array(
		'group' => 'metadata',
		'label' => __( 'Copyright', 'galerio-core' ),
	),
);
