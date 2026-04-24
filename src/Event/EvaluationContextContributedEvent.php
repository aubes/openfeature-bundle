<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Event;

use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use OpenFeature\interfaces\flags\EvaluationContext;
use Symfony\Contracts\EventDispatcher\Event;

final class EvaluationContextContributedEvent extends Event
{
    public function __construct(
        public readonly EvaluationContextProviderInterface $provider,
        public readonly EvaluationContext $context,
    ) {
    }
}
