# Core 0.1.0 integration test report

- Date: 2026-10-21
- Repository: `wordpress-core`
- Branch: `JOOWT-233-core-r1-3-integration-suite-on-wordpress`
- Source commit tested: `65b1370e196187aaa71d8ba225f9192bd1ef260e` (`chore(core): Record verified integration rerun after QA feedback (JOOWT-233)`; report-only changes follow)
- Runtime: WSL shared Workspaces PHP runtime, PHP 8.3.30, PHPUnit 9.6.38
- Theme: `starter`
- Locking: the 6.6 and latest runs were sequential; each `make test-integration` target acquired the repository's shared per-host WordPress test lock.
- Result: **Pass on both requested endpoints**

## Commands and results

`WP_VERSION=6.6` resolved to WordPress 6.6.10. `WP_VERSION=latest` resolved to WordPress 7.1.3 on the run date. Both commands ran the five integration suites below.

| Command | WordPress | PHPUnit suite | Tests | Assertions | Skipped | Failures | Result |
| --- | --- | --- | ---: | ---: | ---: | ---: | --- |
| `make test-integration THEME=starter WP_VERSION=6.6` | 6.6.10 | `kit-integration` | 8 | 26 | 0 | 0 | Pass |
| same | 6.6.10 | `core-integration` | 341 | 1,824 | 0 | 0 | Pass |
| same | 6.6.10 | `plugin-integration` | 43 | 392 | 0 | 0 | Pass |
| same | 6.6.10 | `theme-integration` | 22 | 225 | 15 | 0 | Pass |
| same | 6.6.10 | `portfolio-fixture-integration` | 4 | 45 | 0 | 0 | Pass |
| **Minimum total** | **6.6.10** | **All suites** | **418** | **2,512** | **15** | **0** | **Pass** |
| `make test-integration THEME=starter WP_VERSION=latest` | 7.1.3 | `kit-integration` | 8 | 26 | 0 | 0 | Pass |
| same | 7.1.3 | `core-integration` | 341 | 1,824 | 0 | 0 | Pass |
| same | 7.1.3 | `plugin-integration` | 43 | 392 | 0 | 0 | Pass |
| same | 7.1.3 | `theme-integration` | 22 | 225 | 15 | 0 | Pass |
| same | 7.1.3 | `portfolio-fixture-integration` | 4 | 45 | 0 | 0 | Pass |
| **Latest total** | **7.1.3** | **All suites** | **418** | **2,512** | **15** | **0** | **Pass** |

The `theme-integration` suite includes 7 starter tests; all 7 passed on both versions. Its 15 skipped tests are jooservices-only cases listed below. Across all five suites, 403 tests passed and 15 were skipped for each WordPress version.

## Skipped tests and reasons

The same 15 tests were skipped on WordPress 6.6.10 and 7.1.3. The source gives an explicit reason for every skipped case.

`JooservicesThemeTest` (14 tests): each case was skipped in `set_up()` because the selected theme is `starter`. The source reason is: “Run with JOO_TEST_THEME=jooservices (make test-integration THEME=jooservices TEST_THEME=jooservices).”

- `JooservicesThemeTest::test_the_manifest_is_valid_and_every_module_booted`
- `JooservicesThemeTest::test_theme_php_stays_within_the_line_budget`
- `JooservicesThemeTest::test_image_sizes_come_from_the_manifest`
- `JooservicesThemeTest::test_menu_locations_are_registered`
- `JooservicesThemeTest::test_the_theme_style_is_registered_after_the_global_styles`
- `JooservicesThemeTest::test_templates_and_parts_only_use_registered_blocks`
- `JooservicesThemeTest::test_every_template_part_listed_in_theme_json_exists`
- `JooservicesThemeTest::test_header_and_footer_keep_buyer_content_in_editable_site_editor_blocks`
- `JooservicesThemeTest::test_the_style_variations_define_the_same_palette_slugs`
- `JooservicesThemeTest::test_all_five_style_variations_are_available_to_the_editor_and_runtime`
- `JooservicesThemeTest::test_buyer_patterns_are_registered_in_the_joo_services_editor_category`
- `JooservicesThemeTest::test_theme_manifest_preserves_existing_rendered_block_snapshot_values`
- `JooservicesThemeTest::test_buyer_options_change_frontend_output`
- `JooservicesThemeTest::test_sample_child_theme_overrides_a_template_part_and_block_template`

`ReleaseUpgradeTest::test_update_from_previous_release_keeps_theme_settings_and_company_data` was skipped because its test setup says: “Run with JOO_TEST_THEME=jooservices to verify the release upgrade path.”

The WordPress test library reports that its `ajax`, `ms-files`, and `external-http` groups are not run unless explicitly selected with `--group`; these are not skipped cases in the five repository suites above.

## Findings

- The portfolio fixture runs in its own PHPUnit process so its manifest and block registry are isolated from the selected live theme.
- `site-bootstrap.sh` resolves a stable version for `6.6` or `latest`, then installs it when it differs from the current site version. The two runs confirmed 6.6.10 and 7.1.3 respectively.
- `make lint` was run on the same rebased worktree after refreshing this report.
