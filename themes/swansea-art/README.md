# swansea.art

swansea.art's theme: a child of Galerio, built from the [Galerio Child Starter](../galerio-child-starter/README.md).

It keeps the look of the site's older Eclecticon child theme:

- **Colours** (`theme.json`): white, near-black text (#252525), red links (#cb3725) with the old hover red (#dd3333), and Eclecticon's greys. The grey for small text is #767676 rather than Eclecticon's #828282, which fails WCAG AA on white.
- **Fonts** (`theme.json`): Montserrat for headings, the menu and the tagline; Lato for text. Montserrat is bundled in `assets/fonts/montserrat/` (SIL Open Font License, `OFL.txt`) with its Latin Extended part, so Welsh letters such as ŵ and ŷ use it too. Lato comes from Galerio.
- **Header** (`patterns/site-header.php`, used by `parts/site-header.html`): the wordmark (Site Logo), the tagline, the main menu, then a Log in link, search and the social links.
- **CSS** (`style.css`): tall exhibition cards on the collections archive, and the helper classes that page content uses (`add-padding`, `remove-padding`, `pull-up`, `no-para-spacing`, `no-bullet`, `section-spacer`, `bottom-text`, `standfirst`), moved from the old site's Additional CSS.

- **Galleries** (`functions.php`): Kadence Advanced Galleries on works, exhibitions, articles and profiles open in Kadence's lightbox unless another was chosen, like Galerio's own galleries. Images linked to pages keep their links. Jetpack Tiled Galleries use Jetpack's own lightbox, its Carousel setting (Jetpack → Settings → Writing → Media).

The front page is the Home page's own content (a Galerio Carousel of the latest exhibitions), shown by Galerio's front page template.
