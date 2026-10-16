# ThemeForest release checklist — JOOservices 0.1.0 candidate

Status: engineering release-tool candidate; not yet submitted to ThemeForest. Rebuild from the final 1.0 release commit after the parallel product-feature work is merged.

| Check | Status | Evidence / follow-up |
|---|---|---|
| Theme and Resume versions use strict SemVer | Pass | Canonical values in `themes/jooservices/style.css` and `plugins/jooservices-resume/jooservices-resume.php`; mirrors checked by `make release`. |
| WordPress and PHP minimums are declared | Pass | WordPress 6.6+, PHP 8.1+ in both products. |
| `Tested up to` follows the WordPress release gates | Pass | WordPress 7.1 in the theme header and plugin readme. Recheck if gates move before submission. |
| GPL-compatible license and asset notices are present | Pass | ThemeForest package includes GPL-2.0-or-later plus IBM Plex Sans and JetBrains Mono OFL 1.1 notices. |
| Theme zip installs by itself | Pending | Verify the generated `jooservices-0.1.0.zip` after `make release`. |
| Resume plugin is a separate installable zip | Pending | Verify the generated Resume-only package after `make release`; the later MCP plugin must remain excluded. |
| Demo content is suitable for sale | Pass | Seeded synthetic content, fictionalized profile and company records, locally generated gradient images, no live-site export. |
| Final 1.0 feature set, including RTL, is in the release commit | Pending | This worktree is based on develop and does not include the parallel RTL implementation. Build the submission candidate from the final 1.0 release commit. |
| Primary preview and screenshot are included | Pass | `Preview/01_jooservices.png` is 590×300; full theme screenshot is 1200×900. Recheck Envato dimensions at submission. |
| Buyer documentation is included in English | Pass | `Documentation/README.html` and Markdown guide, compatibility, migration, deployment, and demo-tour files. |
| Item description and changelog are included | Pass | `Item-Description.txt` and `CHANGELOG.md` are in the ThemeForest download. |
| Public live-preview URL is approved and listed | Pending | Add the final licence-clean showcase URL to the item description before submission. |
| Checksums and dependency list are generated | Pending | Verify the generated root `SHA256SUMS`, package checksum manifest, and `DEPENDENCIES.md`. |
| Two full builds are byte-identical | Pending | Verify `make release` compares the theme, plugin, demo, main download, and preview archives and writes `EVIDENCE.md`. |
| N-1 to N preserves settings and company data | Pending | Integration test `ReleaseUpgradeTest::test_update_from_previous_release_keeps_theme_settings_and_company_data` awaits the shared WordPress test lock. |
| Marketplace update and licence delivery are decided | Pass | ThemeForest/Envato Market is the only update channel; no JOOservices endpoint or licence lock. |
| Rollback and hotfix process is documented | Pass | `docs/release-policy.md`; code rollback keeps the existing WordPress database. |
| Full CI gates and marketplace review | Pending | CI is kept ready in `.github/workflows/ci.yml`; this repository has no Git remote. Marketplace title/image review and account acceptance happen at submission. |
