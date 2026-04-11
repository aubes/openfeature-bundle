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

    private function toBool(string $value): bool
    {
        return \in_array(\strtolower($value), ['true', '1', 'yes', 'on'], true);
    }
}
