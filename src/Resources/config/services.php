<?php

declare(strict_types=1);

use Aubes\OpenFeatureBundle\ArgumentResolver\FeatureFlagValueResolver;
use Aubes\OpenFeatureBundle\EventListener\EvaluationContextListener;
use Aubes\OpenFeatureBundle\EventListener\FeatureGateListener;
use Aubes\OpenFeatureBundle\Provider\EnvVarProvider;
use Aubes\OpenFeatureBundle\Provider\InMemoryProvider;
use Aubes\OpenFeatureBundle\Twig\OpenFeatureExtension;
use OpenFeature\interfaces\flags\API;
use OpenFeature\interfaces\flags\Client;
use OpenFeature\OpenFeatureAPI;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(API::class, OpenFeatureAPI::class)
        ->factory([OpenFeatureAPI::class, 'getInstance']);

    $services->set(Client::class)
        ->factory([service(API::class), 'getClient'])
        ->lazy();

    $services->set(InMemoryProvider::class)
        ->args([param('open_feature.flags')]);

    $services->set(EnvVarProvider::class);

    $services->set(EvaluationContextListener::class)
        ->args([
            service(API::class),
            tagged_iterator('openfeature.evaluation_context_provider'),
            service('event_dispatcher')->nullOnInvalid(),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 4])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(FeatureGateListener::class)
        ->args([
            service(Client::class),
            param('open_feature.feature_flag.on_disabled'),
            param('open_feature.feature_flag.status_code'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onKernelController']);

    $services->set(FeatureFlagValueResolver::class)
        ->args([service(Client::class)])
        ->tag('controller.argument_value_resolver');

    if (\class_exists(Twig\Extension\AbstractExtension::class)) {
        $services->set(OpenFeatureExtension::class)
            ->args([service(Client::class)])
            ->tag('twig.extension');
    }
};
