# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-04-18

### Fixed

- Reset of the OpenFeature global `EvaluationContext` between requests now actually fires under FrankenPHP worker mode (and other long-running runtimes). The responsibility has been moved onto `EvaluationContextListener` (which implements `ResetInterface` and is instantiated on every `kernel.request`), so Symfony's `services_resetter` invokes it reliably.

## [0.1.0] - 2026-04-11

Initial release.
