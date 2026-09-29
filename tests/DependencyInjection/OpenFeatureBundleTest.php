<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\DependencyInjection;

use Aubes\OpenFeatureBundle\ArgumentResolver\FeatureFlagValueResolver;
use Aubes\OpenFeatureBundle\Command\DebugFeatureFlagsCommand;
use Aubes\OpenFeatureBundle\EvaluationContext\UserEvaluationContextProvider;
use Aubes\OpenFeatureBundle\EventListener\EvaluationContextListener;
use Aubes\OpenFeatureBundle\EventListener\FeatureGateListener;
use Aubes\OpenFeatureBundle\OpenFeatureBundle;
use Aubes\OpenFeatureBundle\Profiler\ContextProviderRecorder;
use Aubes\OpenFeatureBundle\Profiler\OpenFeatureDataCollector;
use Aubes\OpenFeatureBundle\Profiler\ProfilerHook;
use Aubes\OpenFeatureBundle\Provider\InMemoryProvider;
use Aubes\OpenFeatureBundle\Tests\Fixtures\ContextRecordingHook;
use Aubes\OpenFeatureBundle\Twig\OpenFeatureExtension;
use OpenFeature\interfaces\flags\API;
use OpenFeature\interfaces\flags\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(OpenFeatureBundle::class)]
class OpenFeatureBundleTest extends TestCase
{
    private const SECURITY_BUNDLE = ['SecurityBundle' => 'Symfony\Bundle\SecurityBundle\SecurityBundle'];

