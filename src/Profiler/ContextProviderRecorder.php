<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Profiler;

use Aubes\OpenFeatureBundle\Event\EvaluationContextContributedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\ResetInterface;

#[AsEventListener(event: EvaluationContextContributedEvent::class)]
class ContextProviderRecorder implements ResetInterface
{
    /** @var list<array{provider: class-string, targeting_key: ?string, attributes: array<array-key, mixed>}> */
    private array $contributions = [];

    public function __invoke(EvaluationContextContributedEvent $event): void
    {
        $this->contributions[] = [
            'provider' => $event->provider::class,
            'targeting_key' => $event->context->getTargetingKey(),
            'attributes' => $event->context->getAttributes()->toArray(),
        ];
    }

    /** @return list<array{provider: class-string, targeting_key: ?string, attributes: array<array-key, mixed>}> */
    public function getContributions(): array
    {
        return $this->contributions;
    }

    public function reset(): void
    {
        $this->contributions = [];
    }
}
