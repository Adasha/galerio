# Post Slider Block

A production-ready WordPress Gutenberg block that displays the latest posts from **any post type or taxonomy** in a fully configurable slider, with **six transition effects** and complete design control from the block editor sidebar.

---

## Features

| Category | Details |
|---|---|
| **Query** | Post type, post count (1–20), taxonomy filter, term filter (multi-select), order by date / title / random / comment count |
| **Transitions** | Fade · Slide · Zoom · Flip · Cube · Blur — each with configurable duration |
| **Autoplay** | Optional, configurable interval, pause-on-hover |
| **Navigation** | Prev/next arrow buttons + dot indicators (both optional) |
| **Layout** | Slide height, border radius, content position (6 presets), wide/full alignment |
| **Media** | Featured image with selectable size, overlay colour + opacity |
| **Content** | Heading tag (H2–H5), excerpt with word-count trim, meta (date, author), taxonomy terms, "Read More" CTA with editable label |
| **Design** | Accent colour, text colour, background colour — all via CSS custom properties |
| **Accessibility** | ARIA roles, keyboard navigation (←/→), focus-visible outlines, `prefers-reduced-motion` support |
| **Touch** | Swipe left/right on mobile |

---

## Installation

### Without a build step (quickest)

1. Copy the entire `post-slider-block/` folder into `wp-content/plugins/`.
2. Activate **Post Slider Block** in *Plugins → Installed Plugins*.
3. The `build/` directory ships with pre-compiled assets — no Node.js required.

### With a build step (for development / customisation)

```bash
cd wp-content/plugins/post-slider-block
npm install
npm run build   # production build → build/
npm run start   # watch mode for development
```

Requires **Node 18+** and **npm 9+**.

---

## Usage

1. Open any post or page in the block editor.
2. Insert the **Post Slider** block (search for "Post Slider" or find it under *Widgets*).
3. Use the **Inspector sidebar** to configure:
   - **Query panel** — choose post type, count, taxonomy, terms, and sort order.
   - **Transition & Autoplay panel** — pick a transition effect and optionally enable autoplay.
   - **Layout panel** — set slide height, border radius, content position, and toggle navigation/dots.
   - **Featured Image panel** — toggle image display, pick a size, and configure the overlay.
   - **Content panel** — control which content fields appear and how.
   - **Design & Colours panel** — set accent, text, and background colours.

---

## File Structure

```
post-slider-block/
├── post-slider-block.php        # Plugin bootstrap & autoloader
├── package.json                 # npm / @wordpress/scripts config
├── includes/
│   ├── class-block.php          # Block registration + PHP render callback
│   └── class-restapi.php        # REST API: post types, taxonomies, terms
├── src/                         # Source files (compiled to build/)
│   ├── block.json               # Block metadata & attribute schema
│   ├── index.js                 # Editor script (JSX / ES module)
│   ├── view.js                  # Frontend slider engine (vanilla JS)
│   ├── style.scss               # Frontend stylesheet
│   └── editor.scss              # Editor-only stylesheet
└── build/                       # Compiled assets (committed / pre-built)
    ├── block.json
    ├── index.js + index.asset.php
    ├── index.css
    ├── style-index.css
    ├── view.js + view.asset.php
    └── view.js.map (after npm build)
```

---

## REST API Endpoints

All endpoints require `edit_posts` capability (logged-in editors only).

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/wp-json/post-slider-block/v1/post-types` | All public post types |
| `GET` | `/wp-json/post-slider-block/v1/taxonomies?post_type=post` | Taxonomies for a post type |
| `GET` | `/wp-json/post-slider-block/v1/terms?taxonomy=category` | Terms for a taxonomy (supports `&search=`) |

---

## Extending

### Adding a custom transition

1. Add a new entry to `TRANSITION_OPTIONS` in `src/index.js`.
2. Add the corresponding `_t_<name>` method to the `PostSlider` class in `src/view.js` (and `build/view.js`).
3. Add an icon to `.psb-t-icon--<name>` in `src/editor.scss`.
4. Run `npm run build`.

### Registering additional image sizes

The image size selector uses a fixed list. You can filter it server-side or extend the REST endpoint in `class-restapi.php`.

---

## Coding Standards

- PHP: namespaced, PSR-4 autoloading, strict types, no global state.
- JS: ES2020+, no jQuery, vanilla DOM for the frontend script (zero runtime dependencies).
- CSS: BEM methodology, CSS custom properties for all design tokens.
- Security: all output escaped with `esc_html`, `esc_url`, `esc_attr`; `sanitize_hex_color` on colour values; REST endpoints gated behind `edit_posts` capability.

---

## Changelog

### 1.0.0
- Initial release.
- Six transition effects: Fade, Slide, Zoom, Flip, Cube, Blur.
- Full block-editor inspector with query, design, and content controls.
- Server-side render for SEO-friendly output.
- Accessible markup with ARIA roles and keyboard support.
- Touch/swipe support.

---

## License

GPLv2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
