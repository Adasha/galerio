# GALERIO

Galerio is a WordPress theme and set of plugins for galleries, artists, publishers, musicians and anyone else who presents creative works. It adds content types, templates and blocks for creators, works, collections and articles. Galerio is simply 'Gallery' in Esperanto, as it is meant to be accessible to everyone.

Galerio uses the Pods framework for its underlying data structures, with a block theme providing templates from which to build your own site. It is in a pre-alpha state, so everything is subject to change.

Galerio has a demo/dev site at <https://galerio.net>, but it's also in a very unfinished state. If you'd like to see Galerio in action on a real site, you can visit <https://swansea.art>.

## Repository layout

| Path | Contents |
| --- | --- |
| `themes/galerio` | The Galerio block theme (presentation only). |
| `plugins/galerio-core` | Data layer and tools, including the Pods package importer. |
| `plugins/post-slider`, `plugins/query-carousel` | Block prototypes, to be merged into a single Galerio carousel block. |
| `data/pods/galerio.json` | The Pods package defining Galerio's content types and fields. |
| `tools/` | Development scripts: demo content seeding and a Composer wrapper. |
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
| `npm run test:e2e` | Run the Playwright tests against the test site. |
| `npm run check:plugins` | Run the official Plugin Check against the test site. |

### Installing the Pods package

Import the Pods package with Galerio's importer rather than through the Pods admin screen:

```sh
wp galerio pods import data/pods/galerio.json
```

Pods packages refer to relationship fields by numeric ID. Pods doesn't remap those IDs on import, so on a new site every two-way relationship (for example a work's creator and that creator's works) gets linked to the wrong field. The Galerio importer repairs this after importing. If you have already imported the package through the Pods admin, run `wp galerio pods repair-relationships data/pods/galerio.json`.

## Licence

GPL-2.0-or-later.
