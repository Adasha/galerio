# GALERIO

Galerio is a framework for Wordpress that adds content types, templates and blocks to suit an art gallery or other place that presents works. Galerio is simply 'Gallery' in Esperanto, as it is meant to be accessible to everyone.

Galerio uses the Pods framework for its underlying data structures, with a block theme providing multiple templates from which to build your own site. Ultimately, the plan is to encapsulate all the functionality within a plugin, but for now you'll need to follow a list to get it going on your site. It is in a pre-alpha state, so everything is subject to change - proper versioning will be implemented once the schema have been fixed.

Galerio has a demo/dev site at <https://galerio.net>, but it's also in a very unfinished state. If you'd like to see Galerio in action on a real site, you can visit <https://swansea.art>.

## Repository layout

| Path | Contents |
| --- | --- |
| `themes/galerio` | The Galerio block theme (presentation only). |
| `plugins/galerio-core` | Data layer and tools, including the Pods package importer. |
| `plugins/post-slider`, `plugins/query-carousel` | Block prototypes, to be merged into a single Galerio carousel block. |
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
npm run env:start   # WordPress at http://localhost:8888 (admin / password)
npm run seed        # import the Pods package and create demo content
```

The theme and plugins in this repository are mounted into the site, so edits show up immediately.

If the site loses the theme or plugins after you switch branches, restart it with `npm run env:stop && npm run env:start`. Git sometimes replaces a folder rather than updating it, and Docker stays attached to the old one.

### Commands

| Command | What it does |
| --- | --- |
| `npm run env:start` / `env:stop` | Start or stop the development site (port 8888). |
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
<!-- wp:galerio/field-display {"key":"profile.works","label":"Featured works:","layout":"list"} /-->
```

The theme's templates use bindings and Field blocks only. They no longer depend on Pods shortcodes, Pods blocks or Pods Templates.

To start a new template in the Site Editor with the theme's standard layout (menu column on the left, content and footer on the right), choose the **Side menu layout** pattern.

The wp-env sites run with `WP_DEVELOPMENT_MODE` set to `all`, so WordPress doesn't cache the theme's patterns or `theme.json` between changes.

## Licence

GPL-2.0-or-later.
