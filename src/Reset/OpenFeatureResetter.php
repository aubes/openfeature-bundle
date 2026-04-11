<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Reset;

use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\API;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resets the OpenFeature global evaluation context between requests.
 *
 * Required for FrankenPHP worker mode and any long-running PHP runtime
 * where the process handles multiple requests sequentially. Without this,
 * a per-request evaluation context (e.g. containing the current user's
 * targeting key) would leak into the next request.
 *
 * Hooks and provider are intentionally NOT reset: they are set once at
 * container boot and must persist across requests.
 */
class OpenFeatureResetter implements ResetInterface
{
    public function __construct(private readonly API $api)
    {
    }

    public function reset(): void
    {
        $this->api->setEvaluationContext(new MutableEvaluationContext());
    }
}
