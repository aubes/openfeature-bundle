# OpenFeature Bundle

[![Latest Version](https://img.shields.io/packagist/v/aubes/openfeature-bundle.svg)](https://packagist.org/packages/aubes/openfeature-bundle)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://www.php.net)
[![Symfony Version](https://img.shields.io/badge/symfony-6.4%20%7C%207.x%20%7C%208.x-green.svg)](https://symfony.com)

Symfony bundle for the [OpenFeature PHP SDK](https://github.com/open-feature/php-sdk) -- the [CNCF standard](https://openfeature.dev) for feature flags.

Switch between providers (Flagd, Flagsmith, Unleash, LaunchDarkly...) without touching your application code.

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x
- `open-feature/sdk` ^2.0 (implements [OpenFeature spec v0.5.1](https://github.com/open-feature/spec/releases/tag/v0.5.1))

## Installation

```bash
composer require aubes/openfeature-bundle
```

> **Note:** Without a Symfony Flex recipe, register the bundle manually in `config/bundles.php`:
> ```php
> Aubes\OpenFeatureBundle\OpenFeatureBundle::class => ['all' => true],
> ```

## Configuration

```yaml
# config/packages/open_feature.yaml
open_feature:
    # Service ID of the OpenFeature provider (default: built-in InMemoryProvider)
    provider: Aubes\OpenFeatureBundle\Provider\InMemoryProvider

    # Flags for the InMemoryProvider (dev/test use)
    flags:
        new_checkout: true
        dark_mode: false
        max_items: 10

    # EvaluationContext: populate targeting key from the authenticated Symfony user
    evaluation_context:
        user_provider: auto   # auto | true | false

    # Exception behavior when a #[FeatureGate] flag is disabled
    feature_flag:
        on_disabled: auto     # auto | access_denied | http_exception
        status_code: 403
```

## Providers

The bundle works with any [OpenFeature provider](https://openfeature.dev/ecosystem/?instant_search%5BrefinementList%5D%5Btype%5D%5B0%5D=Provider&instant_search%5BrefinementList%5D%5Btechnology%5D%5B0%5D=PHP). Point to a service that implements the `Provider` interface:

```yaml
open_feature:
    provider: App\OpenFeature\MyProvider
```

Use a provider from [open-feature/php-sdk-contrib](https://github.com/open-feature/php-sdk-contrib) or a vendor SDK (ConfigCat, LaunchDarkly, Kameleoon...):

```php
use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\interfaces\provider\Provider;

class MyProvider extends AbstractProvider implements Provider
{
    // implement resolveBooleanValue, resolveStringValue, etc.
}
```

### Built-in providers

The bundle also ships with three simple providers for quick starts and basic use cases (kill switches, on/off toggles). These are **static flag stores**: they ignore the `EvaluationContext` (no targeting, no rollout rules). For user targeting, percentage rollouts, or A/B testing, use a dedicated provider like Flagd, Unleash, or LaunchDarkly.

#### InMemoryProvider *(default)*

Flags defined statically in configuration. Ideal for local development and tests.

```yaml
open_feature:
    flags:
        my_feature: true
```

#### EnvVarProvider

Resolves flags from environment variables. Ideal for staging/production without a dedicated backend.

```yaml
open_feature:
    provider: Aubes\OpenFeatureBundle\Provider\EnvVarProvider
```

```bash
FEATURE_NEW_CHECKOUT=true
FEATURE_DARK_MODE=false
```

Flag keys are uppercased and prefixed: `new_checkout` -> `FEATURE_NEW_CHECKOUT`. Hyphens and dots are replaced by underscores. The prefix is configurable via the constructor.

#### RedisProvider

Resolves flags from Redis string keys. Ideal when you already have Redis in your stack and want dynamic flags without a dedicated backend.

```yaml
open_feature:
    provider: Aubes\OpenFeatureBundle\Provider\RedisProvider
    redis:
        client: App\OpenFeature\MyRedisClient  # service implementing RedisClientInterface
        prefix: 'feature:'                      # key prefix (default: "feature:")
```

Each flag is stored as a plain Redis string:

```
feature:new_checkout  ->  "true"
feature:max_items     ->  "10"
feature:config        ->  '{"color":"blue"}'
```

Boolean truthy values: `true`, `1`, `yes`, `on`. Object values must be JSON-encoded. The key prefix defaults to `feature:` and is configurable.

If Redis is unavailable, the provider returns the default value with an error reason instead of throwing an exception.

The `RedisProvider` depends on a `RedisClientInterface` service. Implement it to wrap your Redis connection (`\Redis`, `Predis\Client`, etc.):

```php
use Aubes\OpenFeatureBundle\Provider\Redis\RedisClientInterface;

class MyRedisClient implements RedisClientInterface
{
    public function __construct(private readonly \Redis $redis) {}

    public function get(string $key): string|false|null
    {
        return $this->redis->get($key);
    }
}
```

## Usage

### Inject the Client

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

All flag types are supported: `getBooleanValue`, `getStringValue`, `getIntegerValue`, `getFloatValue`, `getObjectValue`.

### Twig

```twig
{% if feature('new_checkout') %}
    {# new checkout #}
{% endif %}

{# Non-boolean values #}
{{ feature_value('theme', 'light') }}
{{ feature_value('max_items', 10) }}
```

### `#[FeatureFlag]` -- value injection

Inject resolved flag values directly into controller parameters:

```php
use Aubes\OpenFeatureBundle\Attribute\FeatureFlag;

public function index(
    #[FeatureFlag('dark_mode')] bool $darkMode,
    #[FeatureFlag('max_items')] int $maxItems,
    #[FeatureFlag('theme')] string $theme,
): Response {
    // values resolved from the active provider
}
```

Supported types: `bool`, `string`, `int`, `float`, `array`. Without a type hint (or `mixed`), defaults to `bool`.

### `#[FeatureGate]` -- access control

Restrict access to a controller action based on a flag:

```php
use Aubes\OpenFeatureBundle\Attribute\FeatureGate;

#[FeatureGate('new_checkout')]
public function checkout(): Response
{
    // throws AccessDeniedException (or HttpException 403) if flag is disabled
}
```

Multiple gates can be stacked on the same method. The exception message includes the flag name for easier debugging.

The exception type is auto-detected: `AccessDeniedException` if `symfony/security-core` is available, `HttpException` otherwise. Override via configuration:

```yaml
open_feature:
    feature_flag:
        on_disabled: http_exception
        status_code: 404
```

## EvaluationContext

The `EvaluationContext` carries targeting information (user ID, attributes) used by providers for segmentation, A/B testing, and percentage rollouts.

### Auto-populate from the Symfony user

When `symfony/security-core` is available, the authenticated user's identifier is automatically set as the `targeting_key`:

```yaml
open_feature:
    evaluation_context:
        user_provider: true  # or "auto" (default)
```

### Custom context provider

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

The interface is autoconfigured: if your service implements `EvaluationContextProviderInterface`, the `openfeature.evaluation_context_provider` tag is added automatically. No manual tagging required.

Multiple providers are supported and their contexts are merged. The global context is reset between requests (FrankenPHP worker mode safe).

## Hooks

Hooks run around every flag evaluation. Register a global hook with the `openfeature.hook` tag:

```php
use OpenFeature\interfaces\hooks\Hook;
use OpenFeature\interfaces\hooks\HookContext;
use OpenFeature\interfaces\hooks\HookHints;
use OpenFeature\interfaces\provider\ResolutionDetails;

class LoggerHook implements Hook
{
    public function after(HookContext $context, ResolutionDetails $details, HookHints $hints): void
    {
        // log, trace, metrics...
    }

    // also: before(), error(), finally(), supportsFlagValueType()
}
```

```yaml
services:
    App\OpenFeature\LoggerHook:
        tags: [openfeature.hook]
```

Pre-built hooks (OpenTelemetry, Datadog) are available in [open-feature/php-sdk-contrib](https://github.com/open-feature/php-sdk-contrib).

## Debug command

List all feature flags detected in your controllers and their current values:

```bash
php bin/console debug:feature-flags
```

The command scans routes for `#[FeatureFlag]` and `#[FeatureGate]` attributes and evaluates them against the active provider. Note that flags are evaluated without HTTP request context (no authenticated user).

## Symfony Profiler

The bundle registers an **OpenFeature panel** in the Symfony Web Debug Toolbar showing:

- Active provider name
- All flags evaluated during the request (key, type, resolved value, reason, error)
- Global EvaluationContext (targeting key and attributes)

## FrankenPHP worker mode

The global `EvaluationContext` is automatically reset between requests via Symfony's `kernel.reset` mechanism. Hooks and the provider are preserved (set once at boot). No configuration required.
