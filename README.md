# Eric Snippets

**Contributors:** [ericrothdotorg](https://github.com/ericrothdotorg)

The code snippets behind [ericroth.org](https://ericroth.org/): Everything dynamic on the site. Shortcodes, custom post types, stats, forms, the admin dashboard and more, as self-contained PHP and CSS files.

**Tags:** eric-snippets, wordpress, website-lab, eric-theme, eric-slider, shortcodes, php, custom-post-types

---

**What makes it genuinely stand out**

Most WordPress sites get their features from a stack of plugins, each with its own settings page, database tables and update cycle. Here, almost all of it is plain, readable code: Views and likes, visitor tracking, a contact form, subscriptions, structured data, a language switcher, text-to-speech, a custom admin dashboard. Each snippet does one job and is loaded only where it's needed, so the site stays fast without trading features for speed. The snippets are kept as files, so every change is version-controlled instead of hidden in the database, and the block editor previews the shortcodes as they will appear on the page.

**Who would actually reach for it**

WordPress developers who'd rather write fifty lines than install another plugin, and want to see how real features are built without a framework. It's not a plugin to install and forget: The snippets were written for one site and expect its theme, page IDs and settings. As a library of working examples to read, borrow from or adapt, it fills a gap that tutorials and plugin code rarely do.

---

## How the pieces fit

The snippets are one of four parts. The theme owns layout, design tokens and CSS; the snippets bring everything that stores, counts, sends or decides something. See the [eric-theme → README](https://github.com/ericrothdotorg/eric-theme#how-the-pieces-fit) for which shortcodes the templates use.

---

## What's inside

All snippets are in [snippets](snippets), grouped by where they run:

| Folder | Runs | Examples |
|---|---|---|
| [overall](snippets/overall) | On every page | Eric Companion (hero, breadcrumbs, archives, related posts), custom post types and taxonomies, stats engine, views and likes, visitor tracking, subscriptions, structured data, language switcher, text-to-speech, database maintenance |
| [conditional](snippets/conditional) | Only on the pages that need them | Front-page cover, contact form, quotes, world map of visitors, number counters, accessibility widget, video placeholders |
| [backend](snippets/backend) | In the admin and the block editor | Custom dashboard, admin columns, block usage tracker, live shortcode previews in the editor, core optimizations and security |

Some snippets build on others: The views and likes display reads from the *Stats Engine*, and the world map reads from *Visitor Tracking*.

---

## Using it on your own site

This is the code of one particular site, shared as it is. Use it, take it apart, borrow what you like.

- **With a snippets plugin:** Add a file as a snippet in the plugin of your choice. File-based execution, where the plugin supports it, keeps the code in version control.
- **As a must-use plugin:** Drop the file into `wp-content/mu-plugins/` and add `defined('ABSPATH') || exit;` after `<?php`. Every file carries this note at the top.
- **Adapt before you run:** Function and shortcode names start with `er_`. Page IDs, paths and settings belong to [ericroth.org](https://ericroth.org/) and may be changed for your site.
---

## License

GPL v3 or later. Parts are adapted from Bill Erickson's [Display Posts ↗](https://displayposts.com/) examples.
