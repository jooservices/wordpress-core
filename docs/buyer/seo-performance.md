# SEO and performance

The theme provides canonical URLs, robots policy, social metadata, structured data, breadcrumbs, sitemap integration, self-hosted fonts, responsive image sizes, and optional lazy loading. Settings live under **Appearance → JOOservices**. The generated [settings reference](../reference/settings.md) lists the current options and defaults.

## SEO settings

- Keep the SEO module enabled unless another plugin owns all SEO output.
- Set a useful site title and tagline in **Settings → General**. Add a search title, description, canonical URL, or social image to a post only when it needs a custom value.
- If using Yoast, Rank Math, AIOSEO, or SEOPress, enable one SEO provider at a time and review the rendered page source for duplicate titles, canonical links, and JSON-LD graphs.
- Use `noindex` only for pages you do not want in search. Search results are noindex by design.

## Performance settings

- Leave performance and lazy-loading options at their defaults first. Change one option at a time and check the homepage, a long post, and a page with video or comments.
- Keep image files appropriately sized. Upload a featured image at least 900 pixels wide; WordPress generates theme sizes for cards, covers, thumbnails, and avatars.
- If an optimization plugin combines or delays JavaScript, verify the slider, search dialog, language switcher, theme toggle, terminal dock, and on-demand comments after enabling it.
- Clear the WordPress, host, CDN, and browser caches after changing templates, styles, or optimization rules.

## Measure changes

Use your host's performance tools or Lighthouse on mobile before and after changing settings. Check that the page still works with JavaScript errors disabled in the browser console and that images and fonts load from your own site. No analytics or tracking request is required by the theme.

See [Plugin compatibility](plugin-compatibility.md) before activating multiple SEO or cache plugins together.
