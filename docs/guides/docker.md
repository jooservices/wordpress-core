# Docker environment

Isolated from the shared Workspaces stack: compose project `jooservices-wp-themes`, containers and volumes labelled `com.joowt.project=<project>`, ports published on `127.0.0.1` only. The database is not published.

| Service | URL | Notes |
|---|---|---|
| WordPress | http://127.0.0.1:8080 | PHP 8.3, WP-CLI (`wp`), imagick, intl, zip, exif, pcov (disabled by default) |
| Mailpit | http://127.0.0.1:8025 | catches every `wp_mail` |
| MariaDB 11 | internal | schemas `wordpress` (demo) and `wp_tests` (integration tests) |

## Commands
```bash
make init-env              # .env with random secrets (never committed, mode 600)
make up THEME=jooservices  # build, start, install WordPress, activate the theme
make wp ARGS="core version"
make shell                 # bash in the container
make logs SERVICE=wordpress
make down                  # stop, keep data
make reset CONFIRM=yes     # remove ONLY this project's containers and volumes
```

## WordPress versions
`WP_VERSION=latest` (default) or `WP_VERSION=6.6` (minimum supported). The WordPress files live in a named volume, so switching versions on an existing stack requires `make reset CONFIRM=yes`, or run a second isolated stack:

```bash
JOOWT_PROJECT=joowt-wp66 WP_VERSION=6.6 WP_PORT=8180 MAIL_PORT=8125 \
  WP_SITE_URL=http://127.0.0.1:8180 THEME=starter make up
JOOWT_PROJECT=joowt-wp66 make reset CONFIRM=yes   # removes only that project
```

## Verified
Latest: WordPress 7.1.3 / PHP 8.3.35. Minimum: WordPress 6.6.2 / PHP 8.3.13 (see the evidence in Jira JOOWT-11).

## Troubleshooting
- Port already in use: `make up WP_PORT=8081 WP_SITE_URL=http://127.0.0.1:8081`.
- Theme not found: `THEME` must match a folder in `themes/`.
- Plugin/WP-CLI downloads need network access to wordpress.org and GitHub.
