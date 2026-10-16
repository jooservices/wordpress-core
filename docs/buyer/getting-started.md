# Getting started

## Before you install

- WordPress 6.6 or newer and PHP 8.1 or newer.
- A working WordPress administrator account and a current backup if this site already has content.
- The JOOservices theme ZIP and the separate JOOservices Resume plugin ZIP from the ThemeForest release package. The theme still renders posts without the plugin, but profile and company features need it.

## Install and activate

1. In WordPress, open **Appearance → Themes → Add New Theme → Upload Theme** and upload the `jooservices-<version>.zip` file. Select **Install Now**, then **Activate**.
2. Open **Plugins → Add New Plugin → Upload Plugin**. Upload the `jooservices-resume-<version>.zip` file, select **Install Now**, then **Activate Plugin**.
3. Visit the site once. The theme supplies dark and light styles; use the theme toggle in the header to choose a scheme.
4. Open **Appearance → Editor → Navigation**. Create the menu links you need and assign the menu to the theme's primary navigation area. Save the changes.
5. Open **Users → Profile** and add a display name, title, short biography, avatar, and social links. See [Profile and company history](resume-and-company.md).
6. Add a post with a category and featured image. Publish it, then open the homepage and the post to confirm the layout.
7. Add an About page and select the **Full width** template in the page editor. Add company history using the Resume plugin's Companies screen.

## Import demo content

The ThemeForest download includes `Demo-Content/jooservices-demo-content.zip`. Activate the JOOservices theme and JOOservices Resume plugin, upload that ZIP to the server, and use WP-CLI to preview and import it:

```sh
wp joo demo import /path/to/jooservices-demo-content.zip --dry-run
wp joo demo import /path/to/jooservices-demo-content.zip --batch-size=10
wp joo demo diff /path/to/jooservices-demo-content.zip
```

The dry run checks the archive, licence files, package checksums, and content conflicts without changing the site. Import reports progress in batches and skips duplicates on an identical rerun. It adds generated posts, pages, company records, media, menus, sample profile data, and selected site options. It never deletes existing posts, media, terms, users, or menus; a clean WordPress install is recommended. If an item conflicts, resolve it or import into a clean site.

To undo the package import, run `wp joo demo rollback`. The importer removes only records it created and restores changed options when they still have the imported value. If you edit an imported option after import, rollback preserves that later edit.

If the JOOservices WP-CLI commands are unavailable, the package includes WXR for the WordPress Importer plugin or `wp import`. WordPress Playground can import the same WXR with its [`importWxr` Blueprint step](https://developer.wordpress.org/playground/handbook/guides/import-content-with-blueprints/). Follow `Demo-Content/README.md` to upload the media files and update the media URL placeholder. These fallback paths import WordPress content only; apply menus and settings from the included JSON files manually.

The package contains synthetic English and Vietnamese writing, fictional profile and company records, and generated gradient images. `LICENSES.md` and `ATTRIBUTION.md` identify every included text and image and grant reuse under CC BY 4.0 with attribution to “JOOservices Demo Content”. There are no third-party articles, photos, icons, videos, or externally hosted media.

## Screenshots

The guide's screenshots come from the showcase, not hand-edited captures. From a prepared showcase site, regenerate them with:

```sh
make screenshots THEME=jooservices ARGS="--output docs/buyer/assets --views home,about --viewports desktop --schemes dark"
```

The screenshot runner writes `home-desktop-dark.png` and `about-desktop-dark.png`. CI checks the generated references and local links; it does not fabricate or refresh screenshots without a running showcase.

![JOOservices homepage, desktop, dark scheme](assets/home-desktop-dark.png)

The supported browser list is in the [buyer guide release notes](README.md#release-notes-for-buyers).
