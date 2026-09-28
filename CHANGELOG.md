# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- The `API` service is now an isolated `OpenFeatureAPI` instance created by the container (SDK 2.3.0 isolated instances) instead of the `OpenFeatureAPI::getInstance()` global singleton. Provider, hooks, and evaluation context are no longer shared with other kernels running in the same PHP process.
- `open-feature/sdk` requirement raised from `^2.2` to `^2.3` (the bundle relies on the public `OpenFeatureAPI` constructor introduced in SDK 2.3.0).
- `EnvVarProvider` and `RedisProvider` now return a `PARSE_ERROR` (and the default value) for raw values that do not match the requested type, instead of silently casting them (`"abc"` as integer used to resolve to `0`, `"banana"` as boolean to `false`).
- `InMemoryProvider` returns a `TYPE_MISMATCH` instead of casting for integers other than `0`/`1` requested as boolean (e.g. `2` or `-1`, previously `true`), non-string values requested as string (e.g. `42`, previously `"42"`), and floats requested as integer (e.g. `1.5`, previously `1`).
- `ResolutionDetailsTrait::toBool()` is replaced by `parseBool()`, `parseInt()`, `parseFloat()`, and `parseObject()`.
- Provider services (`provider`, `providers`) must now declare their class, as Symfony already requires for services built by a factory. A provider created by a factory, or inheriting its class from a `parent` under an id that is not a class name, now fails at compile time without an explicit `class` option instead of skipping validation.

### Fixed

- A provider service whose class does not exist now fails with an explicit "cannot be found" message instead of "must implement Provider", and a provider class whose parent class or interface is missing reports that missing class.
- Providers now receive the application logger when MonologBundle is not installed; previously they got none. A logger already set on a provider service (e.g. a dedicated Monolog channel) is no longer overridden.
- `flags` and `providers`: keys are now kept as declared. Dashes were converted to underscores (a flag declared as `new-checkout` could only be evaluated as `new_checkout`), and an object flag holding a `name` key was renamed after that value. The undocumented list form `flags: [{name: ..., value: ...}]` is no longer supported.
- `RedisProvider` now logs Redis client failures at `error` level. Previously, an unavailable Redis silently resolved every flag to its default value, with nothing in the logs.

### Upgrade notes

- Run `composer update open-feature/sdk` if your lock file pins a version below 2.3.0.
- Code calling `OpenFeatureAPI::getInstance()` directly now gets an instance distinct from the bundle's `API` service (different provider, hooks, and evaluation context). Inject the `API` or `Client` service instead.
- **Check your raw flag values.** `EnvVarProvider` and `RedisProvider` no longer cast unparsable values: a boolean flag set to anything other than `true`/`false`/`1`/`0`/`yes`/`no`/`on`/`off`/empty (e.g. `FEATURE_X=enabled`, previously `false`) or a numeric flag with a non-numeric value (for integers, decimal or exponent notation such as `10.0` or `1e3` too; leading zeros such as `08` are accepted) now resolves to the default value with a `PARSE_ERROR`. `InMemoryProvider` flags declared with a mismatching type (e.g. `max_items: 1.5` read as integer, `label: 42` read as string) now resolve to the default value with a `TYPE_MISMATCH`, like with typed providers such as flagd. In Twig, `{{ feature_value('max_items') }}` without a default reads the flag as a string and now renders `''`: pass a typed default (`feature_value('max_items', 10)`). These errors are not logged by the SDK: check the `open_feature` profiler panel in dev (error column), or register a hook that logs `ResolutionDetails::getError()` in `after()`.
- **Provider services created by a factory, or defined through `parent` under an id that is not a class name,** must set the `class` option (e.g. `class: App\FeatureFlag\MyProvider` next to `factory:`; `OpenFeature\interfaces\provider\Provider` is accepted when the concrete class is unknown), otherwise the container fails to compile with `Class "" used for OpenFeature provider service "..." cannot be found`.
- Custom providers using `ResolutionDetailsTrait::toBool()` must switch to `parseBool($flagKey, $raw, $defaultValue)`, which returns a `ResolutionDetails` instead of a `bool`.

## [0.3.0] - 2026-06-15

### Added

- Multi-provider support through the SDK `MultiProvider`. The new `providers` configuration key declares several providers (map of provider name => service ID, evaluated in declaration order, mutually exclusive with `provider`), and the `strategy` key selects the evaluation strategy: `first_match` (default), `first_successful`, or `comparison` with a required `fallback` provider name. Configuration is validated at compile time (exclusivity, fallback consistency, case-insensitive provider name uniqueness).
- The profiler panel and toolbar now display the evaluation strategy and the sub-providers in evaluation order (with a `fallback` badge for the `comparison` strategy) when multi-provider is configured.

### Changed

- `open-feature/sdk` requirement raised from `^2.0` to `^2.2` (the bundle relies on the `MultiProvider` classes introduced in SDK 2.2.0).
- The `provider` configuration node now defaults to `null` instead of `InMemoryProvider`. When neither `provider` nor `providers` is set, the bundle still falls back to the built-in `InMemoryProvider`: behavior is unchanged, only the output of `config:dump-reference open_feature` differs.

### Upgrade notes

- Run `composer update open-feature/sdk` if your lock file pins a version below 2.2.0.

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

[Unreleased]: https://github.com/aubes/openfeature-bundle/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/aubes/openfeature-bundle/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/aubes/openfeature-bundle/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/aubes/openfeature-bundle/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/aubes/openfeature-bundle/releases/tag/v0.1.0
