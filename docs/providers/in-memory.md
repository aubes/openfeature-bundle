# InMemory provider

Flags defined statically in configuration. This is the default provider, ideal for local development and tests.

> **Bootstrap only:** this provider is a static key/value store. It ignores the `EvaluationContext` (no targeting, no rollouts). Use it to get started or to write tests, then swap to a real provider in production. See [Providers](index.md#real-providers-recommended-for-production).

## Configuration

```yaml
open_feature:
    # provider is optional, InMemoryProvider is the default
    flags:
        new_checkout: true
        dark_mode: false
        max_items: 10
        theme: dark
        config:
            color: blue
```

## Supported types

A flag value must be resolved with the method matching its YAML type:

| YAML value | Resolved via |
|---|---|
| `true` / `false` | `getBooleanValue()` |
| `0` / `1` | `getBooleanValue()` (as `false` / `true`), `getIntegerValue()` or `getFloatValue()` |
| `42` | `getIntegerValue()` or `getFloatValue()` |
| `1.5` | `getFloatValue()` |
| `"dark"` | `getStringValue()` |
| `{color: blue}` or a list | `getObjectValue()` |

Any other combination returns the default value with `ErrorCode::TYPE_MISMATCH`. No value is cast to string: read a boolean or numeric flag with its own method.
