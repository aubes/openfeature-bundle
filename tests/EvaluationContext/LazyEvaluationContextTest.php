<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\EvaluationContext;

use Aubes\OpenFeatureBundle\EvaluationContext\LazyEvaluationContext;
use Aubes\OpenFeatureBundle\Provider\InMemoryProvider;
use Aubes\OpenFeatureBundle\Tests\Fixtures\ContextRecordingHook;
use OpenFeature\implementation\flags\MutableAttributes;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\OpenFeatureAPI;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LazyEvaluationContext::class)]
class LazyEvaluationContextTest extends TestCase
{
    public function testDoesNotResolveOnConstruction(): void
    {
        $context = new LazyEvaluationContext(fn (): EvaluationContext => $this->fail('The resolver must not run before the first read'));

        $this->assertFalse($context->isResolved());
    }

    public function testResolvesOnFirstReadOnly(): void
    {
        $calls = 0;
        $context = new LazyEvaluationContext(static function () use (&$calls): EvaluationContext {
            ++$calls;

            return new MutableEvaluationContext('user-1', new MutableAttributes(['plan' => 'premium']));
        });

        $this->assertSame('user-1', $context->getTargetingKey());
        $this->assertSame(['plan' => 'premium'], $context->getAttributes()->toArray());
        $this->assertTrue($context->isResolved());
        $this->assertSame(1, $calls);
    }

    // The SDK must read the API-level context at evaluation time, not when it is set
    public function testSdkDoesNotResolveContextWithoutEvaluation(): void
    {
        $context = new LazyEvaluationContext(static fn (): EvaluationContext => new MutableEvaluationContext('user-1'));

        $api = $this->makeApi(new ContextRecordingHook());
        $api->setEvaluationContext($context);
        $api->getClient();

        $this->assertFalse($context->isResolved());
    }

    public function testSdkEvaluationSeesResolvedContext(): void
    {
        $hook = new ContextRecordingHook();
        $api = $this->makeApi($hook);
        $api->setEvaluationContext(new LazyEvaluationContext(static fn (): EvaluationContext => new MutableEvaluationContext('user-1')));

        $this->assertTrue($api->getClient()->getBooleanValue('enabled', false));
        $this->assertSame('user-1', $hook->context?->getTargetingKey());
    }

    public function testSdkEvaluationMergesResolvedContextWithInvocationContext(): void
    {
        $hook = new ContextRecordingHook();
        $api = $this->makeApi($hook);
        $api->setEvaluationContext(new LazyEvaluationContext(static fn (): EvaluationContext => new MutableEvaluationContext('user-1')));

        $api->getClient()->getBooleanValue('enabled', false, new MutableEvaluationContext(null, new MutableAttributes(['page' => 'home'])));

        $this->assertNotNull($hook->context);
        $this->assertSame('user-1', $hook->context->getTargetingKey());
        $this->assertSame('home', $hook->context->getAttributes()->get('page'));
    }

    public function testFlagEvaluatedDuringResolutionGetsAnEmptyContext(): void
    {
        $hook = new ContextRecordingHook();
        $api = $this->makeApi($hook);

        $calls = 0;
        $innerDetails = null;
        $innerContext = null;
        $api->setEvaluationContext(new LazyEvaluationContext(static function () use ($api, $hook, &$calls, &$innerDetails, &$innerContext): EvaluationContext {
            ++$calls;
            $innerDetails = $api->getClient()->getBooleanDetails('enabled', false);
            $innerContext = $hook->context;

            return new MutableEvaluationContext('user-1');
        }));

        $api->getClient()->getBooleanValue('enabled', false);

        $this->assertSame(1, $calls);
        $this->assertNull($innerDetails?->getError());
        $this->assertNotNull($innerContext);
        $this->assertNull($innerContext->getTargetingKey());
        $this->assertSame([], $innerContext->getAttributes()->toArray());
        $this->assertSame('user-1', $hook->context?->getTargetingKey());
    }

    public function testRetriesResolutionAfterAFailure(): void
    {
        $calls = 0;
        $context = new LazyEvaluationContext(static function () use (&$calls): EvaluationContext {
            ++$calls;

            return $calls === 1 ? throw new \RuntimeException('boom') : new MutableEvaluationContext('user-1');
        });

        try {
            $context->getTargetingKey();
            $this->fail('The resolver exception must propagate');
        } catch (\RuntimeException) {
        }

        $this->assertFalse($context->isResolved());
        $this->assertSame('user-1', $context->getTargetingKey());
    }

    private function makeApi(ContextRecordingHook $hook): OpenFeatureAPI
    {
        $api = new OpenFeatureAPI();
        $api->setProvider(new InMemoryProvider(['enabled' => true]));
        $api->addHooks($hook);

        return $api;
    }
}
