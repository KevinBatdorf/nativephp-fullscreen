# Changelog

All notable changes to `kevinbatdorf/nativephp-fullscreen` are documented here.

## v0.2.0 — 2026-11-05

- `NATIVEPHP_FULLSCREEN_ON_LAUNCH=true` hides the system bars on Android from
  the app's first frame, before PHP boots, and again on every resume until
  `Fullscreen::exit()` is called. Off by default.

## v0.1.1 — 2026-08-17

- Allow installation alongside nativephp/mobile 4.x (`^3.0 || ^4.0`).

## v0.1.0 — 2026-04-14

Initial release.

- `Fullscreen::enter()`, `Fullscreen::exit()`, `Fullscreen::isActive()` —
  immersive mode on Android and iOS.
- JavaScript library mirroring the three bridge functions.
