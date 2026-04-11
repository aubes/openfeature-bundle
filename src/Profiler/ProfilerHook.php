<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Profiler;

use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\hooks\Hook;
use OpenFeature\interfaces\hooks\HookContext;
use OpenFeature\interfaces\hooks\HookHints;
use OpenFeature\interfaces\provider\ResolutionDetails;
use Symfony\Contracts\Service\ResetInterface;

class ProfilerHook implements Hook, ResetInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $evaluations = [];

    public function before(HookContext $context, HookHints $hints): ?EvaluationContext
    {
        return null;
    }

    public function after(HookContext $context, ResolutionDetails $details, HookHints $hints): void
    {
        $this->evaluations[] = [
            'flag' => $context->getFlagKey(),
            'type' => $context->getType(),
            'value' => $details->getValue(),
            'variant' => $details->getVariant(),
            'reason' => $details->getReason(),
            'error' => $details->getError()?->getResolutionErrorMessage(),
        ];
    }

    public function error(HookContext $context, \Throwable $error, HookHints $hints): void
    {
        $this->evaluations[] = [
            'flag' => $context->getFlagKey(),
            'type' => $context->getType(),
            'value' => null,
            'variant' => null,
            'reason' => 'ERROR',
            'error' => $error::class . ': ' . $error->getMessage(),
        ];
    }

    public function finally(HookContext $context, HookHints $hints): void
    {
    }

    public function supportsFlagValueType(string $flagValueType): bool
    {
        return true;
    }

    public function reset(): void
    {
        $this->evaluations = [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getEvaluations(): array
    {
        return $this->evaluations;
    }
}
