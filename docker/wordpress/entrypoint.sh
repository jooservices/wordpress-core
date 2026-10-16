#!/bin/sh
# Also listen on the published host port inside the container so that WordPress loopback requests to its own
# site URL (http://127.0.0.1:<WP_PORT>) work (Site Health, self-test, wp-cron).
set -e
if [ -n "${WP_PORT:-}" ] && [ "${WP_PORT}" != "80" ]; then
  echo "Listen ${WP_PORT}" > /etc/apache2/conf-enabled/joowt-port.conf
fi
uploads_dir=/var/www/html/wp-content/uploads
mkdir -p "$uploads_dir"
www_data_owner="$(id -u www-data):$(id -g www-data)"
if [ "$(stat -c '%u:%g' "$uploads_dir")" != "$www_data_owner" ]; then
  chown -R www-data:www-data "$uploads_dir"
fi
exec docker-entrypoint.sh "$@"
