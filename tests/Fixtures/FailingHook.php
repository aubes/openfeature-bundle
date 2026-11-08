<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Fixtures;

use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\hooks\Hook;
use OpenFeature\interfaces\hooks\HookContext;
use OpenFeature\interfaces\hooks\HookHints;
use OpenFeature\interfaces\provider\ResolutionDetails;

/**
 * Fails every flag evaluation from its before() stage.
 */
class FailingHook implements Hook
{
    public function before(HookContext $context, HookHints $hints): ?EvaluationContext
    {
        throw new \RuntimeException('Hook failure');
    }

    public function after(HookContext $context, ResolutionDetails $details, HookHints $hints): void
    {
    }

    public function error(HookContext $context, \Throwable $error, HookHints $hints): void
    {
    }

    public function finally(HookContext $context, HookHints $hints): void
    {
    }

    public function supportsFlagValueType(string $flagValueType): bool
    {
        return true;
    }
}
