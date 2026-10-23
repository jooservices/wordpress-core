# Browser compatibility

Supported browser targets are the owner-approved 1.0 baseline:

| Browser | Minimum / release channel | Validation status |
| --- | --- | --- |
| Safari on macOS | 16.4 and newer | Pending release run |
| Google Chrome on desktop | Latest and previous stable | Pending release run |
| Microsoft Edge on desktop | Latest and previous stable | Pending release run |
| Mozilla Firefox on desktop | Current ESR and latest stable | Pending release run |
| Safari on iOS | 16.4 and newer | Pending device/browser validation |
| Chrome on Android | Current stable | Pending device/browser validation |

The repository E2E suite uses Chromium, with optional Firefox and WebKit
projects (`E2E_ENGINES=firefox,webkit`). WebKit is a useful Safari-engine
check, but it does not establish support on every Safari version or on iOS.
Before a release, record exact browser/OS versions and test evidence for each
target. Do not treat viewport emulation as a substitute for the iOS Safari and
Android Chrome checks.

Each engine runs at 390×844 (mobile emulation on WebKit; touch only on
Firefox), 820×1180 and 1440×900. Unsupported-feature fallbacks and the tests
that cover them are listed in [browser-fallbacks.md](browser-fallbacks.md).
