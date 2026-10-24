# GALERIO

Galerio is a framework for Wordpress that adds content types, templates and blocks to suit an art gallery or other place that presents works. Galerio is simply 'Gallery' in Esperanto, as it is meant to be accessible to everyone.

Galerio uses the Pods framework for its underlying data structures, with a block theme providing multiple templates from which to build your own site. Ultimately, the plan is to encapsulate all the functionality within a plugin, but for now you'll need to follow a list to get it going on your site. It is in a pre-alpha state, so everything is subject to change - proper versioning will be implemented once the schema have been fixed.

Galerio has a demo/dev site at <https://galerio.net>, but it's also in a very unfinished state. If you'd like to see Galerio in action on a real site, you can visit <https://swansea.art>.

## Repository layout

| Path | Contents |
| --- | --- |
| `themes/galerio` | The Galerio block theme (presentation only). |
| `themes/galerio-child-starter` | A starter child theme with its own colours, fonts and footer pattern. Its README explains how to make your own Galerio child. |
| `plugins/galerio-core` | Data layer and tools, including the Pods package importer. |
| `plugins/galerio-blocks` | Galerio's blocks, starting with the Carousel. Sources are in `src/`, compiled into `build/` by `npm run build`. |
| `data/pods/galerio.json` | The Pods package defining Galerio's content types and fields. |
| `tools/` | Development scripts: demo content seeding, a Composer wrapper, and must-use plugins mounted into the wp-env sites only (`tools/mu-plugins`). |
| `tests/phpunit` | PHPUnit tests, run inside the test site. |
| `tests/e2e` | Playwright end-to-end tests. |

## Development

### Requirements

