<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\DependencyInjection\Compiler;

use OpenFeature\interfaces\flags\API;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterHooksPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(API::class)) {
            return;
        }

        $api = $container->getDefinition(API::class);
        $taggedServices = $container->findTaggedServiceIds('openfeature.hook');

        foreach ($taggedServices as $id => $tags) {
            $api->addMethodCall('addHooks', [new Reference($id)]);
        }
    }
}
