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
        return $this->resolveRaw($flagKey, $defaultValue, fn (string $raw) => $this->parseBool($flagKey, $raw, $defaultValue));
    }

    public function resolveStringValue(string $flagKey, string $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveRaw($flagKey, $defaultValue, fn (string $raw) => $this->found($raw));
    }

    public function resolveIntegerValue(string $flagKey, int $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveRaw($flagKey, $defaultValue, fn (string $raw) => $this->parseInt($flagKey, $raw, $defaultValue));
    }

    public function resolveFloatValue(string $flagKey, float $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveRaw($flagKey, $defaultValue, fn (string $raw) => $this->parseFloat($flagKey, $raw, $defaultValue));
    }

    /**
     * @param mixed[] $defaultValue
     */
    public function resolveObjectValue(string $flagKey, array $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveRaw($flagKey, $defaultValue, fn (string $raw) => $this->parseObject($flagKey, $raw, $defaultValue));
    }

    /**
     * @param bool|float|int|mixed[]|string       $defaultValue
     * @param \Closure(string): ResolutionDetails $parse
     */
    private function resolveRaw(string $flagKey, bool|string|int|float|array $defaultValue, \Closure $parse): ResolutionDetails
    {
        try {
            $raw = $this->client->get($this->prefix . $flagKey);
        } catch (\Throwable $e) {
            // The SDK does not log errors returned in ResolutionDetails
            $this->logger?->error('OpenFeature Redis provider failed to read flag "{flag}": {message}', [
                'flag' => $flagKey,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return $this->error(ErrorCode::GENERAL(), \sprintf('Flag "%s": %s', $flagKey, $e->getMessage()), $defaultValue);
        }

        if ($raw === false || $raw === null) {
            return $this->flagNotFound($flagKey, $defaultValue);
        }

        return $parse($raw);
    }
}
