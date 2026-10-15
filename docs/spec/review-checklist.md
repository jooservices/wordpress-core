# Spec review checklist

- [ ] API minimal: every `@api` symbol is needed by at least one theme template or block
- [ ] Naming: new names are in `naming-registry.json` and pass `node tools/check-registry.mjs`
- [ ] Defaults are safe and fast (see settings descriptor)
- [ ] Performance impact measured or budgeted
- [ ] Security: output escaped, capability and nonce checks, public REST read-only and published-only
- [ ] Accessibility: keyboard and screen-reader behaviour stated
- [ ] i18n: strings translatable (`joo-core`), JS strings via runtime config
- [ ] Works with the second-theme manifests without core changes
