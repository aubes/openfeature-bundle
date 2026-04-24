# Hooks

Hooks run around every flag evaluation. They are useful for logging, tracing, metrics, and validation.

## Registering a hook

A service implementing the OpenFeature `Hook` interface is autoconfigured with the `openfeature.hook` tag.

```php
use OpenFeature\interfaces\hooks\Hook;
use OpenFeature\interfaces\hooks\HookContext;
use OpenFeature\interfaces\hooks\HookHints;
use OpenFeature\interfaces\provider\ResolutionDetails;
use Psr\Log\LoggerInterface;

class LoggerHook implements Hook
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function after(HookContext $context, ResolutionDetails $details, HookHints $hints): void
    {
        $this->logger->info('Flag evaluated', [
            'flag'  => $context->getFlagKey(),
            'value' => $details->getValue(),
        ]);
    }

    // before(), error(), finally(), supportsFlagValueType() are also available
    // see: https://openfeature.dev/specification/sections/hooks
}
```

### Hooks with constructor options

When a hook takes constructor arguments (for example the `RegexpValidatorHook` from [php-sdk-contrib](https://github.com/open-feature/php-sdk-contrib/blob/main/hooks/Validators/README.md)), declare the service with its arguments. Autoconfiguration still adds the tag:

```yaml
services:
    App\Hook\HexadecimalValidatorHook:
        class: OpenFeature\Hooks\Validators\RegexpValidatorHook
        arguments: ['/^[0-9a-f]+$/']
```

### Per-call hooks (opt-out)

If you want a hook to be applied only at call-site via `EvaluationOptions` and not globally, disable autoconfiguration on that service:

```yaml
services:
    App\Hook\HexadecimalValidatorHook:
        class: OpenFeature\Hooks\Validators\RegexpValidatorHook
        arguments: ['/^[0-9a-f]+$/']
        autoconfigure: false
```

## Hook lifecycle

See the [OpenFeature hooks specification](https://openfeature.dev/specification/sections/hooks) for the full lifecycle.

## Pre-built hooks

The [open-feature/php-sdk-contrib](https://github.com/open-feature/php-sdk-contrib) repository provides pre-built hooks for OpenTelemetry, Datadog, and more.