- [Docker](https://docs.docker.com/get-docker/)
- Node.js 24 (`nvm use` picks up `.nvmrc`)
- Composer is optional: `npm run composer` runs it in Docker if it isn't installed.

### Quick start

```sh
nvm use
npm install
npm run build       # compile the blocks
npm run env:start   # WordPress at http://localhost:8888 (admin / password)
npm run seed        # import the Pods package and create demo content
```

The theme and plugins in this repository are mounted into the site, so edits show up immediately.

If the site loses the theme or plugins after you switch branches, restart it with `npm run env:stop && npm run env:start`. Git sometimes replaces a folder rather than updating it, and Docker stays attached to the old one.

### Commands

| Command | What it does |
| --- | --- |
| `npm run env:start` / `env:stop` | Start or stop the development site (port 8888). |
| `npm run build` / `start` | Compile the blocks in `plugins/galerio-blocks` once, or keep recompiling as you edit. |
| `npm run seed` | Import the Pods package and create demo content. Safe to re-run; add `-- --reset` to recreate the demo content. |
| `npm run env:reset` | Reset the development database and re-seed. |
| `npm run wp -- <command>` | Run WP-CLI against the development site. |
| `npm run lint` | ESLint, Stylelint and PHPCS (WordPress Coding Standards). |
| `npm run lint:php:fix` | Fix what PHPCS can fix automatically. |
| `npm run env:test:start` then `npm run seed:test` | Start and seed a separate test site (port 8889). |
| `npm run test:php` | Run the PHPUnit tests in the test site. They use their own `wptests_` tables, so the site's content is untouched. Pass PHPUnit options after `--`, e.g. `npm run test:php -- --filter Test_Registry`. |
| `npm run test:e2e` | Run the Playwright tests against the test site. |
| `npm run check:plugins` | Run the official Plugin Check against the test site. |

### The Pods package

`data/pods/galerio.json` defines Galerio's content types and fields. Manage it with Galerio's commands rather than the Pods admin screens:

| Command | What it does |
| --- | --- |
| `wp galerio pods import <file>` | Import the package and repair its two-way relationships. |
| `wp galerio pods diff <file>` | List the differences between the site's Pods configuration and the package. Add `--exit-code` to exit with status 1 when there are any. |
| `wp galerio pods export [<file>]` | Export the site's Pods configuration as a package. |
| `wp galerio pods repair-relationships <file>` | Repair relationships after importing through the Pods admin. |

In the development site the package is at `wp-content/galerio-data/pods/galerio.json`, so to bring changes made in the Pods admin back into the repository:

```sh
npm run wp -- galerio pods diff wp-content/galerio-data/pods/galerio.json
npm run wp -- galerio pods export wp-content/galerio-data/pods/galerio.json
git diff data/pods/galerio.json
```

Exports are canonical: pods and templates are sorted by name, settings are in a fixed order, IDs are numbered from 1, and values that only describe one site (database IDs, edit locks, Pods' upgrade markers) are left out. The same configuration exports identically from any site, so `git diff` shows only real changes. `--raw` gives Pods' own export instead.

Pods packages refer to relationship fields by numeric ID, and Pods doesn't remap those IDs on import, so on a new site every two-way relationship (for example a work's creator and that creator's works) would be linked to the wrong field. Galerio's importer repairs this, which is why packages should be imported with `wp galerio pods import`.

Two side effects of importing are worth knowing about:

- **Removed settings stay put.** Importing updates and adds settings but doesn't remove them, so a setting deleted from the package stays on sites that already have it. `diff` lists these as "site only".
- **Run imports as an administrator.** WP-CLI and the Pods admin both do this. For other users, Pods filters template code on save.

## Reading fields

Templates and blocks read Galerio's fields through `galerio-core`, never through Pods directly, so the storage can change later without touching them:

```php
galerio_get_field( 'year' );                         // 2018 (current post)
galerio_get_field( 'work.creator', $post );          // 149 (profile post ID)
galerio_get_field( 'creator', $post, 'display' );    // '<a href="…">Tomos Price</a>'
galerio_get_related( 'collections', $post );         // WP_Post[] (published, in saved order)
galerio_get_field( 'links', $profile );              // [ [ 'url' => …, 'label' => …, 'offline' => false ], … ]
```

Raw values are normalised by type: strings for text, `Y-m-d` for dates, integers for years, booleans, post IDs for images and relations, and `{url, label, offline}` arrays for links. Fields that hold several values return lists. Display values are escaped HTML. Every field is listed in `plugins/galerio-core/includes/fields/default-fields.php`, under the same name it has in the Pods package. Other plugins and child themes can add fields on the `galerio_register_fields` action.

Links (a profile's `links`, and the `website` of works and collections) use Galerio's own Link field type in Pods. Each link has optional display text. Without it, links to recognised services (Instagram, YouTube, Bluesky and others; extend the list with the `galerio_link_services` filter) show the service's name, and other links show their address.

A work without a featured image uses its `definitive_image` as one, so core blocks such as Post Featured Image show it.

Public fields are also included in REST API responses for each post type, under `galerio_fields`.

### Field reference

Every field templates, blocks and code can use, by content type. Keys work in `galerio_get_field()`, in block bindings and in the Field block. `wp galerio fields list` prints the same list. This section is generated from the field registry by `npm run docs:fields`, and a test fails if it falls out of date.

<!-- field-reference:start -->

#### Works

| Key | Label | Type | Notes |
| --- | --- | --- | --- |
| `work.definitive_title` | Definitive title | Text |  |
| `work.creator` | Creator | Relationship to profiles |  |
| `work.year` | Year | Year |  |
| `work.definitive_image` | Definitive image | Image |  |
| `work.images` | Images | Image (list) |  |
| `work.description` | Description | Formatted text |  |
| `work.medium` | Medium | Text |  |
| `work.dimensions` | Dimensions | Text |  |
| `work.duration` | Duration | Text |  |
| `work.location` | Location | Text |  |
| `work.owner` | Current owner | Text | Private: not shown on the site, in REST or in block bindings. |
| `work.website` | Website | Link |  |
| `work.collections` | Collections | Relationship to collections (list) |  |
| `work.articles` | Articles | Relationship to articles (list) |  |
| `work.related_works` | Related works | Relationship to works (list) |  |

#### Profiles

| Key | Label | Type | Notes |
| --- | --- | --- | --- |
| `profile.portrait` | Portrait | Image |  |
| `profile.show_portrait` | Show portrait | On/off |  |
| `profile.nationality` | Nationality | Text |  |
| `profile.show_nationality` | Show nationality | On/off |  |
| `profile.year_of_birth` | Year of birth or formation | Year |  |
| `profile.year_of_death` | Year of death or disbanding | Year |  |
| `profile.show_lifespan` | Show lifespan | On/off |  |
| `profile.is_collective` | Is a collective | On/off |  |
| `profile.members` | Members | Relationship to profiles (list) |  |
| `profile.member_of` | Member of | Relationship to profiles (list) |  |
| `profile.bio` | Biography | Formatted text |  |
| `profile.notes` | Notes | Formatted text |  |
| `profile.accolades` | Accolades | Plain text, several lines (list) |  |
| `profile.works` | Works | Relationship to works (list) |  |
| `profile.collections` | Collections | Relationship to collections (list) |  |
| `profile.article_mentions` | Mentioned in articles | Relationship to articles (list) |  |
| `profile.authored_articles` | Authored articles | Relationship to articles (list) |  |
| `profile.related_profiles` | Related profiles | Relationship to profiles (list) |  |
| `profile.show_links` | Show links | On/off |  |
| `profile.links` | Links | Link (list) |  |

#### Collections

| Key | Label | Type | Notes |
| --- | --- | --- | --- |
| `collection.standfirst` | Standfirst | Text |  |
| `collection.description` | Description | Formatted text |  |
| `collection.location` | Location | Text |  |
| `collection.start_date` | Start date | Date |  |
| `collection.end_date` | End date | Date |  |
| `collection.website` | Website | Link |  |
| `collection.images` | Images | Image (list) |  |
| `collection.shuffle_images` | Shuffle images | On/off |  |
| `collection.embeds` | Embeds | Embed URL (list) |  |
| `collection.notes` | Notes | Formatted text |  |
| `collection.profiles` | Profiles | Relationship to profiles (list) |  |
| `collection.curators` | Curators | Relationship to profiles (list) |  |
| `collection.other_contributors` | Other contributors | Text (list) |  |
| `collection.works` | Works | Relationship to works (list) |  |
| `collection.articles` | Articles | Relationship to articles (list) |  |
| `collection.related_collections` | Related collections | Relationship to collections (list) |  |

#### Articles

| Key | Label | Type | Notes |
| --- | --- | --- | --- |
| `article.standfirst` | Standfirst | Text |  |
| `article.authors` | Authors | Relationship to profiles (list) |  |
| `article.profiles` | Profiles | Relationship to profiles (list) |  |
| `article.works` | Works | Relationship to works (list) |  |
| `article.collections` | Collections | Relationship to collections (list) |  |
| `article.related_articles` | Related articles | Relationship to articles (list) |  |

#### Media

| Key | Label | Type | Notes |
| --- | --- | --- | --- |
| `attachment.year` | Year | Year |  |
| `attachment.credit` | Credit | Text |  |
| `attachment.copyright` | Copyright | Text |  |

<!-- field-reference:end -->

### Binding fields to blocks

Core Paragraph, Heading, List item, Image and Button blocks can show a field through the `galerio/field` block bindings source. In the editor, choose a field from the block's **Attributes** panel, or write the binding in a template:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.year"}}}}} -->
<p>Year</p>
<!-- /wp:paragraph -->
```

Bindings take optional arguments beside `key`:

| Argument | Example | Effect |
| --- | --- | --- |
| `fallback` | `"title"` | Show the post title when the field is empty. |
| `prefix`, `suffix` | `"b. "` | Plain text around the value, only when there is one. |
| `if` | `"show_lifespan"` | Show the value only when that on/off field is on. |
| `link` | `true` | Link the value to the post, e.g. a title in an archive card. |

The value fits the attribute it fills: an image field gives an Image block its URL, alt text and caption, a relation gives a Button its link, and text attributes get the display value. When a field is empty, private, or belongs to another content type, the block keeps the content saved in it, so that content works as a fallback.

### The Field block

Some fields can't sit in a core block: formatted text such as a profile's `bio`, lists such as accolades or related works, and sections that should disappear when empty. The **Field** block (`galerio/field-display`) shows any field's display value, with an optional heading. Its "One per line" layout puts each value of a list on its own line, and "Show only when" ties it to an on/off field. When there's nothing to show, it renders nothing, heading included.

```html
<!-- wp:galerio/field-display {"key":"profile.accolades","label":"{field}:","layout":"list"} /-->
```

The theme's templates use bindings and Field blocks only. They no longer depend on Pods shortcodes, Pods blocks or Pods Templates.

Each Galerio template is a thin wrapper: the layout around a single `<!-- wp:pattern {"slug":"galerio/template-…"} /-->` block. The page's content lives in that hidden pattern in `themes/galerio/patterns/template-*.php`, so its wording is translatable (text domain `galerio`), and a child theme can override one pattern without copying the whole template. In patterns, `galerio_theme_text()` puts the site's type names into a translated text, using the same tokens as above, and `galerio_theme_attr()` writes a translated string into a block attribute.

To start a new template in the Site Editor, choose one of the two layout patterns:

- **Side menu layout**, the theme's standard: the site menu in a sticky column on the left (16rem wide), the content and footer taking the rest. On phones the columns stack and the header becomes a bar, with the menu button beside the site title.
- **Top header layout**: the site title and menu in a bar across the top (the `header-bar` template part), the content and footer below.

Style variations, chosen under Site Editor → Styles:

- **Gallery White** (colours): white and near-black, without the red accent.
- **Dark Room** (colours): a dark background with amber links.
- **Editorial** (typography): Lato headings in sentence case, and serif body text with more line spacing.

A colour variation must define all eight of Galerio's colour slugs, and its text must meet WCAG AA contrast (4.5:1). `tests/phpunit/theme/test-style-variations.php` checks both, for the variations and the child starter.

The work page's details table puts each label above its value when its box is too narrow for both side by side (a container query on `.galerio-details`).

The wp-env sites run with `WP_DEVELOPMENT_MODE` set to `all`, so WordPress doesn't cache the theme's patterns or `theme.json` between changes.

## Renaming content types

A site can give Galerio's content types and taxonomies its own names and addresses under **Settings → Galerio content types**, for example Collections → Exhibitions at `/exhibitions/`. The Gallery, Record label and Publisher presets fill in the form as a starting point, and every value can still be changed. An empty box keeps the registered name or slug.

- The aliases are stored in the `galerio_aliases` option, not in the Pods package, so `wp galerio pods import` never overwrites them. They're applied with the `register_post_type_args` and `register_taxonomy_args` filters.
- Internal names don't change (`galerio_collection`, field keys such as `collection.works`), so no content moves.
- When a slug changes, the old address 301-redirects to the new one, for addresses that would otherwise be "not found". Turn this off on a site whose redirects are handled by another plugin.
- Filter `galerio_alias_presets` to add or change presets.

### Type names in labels

Labels can name a type with a token, which becomes the site's name for it. A token is the type's standard name in braces, and its case sets how the name is written: `{Collections}` gives "Exhibitions", `{collections}` gives "exhibitions", and `{Collection}` and `{collection}` give the singular. Tokens exist for works, profiles, collections and articles, and for the medium, label, group and role taxonomies. Unknown tokens are left as they are.

- They work in the Field block's heading, in a binding's `prefix` and `suffix`, and in field labels in the registry. The registry's relationship labels already use them: `work.related_works` reads "Related artworks" on a site that calls its works Artworks.
- In a Field block heading, `{field}` is the field's own label, which galerio-core translates. The theme's headings use it where they just name the field: `"label":"{field}:"`.

```html
<!-- wp:galerio/field-display {"key":"profile.works","label":"Featured {works}:","layout":"list"} /-->
```

## Packaging

`npm run package` builds installable zips into `dist/` (not committed): `galerio-<version>.zip`, `galerio-child-starter-<version>.zip` and `galerio-core-<version>.zip`, for Themes/Plugins → Add New → Upload. It stops if a package's version numbers disagree, or if the theme's screenshot or either `.pot` file is missing.

Before packaging a release:

1. Bump the version in each package that changed: the theme's `style.css` and `readme.txt`; galerio-core's plugin header, `GALERIO_CORE_VERSION` and readme `Stable tag`; the child starter's `style.css`. The theme and the starter use semantic versions with pre-release suffixes (`3.0.0-alpha.1`).
2. Add the changes to each readme's changelog.
3. Run `npm run i18n:pot` to regenerate `themes/galerio/languages/galerio.pot` and `plugins/galerio-core/languages/galerio-core.pot`.

## Licence

GPL-2.0-or-later.
