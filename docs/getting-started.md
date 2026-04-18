# Getting started

This guide assumes you have already installed and registered the bundle (see the [README Quick start](../README.md#quick-start)) and added a minimal `open_feature.yaml` with a few flags.

## Your first controller

Two PHP attributes cover most use cases: `#[FeatureGate]` to guard access, `#[FeatureFlag]` to inject resolved values.

```php
use Aubes\OpenFeatureBundle\Attribute\FeatureFlag;
use Aubes\OpenFeatureBundle\Attribute\FeatureGate;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController
{
    #[FeatureGate('new_checkout')]
    public function checkout(
        #[FeatureFlag('dark_mode')] bool $darkMode,
        #[FeatureFlag('max_items')] int $maxItems,
    ): Response {
        // $darkMode and $maxItems are resolved from the active provider
    }
}
```

What happens here:

- `#[FeatureGate('new_checkout')]` : the method is unreachable when `new_checkout` evaluates to `false`. A 403 is thrown (configurable, see [Configuration](configuration.md)).
- `#[FeatureFlag('dark_mode')] bool $darkMode` : the flag is resolved as a boolean based on the parameter type hint.
- `#[FeatureFlag('max_items')] int $maxItems` : same, resolved as an integer via `getIntegerValue()`.

Type dispatch is automatic for `bool`, `string`, `int`, `float`, and `array`. See [`#[FeatureFlag]`](features/feature-flag.md) for the full table.

## Next steps

- [Configuration reference](configuration.md) for the full config tree
- [Providers](providers/index.md) to connect a real feature flag backend (Flagd, ConfigCat, Unleash...)
- [Features](features/index.md) for all integrations: attributes, Twig, EvaluationContext, Hooks
- [Profiler & Debug](profiler.md) to inspect evaluated flags during development
