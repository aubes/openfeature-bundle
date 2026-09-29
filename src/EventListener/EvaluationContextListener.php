<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\EventListener;

use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use Aubes\OpenFeatureBundle\EvaluationContext\LazyEvaluationContext;
use Aubes\OpenFeatureBundle\Event\EvaluationContextContributedEvent;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\API;
use OpenFeature\interfaces\flags\EvaluationContext as EvaluationContextInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Contracts\Service\ResetInterface;

class EvaluationContextListener implements ResetInterface
{
    /** @param iterable<EvaluationContextProviderInterface> $providers */
    public function __construct(
        private readonly API $api,
        private readonly iterable $providers = [],
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $previous = $this->api->getEvaluationContext();

        // Providers run on the first flag evaluation, so requests without flags never trigger them (e.g. a lazy firewall)
        $this->api->setEvaluationContext(new LazyEvaluationContext(
            fn (): EvaluationContextInterface => $this->collect($request) ?? $previous ?? EvaluationContext::createNull(),
        ));
    }

    /**
     * Clears the API-level evaluation context between requests.
     * Required under any long-running runtime (FrankenPHP worker, Messenger)
     * where the API service survives across requests.
     */
    public function reset(): void
    {
        $this->api->setEvaluationContext(new MutableEvaluationContext());
    }

    private function collect(Request $request): ?EvaluationContextInterface
    {
        $contexts = [];
        foreach ($this->providers as $provider) {
            try {
                $context = $provider->getContext($request);
            } catch (\Throwable $e) {
                // Runs inside flag evaluation, which must not throw
                $this->logger?->error('OpenFeature evaluation context provider "{provider}" failed: {message}', [
                    'provider' => $provider::class,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);

                continue;
            }

            if ($context === null) {
                continue;
            }

            $contexts[] = $context;

            try {
                $this->dispatcher?->dispatch(new EvaluationContextContributedEvent($provider, $context));
            } catch (\Throwable $e) {
                $this->logger?->error('OpenFeature listener of "{event}" failed: {message}', [
                    'event' => EvaluationContextContributedEvent::class,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return $contexts === [] ? null : EvaluationContext::merge(...$contexts);
    }
}
