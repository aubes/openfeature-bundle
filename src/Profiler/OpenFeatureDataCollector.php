<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Profiler;

use OpenFeature\interfaces\flags\API;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class OpenFeatureDataCollector extends DataCollector
{
    public function __construct(
        private readonly ProfilerHook $hook,
        private readonly API $api,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'evaluations' => $this->hook->getEvaluations(),
            'provider' => $this->api->getProviderMetadata()->getName(),
            'evaluation_context' => $this->serializeContext(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getEvaluations(): array
    {
        /** @var array<int, array<string, mixed>> $evaluations */
        $evaluations = $this->data['evaluations'] ?? [];

        return $evaluations;
    }

    public function getEvaluationCount(): int
    {
        return \count($this->getEvaluations());
    }

    public function getProvider(): string
    {
        /** @var string $provider */
        $provider = $this->data['provider'] ?? 'unknown';

        return $provider;
    }

    /** @return array<string, mixed> */
    public function getEvaluationContext(): array
    {
        /** @var array<string, mixed> $context */
        $context = $this->data['evaluation_context'] ?? [];

        return $context;
    }

    public function getName(): string
    {
        return 'open_feature';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    private function anonymizeTargetingKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        return \substr(\hash('sha256', $key), 0, 12);
    }

    /** @return array<string, mixed> */
    private function serializeContext(): array
    {
        $context = $this->api->getEvaluationContext();

        if ($context === null) {
            return [];
        }

        return [
            'targeting_key' => $this->anonymizeTargetingKey($context->getTargetingKey()),
            'attributes' => $context->getAttributes()->toArray(),
        ];
    }
}
