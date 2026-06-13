<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\DependencyInjection\Compiler;

use OpenFeature\implementation\multiprovider\MultiProvider;
use OpenFeature\implementation\multiprovider\strategy\ComparisonStrategy;
use OpenFeature\implementation\multiprovider\strategy\FirstMatchStrategy;
use OpenFeature\implementation\multiprovider\strategy\FirstSuccessfulStrategy;
use OpenFeature\interfaces\flags\API;
use OpenFeature\interfaces\provider\Provider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SetProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, string> $providers */
        $providers = $container->getParameter('open_feature.providers');

        if ($providers !== []) {
            $this->registerMultiProvider($container, $providers);

            return;
        }

        $providerId = $container->getParameter('open_feature.provider');

        if (!\is_string($providerId)) {
            return;
        }

        $definition = $this->validateProvider($container, $providerId);

        if ($container->has('logger')) {
            $definition->addMethodCall('setLogger', [new Reference('logger')]);
        }

        $container->getDefinition(API::class)
            ->addMethodCall('setProvider', [new Reference($providerId)]);
    }

    /**
     * @param array<string, string> $providers
     */
    private function registerMultiProvider(ContainerBuilder $container, array $providers): void
    {
        $hasLogger = $container->has('logger');
        $providerData = [];

        foreach ($providers as $name => $providerId) {
            $definition = $this->validateProvider($container, $providerId);

            if ($hasLogger) {
                $definition->addMethodCall('setLogger', [new Reference('logger')]);
            }

            $providerData[] = ['name' => (string) $name, 'provider' => new Reference($providerId)];
        }

        /** @var array{type: string, fallback: null|string} $strategy */
        $strategy = $container->getParameter('open_feature.strategy');

        $strategyDefinition = match ($strategy['type']) {
            'first_successful' => new Definition(FirstSuccessfulStrategy::class),
            'comparison' => new Definition(ComparisonStrategy::class, [new Reference($providers[(string) $strategy['fallback']])]),
            default => new Definition(FirstMatchStrategy::class),
        };

        $definition = new Definition(MultiProvider::class, [$providerData, $strategyDefinition]);

        if ($hasLogger) {
            $definition->addMethodCall('setLogger', [new Reference('logger')]);
        }

        $container->getDefinition(API::class)
            ->addMethodCall('setProvider', [$definition]);
    }

    private function validateProvider(ContainerBuilder $container, string $providerId): Definition
    {
        $definition = $container->findDefinition($providerId);
        $class = $definition->getClass();

        if ($class !== null && !\is_subclass_of($class, Provider::class)) {
            throw new \InvalidArgumentException(\sprintf('The service "%s" (class "%s") configured as OpenFeature provider must implement "%s".', $providerId, $class, Provider::class));
        }

        return $definition;
    }
}