    /**
     * Loads the extension like a kernel does: in the isolated container of MergeExtensionConfigurationPass, where only parameters are visible.
     *
     * @param array<string, mixed>  $config
     * @param array<string, string> $bundles
     */
    private function createContainer(array $config = [], bool $debug = false, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => $debug,
            'kernel.environment' => $debug ? 'dev' : 'prod',
            'kernel.project_dir' => __DIR__,
            'kernel.build_dir' => __DIR__ . '/cache',
            'kernel.cache_dir' => __DIR__ . '/cache',
            'kernel.bundles' => $bundles,
            'kernel.bundles_metadata' => [],
        ]));

        $bundle = new OpenFeatureBundle();
        $bundle->build($container);
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('open_feature', $config);

        (new MergeExtensionConfigurationPass())->process($container);
        // Already merged above: merging again on compile() would load the extension twice
        $container->getCompilerPassConfig()->setMergePass(new class extends MergeExtensionConfigurationPass {
            public function process(ContainerBuilder $container): void
            {
            }
        });

        return $container;
    }

    /** @param array<string, mixed> $config */
    private function buildContainer(array $config = [], bool $debug = false): ContainerBuilder
    {
        $container = $this->createContainer($config, $debug);
        $container->compile();

        return $container;
    }

    public function testRegistersTheCoreServices(): void
    {
        $container = $this->createContainer();

        $this->assertTrue($container->hasDefinition(API::class));
        $this->assertTrue($container->hasDefinition(Client::class));
        $this->assertTrue($container->hasDefinition(InMemoryProvider::class));
        $this->assertTrue($container->hasDefinition(EvaluationContextListener::class));
        $this->assertTrue($container->hasDefinition(FeatureGateListener::class));
        $this->assertTrue($container->hasDefinition(FeatureFlagValueResolver::class));
        $this->assertTrue($container->hasDefinition(OpenFeatureExtension::class));

        $listenerDef = $container->getDefinition(EvaluationContextListener::class);
        $this->assertTrue($listenerDef->hasTag('kernel.reset'));
    }

    public function testDefaultProviderIsInMemory(): void
    {
        $container = $this->buildContainer();

        $this->assertSame(InMemoryProvider::class, $container->getParameter('open_feature.provider'));
    }

    public function testFlagsParameter(): void
    {
        $container = $this->buildContainer([
            'flags' => ['dark_mode' => true, 'max_items' => 10],
        ]);

        $this->assertSame(
            ['dark_mode' => true, 'max_items' => 10],
            $container->getParameter('open_feature.flags'),
        );
    }

    public function testCustomProvider(): void
    {
        $container = $this->createContainer(['provider' => 'app.custom_provider']);
        $container->register('app.custom_provider', InMemoryProvider::class);
        $container->compile();

        $this->assertSame('app.custom_provider', $container->getParameter('open_feature.provider'));
    }

    public function testCompilationValidatesTheProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OpenFeature provider service "app.bad_provider" (class "stdClass") must implement interface "OpenFeature\interfaces\provider\Provider".');

        $container = $this->createContainer(['provider' => 'app.bad_provider']);
        $container->register('app.bad_provider', \stdClass::class);
        $container->compile();
    }

    public function testProfilerRegisteredInDebugMode(): void
    {
        $container = $this->createContainer([], debug: true);

        $this->assertTrue($container->hasDefinition(ProfilerHook::class));
        $this->assertTrue($container->hasDefinition(OpenFeatureDataCollector::class));
        $this->assertTrue($container->getDefinition(ContextProviderRecorder::class)->hasTag('kernel.reset'));
    }

    public function testProfilerNotRegisteredInProdMode(): void
    {
        $container = $this->createContainer([], debug: false);

        $this->assertFalse($container->hasDefinition(ProfilerHook::class));
        $this->assertFalse($container->hasDefinition(OpenFeatureDataCollector::class));
    }

    public function testFeatureFlagOnDisabledAutoWithSecurityBundle(): void
    {
        $container = $this->createContainer(bundles: self::SECURITY_BUNDLE);

        $this->assertSame('access_denied', $container->getParameter('open_feature.feature_flag.on_disabled'));
    }

    // symfony/security-core is installed (dev dependency), but only the SecurityBundle firewall turns the exception into a 403
    public function testFeatureFlagOnDisabledAutoWithoutSecurityBundle(): void
    {
        $container = $this->createContainer();

        $this->assertSame('http_exception', $container->getParameter('open_feature.feature_flag.on_disabled'));
    }

    public function testFeatureFlagOnDisabledHttpException(): void
    {
        $container = $this->buildContainer([
            'feature_flag' => ['on_disabled' => 'http_exception', 'status_code' => 404],
        ]);

        $this->assertSame('http_exception', $container->getParameter('open_feature.feature_flag.on_disabled'));
        $this->assertSame(404, $container->getParameter('open_feature.feature_flag.status_code'));
    }

    public function testUserProviderAutoEnabledWithSecurityBundle(): void
    {
        $container = $this->createContainer(bundles: self::SECURITY_BUNDLE);

        $this->assertSame('true', $container->getParameter('open_feature.evaluation_context.user_provider'));

        $definition = $container->getDefinition(UserEvaluationContextProvider::class);
        $this->assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $definition->getArguments());
        $this->assertSame([['priority' => 0]], $definition->getTag('openfeature.evaluation_context_provider'));
    }

    public function testUserProviderToleratesSecurityBundleWithoutTokenStorage(): void
    {
        // Symfony 6.4 registers no security service when SecurityBundle is enabled but not configured
        $container = $this->createContainer(bundles: self::SECURITY_BUNDLE);
        (new CheckExceptionOnInvalidReferenceBehaviorPass())->process($container);

        $provider = $container->get(UserEvaluationContextProvider::class);
        $this->assertInstanceOf(UserEvaluationContextProvider::class, $provider);
        $this->assertNull($provider->getContext(Request::create('/')));
    }

    public function testUserProviderAutoDisabledWithoutSecurityBundle(): void
    {
        $container = $this->createContainer();

        $this->assertSame('false', $container->getParameter('open_feature.evaluation_context.user_provider'));
        $this->assertFalse($container->hasDefinition(UserEvaluationContextProvider::class));
    }

    public function testUserProviderExplicitlyEnabledWithSecurityBundle(): void
    {
        $container = $this->createContainer(['evaluation_context' => ['user_provider' => true]], bundles: self::SECURITY_BUNDLE);

        $this->assertTrue($container->hasDefinition(UserEvaluationContextProvider::class));
    }

    public function testUserProviderExplicitlyEnabledWithoutSecurityBundleThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Setting "user_provider" to "true" requires symfony/security-bundle to be enabled. Install and enable it or use "false".');

        $this->createContainer(['evaluation_context' => ['user_provider' => true]]);
    }

    public function testUserProviderDisabled(): void
    {
        $container = $this->buildContainer([
            'evaluation_context' => ['user_provider' => 'false'],
        ]);

        $this->assertSame('false', $container->getParameter('open_feature.evaluation_context.user_provider'));
    }

    public function testDebugCommandRegistered(): void
    {
        $container = $this->createContainer(debug: true);

        $this->assertTrue($container->hasDefinition(DebugFeatureFlagsCommand::class));
    }

    public function testDebugCommandNotRegisteredInProd(): void
    {
        $container = $this->createContainer(debug: false);

        $this->assertFalse($container->hasDefinition(DebugFeatureFlagsCommand::class));
    }

    public function testDataCollectorReceivesProvidersAndStrategy(): void
    {
        $container = $this->createContainer([
            'providers' => ['local' => InMemoryProvider::class],
            'strategy' => 'first_successful',
        ], debug: true);

        $definition = $container->getDefinition(OpenFeatureDataCollector::class);
        $this->assertSame(['local' => InMemoryProvider::class], $definition->getArgument(3));
        $this->assertSame(['type' => 'first_successful', 'fallback' => null], $definition->getArgument(4));
    }

    public function testDataCollectorStrategyIsNullInSingleProviderMode(): void
    {
        $container = $this->createContainer([], debug: true);

        $definition = $container->getDefinition(OpenFeatureDataCollector::class);
        $this->assertSame([], $definition->getArgument(3));
        $this->assertNull($definition->getArgument(4));
    }

    public function testProviderAndProvidersAreMutuallyExclusive(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid "open_feature" configuration: "provider" and "providers" are mutually exclusive.');

        $this->createContainer([
            'provider' => 'app.custom_provider',
            'providers' => ['local' => InMemoryProvider::class],
        ]);
    }

    public function testStrategyRequiresProviders(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid "open_feature" configuration: "strategy" requires "providers".');

        $this->createContainer(['strategy' => 'first_successful']);
    }

    public function testComparisonStrategyRequiresFallback(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid "open_feature" configuration: the "comparison" strategy requires "strategy.fallback".');

        $this->createContainer([
            'providers' => ['local' => InMemoryProvider::class],
            'strategy' => 'comparison',
        ]);
    }

    public function testFallbackOnlyAllowedForComparison(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid "open_feature" configuration: "strategy.fallback" is only allowed when "strategy.type" is "comparison".');

        $this->createContainer([
            'providers' => ['local' => InMemoryProvider::class],
            'strategy' => ['type' => 'first_match', 'fallback' => 'local'],
        ]);
    }

    public function testFallbackMustBeADeclaredProviderName(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid "open_feature" configuration: "strategy.fallback" must be one of the names declared in "providers".');

        $this->createContainer([
            'providers' => ['local' => InMemoryProvider::class],
            'strategy' => ['type' => 'comparison', 'fallback' => 'unknown'],
        ]);
    }

    public function testProviderNamesMustBeUniqueCaseInsensitive(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid "open_feature" configuration: provider names in "providers" must be unique (case-insensitive, the SDK normalizes them to lowercase).');

        $this->createContainer([
            'providers' => [
                'Local' => InMemoryProvider::class,
                'local' => 'app.custom_provider',
            ],
        ]);
    }

    public function testHookInterfaceIsAutoconfigured(): void
    {
        $container = $this->createContainer();
        $container->register('app.custom_hook', ContextRecordingHook::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->compile();

        $definition = $container->getDefinition('app.custom_hook');
        $this->assertTrue($definition->hasTag('openfeature.hook'));
    }
}
