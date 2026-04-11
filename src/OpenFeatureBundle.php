<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle;

use Aubes\OpenFeatureBundle\DependencyInjection\Compiler\RegisterHooksPass;
use Aubes\OpenFeatureBundle\DependencyInjection\Compiler\SetProviderPass;
use Aubes\OpenFeatureBundle\EvaluationContext\UserEvaluationContextProvider;
use Aubes\OpenFeatureBundle\Profiler\OpenFeatureDataCollector;
use Aubes\OpenFeatureBundle\Profiler\ProfilerHook;
use OpenFeature\interfaces\flags\API;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class OpenFeatureBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('provider')
                    ->info('Service ID of the OpenFeature provider. Defaults to the built-in InMemoryProvider.')
                    ->defaultValue(Provider\InMemoryProvider::class)
                ->end()
                ->arrayNode('flags')
                    ->info('Flag values for the built-in InMemoryProvider (local/dev use).')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()->end()
                ->end()
                ->arrayNode('redis')
                    ->info('Configuration for the built-in RedisProvider. Requires a service implementing RedisClientInterface.')
                    ->children()
                        ->scalarNode('client')
                            ->info('Service ID implementing Aubes\OpenFeatureBundle\Provider\Redis\RedisClientInterface.')
                            ->isRequired()
                        ->end()
                        ->scalarNode('prefix')
                            ->info('Key prefix for Redis flag keys.')
                            ->defaultValue('feature:')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('evaluation_context')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('user_provider')
                            ->info('Populate EvaluationContext targeting key from the authenticated Symfony user. "auto" enables it if symfony/security-core is available.')
                            ->values(['auto', 'true', 'false'])
                            ->defaultValue('auto')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('feature_flag')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('on_disabled')
                            ->info('Exception thrown when a #[FeatureFlag] method-level flag is disabled. "auto" detects symfony/security-core availability.')
                            ->values(['auto', 'access_denied', 'http_exception'])
                            ->defaultValue('auto')
                        ->end()
                        ->integerNode('status_code')
                            ->info('HTTP status code used when on_disabled is "http_exception".')
                            ->defaultValue(403)
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $v) => $v['on_disabled'] === 'access_denied' && $v['status_code'] !== 403)
                        ->thenInvalid('"status_code" has no effect when "on_disabled" is "access_denied".')
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array{
     *     provider: string,
     *     flags: array<string, mixed>,
     *     redis?: array{client: string, prefix: string},
     *     feature_flag: array{on_disabled: string, status_code: int},
     *     evaluation_context: array{user_provider: string},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $loader = new PhpFileLoader($builder, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.php');

        $builder->setParameter('open_feature.provider', $config['provider']);
        $builder->setParameter('open_feature.flags', $config['flags']);

        if (isset($config['redis'])) {
            $definition = new Definition(Provider\RedisProvider::class);
            $definition->addArgument(new Reference($config['redis']['client']));
            $definition->addArgument($config['redis']['prefix']);
            $builder->setDefinition(Provider\RedisProvider::class, $definition);
        }

        $onDisabled = $config['feature_flag']['on_disabled'];
        $hasSecurityCore = \class_exists(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        if ($onDisabled === 'auto') {
            $onDisabled = $hasSecurityCore
                ? 'access_denied'
                : 'http_exception';
        } elseif ($onDisabled === 'access_denied' && !$hasSecurityCore) {
            throw new \LogicException('Setting "on_disabled" to "access_denied" requires symfony/security-core. Install it or use "http_exception".');
        }

        $builder->setParameter('open_feature.feature_flag.on_disabled', $onDisabled);
        $builder->setParameter('open_feature.feature_flag.status_code', $config['feature_flag']['status_code']);

        $userProvider = $config['evaluation_context']['user_provider'];
        $hasTokenStorage = $builder->has('security.token_storage');
        if ($userProvider === 'auto') {
            $userProvider = $hasTokenStorage
                ? 'true'
                : 'false';
        } elseif ($userProvider === 'true' && !$hasTokenStorage) {
            throw new \LogicException('Setting "user_provider" to "true" requires symfony/security-bundle to be enabled. Install and enable it or use "false".');
        }
        $builder->setParameter('open_feature.evaluation_context.user_provider', $userProvider);

        if ($userProvider === 'true') {
            $definition = new Definition(UserEvaluationContextProvider::class);
            $definition->addArgument(new Reference('security.token_storage'));
            $definition->addTag('openfeature.evaluation_context_provider', ['priority' => 0]);
            $builder->setDefinition(UserEvaluationContextProvider::class, $definition);
        }

        if ($builder->getParameter('kernel.debug')) {
            $builder->register(ProfilerHook::class)
                ->setPublic(false)
                ->setAutoconfigured(true)
                ->addTag('openfeature.hook');

            $builder->register(OpenFeatureDataCollector::class)
                ->addArgument(new Reference(ProfilerHook::class))
                ->addArgument(new Reference(API::class))
                ->addTag('data_collector', [
                    'template' => '@OpenFeature/Collector/openfeature.html.twig',
                    'id' => 'open_feature',
                ]);
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('twig')) {
            return;
        }

        $builder->prependExtensionConfig('twig', [
            'paths' => [__DIR__ . '/Resources/views' => 'OpenFeature'],
        ]);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new SetProviderPass());
        $container->addCompilerPass(new RegisterHooksPass());
    }
}
