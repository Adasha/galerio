# Galerio Migrate

A one-off WP-CLI migration that moves galerio.net and swansea.art from their old Pods model onto Galerio's content model, as agreed in the content model proposal. It isn't part of the Galerio package: delete it once a site has moved.

It needs Pods and Galerio Core (0.3.0 or later) active.

## Running it

1. Back up the database: `wp db export before-migration.sql`.
2. See what would change: `wp galerio migrate content-model --site=galerio-net --dry-run`.
3. Migrate: `wp galerio migrate content-model --site=galerio-net --yes`. It's safe to run again, and a second run should find nothing to do.
4. Switch the site to the new theme's templates, removing Site Editor changes made for the old ones: `wp galerio migrate theme-reset --dry-run`, then `--yes`.

The reset keeps which menu each Navigation block showed: the theme's version of that template or part is saved with the same menus chosen, as you would in the Site Editor.

## What it does

1. Imports the Galerio Pods package (bundled in the zip as `package/galerio.json`) alongside the old fields.
2. Copies each old field's values to its new name, keeping the order Pods keeps for relationships and images (`work_creator` → `creator`, `year_created` → `year`, `principal_image` → `definitive_image`, and so on: see `includes/class-map.php`).
3. Merges a profile's ten link fields into `links`, and a work's or collection's website name, URL and dead-link flag into `website`. It lists collections whose images were hidden by `managed_media`, as they will now be shown.
4. Rebuilds collective membership (`members` and `member_of`) from `collective_members`.
5. Moves terms to their new taxonomies (Profile groups → Groups, Styles and Genres → Labels, Forms → Mediums), then applies the site's decisions in `sites/<site>.php`: renames and merges, the Medium hierarchy, `work_categorisation` values as Labels, and mediums inferred from the free-text medium.
6. Removes what the package no longer has: the old fields and their values, old taxonomies, Pods Templates and stale settings.
7. Checks the site's Pods setup against the package, in a fresh process.

A value already in its new place is left alone. If a new field already holds something different, it's reported and never overwritten.

## A site's decisions

`sites/<site>.php` returns the site's decisions. Each key is optional:

| Key | What it does |
| --- | --- |
| `post_types` | Moves an old post type's items to a Galerio type, keeping IDs, fields and menu items; blocks that query the old type follow. |
| `taxonomies`, `drop_taxonomies` | Moves more old taxonomies into Galerio's (terms keep their IDs), or deletes them. |
| `fields`, `profile_links`, `retire` | More field renames, link fields to merge, and old fields to remove (their values are listed first). |
| `settings` | Galerio settings to set. |
| `rename`, `labels`, `medium_tree`, `forms`, `categorisation`, `infer_medium` | Term renames and merges, terms that become Labels, the Medium hierarchy (missing terms are created; a form can sit under two disciplines). |
| `page_templates`, `presents`, `page_css` | Old page templates → Galerio's, the "Site presents…" line, and per-item custom CSS → Page style. |
| `drafts`, `menu_archives`, `navigation`, `aliases` | Pages to retire, menu items that become archive links, the classic menu to show as the block menu, and Galerio's names and addresses for content types. |

Fields the site has that the package doesn't, and that aren't retired (such as swansea.art's review checklist), are kept with their group. Pods blocks become Field, Gallery, Exhibition Dates and Post Terms blocks. At the end, Pods' relationship table is rebuilt from the lists the site shows: where only one side of a two-way relationship lists the other, the other side gains it.

## Trying it locally

For swansea.art: `npm run swansea:load -- <dump.sql> https://swansea.art` loads a database dump into the local copy (port 8891, images served from the live site), which mounts the Galerio plugins and themes from this repository. Deactivate Ignition there before looking at pages: Eclecticon's framework takes over archive templates.

For galerio.net: `npm run env:trial:start`, then `npm run trial:import -- wp/<export>.xml pods/live/<package>.json` builds the trial site (port 8890) from a live site's WordPress export and Pods package, keeping post IDs. Run the commands above with `npx wp-env --config=.wp-env.trial.json run cli wp …`.
