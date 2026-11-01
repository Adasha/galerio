# galerio.net theme

A child of Galerio for <https://galerio.net>. It adds one thing: the **Documentation** page template (a menu column on the left, the page's content beside it), which only galerio.net uses.

- `templates/wp-custom-template-documentation.html`: the template, in Galerio's side menu layout.
- `patterns/template-documentation.php`: its content.
- `theme.json`: lists the template so it can be chosen on a page.

Pages that already use the Documentation template keep using it once this theme is active.
