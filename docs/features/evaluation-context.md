# EvaluationContext

The `EvaluationContext` carries targeting information (user ID, attributes) used by providers for segmentation, A/B testing, and percentage rollouts.

## Auto-populate from the Symfony user

When `symfony/security-core` is available, the authenticated user's identifier is automatically set as the `targeting_key`:

```yaml
open_feature:
    evaluation_context:
        user_provider: true  # or "auto" (default)
```

| Value | Behavior |
|---|---|
| `auto` (default) | Enabled if `symfony/security-core` is installed |
| `true` | Always enabled (requires `symfony/security-core`) |
| `false` | Disabled |

## Custom context provider

Implement `EvaluationContextProviderInterface` to contribute additional attributes:

```php
use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use OpenFeature\implementation\flags\MutableAttributes;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\EvaluationContext;
use Symfony\Component\HttpFoundation\Request;

class TenantContextProvider implements EvaluationContextProviderInterface
{
    public function getContext(Request $request): ?EvaluationContext
    {
        return new MutableEvaluationContext(null, new MutableAttributes([
            'tenant' => $request->attributes->get('tenant'),
            'plan'   => 'premium',
        ]));
    }
}
```

The service is picked up automatically, no tag or config needed.

## Multiple providers

Multiple context providers are supported. Their contexts are merged via the SDK's `EvaluationContext::merge()` method.

## FrankenPHP worker mode

The global `EvaluationContext` is automatically cleared between requests. No configuration required.
