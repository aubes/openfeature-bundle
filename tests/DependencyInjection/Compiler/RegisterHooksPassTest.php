<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\DependencyInjection\Compiler;

use Aubes\OpenFeatureBundle\DependencyInjection\Compiler\RegisterHooksPass;
use Aubes\OpenFeatureBundle\Tests\Fixtures\ContextRecordingHook;
use OpenFeature\interfaces\flags\API;
use OpenFeature\OpenFeatureAPI;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(RegisterHooksPass::class)]
class RegisterHooksPassTest extends TestCase
{
    public function testAddsTaggedHooksToTheApi(): void
    {
        $container = new ContainerBuilder();
        $container->register(API::class, OpenFeatureAPI::class);
        $container->register('app.hook', ContextRecordingHook::class)->addTag('openfeature.hook');
        $container->register('app.other_hook', ContextRecordingHook::class)->addTag('openfeature.hook');
        $container->register('app.untagged_hook', ContextRecordingHook::class);

        (new RegisterHooksPass())->process($container);

        $this->assertEquals(
            [
                ['addHooks', [new Reference('app.hook')]],
                ['addHooks', [new Reference('app.other_hook')]],
            ],
            $container->getDefinition(API::class)->getMethodCalls(),
        );
    }

    public function testDoesNothingWithoutTheApiService(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.hook', ContextRecordingHook::class)->addTag('openfeature.hook');

        $this->expectNotToPerformAssertions();

        (new RegisterHooksPass())->process($container);
    }
}
