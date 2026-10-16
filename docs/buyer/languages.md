# Language setup

## English and Vietnamese

The theme includes English and Vietnamese strings. Set the WordPress default under **Settings → General → Site Language**. For a multilingual site, install a translation plugin listed in the [compatibility table](plugin-compatibility.md), then create translated pages and posts and connect each translation using that plugin's language workflow.

The theme also supports its own language metadata for posts. In the post editor, set the language to `en` or `vi`; when a translation pair is supported by your setup, set **Translation of** on each translated post. The theme can then emit language alternates for the pair.

## Add another language

Use a WordPress translation plugin that manages translated content and locale URLs. Translate theme strings in the `jooservices` text domain with a translation tool such as Loco Translate, and ask the translation provider to review text direction and keyboard order. Do not add translation files by editing the parent theme; keep generated language files in a child theme or in the translation plugin's custom language directory.

## Right-to-left languages

RTL layout is included in 1.0. Select an Arabic or Hebrew locale, then review the Site Editor and the public homepage, post, search, About page, and terminal controls. Translated content is not supplied for every language; the browser and theme direction support does not translate your words.
