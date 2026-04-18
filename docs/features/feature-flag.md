# `#[FeatureFlag]` : value injection

Inject resolved flag values directly into controller parameters.

## Usage

```php
use Aubes\OpenFeatureBundle\Attribute\FeatureFlag;

public function index(
    #[FeatureFlag('dark_mode')] bool $darkMode,
    #[FeatureFlag('max_items')] int $maxItems,
    #[FeatureFlag('theme')] string $theme,
    #[FeatureFlag('ratio')] float $ratio,
    #[FeatureFlag('config')] array $config,
): Response {
    // values resolved from the active provider
}
```

## Supported types

The flag value is resolved based on the parameter's type hint:

| Type hint | SDK method called |
|---|---|
| `bool` | `getBooleanValue()` |
| `string` | `getStringValue()` |
| `int` | `getIntegerValue()` |
| `float` | `getFloatValue()` |
| `array` | `getObjectValue()` |
| `mixed` or none | `getBooleanValue()` |

## Default values

When the flag is not found or a type mismatch occurs, the SDK returns a default value. The default is the zero value of the type: `false`, `""`, `0`, `0.0`, `[]`.
