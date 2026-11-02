== Eric ==

Contributors: Eric Roth
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html


== Description ==

Own block theme for ericroth.org.


== Changelog ==

= 1.1.1 =
* Fix: style.css loads after the global styles again (as in 1.0.0), so its rules win over theme.json's :root :where() rules again (e.g. quotes centred). The mobile-submenu CSS is now appended to style.css.

= 1.1.0 =
* Removed base CSS that never takes effect on the site (overridden everywhere, or for block styles and helper classes the site doesn't offer).
* Code and preformatted blocks now use the monospace font (broken font-family value fixed).
* Mobile menu: one submenu fix instead of two (functions.php keeps aria-expanded correct per submenu).
* Removed the inline "hide last separator" style; no page uses a post list.
* Comments cleaned up.

= 1.0.0 =
* Initial release.


== Copyright ==

Eric WordPress Theme, (C) 2026 Eric Roth
Eric is distributed under the terms of the GNU GPL v3 or later.

Parts of the code, CSS and templates are derived from Ollie
(https://olliewp.com), (C) Mike McAlister, GPL v3 or later.
