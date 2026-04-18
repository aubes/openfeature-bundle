# EnvVar provider

Resolves flags from environment variables. Fits simple kill switches when you don't want to introduce a dedicated feature flag backend.

> **Bootstrap only:** this provider is a static key/value store. It ignores the `EvaluationContext` (no targeting, no rollouts, no A/B testing) and flag changes require a redeploy (or at least a process reload). Use a real provider like Flagd or ConfigCat as soon as you need runtime-driven flags. See [Providers](index.md#real-providers-recommended-for-production).

## Configuration

```yaml
open_feature:
    provider: Aubes\OpenFeatureBundle\Provider\EnvVarProvider
```

## Environment variables

Flag keys are uppercased and prefixed with `FEATURE_`:

| Flag key | Environment variable |
|---|---|
| `new_checkout` | `FEATURE_NEW_CHECKOUT` |
| `dark_mode` | `FEATURE_DARK_MODE` |
| `max-items` | `FEATURE_MAX_ITEMS` |
| `config.theme` | `FEATURE_CONFIG_THEME` |

Hyphens and dots are replaced by underscores. The prefix is configurable via the constructor.

## Values

```bash
FEATURE_NEW_CHECKOUT=true
FEATURE_DARK_MODE=false
FEATURE_MAX_ITEMS=10
FEATURE_CONFIG='{"color":"blue"}'
```

Boolean truthy values: `true`, `1`, `yes`, `on`.

Object values must be JSON-encoded. Invalid JSON returns the default value with `ErrorCode::PARSE_ERROR`.

If the environment variable is not set, the provider returns the default value with `ErrorCode::FLAG_NOT_FOUND`.
