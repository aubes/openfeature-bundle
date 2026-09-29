# EvaluationContext

The `EvaluationContext` carries targeting information (user ID, attributes) used by providers for segmentation, A/B testing, and percentage rollouts.

## Auto-populate from the Symfony user

When SecurityBundle is enabled, the authenticated user's identifier is automatically set as the `targeting_key`:

```yaml
open_feature:
    evaluation_context:
        user_provider: true  # or "auto" (default)
```

| Value | Behavior |
|---|---|
| `auto` (default) | Enabled if SecurityBundle is enabled |
| `true` | Always enabled (requires SecurityBundle) |
| `false` | Disabled |

> **Note:** The user identifier is sent as-is to the flag provider, which may be a remote service (flagd, GO Feature Flag relay proxy, LaunchDarkly). If it is personal data, such as an email address, set `user_provider: false` and register a [custom context provider](#custom-context-provider) that sets a non-personal targeting key (internal user ID, hash). The profiler only displays a hash of the targeting key.

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

Multiple context providers are supported. They run highest priority first, then their contexts are merged by the SDK: on conflicts, the last context wins. A lower-priority provider therefore overrides a higher-priority one for the targeting key and any shared attribute. The built-in user provider runs at priority `0`.

Set the priority with the `#[AsTaggedItem]` attribute on the provider class, or with the `priority` attribute of the `openfeature.evaluation_context_provider` tag:

```php
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 100)]
class FallbackContextProvider implements EvaluationContextProviderInterface
```

Register a fallback (e.g. an anonymous targeting key) at a high priority, so that a real identity contributed at a lower priority wins.

## When context providers run

Context providers run on the first flag evaluation of each main request, not when the request starts. Their merged context is reused for the other evaluations of the same request.

A request that evaluates no flag never runs them. This keeps a `lazy` firewall lazy: the user provider reads the security token, which authenticates the user and makes the response private (not HTTP-cacheable). Pages without flags stay cacheable.

- `EvaluationContextContributedEvent` is dispatched at that time, not on `kernel.request`.
- An exception thrown by a context provider is logged (`error` level) and the provider is skipped. Evaluation goes on with the other contexts. An exception thrown by a listener of `EvaluationContextContributedEvent` is logged too, and the provider's context is kept.
- A flag evaluated from inside a context provider gets an empty context.
- Reading the targeting key or attributes of `API::getEvaluationContext()` also runs the providers.

Logic that must run at the start of every request belongs in its own `kernel.request` listener, not in a context provider.

## FrankenPHP worker mode

The global `EvaluationContext` is automatically cleared between requests. No configuration required.
