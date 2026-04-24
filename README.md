# OpenFeature Bundle

[![CI](https://github.com/aubes/openfeature-bundle/actions/workflows/php.yml/badge.svg)](https://github.com/aubes/openfeature-bundle/actions/workflows/php.yml)
[![Latest Version](https://img.shields.io/packagist/v/aubes/openfeature-bundle.svg)](https://packagist.org/packages/aubes/openfeature-bundle)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://www.php.net)
[![Symfony Version](https://img.shields.io/badge/symfony-6.4%20%7C%207.x%20%7C%208.x-green.svg)](https://symfony.com)

Feature flags, the Symfony way.

Symfony bundle for the [OpenFeature PHP SDK](https://github.com/open-feature/php-sdk) — the [CNCF standard](https://openfeature.dev) for feature flags.

```php
class CheckoutController
{
    #[FeatureGate('new_checkout')]
    public function checkout(
        #[FeatureFlag('dark_mode')] bool $darkMode,
        #[FeatureFlag('max_items')] int $maxItems,
    ): Response {
        // values resolved from your feature flag provider
    }
}
```

- **`#[FeatureGate]`** blocks access when a flag is off
- **`#[FeatureFlag]`** injects resolved values, fully typed
- **Twig** helpers: `feature('flag')`, `feature_value('flag', default)`
- **Hooks** autoconfigured: implement `Hook` for logging, tracing, validation
- **Evaluation context** autoconfigured: implement `EvaluationContextProviderInterface` to feed targeting attributes
- **Symfony Profiler** panel with evaluated flags, provider info, and context
- **Any provider**: basic built-ins (InMemory, EnvVar, Redis) for quick starts, or plug any real OpenFeature provider (Flagd, ConfigCat, Unleash, LaunchDarkly...)
- **FrankenPHP** worker mode safe out of the box

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x
- `open-feature/sdk` ^2.0 (implements [OpenFeature spec v0.5.1](https://github.com/open-feature/spec/releases/tag/v0.5.1))

## Quick start

```bash
composer require aubes/openfeature-bundle
```

> Register the bundle manually in `config/bundles.php`:
> ```php
> Aubes\OpenFeatureBundle\OpenFeatureBundle::class => ['all' => true],
> ```

```yaml
# config/packages/open_feature.yaml
open_feature:
    flags:
        new_checkout: true
        dark_mode: false
        max_items: 10
```

Use flags in controllers with attributes, in templates with Twig, or inject the `Client` directly:

```php
use OpenFeature\interfaces\flags\Client;

class MyService
{
    public function __construct(private readonly Client $client) {}

    public function checkout(): void
    {
        if ($this->client->getBooleanValue('new_checkout', false)) {
            // new flow
        }
    }
}
```

```twig
{% if feature('new_checkout') %}
    {# new checkout #}
{% endif %}

{{ feature_value('max_items', 10) }}
```

## Providers

The bundle works with any [OpenFeature provider](https://openfeature.dev/ecosystem/?instant_search%5BrefinementList%5D%5Btype%5D%5B0%5D=Provider&instant_search%5BrefinementList%5D%5Btechnology%5D%5B0%5D=PHP).

### Use a real provider in production

For anything beyond a quick demo (user targeting, percentage rollouts, A/B testing, remote flag management, audit log), use a dedicated provider:

- [`aubes/openfeature-flagd-bundle`](https://github.com/aubes/openfeature-flagd-bundle) : self-hosted [Flagd](https://flagd.dev/) backend from the OpenFeature project
- [`aubes/openfeature-configcat-bundle`](https://github.com/aubes/openfeature-configcat-bundle) : [ConfigCat](https://configcat.com/) SaaS
- Any other provider from [open-feature/php-sdk-contrib](https://github.com/open-feature/php-sdk-contrib) (Unleash, LaunchDarkly, Split, GO Feature Flag, ...)

### Built-in providers (bootstrap only)

> **Warning:** The three built-in providers are **simple key/value stores**. They ignore the `EvaluationContext` (no targeting, no rollouts, no A/B testing) and are only meant to get you running without setting up infrastructure. Swap them out as soon as you need real feature flag semantics.

| Provider | Best for | Config key |
|---|---|---|
| InMemoryProvider *(default)* | Local development, tests | `flags` |
| EnvVarProvider | Kill switches via env vars | `provider` |
| RedisProvider | Shared on/off toggles via Redis | `provider` + `redis` |

## Documentation

Full documentation lives in the [`docs/`](docs/index.md) folder:

- [Getting started](docs/getting-started.md)
- [Configuration reference](docs/configuration.md)
- [Providers](docs/providers/index.md) (Flagd, ConfigCat, built-ins for bootstrap)
- [Features](docs/features/index.md) (#[FeatureFlag], #[FeatureGate], Twig, EvaluationContext, Hooks)
- [Profiler & Debug](docs/profiler.md)

## License

MIT. See [LICENSE](LICENSE).
