# Galerio Child Starter

A starting point for your own Galerio child theme. It inherits everything from Galerio and changes three things, one in each of the ways a child theme usually customises it:

- **Colours:** its own palette, in `theme.json`.
- **Fonts:** its own heading and body fonts, in `theme.json`.
- **A pattern:** its own footer credit line, in `patterns/footer-credit.php`.

Galerio needs the Galerio Core plugin, which needs Pods.

## Make your own

1. Copy this folder and rename it, e.g. `my-gallery`.
2. In `style.css`, change `Theme Name`, `Text Domain`, `Author` and `Description`. Keep `Template: galerio`: that line makes it a child of Galerio.
3. Change the text domain in `patterns/*.php` to match (`'galerio-child-starter'` → `'my-gallery'`).
4. Activate it under Appearance → Themes.

## What you can change, from lightest to heaviest

Change as little as you can: anything you don't copy keeps getting Galerio's updates.

1. **`theme.json`**: colours, fonts, sizes and block styles. Your file is merged over Galerio's, but each preset list replaces Galerio's list as a whole. Define every slug you list:
   - **Colours:** `base`, `contrast`, `primary`, `secondary`, `custom-lower-contrast`, `custom-highlight`, `custom-hint` and `custom-lowlight`. Galerio's styles use all eight.
   - **Fonts:** `heading` and `body`. Galerio's styles use them, so redefining these two changes every heading, the site title, the menu and the text. To use font files, put them in `assets/fonts/` and add a `fontFace` list, as Galerio's own `theme.json` does.
2. **Patterns**: a file in `patterns/` with the same `Slug:` as one of Galerio's replaces it. Each page's content is its own pattern, so you can change one page without copying its template:

   | Pattern | Used by |
   | --- | --- |
   | `galerio/template-single-work` | a work's page |
   | `galerio/template-single-profile` | a creator's page |
   | `galerio/template-single-collection` | a collection's page |
   | `galerio/template-single-article` | an article's page |
   | `galerio/template-archive-works`, `-profiles`, `-collections`, `-articles` | the lists of each |
   | `galerio/template-taxonomy-group` | a group's page |
   | `galerio/template-404` | the "page not found" page |
   | `galerio/template-single-item-collection` | the single-item collection template |
   | `galerio/footer-credit` | the footer's credit line |

   Start by copying Galerio's file. For text, use `esc_html_e()` with your text domain. For text inside a block's settings, use `galerio_theme_attr( __( '…', 'my-gallery' ) )`. To use the site's names for its content types, wrap the text in `galerio_theme_text()`, with tokens such as `{Collections}` or `{works}`.
3. **Template parts** (`parts/`): the header (`site-header`, or `header-bar` for the top header layout) and the footer.
4. **Templates** (`templates/`): the layout around a page. Copy one only to change that layout, such as using the Top header layout for every page.

Changes made in the Site Editor are saved in the database and win over all of these files. Use Site Editor → Templates → Reset to go back to the theme's version.
