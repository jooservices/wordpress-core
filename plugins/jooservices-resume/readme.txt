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
* **Resume details**: location, employment type, multiple roles within a period, achievements, technologies and owner-defined custom fields. Compensation and equity fields are private by default and can be made public individually.
* **Profile and editorial fields**: author profile fields, topic and category fields, and featured-post controls.
* **Admin tools**: editor boxes with repeaters, company list filters and bulk pin/hide/show actions, plus Settings with a custom-field builder.
* **Import and export**: own JSON, JSON Resume and CSV formats, with a dry run before importing.
* **Theme integration**: `[joo_resume]` shortcode and `joo/resume` block, plus read-only routes under `/wp-json/joo/v1/` and `/wp-json/jooservices-resume/v1/resume` (including `/resume/json-resume`). Resume structured data includes `worksFor`. The theme works without the plugin and hides blocks that need its data.

The stable `joo_company` post type and `_joo_company_*` meta keys are the company data contract. Deactivating the plugin keeps all data. Uninstall also keeps all data by default.

== Installation ==

1. Upload the zip from Plugins, Add New, Upload Plugin, or run `wp plugin install jooservices-resume-0.1.0.zip --activate`.
2. Add entries under Companies, then fill in the profile under Users, Profile.
3. To erase plugin data during uninstall, define `JOO_RESUME_DELETE_DATA_ON_UNINSTALL` as `true` in `wp-config.php` before uninstalling. Otherwise, company entries, profile and editorial fields remain in the database.

== Frequently Asked Questions ==

= Does the plugin change existing company data? =

No. The `joo_company` post type and `_joo_company_*` meta keys remain stable. No migration is required for the former lab-only plugin slug because it has no public installs.

= Can I use it without a JOOservices theme? =

Yes. Company, profile, topic and featured-post data remains stored and available through the read-only REST routes.

= How do I show the resume? =

Use the `[joo_resume]` shortcode or add the JOOservices Resume block (`joo/resume`) in the editor.

= How do I import or export resume data? =

Use the Import / Export tools in the plugin settings. They support the plugin's own JSON format, JSON Resume and CSV. Imports can be checked with a dry run first.

= Are private fields shown publicly? =

No. Compensation and equity fields are private unless you enable their public switch individually. Other owner-defined custom fields can be configured in Settings.

= What happens when I deactivate or uninstall it? =

Deactivation keeps all data. Uninstall keeps all data unless `JOO_RESUME_DELETE_DATA_ON_UNINSTALL` is set to `true`; that opt-in also deletes the plugin's profile, topic and featured-post fields.

== Changelog ==

= 0.1.0 =
Product name and package slug: JOOservices Resume (`jooservices-resume`).
Added company location, employment type, roles within a period, achievements, technologies and owner-defined custom fields, with per-field public switches for private compensation and equity.
Added company editor repeaters, list filters and bulk pin/hide/show actions, plus the Settings custom-field builder.
Added JSON, JSON Resume and CSV import/export with dry-run support.
Added the `[joo_resume]` shortcode, `joo/resume` block, REST resume and JSON Resume endpoints, and `worksFor` structured data.
