<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Provider;

use OpenFeature\implementation\provider\ResolutionDetailsBuilder;
use OpenFeature\implementation\provider\ResolutionError;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Reason;
use OpenFeature\interfaces\provider\ResolutionDetails;

trait ResolutionDetailsTrait
{
    /** @param bool|float|int|mixed[]|string $value */
    private function found(bool|string|int|float|array $value): ResolutionDetails
    {
        return (new ResolutionDetailsBuilder())
            ->withValue($value)
            ->withReason(Reason::DEFAULT)
            ->build();
    }

    /** @param bool|float|int|mixed[]|string $defaultValue */
    private function flagNotFound(string $flagKey, bool|string|int|float|array $defaultValue): ResolutionDetails
    {
        return (new ResolutionDetailsBuilder())
            ->withValue($defaultValue)
            ->withReason(Reason::ERROR)
            ->withError(new ResolutionError(ErrorCode::FLAG_NOT_FOUND(), \sprintf('Flag "%s" not found', $flagKey)))
            ->build();
    }

    /** @param bool|float|int|mixed[]|string $defaultValue */
    private function error(ErrorCode $code, string $message, bool|string|int|float|array $defaultValue): ResolutionDetails
    {
        return (new ResolutionDetailsBuilder())
            ->withValue($defaultValue)
            ->withReason(Reason::ERROR)
            ->withError(new ResolutionError($code, $message))
            ->build();
    }

    /**
     * Accepts true/false, 1/0, yes/no, on/off (case-insensitive) and the empty string (false).
     */
    private function parseBool(string $flagKey, string $raw, bool $defaultValue): ResolutionDetails
    {
        $value = \filter_var($raw, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);

        return $value === null ? $this->parseError($flagKey, 'boolean', $defaultValue) : $this->found($value);
    }

    private function parseInt(string $flagKey, string $raw, int $defaultValue): ResolutionDetails
    {
        // Leading zeros are accepted ("08"), as FILTER_VALIDATE_FLOAT already does
        $normalized = \preg_replace('/^([+-]?)0+(?=\d)/', '$1', \trim($raw));
        $value = \filter_var($normalized, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);

        return $value === null ? $this->parseError($flagKey, 'integer', $defaultValue) : $this->found($value);
    }

    private function parseFloat(string $flagKey, string $raw, float $defaultValue): ResolutionDetails
    {
        $value = \filter_var($raw, \FILTER_VALIDATE_FLOAT, \FILTER_NULL_ON_FAILURE);

        return $value === null ? $this->parseError($flagKey, 'float', $defaultValue) : $this->found($value);
    }

    /** @param mixed[] $defaultValue */
    private function parseObject(string $flagKey, string $raw, array $defaultValue): ResolutionDetails
    {
        $value = \json_decode($raw, true);

        return \is_array($value) ? $this->found($value) : $this->parseError($flagKey, 'JSON object', $defaultValue);
    }

    /** @param bool|float|int|mixed[]|string $defaultValue */
    private function parseError(string $flagKey, string $type, bool|string|int|float|array $defaultValue): ResolutionDetails
    {
        return $this->error(ErrorCode::PARSE_ERROR(), \sprintf('Flag "%s" is not a valid %s', $flagKey, $type), $defaultValue);
    }
}
