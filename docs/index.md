# OpenFeature Bundle

Feature flags, the Symfony way.

Symfony bundle for the [OpenFeature PHP SDK](https://github.com/open-feature/php-sdk), the [CNCF standard](https://openfeature.dev) for feature flags.

- [Get started](getting-started.md)
- [View on GitHub](https://github.com/aubes/openfeature-bundle)

---

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

## Highlights

- **`#[FeatureGate]`** blocks access when a flag is off
- **`#[FeatureFlag]`** injects resolved values, fully typed
- **Twig** helpers: `feature('flag')`, `feature_value('flag', default)`
- **Symfony Profiler** panel with evaluated flags, provider info, and context
- **Any provider**: basic built-ins (InMemory, EnvVar, Redis) for quick starts, or plug any real OpenFeature provider (Flagd, ConfigCat, Unleash, LaunchDarkly...)
- **FrankenPHP** worker mode safe out of the box

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x
- `open-feature/sdk` ^2.0

## Documentation

- [Getting started](getting-started.md)
- [Configuration](configuration.md)
- [Providers](providers/index.md)
- [Features](features/index.md)
- [Profiler & Debug](profiler.md)
