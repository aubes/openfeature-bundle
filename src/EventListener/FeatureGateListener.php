<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\EventListener;

use Aubes\OpenFeatureBundle\Attribute\FeatureGate;
use OpenFeature\interfaces\flags\Client;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FeatureGateListener
{
    public function __construct(
        private readonly Client $client,
        private readonly string $onDisabled,
        private readonly int $statusCode,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $reflector = $event->getControllerReflector();

        if (!$reflector instanceof \ReflectionMethod) {
            return;
        }

        $attributes = $reflector->getAttributes(FeatureGate::class);

        if ($attributes === []) {
            return;
        }

        foreach ($attributes as $attribute) {
            /** @var FeatureGate $gate */
            $gate = $attribute->newInstance();

            if ($this->client->getBooleanValue($gate->flag, false)) {
                continue;
            }

            $this->deny($gate->flag);
        }
    }

    private function deny(string $flagKey): never
    {
        $message = \sprintf('Feature gate "%s" denied access.', $flagKey);

        if ($this->onDisabled === 'access_denied') {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException($message);
        }

        throw new HttpException($this->statusCode, $message);
    }
}
