-- Fixture for make test-migrations: idempotent SQL with {prefix}.
DELETE FROM {prefix}options WHERE option_name = 'joo_migration_fixture_sql';
INSERT INTO {prefix}options (option_name, option_value, autoload) VALUES ('joo_migration_fixture_sql', 'ok', 'no');
