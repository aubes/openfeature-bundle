<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Profiler;

use Aubes\OpenFeatureBundle\Profiler\ProfilerHook;
use OpenFeature\implementation\hooks\HookContextBuilder;
use OpenFeature\implementation\hooks\HookHints;
use OpenFeature\implementation\provider\ResolutionDetailsBuilder;
use OpenFeature\implementation\provider\ResolutionError;
use OpenFeature\interfaces\flags\FlagValueType;
use OpenFeature\interfaces\hooks\HookContext;
use OpenFeature\interfaces\provider\ErrorCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfilerHook::class)]
class ProfilerHookTest extends TestCase
{
    public function testStartsWithNoEvaluations(): void
    {
        $hook = new ProfilerHook();

        $this->assertSame([], $hook->getEvaluations());
    }

    public function testAfterRecordsEvaluation(): void
    {
        $hook = new ProfilerHook();
        $details = (new ResolutionDetailsBuilder())->withValue(true)->withReason('STATIC')->withVariant('on')->build();

        $hook->after($this->hookContext('my_flag'), $details, new HookHints());

        $this->assertSame([
            ['flag' => 'my_flag', 'type' => FlagValueType::BOOLEAN, 'value' => true, 'variant' => 'on', 'reason' => 'STATIC', 'error' => null],
        ], $hook->getEvaluations());
    }

    public function testErrorRecordsEvaluationWithErrorReason(): void
    {
        $hook = new ProfilerHook();

        $hook->error($this->hookContext('broken_flag'), new \RuntimeException('Provider unavailable'), new HookHints());

        $this->assertSame([
            ['flag' => 'broken_flag', 'type' => FlagValueType::BOOLEAN, 'value' => null, 'variant' => null, 'reason' => 'ERROR', 'error' => 'RuntimeException: Provider unavailable'],
        ], $hook->getEvaluations());
    }

    public function testAfterRecordsErrorMessageFromResolutionError(): void
    {
        $hook = new ProfilerHook();
        $details = (new ResolutionDetailsBuilder())
            ->withValue(false)
            ->withError(new ResolutionError(ErrorCode::FLAG_NOT_FOUND(), 'flag not found'))
            ->build();

        $hook->after($this->hookContext('my_flag'), $details, new HookHints());

        $this->assertSame('flag not found', $hook->getEvaluations()[0]['error']);
    }

    #[DataProvider('provideFlagValueTypes')]
    public function testSupportsEveryFlagValueType(string $type): void
    {
        $this->assertTrue((new ProfilerHook())->supportsFlagValueType($type));
    }

    /** @return iterable<string, array{string}> */
    public static function provideFlagValueTypes(): iterable
    {
        foreach ([FlagValueType::BOOLEAN, FlagValueType::STRING, FlagValueType::INTEGER, FlagValueType::FLOAT, FlagValueType::OBJECT] as $type) {
            yield $type => [$type];
        }
    }

    public function testBeforeReturnsNull(): void
    {
        $this->assertNull((new ProfilerHook())->before($this->hookContext('my_flag'), new HookHints()));
    }

    public function testAccumulatesMultipleEvaluations(): void
    {
        $hook = new ProfilerHook();
        $details = (new ResolutionDetailsBuilder())->withValue(true)->build();

        foreach (['flag_a', 'flag_b', 'flag_c'] as $flagKey) {
            $hook->after($this->hookContext($flagKey), $details, new HookHints());
        }

        $this->assertSame(['flag_a', 'flag_b', 'flag_c'], \array_column($hook->getEvaluations(), 'flag'));
    }

    public function testResetClearsEvaluations(): void
    {
        $hook = new ProfilerHook();
        $hook->error($this->hookContext('my_flag'), new \RuntimeException('boom'), new HookHints());
        $this->assertNotEmpty($hook->getEvaluations());

        $hook->reset();

        $this->assertSame([], $hook->getEvaluations());
    }

    private function hookContext(string $flagKey): HookContext
    {
        return (new HookContextBuilder())->withFlagKey($flagKey)->withType(FlagValueType::BOOLEAN)->build();
    }
}
