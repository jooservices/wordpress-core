=== JOOservices Content ===
Contributors: jooservices
Tags: companies, profile, featured posts, term meta
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companies, owner profile fields, topic fields and featured posts for the JOOservices themes.

== Description ==

* **Companies**: a private post type (`joo_company`) with role, start and end month, URL, logo and one pinned "current" company. Shown by the theme's Companies block and available over REST at `/wp-json/joo/v1/companies`.
* **Profile**: title, short bio, socials and avatar on the user profile (used by the profile card, author box and structured data).
* **Topics and categories**: short description, intro, image and icon fields on categories and tags.
* **Featured posts**: a toggle and an order in the editor and in the posts list, used by the featured slider.

The plugin has no front-end output of its own and no settings screen. Without a JOOservices theme the data is still stored and exposed over REST.

== Installation ==

1. Upload the zip under Plugins, Add New, Upload Plugin, or run `wp plugin install jooservices-content-0.1.0.zip --activate`.
2. Add your companies under Companies, fill your profile under Users, Profile.
3. Deactivating keeps all data. To delete the companies when the plugin is deleted, define `JOO_CONTENT_DELETE_DATA_ON_UNINSTALL` as `true` in `wp-config.php` first.

== Changelog ==

= 0.1.0 =
First release.
