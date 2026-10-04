# Profiler & Debug

## Symfony Profiler panel

The bundle registers an **OpenFeature panel** in the Symfony Web Debug Toolbar showing:

- Active provider name
- In multi-provider mode: the evaluation strategy, the fallback marker, and the sub-providers in evaluation order
- All flags evaluated during the request (key, type, resolved value, reason, error)
- Global EvaluationContext (targeting key and attributes)
- Context providers that contributed to it. They only run on the first flag evaluation, so the panel reports that they did not run when the request evaluated no flag
- Registered hooks (the profiler's own hook is hidden)

The profiler panel is automatically enabled in `debug` mode. No configuration needed.

## Debug command

List all feature flags detected in your controllers and their current values:

```bash
php bin/console debug:feature-flags
```

The command scans routes for `#[FeatureFlag]` and `#[FeatureGate]` attributes and evaluates them against the active provider.

> **Note:** Flags are evaluated without HTTP request context (no authenticated user, no request attributes). The `EvaluationContext` will be empty.

Example output:

```
Provider
--------

 MultiProvider

Feature flags
-------------

 -------------- ------------- -------- ------- ----------------------------------------------
  Flag           Attribute     Type     Value   Used in
 -------------- ------------- -------- ------- ----------------------------------------------
  dark_mode      FeatureGate   bool     false   App\Controller\DemoController::darkModeOnly
  max_items      FeatureFlag   int      10      App\Controller\DemoController::valueResolver
  new_checkout   FeatureFlag   bool     true    App\Controller\DemoController::valueResolver
 -------------- ------------- -------- ------- ----------------------------------------------

Evaluation context
------------------

 (none)

Hooks
-----

 App\OpenFeature\LoggerHook
```
