<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Profiler;

use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use Aubes\OpenFeatureBundle\Event\EvaluationContextContributedEvent;
use Aubes\OpenFeatureBundle\Profiler\ContextProviderRecorder;
use OpenFeature\implementation\flags\MutableAttributes;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContextProviderRecorder::class)]
class ContextProviderRecorderTest extends TestCase
{
    public function testRecordsEachContributionInOrder(): void
    {
        $provider = $this->createStub(EvaluationContextProviderInterface::class);

        $recorder = new ContextProviderRecorder();
        $recorder(new EvaluationContextContributedEvent($provider, new MutableEvaluationContext('user-1')));
        $recorder(new EvaluationContextContributedEvent($provider, new MutableEvaluationContext(null, new MutableAttributes(['plan' => 'premium']))));

        $this->assertSame([
            ['provider' => $provider::class, 'targeting_key' => 'user-1', 'attributes' => []],
            ['provider' => $provider::class, 'targeting_key' => null, 'attributes' => ['plan' => 'premium']],
        ], $recorder->getContributions());
    }

    public function testResetClearsContributions(): void
    {
        $provider = $this->createStub(EvaluationContextProviderInterface::class);

        $recorder = new ContextProviderRecorder();
        $recorder(new EvaluationContextContributedEvent($provider, new MutableEvaluationContext('user-1')));
        $this->assertNotEmpty($recorder->getContributions());

        $recorder->reset();
        $this->assertSame([], $recorder->getContributions());
    }
}
