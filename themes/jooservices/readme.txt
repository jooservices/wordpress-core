=== JOOservices ===
Contributors: jooservices
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, full-site-editing, block-patterns, translation-ready

A responsive block theme for editorial sites, built on the JOOservices theme core.

== Description ==

JOOservices includes full-site editing templates and patterns, local fonts,
light and dark color schemes, and English and Vietnamese translations. The
theme works without JOOservices Resume. Its company and author profile blocks
appear when that optional plugin is installed and active.

WooCommerce is not supported as a 1.0 product feature.

== Installation ==

1. In WordPress, go to Appearance > Themes > Add New > Upload Theme.
2. Upload the JOOservices theme ZIP and activate the theme.
3. Use Appearance > Editor to adjust templates, colors, typography, and navigation.
4. If you want company history and author profile fields, extract
   `jooservices/plugins/jooservices-resume.zip` from the theme download, then
   upload it under Plugins > Add New > Upload Plugin and activate it.
5. Review the theme documentation for content setup, compatibility, and
   customization guidance.

== Frequently Asked Questions ==

= Is JOOservices Resume required? =

No. The theme works without it; content blocks that need Resume data are hidden
until the plugin is active.

= Does the theme load remote fonts or analytics? =

No. Font files are served locally. The theme stores the color preference in
local browser storage and does not include analytics or tracking pixels.

= What browser versions are supported? =

See docs/browser-compatibility.md for supported browser targets and current
validation status.

== Credits ==

IBM Plex Sans and JetBrains Mono are distributed under the SIL Open Font
License 1.1. Their license files ship under assets/fonts/.

== Changelog ==

= 0.1.0 =

* Initial product package.
