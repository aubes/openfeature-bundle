<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Provider;

use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Provider;
use OpenFeature\interfaces\provider\ResolutionDetails;

class InMemoryProvider extends AbstractProvider implements Provider
{
    use ResolutionDetailsTrait;

    protected static string $NAME = 'InMemoryProvider';

    /** @param array<string, mixed> $flags */
    public function __construct(private readonly array $flags = [])
    {
    }

    public function resolveBooleanValue(string $flagKey, bool $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        if (!\array_key_exists($flagKey, $this->flags)) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        $value = $this->flags[$flagKey];

        if (!\is_bool($value) && !\is_int($value)) {
            return $this->error(ErrorCode::TYPE_MISMATCH(), \sprintf('Flag "%s" is not of type boolean', $flagKey), $defaultValue);
        }

        return $this->found((bool) $value);
    }

    public function resolveStringValue(string $flagKey, string $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        if (!\array_key_exists($flagKey, $this->flags)) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        $value = $this->flags[$flagKey];

        if (!\is_scalar($value)) {
            return $this->error(ErrorCode::TYPE_MISMATCH(), \sprintf('Flag "%s" is not of type string', $flagKey), $defaultValue);
        }

        return $this->found((string) $value);
    }

    public function resolveIntegerValue(string $flagKey, int $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        if (!\array_key_exists($flagKey, $this->flags)) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        $value = $this->flags[$flagKey];

        if (!\is_int($value) && !\is_float($value)) {
            return $this->error(ErrorCode::TYPE_MISMATCH(), \sprintf('Flag "%s" is not of type integer', $flagKey), $defaultValue);
        }

        return $this->found((int) $value);
    }

    public function resolveFloatValue(string $flagKey, float $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        if (!\array_key_exists($flagKey, $this->flags)) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        $value = $this->flags[$flagKey];

        if (!\is_int($value) && !\is_float($value)) {
            return $this->error(ErrorCode::TYPE_MISMATCH(), \sprintf('Flag "%s" is not of type float', $flagKey), $defaultValue);
        }

        return $this->found((float) $value);
    }

    /**
     * @param mixed[] $defaultValue
     */
    public function resolveObjectValue(string $flagKey, array $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        if (!\array_key_exists($flagKey, $this->flags)) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        $value = $this->flags[$flagKey];

        if (!\is_array($value)) {
            return $this->error(ErrorCode::TYPE_MISMATCH(), \sprintf('Flag "%s" is not of type array', $flagKey), $defaultValue);
        }

        return $this->found($value);
    }
}
