# Customize the design

## Choose a style

Open **Appearance → Editor → Styles → Browse styles** and select **Dark** or **Light**, then save. Visitors can also use the header theme toggle. Changes in the Site Editor are saved as WordPress styles and remain separate from the theme files.

## Edit global styles

In **Appearance → Editor → Styles**, change the palette, typography, spacing, and block defaults. Preview both colour schemes and phone layouts before saving. The bundled variations are in `themes/jooservices/styles/`; a new named variation can be added by a child theme or a developer release.

Use **Appearance → Editor → Patterns** to insert and adapt patterns. Use **Templates** to edit the homepage, posts, archives, and pages. Use **Template Parts** to edit shared header, footer, and other regions. Keep edits in the Site Editor or a child theme so a theme update does not replace custom files.

## Site identity and navigation

Edit the site title under **Settings → General**. Edit the Site Logo and Navigation blocks in the header template part under **Appearance → Editor**. Social profile details come from **Users → Profile**; footer text and links live in the footer template part.

## Show or hide features

Open **Appearance → JOOservices** to review SEO, performance, lazy-loading, content, language, and terminal options. Labels can vary by release; the authoritative keys, defaults, and groups are in the generated [settings reference](../reference/settings.md).

## Template overrides and child themes

For a small template change, make a child theme and copy the matching template or part into the same relative path. For example, a child override of `templates/single.html` belongs at `jooservices-child/templates/single.html`. Do not edit the parent theme or `packages/theme-core` directly. See the [developer guide](developer-guide.md) for a minimal child theme and supported extension points.
