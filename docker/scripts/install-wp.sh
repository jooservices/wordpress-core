#!/usr/bin/env bash
# Idempotent WordPress install and theme activation. Runs inside the wordpress container as www-data.
# Environment: THEME (slug), WP_SITE_URL, WP_ADMIN_USER, WP_ADMIN_PASSWORD, WP_ADMIN_EMAIL
set -euo pipefail
cd /var/www/html
THEME="${THEME:-jooservices}"
: "${WP_SITE_URL:?}" "${WP_ADMIN_USER:?}" "${WP_ADMIN_PASSWORD:?}" "${WP_ADMIN_EMAIL:?}"

bash /scripts/wait-for-db.sh 120

if [ ! -d "wp-content/themes/${THEME}" ]; then
  echo "ERROR: theme '${THEME}' not found. Available: $(ls wp-content/themes | tr '\n' ' ')" >&2
  exit 1
fi

if wp core is-installed 2>/dev/null; then
  echo "WordPress already installed: $(wp core version)"
else
  wp core install --url="${WP_SITE_URL}" --title="JOOservices Demo" --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" --admin_email="${WP_ADMIN_EMAIL}" --skip-email
fi

wp option update home "${WP_SITE_URL}" >/dev/null
wp option update siteurl "${WP_SITE_URL}" >/dev/null
wp option update blogdescription "Technical notes on AI, engineering and R&D" >/dev/null
wp option update timezone_string "Asia/Ho_Chi_Minh" >/dev/null
wp option update blog_public 1 >/dev/null
wp option update show_on_front posts >/dev/null
wp option update posts_per_page 10 >/dev/null
wp option update default_pingback_flag 0 >/dev/null
wp rewrite structure '/%category%/%postname%/' --hard >/dev/null 2>&1 || wp rewrite structure '/%category%/%postname%/' >/dev/null
wp rewrite flush --hard >/dev/null 2>&1 || true

wp language core install vi >/dev/null 2>&1 || echo "warning: could not download the Vietnamese language pack (offline?)"

if [ -d wp-content/plugins/jooservices-content ]; then
  wp plugin is-active jooservices-content 2>/dev/null || wp plugin activate jooservices-content
fi

wp theme activate "${THEME}" >/dev/null
active="$(wp theme list --status=active --field=name)"
if [ "${active}" != "${THEME}" ]; then
  echo "ERROR: expected active theme '${THEME}' but found '${active}'" >&2
  exit 1
fi
echo "OK: WordPress $(wp core version), PHP $(php -r 'echo PHP_VERSION;'), theme '${active}' active at ${WP_SITE_URL}"
echo "Admin user: ${WP_ADMIN_USER} (password is in .env)"
