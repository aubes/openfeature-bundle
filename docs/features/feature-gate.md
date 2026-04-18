# `#[FeatureGate]` : access control

Restrict access to a controller action based on a feature flag.

## Usage

```php
use Aubes\OpenFeatureBundle\Attribute\FeatureGate;

#[FeatureGate('new_checkout')]
public function checkout(): Response
{
    // only reachable when new_checkout is true
}
```

When the flag evaluates to `false`, an exception is thrown (403 by default). The exception message includes the flag name for easier debugging.

## Stacking multiple gates

The attribute is repeatable. Multiple gates can be stacked on the same method:

```php
#[FeatureGate('new_checkout')]
#[FeatureGate('checkout_v2')]
public function checkout(): Response
{
    // both flags must be true
}
```

## Exception behavior

The exception type is auto-detected:

| `on_disabled` | Exception |
|---|---|
| `auto` (default) | `AccessDeniedException` if `symfony/security-core` is installed, `HttpException` otherwise |
| `access_denied` | `AccessDeniedException` (always) |
| `http_exception` | `HttpException` (always, with configurable status code) |

Override in configuration:

```yaml
open_feature:
    feature_flag:
        on_disabled: http_exception
        status_code: 404
```
