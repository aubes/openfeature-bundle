<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Profiler;

use Aubes\OpenFeatureBundle\EvaluationContext\LazyEvaluationContext;
use Aubes\OpenFeatureBundle\Profiler\OpenFeatureDataCollector;
use Aubes\OpenFeatureBundle\Profiler\ProfilerHook;
use OpenFeature\implementation\flags\MutableAttributes;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\OpenFeatureAPI;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

#[CoversClass(OpenFeatureDataCollector::class)]
class OpenFeatureDataCollectorLazyContextTest extends TestCase
{
    public function testDoesNotResolvePendingLazyContext(): void
    {
        $api = new OpenFeatureAPI();
        $api->setEvaluationContext(new LazyEvaluationContext(fn (): EvaluationContext => $this->fail('The collector must not resolve the context')));

        $collector = $this->collect($api);

        $this->assertFalse($collector->isEvaluationContextResolved());
        $this->assertSame([], $collector->getEvaluationContext());
    }

    public function testSerializesResolvedLazyContext(): void
    {
        $context = new LazyEvaluationContext(static fn (): EvaluationContext => new MutableEvaluationContext('user-1', new MutableAttributes(['plan' => 'premium'])));
        $context->getTargetingKey();

        $api = new OpenFeatureAPI();
        $api->setEvaluationContext($context);

        $collector = $this->collect($api);

        $this->assertTrue($collector->isEvaluationContextResolved());

        $context = $collector->getEvaluationContext();
        $this->assertSame(\substr(\hash('sha256', 'user-1'), 0, 12), $context['targeting_key']);
        $this->assertIsArray($context['attributes']);
        $plan = $context['attributes']['plan'] ?? null;
        $this->assertInstanceOf(Data::class, $plan);
        $this->assertSame('premium', $plan->getValue());
    }

    public function testRegularContextIsReportedAsResolved(): void
    {
        $api = new OpenFeatureAPI();
        $api->setEvaluationContext(new MutableEvaluationContext('user-1'));

        $this->assertTrue($this->collect($api)->isEvaluationContextResolved());
    }

    private function collect(OpenFeatureAPI $api): OpenFeatureDataCollector
    {
        $collector = new OpenFeatureDataCollector(new ProfilerHook(), $api);
        $collector->collect(Request::create('/'), new Response());

        return $collector;
    }
}
