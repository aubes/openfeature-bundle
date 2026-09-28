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
use Symfony\Component\DependencyInjection\ContainerInterface;
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

        $this->injectLogger($this->validateProvider($container, $providerId));

        $container->getDefinition(API::class)
            ->addMethodCall('setProvider', [new Reference($providerId)]);
    }

    /**
     * @param array<string, string> $providers
     */
    private function registerMultiProvider(ContainerBuilder $container, array $providers): void
    {
        $providerData = [];

        foreach ($providers as $name => $providerId) {
            $this->injectLogger($this->validateProvider($container, $providerId));

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
        $this->injectLogger($definition);

        $container->getDefinition(API::class)
            ->addMethodCall('setProvider', [$definition]);
    }

    private function injectLogger(Definition $definition): void
    {
        // Keeps a logger already set on the service (explicit call or LoggerAwareInterface autoconfiguration)
        if ($definition->hasMethodCall('setLogger')) {
            return;
        }

        // Optional: without MonologBundle, FrameworkBundle's LoggerPass registers the default logger after this pass
        $definition->addMethodCall('setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]);
    }

    private function validateProvider(ContainerBuilder $container, string $providerId): Definition
    {
        $definition = $container->findDefinition($providerId);
        /** @var null|string $class */
        $class = $container->getParameterBag()->resolveValue($definition->getClass());

        // Runs before parent resolution, like the core voter and env var processor passes: the class must be set on the service itself
        if (!$reflection = $container->getReflectionClass($class)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" used for OpenFeature provider service "%s" cannot be found. Set the "class" option on the service, including when it is created by a factory or inherits from a parent.', $class, $providerId));
        }

        if (!$reflection->implementsInterface(Provider::class)) {
            throw new \InvalidArgumentException(\sprintf('OpenFeature provider service "%s" (class "%s") must implement interface "%s".', $providerId, $reflection->getName(), Provider::class));
        }

        return $definition;
    }
}
