<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\EvaluationContext;

use OpenFeature\interfaces\flags\EvaluationContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implement this interface to contribute attributes to the global OpenFeature
 * EvaluationContext on each request.
 *
 * Multiple providers are supported and their contexts are merged in priority order
 * (highest priority first). Use the "priority" attribute on the tag to control ordering.
 */
interface EvaluationContextProviderInterface
{
    public function getContext(Request $request): ?EvaluationContext;
}
