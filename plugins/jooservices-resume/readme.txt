=== JOOservices Resume ===
Contributors: jooservices
Tags: resume, companies, profile, featured posts, topics
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Company history, author profiles, topic fields and featured posts for JOOservices themes.

== Description ==

JOOservices Resume keeps the editorial data used by JOOservices themes together in one plugin.

* **Company history**: a private `joo_company` post type with role, dates, website, logo and a pinned current company.
* **Profile and editorial fields**: author profile fields, topic and category fields, and featured-post controls.
* **Theme integration**: public read-only data routes under `/wp-json/joo/v1/`; the theme works without the plugin and hides blocks that need its data.

The stable `joo_company` post type and `_joo_company_*` meta keys are the company data contract. Deactivating the plugin keeps all data. Uninstall also keeps all data by default.

== Installation ==

1. Upload the zip from Plugins, Add New, Upload Plugin, or run `wp plugin install jooservices-resume-0.1.0.zip --activate`.
2. Add entries under Companies, then fill in the profile under Users, Profile.
3. To erase plugin data during uninstall, define `JOO_RESUME_DELETE_DATA_ON_UNINSTALL` as `true` in `wp-config.php` before uninstalling. Otherwise, company entries, profile and editorial fields remain in the database.

== Frequently Asked Questions ==

= Does the plugin change existing company data? =

No. The `joo_company` post type and `_joo_company_*` meta keys remain stable. No migration is required for the former lab-only plugin slug because it has no public installs.

= Can I use it without a JOOservices theme? =

Yes. Company, profile, topic and featured-post data remains stored and available through the read-only REST routes. The plugin has no front-end output or settings screen of its own.

= What happens when I deactivate or uninstall it? =

Deactivation keeps all data. Uninstall keeps all data unless `JOO_RESUME_DELETE_DATA_ON_UNINSTALL` is set to `true`; that opt-in also deletes the plugin's profile, topic and featured-post fields.

== Changelog ==

= 0.1.0 =
Product name and package slug: JOOservices Resume (`jooservices-resume`).
