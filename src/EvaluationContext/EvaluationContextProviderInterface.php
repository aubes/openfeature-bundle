<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\EvaluationContext;

use OpenFeature\interfaces\flags\EvaluationContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implement this interface to contribute attributes to the global OpenFeature
 * EvaluationContext on each request.
 *
 * Multiple providers are supported. They are iterated highest priority first
 * (set the "priority" attribute on the tag), then their contexts are merged by the
 * OpenFeature SDK, which gives precedence to the last context on conflicts.
 * As a result, a lower-priority provider overrides a higher-priority one for the
 * targeting key and any shared attribute. Register a fallback at high priority so a
 * real identity contributed at lower priority wins.
 */
interface EvaluationContextProviderInterface
{
    public function getContext(Request $request): ?EvaluationContext;
}
