# Profiler & Debug

## Symfony Profiler panel

The bundle registers an **OpenFeature panel** in the Symfony Web Debug Toolbar showing:

- Active provider name
- In multi-provider mode: the evaluation strategy, the fallback marker, and the sub-providers in evaluation order
- All flags evaluated during the request (key, type, resolved value, reason, error)
- Global EvaluationContext (targeting key and attributes)

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
 -------------- ------ ------- -----------
  Flag           Type   Value   Attribute
 -------------- ------ ------- -----------
  new_checkout   bool   true    FeatureGate
  dark_mode      bool   false   FeatureFlag
  max_items      int    10      FeatureFlag
 -------------- ------ ------- -----------
```
