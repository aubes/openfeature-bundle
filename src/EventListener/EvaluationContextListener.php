<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\EventListener;

use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\API;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Contracts\Service\ResetInterface;

class EvaluationContextListener implements ResetInterface
{
    /** @param iterable<EvaluationContextProviderInterface> $providers */
    public function __construct(
        private readonly API $api,
        private readonly iterable $providers = [],
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $contexts = [];
        foreach ($this->providers as $provider) {
            $context = $provider->getContext($event->getRequest());
            if ($context !== null) {
                $contexts[] = $context;
            }
        }

        if ($contexts === []) {
            return;
        }

        $this->api->setEvaluationContext(EvaluationContext::merge(...$contexts));
    }

    /**
     * Clears the OpenFeature global evaluation context between requests.
     * Required under any long-running runtime (FrankenPHP worker, Messenger)
     * where the SDK singleton survives across requests.
     */
    public function reset(): void
    {
        $this->api->setEvaluationContext(new MutableEvaluationContext());
    }
}
