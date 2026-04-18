# Providers

The bundle works with any [OpenFeature provider](https://openfeature.dev/ecosystem/?instant_search%5BrefinementList%5D%5Btype%5D%5B0%5D=Provider&instant_search%5BrefinementList%5D%5Btechnology%5D%5B0%5D=PHP). Point `provider` to a service that implements the `Provider` interface:

```yaml
open_feature:
    provider: App\OpenFeature\MyProvider
```

## Picking a provider

The whole point of OpenFeature is that you can swap the provider without touching your application code. Pick the one that matches where you actually are:

- **Just exploring the bundle / running tests?** Start with the built-in `InMemoryProvider` below. No infrastructure needed.
- **Running in production?** Use a real provider. The built-ins do **not** support user targeting, percentage rollouts, A/B testing, or remote flag management. They are glorified key/value stores.

## Real providers (recommended for production)

Use a dedicated provider as soon as you need feature flag semantics beyond on/off:

| Provider | Package | Documentation |
|---|---|---|
| Flagd | `aubes/openfeature-flagd-bundle` | [github.com/aubes/openfeature-flagd-bundle](https://github.com/aubes/openfeature-flagd-bundle) |
| ConfigCat | `aubes/openfeature-configcat-bundle` | [github.com/aubes/openfeature-configcat-bundle](https://github.com/aubes/openfeature-configcat-bundle) |
| Unleash | See vendor SDK | [docs.getunleash.io](https://docs.getunleash.io/reference/sdks/php) |
| LaunchDarkly | See vendor SDK | [docs.launchdarkly.com](https://docs.launchdarkly.com/sdk/server-side/php) |

Any other provider from [open-feature/php-sdk-contrib](https://github.com/open-feature/php-sdk-contrib) works too: register it as a Symfony service and reference its service ID in `open_feature.provider`.

## Built-in providers (bootstrap only)

> **Warning:** The three built-in providers are intentionally minimal. They are **static key/value stores**: they ignore the `EvaluationContext` (no targeting, no rollouts, no A/B testing) and do not talk to any management UI. Use them to bootstrap a project or to write tests, then swap them out for a real provider.

| Provider | Best for |
|---|---|
| [InMemoryProvider](in-memory.md) (default) | Local development, tests |
| [EnvVarProvider](env-var.md) | Kill switches via env vars, no extra infra |
| [RedisProvider](redis.md) | Shared on/off toggles when Redis is already in the stack |

## Writing a custom provider

```php
use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\interfaces\provider\Provider;

class MyProvider extends AbstractProvider implements Provider
{
    // implement resolveBooleanValue, resolveStringValue,
    // resolveIntegerValue, resolveFloatValue, resolveObjectValue
}
```

Register it as a Symfony service and reference its service ID in the configuration:

```yaml
open_feature:
    provider: App\OpenFeature\MyProvider
```
