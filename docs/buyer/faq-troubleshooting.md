# FAQ and troubleshooting

## The left rail is blank

- Confirm JOOservices Resume is installed and active if you expect profile or company information.
- Fill in the public profile under **Users → Profile** and publish at least one company.
- Check that the current page template includes the rail. A full-width or canvas page can intentionally omit it.
- Clear the page cache and reload in a private browser window.

## Images are missing or look blurry

- Check **Media → Library** to confirm the file uploaded successfully.
- Use a featured image at least 900 pixels wide. Set a featured image on the post and verify it is publicly accessible.
- If the image was uploaded before installing the theme, regenerate image sizes with your host's WordPress tools or ask the host administrator to run `wp media regenerate`.
- Temporarily disable image optimization and CDN rewriting to find out whether the original image URL works.

## A page shows a 404

- Go to **Settings → Permalinks** and select **Save Changes** once to refresh rewrite rules.
- Confirm the page or post is published and its slug is correct.
- If the URL changed, add a redirect with your host or redirect plugin.
- Check for a cache or security plugin rule returning a cached 404.

## My changes do not appear

- Save the Site Editor change and confirm you edited the active theme.
- Purge the WordPress plugin cache, host cache, and CDN cache, then hard-reload the browser.
- Test with optimization plugins disabled temporarily. Re-enable them one at a time after identifying a conflict.

## The slider, search, or terminal does not respond

- Update WordPress and the theme, then open the browser console and note JavaScript errors.
- Disable script minification or delay in the cache plugin and retest.
- Confirm the relevant feature is enabled in **Appearance → JOOservices**.

## Where are company settings?

Install and activate JOOservices Resume, then use the **Companies** screen and **Users → Profile**. See [Profile and company history](resume-and-company.md).

## Where do I ask for help?

Include the theme and plugin versions, WordPress version, browser and device, affected page URL, exact steps, and any console error. Remove passwords, private tokens, and personal visitor data from screenshots and logs.
