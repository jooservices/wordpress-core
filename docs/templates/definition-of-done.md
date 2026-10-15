# Definition of done checklist

A theme is done only when every box is ticked, with the evidence linked in the release report.

- [ ] Developed to the approved design; deviations listed in `design/README.md`
- [ ] `make lint` and `make analyse` pass
- [ ] Unit and integration tests pass on the latest and the minimum WordPress
- [ ] `make demo THEME=<slug>` installs WordPress and the theme and the demo shows every view
- [ ] `make health` passes
- [ ] Playwright passes at phone, tablet and desktop widths in Docker; WebKit also run
- [ ] axe finds no serious or critical violation; targets are at least 24 px
- [ ] `make perf` within `budgets.json`
- [ ] `make test-matrix` has no failure; skipped plugins are listed with a reason
- [ ] `node tools/theme-check.mjs` and `node tools/release.mjs` pass
- [ ] `make package` zip installs on a clean site
- [ ] Core untouched by the theme commits, or each core change has its own commit and test
- [ ] Jira updated and the evidence report written
