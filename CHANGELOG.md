# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-04-24

### Changed

- Services implementing `OpenFeature\interfaces\hooks\Hook` are now autoconfigured with the `openfeature.hook` tag. No more manual tagging required in `services.yaml` (opt out with `autoconfigure: false` for per-call hooks such as `RegexpValidatorHook`).
- Services implementing `EvaluationContextProviderInterface` are now autoconfigured via `registerForAutoconfiguration()` in the bundle extension (the previous `#[AutoconfigureTag]` on the interface was not picked up by Symfony and had no effect).

### Upgrade notes

- **Remove redundant hook tags.** If your `services.yaml` has both `_defaults: autoconfigure: true` and `tags: [openfeature.hook]` on a `Hook` service, the hook will now be registered **twice** and fire twice per evaluation. Remove the explicit `tags: [openfeature.hook]` from such services.
- **Remove redundant evaluation context provider tags.** Same applies to `tags: [openfeature.evaluation_context_provider]` on services implementing `EvaluationContextProviderInterface` with autoconfigure on.

## [0.1.1] - 2026-04-18

### Fixed

- Reset of the OpenFeature global `EvaluationContext` between requests now actually fires under FrankenPHP worker mode (and other long-running runtimes). The responsibility has been moved onto `EvaluationContextListener` (which implements `ResetInterface` and is instantiated on every `kernel.request`), so Symfony's `services_resetter` invokes it reliably.

## [0.1.0] - 2026-04-11

Initial release.

[Unreleased]: https://github.com/aubes/openfeature-bundle/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/aubes/openfeature-bundle/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/aubes/openfeature-bundle/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/aubes/openfeature-bundle/releases/tag/v0.1.0
