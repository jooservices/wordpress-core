#!/bin/bash
# Creates the schema used by wp-phpunit; the demo schema is never touched by tests.
set -e
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" -e "CREATE DATABASE IF NOT EXISTS wp_tests CHARACTER SET utf8mb4; GRANT ALL ON wp_tests.* TO '${MARIADB_USER}'@'%'; FLUSH PRIVILEGES;"
