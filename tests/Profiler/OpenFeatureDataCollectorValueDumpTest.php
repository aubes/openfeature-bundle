<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Profiler;

use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use Aubes\OpenFeatureBundle\Event\EvaluationContextContributedEvent;
use Aubes\OpenFeatureBundle\Profiler\ContextProviderRecorder;
use Aubes\OpenFeatureBundle\Profiler\OpenFeatureDataCollector;
use Aubes\OpenFeatureBundle\Profiler\ProfilerHook;
use OpenFeature\implementation\flags\MutableAttributes;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\implementation\hooks\HookContextBuilder;
use OpenFeature\implementation\hooks\HookHints;
use OpenFeature\implementation\provider\ResolutionDetailsBuilder;
use OpenFeature\interfaces\flags\FlagValueType;
use OpenFeature\OpenFeatureAPI;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Structured values (object flags, array or date attributes) cannot be printed as strings in the profiler template.
 */
#[CoversClass(OpenFeatureDataCollector::class)]
class OpenFeatureDataCollectorValueDumpTest extends TestCase
{
    public function testKeepsBooleanValuesRawAndDumpsTheOthers(): void
    {
        $hook = new ProfilerHook();
        $hook->after((new HookContextBuilder())->withFlagKey('enabled')->withType(FlagValueType::BOOLEAN)->build(), (new ResolutionDetailsBuilder())->withValue(true)->build(), new HookHints());
        $hook->after((new HookContextBuilder())->withFlagKey('config')->withType(FlagValueType::OBJECT)->build(), (new ResolutionDetailsBuilder())->withValue(['color' => 'blue'])->build(), new HookHints());

        $collector = new OpenFeatureDataCollector($hook, new OpenFeatureAPI());
        $collector->collect(Request::create('/'), new Response());

        [$enabled, $config] = $collector->getEvaluations();
        $this->assertTrue($enabled['value']);
        $this->assertInstanceOf(Data::class, $config['value']);
        $this->assertEquals(['color' => 'blue'], $config['value']->getValue(true));
    }

    public function testDumpsContextAttributesAndContributions(): void
    {
        $context = new MutableEvaluationContext('user-1', new MutableAttributes(['plan' => 'premium', 'since' => new \DateTime('2026-09-28')]));

        $api = new OpenFeatureAPI();
        $api->setEvaluationContext($context);
        $recorder = new ContextProviderRecorder();
        $recorder(new EvaluationContextContributedEvent($this->createStub(EvaluationContextProviderInterface::class), $context));

        $collector = new OpenFeatureDataCollector(new ProfilerHook(), $api, $recorder);
        $collector->collect(Request::create('/'), new Response());

        $attributes = $collector->getEvaluationContext()['attributes'] ?? null;
        $this->assertIsArray($attributes);
        $this->assertContainsOnlyInstancesOf(Data::class, $attributes);
        $this->assertSame('premium', $attributes['plan']->getValue());

        $this->assertCount(2, $collector->getContextProviders()[0]['attributes']);
    }
}
