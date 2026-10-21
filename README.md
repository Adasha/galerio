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
| `tools/` | Development scripts: demo content seeding and a Composer wrapper. |
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

### Installing the Pods package

Import the Pods package with Galerio's importer rather than through the Pods admin screen:

```sh
wp galerio pods import data/pods/galerio.json
```

Pods packages refer to relationship fields by numeric ID. Pods doesn't remap those IDs on import, so on a new site every two-way relationship (for example a work's creator and that creator's works) gets linked to the wrong field. The Galerio importer repairs this after importing. If you have already imported the package through the Pods admin, run `wp galerio pods repair-relationships data/pods/galerio.json`.

## Reading fields

Templates and blocks read Galerio's fields through `galerio-core`, never through Pods directly, so the storage can change later without touching them:

```php
galerio_get_field( 'year' );                         // 2018 (current post)
galerio_get_field( 'work.creator', $post );          // 149 (profile post ID)
galerio_get_field( 'creator', $post, 'display' );    // '<a href="…">Tomos Price</a>'
galerio_get_related( 'in_collections', $post );      // WP_Post[] (published, in saved order)
```

Raw values are normalised by type: strings for text, `Y-m-d` for dates, integers for years, booleans, and post IDs for images and relations. Fields that hold several values return lists. Display values are escaped HTML. Every field is listed in `plugins/galerio-core/includes/fields/default-fields.php`. Other plugins and child themes can add fields on the `galerio_register_fields` action.

Public fields are also included in REST API responses for each post type, under `galerio_fields`.

### Binding fields to blocks

Core Paragraph, Heading, List item, Image and Button blocks can show a field through the `galerio/field` block bindings source. In the editor, choose a field from the block's **Attributes** panel, or write the binding in a template:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.year"}}}}} -->
<p>Year</p>
<!-- /wp:paragraph -->
```

The value fits the attribute it fills: an image field gives an Image block its URL, alt text and caption, a relation gives a Button its link, and text attributes get the display value. When a field is empty, private, or belongs to another content type, the block keeps the content saved in it, so that content works as a fallback.

## Licence

GPL-2.0-or-later.
