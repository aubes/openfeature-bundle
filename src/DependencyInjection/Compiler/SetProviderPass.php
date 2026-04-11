<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\DependencyInjection\Compiler;

use OpenFeature\interfaces\flags\API;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SetProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $providerId = $container->getParameter('open_feature.provider');

        if ($providerId === null || !\is_string($providerId)) {
            return;
        }

        $definition = $container->findDefinition($providerId);
        $class = $definition->getClass();

        if ($class !== null && !\is_subclass_of($class, \OpenFeature\interfaces\provider\Provider::class)) {
            throw new \InvalidArgumentException(\sprintf('The service "%s" (class "%s") configured as OpenFeature provider must implement "%s".', $providerId, $class, \OpenFeature\interfaces\provider\Provider::class));
        }

        if ($container->has('logger')) {
            $definition->addMethodCall('setLogger', [new Reference('logger')]);
        }

        $container->getDefinition(API::class)
            ->addMethodCall('setProvider', [new Reference($providerId)]);
    }
}
