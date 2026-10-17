<?php
// Fixture for make test-migrations: idempotent PHP migration ($wpdb is in scope).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( $wpdb->prepare( "REPLACE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", 'joo_migration_fixture_php', 'ok' ) );
