# Configuration reference

Full configuration tree for `open_feature`:

```yaml
# config/packages/open_feature.yaml
open_feature:

    # Service ID of the OpenFeature provider
    # Default: Aubes\OpenFeatureBundle\Provider\InMemoryProvider
    # Mutually exclusive with "providers"
    provider: Aubes\OpenFeatureBundle\Provider\InMemoryProvider

    # Multiple providers combined through the SDK MultiProvider
    # Keys are provider names, values are service IDs
    # Evaluation follows declaration order
    # Mutually exclusive with "provider"
    providers:
        remote: App\OpenFeature\MyProvider
        local: Aubes\OpenFeatureBundle\Provider\InMemoryProvider

    # Evaluation strategy for the MultiProvider (only when using "providers")
    # Shorthand: strategy: first_match
    strategy:
        type: first_match     # first_match | first_successful | comparison
        # Provider name used as fallback on mismatch
        # Required (and only allowed) when type is "comparison"
        fallback: ~

    # Flags for the InMemoryProvider (dev/test use)
    flags:
        new_checkout: true
        dark_mode: false
        max_items: 10

    # EvaluationContext settings
    evaluation_context:
        # Populate targeting key from the authenticated Symfony user
        # auto: enabled if symfony/security-core is installed
        # true: always enabled (requires symfony/security-core)
        # false: disabled
        user_provider: auto   # auto | true | false

    # Exception behavior for #[FeatureGate]
    feature_flag:
        # auto: AccessDeniedException if security-core is available, HttpException otherwise
        # access_denied: always throw AccessDeniedException
        # http_exception: always throw HttpException
        on_disabled: auto     # auto | access_denied | http_exception

        # HTTP status code when using http_exception
        status_code: 403

    # Redis provider settings (only when using RedisProvider)
    redis:
        # Service implementing RedisClientInterface
        client: ~
        # Key prefix for flag lookup
        prefix: 'feature:'
```

## Provider

Point `provider` to any service implementing the OpenFeature `Provider` interface:

```yaml
open_feature:
    provider: App\OpenFeature\MyProvider
```

See [Providers](providers/index.md) for available options.

## Multiple providers

Declare several providers under `providers` to combine them through the SDK `MultiProvider` (requires `open-feature/sdk` >= 2.2). Each key is a provider name, each value a service ID. Providers are evaluated in declaration order:

```yaml
open_feature:
    providers:
        remote: App\OpenFeature\MyProvider
        local: Aubes\OpenFeatureBundle\Provider\InMemoryProvider
    strategy: first_match
```

`provider` and `providers` are mutually exclusive.

### Strategies

| Strategy | Behavior |
|---|---|
| `first_match` (default) | Sequential. Returns the first provider that knows the flag; `FLAG_NOT_FOUND` moves to the next provider, any other error stops the evaluation. |
| `first_successful` | Sequential. Returns the first result without error; errors do not stop the chain. |
| `comparison` | Evaluates all providers and compares results. If they disagree, the result of the `fallback` provider is used. |

The `comparison` strategy requires a `fallback` pointing to one of the declared provider names:

```yaml
open_feature:
    providers:
        remote: App\OpenFeature\MyProvider
        local: Aubes\OpenFeatureBundle\Provider\InMemoryProvider
    strategy:
        type: comparison
        fallback: local
```

## Flags

The `flags` key is only used by the `InMemoryProvider`. Values can be booleans, integers, floats, strings, or arrays:

```yaml
open_feature:
    flags:
        enable_feature: true
        max_retries: 3
        ratio: 0.5
        theme: dark
        config:
            color: blue
            size: large
```

## Feature gate behavior

The `feature_flag.on_disabled` setting controls what happens when a `#[FeatureGate]` flag evaluates to `false`:

| Value | Exception type | When to use |
|---|---|---|
| `auto` (default) | `AccessDeniedException` if `symfony/security-core` is installed, `HttpException` otherwise | Most apps |
| `access_denied` | `AccessDeniedException` | When you have a security error handler |
| `http_exception` | `HttpException` with configurable status code | APIs, custom error pages |
