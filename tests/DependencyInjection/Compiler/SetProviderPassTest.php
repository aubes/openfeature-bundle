<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\DependencyInjection\Compiler;

use Aubes\OpenFeatureBundle\DependencyInjection\Compiler\SetProviderPass;
use Aubes\OpenFeatureBundle\Provider\InMemoryProvider;
use Aubes\OpenFeatureBundle\Tests\Fixtures\ProviderWithMissingParent;
use OpenFeature\implementation\multiprovider\MultiProvider;
use OpenFeature\implementation\multiprovider\strategy\ComparisonStrategy;
use OpenFeature\implementation\multiprovider\strategy\FirstMatchStrategy;
use OpenFeature\implementation\multiprovider\strategy\FirstSuccessfulStrategy;
use OpenFeature\interfaces\flags\API;
use OpenFeature\interfaces\provider\Provider;
use OpenFeature\OpenFeatureAPI;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(SetProviderPass::class)]
class SetProviderPassTest extends TestCase
{
    public function testWiresTheProviderOnTheApi(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider', InMemoryProvider::class);

        $this->process($container);

        $this->assertEquals(
            [['setProvider', [new Reference('app.provider')]]],
            $container->getDefinition(API::class)->getMethodCalls(),
        );
    }

    // No "logger" service yet: without MonologBundle, it is registered after this pass
    public function testInjectsAnOptionalLoggerIntoTheProvider(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider', InMemoryProvider::class);

        $this->process($container);

        $this->assertEquals(
            [['setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]]],
            $container->getDefinition('app.provider')->getMethodCalls(),
        );
    }

    /** @param array<string, string> $providers */
    #[DataProvider('provideSingleAndMultiProvider')]
    public function testKeepsALoggerAlreadySetOnTheProvider(?string $provider, array $providers): void
    {
        $container = $this->createContainer(provider: $provider, providers: $providers);
        $container->register('app.provider', InMemoryProvider::class)
            ->addMethodCall('setLogger', [new Reference('app.feature_logger')]);

        $this->process($container);

        $this->assertEquals(
            [['setLogger', [new Reference('app.feature_logger')]]],
            $container->getDefinition('app.provider')->getMethodCalls(),
        );
    }

    /** @return array<string, array{?string, array<string, string>}> */
    public static function provideSingleAndMultiProvider(): array
    {
        return [
            'single provider' => ['app.provider', []],
            'multi-provider' => [null, ['local' => 'app.provider']],
        ];
    }

    public function testValidatesAndConfiguresTheServiceBehindAnAlias(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.real_provider', InMemoryProvider::class);
        $container->setAlias('app.provider', 'app.real_provider');

        $this->process($container);

        $this->assertEquals(
            [['setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]]],
            $container->getDefinition('app.real_provider')->getMethodCalls(),
        );
        $this->assertEquals(
            [['setProvider', [new Reference('app.provider')]]],
            $container->getDefinition(API::class)->getMethodCalls(),
        );
    }

    public function testRejectsAClassThatIsNotAProvider(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider', \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OpenFeature provider service "app.provider" (class "stdClass") must implement interface "OpenFeature\interfaces\provider\Provider".');

        $this->process($container);
    }

    public function testRejectsAClassThatDoesNotExist(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider', 'App\Missing\Provider');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "App\Missing\Provider" used for OpenFeature provider service "app.provider" cannot be found. Set the "class" option on the service, including when it is created by a factory or inherits from a parent.');

        $this->process($container);
    }

    public function testReportsTheMissingParentOfAProviderClass(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider', ProviderWithMissingParent::class);

        $this->expectException(\ReflectionException::class);
        $this->expectExceptionMessage(\sprintf('Class "Aubes\OpenFeatureBundle\Tests\Fixtures\Missing\ParentProvider" not found while loading "%s".', ProviderWithMissingParent::class));

        $this->process($container);
    }

    public function testRequiresTheClassOfAFactoryProvider(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider')->setFactory([InMemoryProvider::class, 'new']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "" used for OpenFeature provider service "app.provider" cannot be found. Set the "class" option on the service, including when it is created by a factory or inherits from a parent.');

        $this->process($container);
    }

    public function testAcceptsAFactoryProviderWithItsClass(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider', InMemoryProvider::class)->setFactory([InMemoryProvider::class, 'new']);

        $this->process($container);

        $this->assertCount(1, $container->getDefinition(API::class)->getMethodCalls());
    }

    public function testAcceptsAFactoryProviderDeclaredWithTheProviderInterface(): void
    {
        $container = $this->createContainer(provider: 'app.provider');
        $container->register('app.provider', Provider::class)->setFactory([InMemoryProvider::class, 'new']);

        $this->process($container);

        $this->assertCount(1, $container->getDefinition(API::class)->getMethodCalls());
    }

    public function testRequiresTheClassOfAChildProvider(): void
    {
        $container = $this->createContainer(provider: 'app.child_provider');
        $container->register('app.base_provider', InMemoryProvider::class)->setAbstract(true);
        $container->setDefinition('app.child_provider', new ChildDefinition('app.base_provider'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "" used for OpenFeature provider service "app.child_provider" cannot be found. Set the "class" option on the service, including when it is created by a factory or inherits from a parent.');

        $this->process($container);
    }

    public function testBuildsAMultiProviderInDeclarationOrder(): void
    {
        $container = $this->createContainer(providers: [
            'custom' => 'app.custom_provider',
            'local' => InMemoryProvider::class,
        ]);
        $container->register('app.custom_provider', InMemoryProvider::class);
        $container->register(InMemoryProvider::class);

        $this->process($container);

        $definition = $this->getMultiProviderDefinition($container);
        $this->assertEquals([
            ['name' => 'custom', 'provider' => new Reference('app.custom_provider')],
            ['name' => 'local', 'provider' => new Reference(InMemoryProvider::class)],
        ], $definition->getArgument(0));

        $strategy = $definition->getArgument(1);
        $this->assertInstanceOf(Definition::class, $strategy);
        $this->assertSame(FirstMatchStrategy::class, $strategy->getClass());
    }

    public function testBuildsTheFirstSuccessfulStrategy(): void
    {
        $container = $this->createContainer(
            providers: ['local' => InMemoryProvider::class],
            strategy: ['type' => 'first_successful', 'fallback' => null],
        );
        $container->register(InMemoryProvider::class);

        $this->process($container);

        $strategy = $this->getMultiProviderDefinition($container)->getArgument(1);
        $this->assertInstanceOf(Definition::class, $strategy);
        $this->assertSame(FirstSuccessfulStrategy::class, $strategy->getClass());
    }

    public function testBuildsTheComparisonStrategyWithItsFallbackProvider(): void
    {
        $container = $this->createContainer(
            providers: ['custom' => 'app.custom_provider', 'local' => InMemoryProvider::class],
            strategy: ['type' => 'comparison', 'fallback' => 'local'],
        );
        $container->register('app.custom_provider', InMemoryProvider::class);
        $container->register(InMemoryProvider::class);

        $this->process($container);

        $strategy = $this->getMultiProviderDefinition($container)->getArgument(1);
        $this->assertInstanceOf(Definition::class, $strategy);
        $this->assertSame(ComparisonStrategy::class, $strategy->getClass());
        $this->assertEquals([new Reference(InMemoryProvider::class)], $strategy->getArguments());
    }

    public function testInjectsTheLoggerIntoTheMultiProviderAndEachProvider(): void
    {
        $container = $this->createContainer(providers: [
            'custom' => 'app.custom_provider',
            'local' => InMemoryProvider::class,
        ]);
        $container->register('app.custom_provider', InMemoryProvider::class);
        $container->register(InMemoryProvider::class);

        $this->process($container);

        $setLogger = [['setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]]];
        $this->assertEquals($setLogger, $container->getDefinition('app.custom_provider')->getMethodCalls());
        $this->assertEquals($setLogger, $container->getDefinition(InMemoryProvider::class)->getMethodCalls());
        $this->assertEquals($setLogger, $this->getMultiProviderDefinition($container)->getMethodCalls());
    }

    public function testRejectsAnInvalidProviderInAMultiProvider(): void
    {
        $container = $this->createContainer(providers: ['bad' => 'app.bad_provider']);
        $container->register('app.bad_provider', \stdClass::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OpenFeature provider service "app.bad_provider" (class "stdClass") must implement interface "OpenFeature\interfaces\provider\Provider".');

        $this->process($container);
    }

    /**
     * Only the parameters set by the extension and the API service: the pass does not need the rest of the bundle.
     *
     * @param array<string, string>                      $providers
     * @param array{type: string, fallback: null|string} $strategy
     */
    private function createContainer(?string $provider = null, array $providers = [], array $strategy = ['type' => 'first_match', 'fallback' => null]): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'open_feature.provider' => $provider,
            'open_feature.providers' => $providers,
            'open_feature.strategy' => $strategy,
        ]));
        $container->register(API::class, OpenFeatureAPI::class);

        return $container;
    }

    /**
     * Replays the compile order: ResolveClassPass (priority 100) fills the class of FQCN-named services first.
     */
    private function process(ContainerBuilder $container): void
    {
        (new ResolveClassPass())->process($container);
        (new SetProviderPass())->process($container);
    }

    private function getMultiProviderDefinition(ContainerBuilder $container): Definition
    {
        /** @var list<array{string, array<int, mixed>}> $methodCalls */
        $methodCalls = $container->getDefinition(API::class)->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertSame('setProvider', $methodCalls[0][0]);

        $definition = $methodCalls[0][1][0];
        $this->assertInstanceOf(Definition::class, $definition);
        $this->assertSame(MultiProvider::class, $definition->getClass());

        return $definition;
    }
}
