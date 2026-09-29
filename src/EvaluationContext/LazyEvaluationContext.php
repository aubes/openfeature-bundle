<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\EvaluationContext;

use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\Attributes;
use OpenFeature\interfaces\flags\EvaluationContext;

/**
 * Evaluation context resolved on first read, i.e. on the first flag evaluation.
 *
 * @internal
 */
class LazyEvaluationContext implements EvaluationContext
{
    private ?EvaluationContext $resolved = null;
    private bool $resolving = false;

    /** @param \Closure(): EvaluationContext $resolver */
    public function __construct(private readonly \Closure $resolver)
    {
    }

    public function getTargetingKey(): ?string
    {
        return $this->resolve()->getTargetingKey();
    }

    public function getAttributes(): Attributes
    {
        return $this->resolve()->getAttributes();
    }

    public function isResolved(): bool
    {
        return $this->resolved !== null;
    }

    private function resolve(): EvaluationContext
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        // A flag evaluated during resolution (e.g. by a context provider) gets an empty context instead of recursing
        if ($this->resolving) {
            return new MutableEvaluationContext();
        }

        $this->resolving = true;

        try {
            return $this->resolved = ($this->resolver)();
        } finally {
            $this->resolving = false;
        }
    }
}
