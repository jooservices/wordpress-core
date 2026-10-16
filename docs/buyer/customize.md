# Customize the design

## Choose a colour scheme

Open **Appearance → Editor → Styles → Browse styles** and choose **Dark**, **Light**, **Ocean**, **Forest**, or **Paper**. Each variation is available in the Site Editor and uses the same named palette tokens. Visitors can use the header colour-theme control to cycle through all five schemes; the choice is stored in their browser before the next page paint. Before a visitor chooses, the theme follows the device light/dark preference when the active theme supports it.

The theme variations are the JSON files in `themes/jooservices/styles/`. Palette values and the runtime CSS are checked against `themes/jooservices/design/tokens.json`; contrast tests cover every shipped palette.

## Logo and site title

Open **Appearance → Editor → Template Parts → Header**. Set a **Site Logo** to use an image logo; the text title hides while a logo image is present. Remove the logo to return to the site title. Select the **Site Title** block to change its typography and alignment. Both blocks use the normal WordPress Site Editor controls.

## Profile and social links

Set the owner name, title, bio, avatar, and social URLs under **Users → Profile** in the JOOservices Resume profile fields. The same social URL list feeds the sidebar profile card, the footer Connect column, and the owner's `Person.sameAs` structured data. Edit the labels and URLs once in the profile; do not maintain a second social menu in the footer.

## Footer and patterns

Edit **Appearance → Editor → Template Parts → Footer** to change the footer text, navigation, and number of columns. Add or remove the column groups in the Site Editor. The Connect column displays the owner profile's social links.

Open **Appearance → Editor → Patterns → JOOservices** for the Technical hero, About and expertise, Contact and availability, two 404 layouts, and Newsletter placeholder patterns. The newsletter pattern is static: it makes no external requests and collects no email addresses. Add a form block from a form plugin when you want a working signup form.

## Buyer options

Open **Appearance → JOOservices** to set the options below. Each setting takes effect on the next page load.

| Option | Where to change it | Effect |
| --- | --- | --- |
| Visitor colour-theme control | General | Shows or hides the header control that cycles through the available schemes. |
| Show the rail | General | Shows or hides the complete sidebar rail. |
| Show profile, companies, categories, top topics, and tags | Content | Controls each matching rail block independently. |
| Show the featured slider | General | Shows or hides the slider block on the home and archive templates. |
| Number of featured posts | Select the Featured Slider block in the Site Editor | Set **Posts** from 1 to 12. This is a block attribute stored with the template. |
| Card density | Appearance | Choose Comfortable or Compact spacing for post cards. |
| Date format | Appearance | Use the site-wide WordPress format or choose `F j, Y`, `Y-m-d`, or `d/m/Y`. This applies to post dates, including slider dates. |
| Back to top | General | Shows or hides the fixed return-to-header link. |
| Terminal dock and default open state | Terminal | Use **Enable terminal** to show or hide the dock; set **Open by default** separately. |

Logo and site title treatment, footer text and columns, and header/footer template content live in the Site Editor. Social URLs live in the owner profile. The remaining options are in the JOOservices settings page. See the generated [settings reference](../reference/settings.md) for keys, defaults, and storage behavior.

The General tab also includes module switches for advanced site setup. The **Terminal module** switch controls whether the core module is available; **Enable terminal** in the Terminal tab is the buyer-facing dock option.

## Edit global styles and templates

In **Appearance → Editor → Styles**, change colors, typography, spacing, and block defaults. In **Templates**, edit the homepage, posts, archives, and pages. In **Template Parts**, edit the shared header, footer, and rail. Changes saved in the Site Editor stay separate from the theme files and survive theme updates.

## Template overrides and child themes

For template files, put the matching relative path in a child theme, such as `templates/single.html` or `parts/footer.html`. For a dynamic block template, add `parts/joo/{slug}.php`; for example, `parts/joo/profile-card.php` overrides the parent profile-card renderer. The [developer guide](developer-guide.md) shows the child theme structure and the supported override order.
