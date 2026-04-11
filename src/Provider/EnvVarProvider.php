<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Provider;

use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Provider;
use OpenFeature\interfaces\provider\ResolutionDetails;

/**
 * Reads from the process environment via getenv(). Variables loaded from
 * .env files with use_putenv: false will NOT be visible to this provider.
 */
class EnvVarProvider extends AbstractProvider implements Provider
{
    use ResolutionDetailsTrait;

    protected static string $NAME = 'EnvVarProvider';

    public function __construct(private readonly string $prefix = 'FEATURE_')
    {
    }

    public function resolveBooleanValue(string $flagKey, bool $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        $raw = $this->getEnvVar($flagKey);

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $this->found($this->toBool($raw));
    }

    public function resolveStringValue(string $flagKey, string $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        $raw = $this->getEnvVar($flagKey);

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $this->found($raw);
    }

    public function resolveIntegerValue(string $flagKey, int $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        $raw = $this->getEnvVar($flagKey);

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $this->found((int) $raw);
    }

    public function resolveFloatValue(string $flagKey, float $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        $raw = $this->getEnvVar($flagKey);

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $this->found((float) $raw);
    }

    /**
     * @param mixed[] $defaultValue
     */
    public function resolveObjectValue(string $flagKey, array $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        $raw = $this->getEnvVar($flagKey);

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        $decoded = \json_decode($raw, true);

        if (!\is_array($decoded)) {
            return $this->error(ErrorCode::PARSE_ERROR(), \sprintf('Flag "%s" contains invalid JSON', $flagKey), $defaultValue);
        }

        return $this->found($decoded);
    }

    private function getEnvVar(string $flagKey): ?string
    {
        $envKey = $this->prefix . \strtoupper(\str_replace(['-', '.'], '_', $flagKey));
        $value = \getenv($envKey);

        return $value !== false ? $value : null;
    }
}
