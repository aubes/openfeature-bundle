<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle;

use Aubes\OpenFeatureBundle\Command\DebugFeatureFlagsCommand;
use Aubes\OpenFeatureBundle\DependencyInjection\Compiler\RegisterHooksPass;
use Aubes\OpenFeatureBundle\DependencyInjection\Compiler\SetProviderPass;
use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use Aubes\OpenFeatureBundle\EvaluationContext\UserEvaluationContextProvider;
use Aubes\OpenFeatureBundle\Profiler\ContextProviderRecorder;
use Aubes\OpenFeatureBundle\Profiler\OpenFeatureDataCollector;
use Aubes\OpenFeatureBundle\Profiler\ProfilerHook;
use OpenFeature\interfaces\flags\API;
use OpenFeature\interfaces\flags\Client;
use OpenFeature\interfaces\hooks\Hook;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\RouterInterface;

class OpenFeatureBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $rootNode
            ->children()
                ->scalarNode('provider')
                    ->info('Service ID of the OpenFeature provider. Defaults to the built-in InMemoryProvider. Mutually exclusive with "providers".')
                    ->defaultNull()
                ->end()
                ->arrayNode('providers')
                    ->info('Multiple providers combined through the SDK MultiProvider. Keys are provider names, values are service IDs. Evaluation follows declaration order.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('strategy')
                    ->info('Evaluation strategy used by the MultiProvider. Shorthand: a plain string sets "type".')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static fn (string $v) => ['type' => $v])
                    ->end()
                    ->children()
                        ->enumNode('type')
                            ->values(['first_match', 'first_successful', 'comparison'])
                            ->defaultValue('first_match')
                        ->end()
                        ->scalarNode('fallback')
                            ->info('Provider name (key of "providers") used as fallback on mismatch. Required and only allowed when type is "comparison".')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('flags')
                    ->info('Flag values for the built-in InMemoryProvider (local/dev use).')
                    // No key attribute: it would re-key object flags holding that key
                    ->normalizeKeys(false)
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
                            ->info('Populate the EvaluationContext targeting key from the authenticated Symfony user identifier, which is sent to the flag provider. "auto" enables it if SecurityBundle is enabled. Disabled by default.')
                            ->beforeNormalization()
                                ->ifTrue(\is_bool(...))
                                ->then(static fn (bool $v): string => $v ? 'true' : 'false')
                            ->end()
                            ->values(['auto', 'true', 'false'])
                            ->defaultValue('false')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('feature_flag')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('on_disabled')
                            ->info('Exception thrown when a #[FeatureGate] flag is disabled. "auto" uses "access_denied" if SecurityBundle is enabled, "http_exception" otherwise.')
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
     * @param array{provider: null|string, providers: array<string, string>, strategy: array{type: string, fallback: null|string}} $config
     */
    private function validateProviderConfig(array $config): void
    {
        $providers = $config['providers'];
        $strategy = $config['strategy'];

        if ($config['provider'] !== null && $providers !== []) {
            throw new InvalidConfigurationException('Invalid "open_feature" configuration: "provider" and "providers" are mutually exclusive.');
        }

        if ($providers === [] && $strategy['type'] !== 'first_match') {
            throw new InvalidConfigurationException('Invalid "open_feature" configuration: "strategy" requires "providers".');
        }

        if ($strategy['type'] === 'comparison' && $strategy['fallback'] === null) {
            throw new InvalidConfigurationException('Invalid "open_feature" configuration: the "comparison" strategy requires "strategy.fallback".');
        }

        if ($strategy['type'] !== 'comparison' && $strategy['fallback'] !== null) {
            throw new InvalidConfigurationException('Invalid "open_feature" configuration: "strategy.fallback" is only allowed when "strategy.type" is "comparison".');
        }

        if ($strategy['fallback'] !== null && !isset($providers[$strategy['fallback']])) {
            throw new InvalidConfigurationException('Invalid "open_feature" configuration: "strategy.fallback" must be one of the names declared in "providers".');
        }

        $names = \array_map(\strtolower(...), \array_keys($providers));
        if (\count($names) !== \count(\array_unique($names))) {
            throw new InvalidConfigurationException('Invalid "open_feature" configuration: provider names in "providers" must be unique (case-insensitive, the SDK normalizes them to lowercase).');
        }
    }

    /**
     * @param array{
     *     provider: null|string,
     *     providers: array<string, string>,
     *     strategy: array{type: string, fallback: null|string},
     *     flags: array<string, mixed>,
     *     redis?: array{client: string, prefix: string},
     *     feature_flag: array{on_disabled: string, status_code: int},
     *     evaluation_context: array{user_provider: string},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $this->validateProviderConfig($config);

        $loader = new PhpFileLoader($builder, new FileLocator(__DIR__ . '/Resources/config'));
        $loader->load('services.php');

        $builder->registerForAutoconfiguration(Hook::class)
            ->addTag('openfeature.hook');

        $builder->registerForAutoconfiguration(EvaluationContextProviderInterface::class)
            ->addTag('openfeature.evaluation_context_provider');

        $provider = $config['provider'];
        if ($provider === null && $config['providers'] === []) {
            $provider = Provider\InMemoryProvider::class;
        }

        $builder->setParameter('open_feature.provider', $provider);
        $builder->setParameter('open_feature.providers', $config['providers']);
        $builder->setParameter('open_feature.strategy', $config['strategy']);
        $builder->setParameter('open_feature.flags', $config['flags']);

        if (isset($config['redis'])) {
            $definition = new Definition(Provider\RedisProvider::class);
            $definition->addArgument(new Reference($config['redis']['client']));
            $definition->addArgument($config['redis']['prefix']);
            $builder->setDefinition(Provider\RedisProvider::class, $definition);
        }

        // Services from other bundles are not visible here (isolated merge container), only parameters are
        /** @var array<string, class-string> $bundles */
        $bundles = $builder->getParameter('kernel.bundles');
        $hasSecurityBundle = isset($bundles['SecurityBundle']);

        $onDisabled = $config['feature_flag']['on_disabled'];
        if ($onDisabled === 'auto') {
            // Only the SecurityBundle firewall turns an AccessDeniedException into a 403 (or a login redirect)
            $onDisabled = $hasSecurityBundle
                ? 'access_denied'
                : 'http_exception';
        } elseif ($onDisabled === 'access_denied' && !\class_exists(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class)) {
            throw new \LogicException('Setting "on_disabled" to "access_denied" requires symfony/security-core. Install it or use "http_exception".');
        }

        $builder->setParameter('open_feature.feature_flag.on_disabled', $onDisabled);
        $builder->setParameter('open_feature.feature_flag.status_code', $config['feature_flag']['status_code']);

        $userProvider = $config['evaluation_context']['user_provider'];
        if ($userProvider === 'auto') {
            $userProvider = $hasSecurityBundle
                ? 'true'
                : 'false';
        } elseif ($userProvider === 'true' && !$hasSecurityBundle) {
            throw new \LogicException('Setting "user_provider" to "true" requires symfony/security-bundle to be enabled. Install and enable it or use "false".');
        }
        $builder->setParameter('open_feature.evaluation_context.user_provider', $userProvider);

        if ($userProvider === 'true') {
            $definition = new Definition(UserEvaluationContextProvider::class);
            // Symfony 6.4 registers no security service when SecurityBundle is enabled but not configured
            $definition->addArgument(new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addTag('openfeature.evaluation_context_provider', ['priority' => 0]);
            $builder->setDefinition(UserEvaluationContextProvider::class, $definition);
        }

        if ($builder->getParameter('kernel.debug') && \interface_exists(RouterInterface::class)) {
            $builder->register(DebugFeatureFlagsCommand::class)
                ->setArguments([
                    new Reference(Client::class),
                    new Reference(API::class),
                    new Reference('router'),
                ])
                ->addTag('console.command');
        }

        if ($builder->getParameter('kernel.debug')) {
            $builder->register(ProfilerHook::class)
                ->setPublic(false)
                ->setAutoconfigured(true)
                ->addTag('openfeature.hook');

            $builder->register(ContextProviderRecorder::class)
                ->setPublic(false)
                ->setAutoconfigured(true)
                ->addTag('kernel.reset', ['method' => 'reset']);

            $builder->register(OpenFeatureDataCollector::class)
                ->addArgument(new Reference(ProfilerHook::class))
                ->addArgument(new Reference(API::class))
                ->addArgument(new Reference(ContextProviderRecorder::class))
                ->addArgument($config['providers'])
                ->addArgument($config['providers'] === [] ? null : $config['strategy'])
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
