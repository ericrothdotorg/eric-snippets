# Eric Theme
**Contributors:** [ericrothdotorg](https://github.com/ericrothdotorg)

The block theme (FSE) behind [ericroth.org](https://ericroth.org/): templates, template parts, theme.json, base and site CSS.

**Tags:** wordpress, block-theme, fse, website-lab, eric-slider

---

## How the pieces fit

The theme is one of four parts. On its own it gives the layout, the design tokens and the CSS; everything dynamic comes from the snippets.

| Part | Where | What it brings |
|---|---|---|
| **Eric theme** | this folder | Templates, template parts, theme.json, style.css (Part A base, Part B site), per-block CSS in `assets/styles/`, the mobile-menu submenu fix |
| **Code snippets** | [snippets](../snippets) | The shortcodes the templates use, the fixed header bar, Eric Companion CSS, custom post types, quotes, stats and more. Run with [Code Snippets](https://wordpress.org/plugins/code-snippets/) |
| **Eric Slider** | standalone: [eric-slider](https://github.com/ericrothdotorg/eric-slider); on the site its files sit in the theme's `assets/eric-slider/` folder, already included in the release zip | Sliders and carousels (loaded by the snippet *Eric Slider & Animate*) |
| **Display Posts** | [WordPress.org plugin](https://wordpress.org/plugins/display-posts-shortcode/) | The `[display-posts]` listings in the templates |

Shortcodes in the templates and where they come from:

| Shortcode | Snippet |
|---|---|
| `[er_hero]`, `[er_breadcrumbs]`, `[er_cpt_archive]`, `[er_post_footer]`, `[er_related_posts]`, `[er_socials_support]` | `snippets/overall/eric-companion.php` |
| `[er_cached]` | `snippets/overall/display-posts-bill-erickson.php` |
| `[display-posts]` | Display Posts plugin |

---

## Using it on your own site

This is the theme of one particular site, shared as it is. Use it, take it apart, borrow what you like.

- **Theme only:** installs and runs, but the shortcodes above show as plain text and much of the look is missing.
- **The full picture:** add the Display Posts plugin and the snippets, at least *Eric Companion PHP* and *CSS*, *Display Posts – Bill Erickson* and *CPTs & Taxonomies* (the custom post types the archive templates expect).
- **Menu:** the header points to a navigation menu of this site. On yours, pick your own menu for the Navigation block in the Site Editor.

---

## Install

1. Download **eric-x.y.z.zip** from [Releases](https://github.com/ericrothdotorg/website-lab/releases).
2. WordPress: Appearance → Themes → Add New Theme → Upload Theme → choose the zip → Install Now.

Don't use the green **Code → Download ZIP** button for this: it downloads the whole repository, and the theme would end up in the wrong folder.

**Requires:** WordPress 7.0 or later, PHP 8.0 or later.

---

## License

GPL v3 or later. Parts of the code, CSS and templates are derived from [Ollie](https://olliewp.com) by Mike McAlister (GPL v3 or later). Eric Slider: MIT.
