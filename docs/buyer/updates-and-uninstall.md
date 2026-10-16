# Updates, backups, and uninstall

## Before an update

1. Back up the database and `wp-content` files, especially uploads and any child theme.
2. Note the current theme and JOOservices Resume plugin versions.
3. Read the release entry in the [changelog](../../themes/jooservices/CHANGELOG.md).

## Update the theme

Use **Appearance → Themes** to upload the new `jooservices-<version>.zip`. If WordPress asks whether to replace the installed theme, keep your backup and confirm the update. Alternatively, use your host's safe update workflow. Do not overwrite a child theme.

Update JOOservices Resume separately from **Plugins** using its release ZIP. Keep the plugin active while the theme updates. Afterward, visit the homepage, one post, the About page, and a missing URL. Clear page and CDN caches if the old design persists.

## Roll back

Install the backed-up theme ZIP and plugin ZIP, then clear caches. If the site is unavailable, use the hosting file manager or SFTP to restore the theme directory from the backup. Restore the database only if the release notes say a data migration changed it or the site content itself is damaged.

## Uninstall

1. Back up the database and files.
2. Deactivate JOOservices Resume if you are removing its company/profile features. Deactivation alone does not remove company content.
3. Switch to another theme under **Appearance → Themes**, then delete JOOservices from that screen if you no longer need it.
4. Delete the Resume plugin only from **Plugins** after confirming you no longer need its company records and custom fields.

Theme and plugin removal behavior can change between releases. Confirm the current version's release notes and retain the backup until the replacement site is verified.
