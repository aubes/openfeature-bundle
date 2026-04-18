# Hooks

Hooks run around every flag evaluation. They are useful for logging, tracing, metrics, and validation.

## Registering a hook

Implement the OpenFeature `Hook` interface and tag the service with `openfeature.hook`:

```php
use OpenFeature\interfaces\hooks\Hook;
use OpenFeature\interfaces\hooks\HookContext;
use OpenFeature\interfaces\hooks\HookHints;
use OpenFeature\interfaces\provider\ResolutionDetails;

class LoggerHook implements Hook
{
    public function before(HookContext $context, HookHints $hints): ?EvaluationContext
    {
        return null;
    }

    public function after(HookContext $context, ResolutionDetails $details, HookHints $hints): void
    {
        // log, trace, metrics...
    }

    public function error(HookContext $context, \Throwable $error, HookHints $hints): void
    {
        // handle evaluation errors
    }

    public function finally(HookContext $context, HookHints $hints): void
    {
        // cleanup
    }

    public function supportsFlagValueType(): bool
    {
        return true; // support all flag types
    }
}
```

```yaml
services:
    App\OpenFeature\LoggerHook:
        tags: [openfeature.hook]
```

## Hook lifecycle

Hooks are called in the following order for each flag evaluation:

1. `before()` : before the provider resolves the flag
2. `after()` : after successful resolution
3. `error()` : if the provider throws an exception (instead of `after`)
4. `finally()` : always called, regardless of success or failure

## Pre-built hooks

The [open-feature/php-sdk-contrib](https://github.com/open-feature/php-sdk-contrib) repository provides pre-built hooks for OpenTelemetry, Datadog, and more.
