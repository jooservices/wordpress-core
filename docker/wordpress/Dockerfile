# WP_IMAGE_TAG examples: php8.3-apache (latest WordPress) or 6.6-php8.3-apache (minimum supported)
ARG WP_IMAGE_TAG=php8.3-apache
FROM wordpress:${WP_IMAGE_TAG}

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libzip-dev libicu-dev libmagickwand-dev ghostscript unzip git less default-mysql-client; \
    for ext in intl zip exif; do php -m | grep -qix "$ext" || docker-php-ext-install "$ext"; done; \
    php -m | grep -qix imagick || { pecl install imagick && docker-php-ext-enable imagick; }; \
    php -m | grep -qix pcov || { pecl install pcov && docker-php-ext-enable pcov; }; \
    rm -rf /var/lib/apt/lists/*; \
    curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp; \
    chmod +x /usr/local/bin/wp; \
    wp --info --allow-root >/dev/null

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN mkdir -p /var/log/php && chown www-data:www-data /var/log/php

COPY php.ini /usr/local/etc/php/conf.d/zz-joowt.ini
