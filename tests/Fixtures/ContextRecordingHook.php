<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Fixtures;

use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\hooks\Hook;
use OpenFeature\interfaces\hooks\HookContext;
use OpenFeature\interfaces\hooks\HookHints;
use OpenFeature\interfaces\provider\ResolutionDetails;

/**
 * Records the evaluation context seen by the last flag evaluation.
 */
class ContextRecordingHook implements Hook
{
    public ?EvaluationContext $context = null;

    public function before(HookContext $context, HookHints $hints): ?EvaluationContext
    {
        return null;
    }

    public function after(HookContext $context, ResolutionDetails $details, HookHints $hints): void
    {
        $this->context = $context->getEvaluationContext();
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
