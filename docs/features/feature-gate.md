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

When the flag evaluates to `false`, access is denied with an exception whose message includes the flag name. The response depends on `on_disabled` (see below): with SecurityBundle, the firewall returns a 403 to an authenticated user and starts authentication for an anonymous one (redirect to the login page, or 401). Without SecurityBundle, the response is a 403 by default.

The flag is evaluated with `false` as default value: if it cannot be evaluated (unknown flag, provider error), the gate stays closed.

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
| `auto` (default) | `AccessDeniedException` if SecurityBundle is enabled, `HttpException` otherwise |
| `access_denied` | `AccessDeniedException` (always) |
| `http_exception` | `HttpException` (always, with configurable status code) |

Override in configuration:

```yaml
open_feature:
    feature_flag:
        on_disabled: http_exception
        status_code: 404
```
