<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Provider;

use Aubes\OpenFeatureBundle\Provider\Redis\RedisClientInterface;
use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Provider;
use OpenFeature\interfaces\provider\ResolutionDetails;

class RedisProvider extends AbstractProvider implements Provider
{
    use ResolutionDetailsTrait;

    public const DEFAULT_PREFIX = 'feature:';

    protected static string $NAME = 'RedisProvider';

    public function __construct(
        private readonly RedisClientInterface $client,
        private readonly string $prefix = self::DEFAULT_PREFIX,
    ) {
    }

    public function resolveBooleanValue(string $flagKey, bool $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        try {
            $raw = $this->getRaw($flagKey);
        } catch (\RuntimeException $e) {
            return $this->error(ErrorCode::GENERAL(), \sprintf('Flag "%s": %s', $flagKey, $e->getMessage()), $defaultValue);
        }

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $this->found($this->toBool($raw));
    }

    public function resolveStringValue(string $flagKey, string $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        try {
            $raw = $this->getRaw($flagKey);
        } catch (\RuntimeException $e) {
            return $this->error(ErrorCode::GENERAL(), \sprintf('Flag "%s": %s', $flagKey, $e->getMessage()), $defaultValue);
        }

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $this->found($raw);
    }

    public function resolveIntegerValue(string $flagKey, int $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        try {
            $raw = $this->getRaw($flagKey);
        } catch (\RuntimeException $e) {
            return $this->error(ErrorCode::GENERAL(), \sprintf('Flag "%s": %s', $flagKey, $e->getMessage()), $defaultValue);
        }

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $this->found((int) $raw);
    }

    public function resolveFloatValue(string $flagKey, float $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        try {
            $raw = $this->getRaw($flagKey);
        } catch (\RuntimeException $e) {
            return $this->error(ErrorCode::GENERAL(), \sprintf('Flag "%s": %s', $flagKey, $e->getMessage()), $defaultValue);
        }

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
        try {
            $raw = $this->getRaw($flagKey);
        } catch (\RuntimeException $e) {
            return $this->error(ErrorCode::GENERAL(), \sprintf('Flag "%s": %s', $flagKey, $e->getMessage()), $defaultValue);
        }

        if ($raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        $decoded = \json_decode($raw, true);

        if (!\is_array($decoded)) {
            return $this->error(ErrorCode::PARSE_ERROR(), \sprintf('Flag "%s" contains invalid JSON', $flagKey), $defaultValue);
        }

        return $this->found($decoded);
    }

    private function getRaw(string $flagKey): ?string
    {
        try {
            $value = $this->client->get($this->prefix . $flagKey);
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), previous: $e);
        }

        return ($value === false || $value === null) ? null : $value;
    }
}
