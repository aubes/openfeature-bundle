<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Provider;

use Aubes\OpenFeatureBundle\Provider\InMemoryProvider;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Reason;
use OpenFeature\interfaces\provider\ResolutionDetails;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryProvider::class)]
class InMemoryProviderTest extends TestCase
{
    #[DataProvider('provideFlagValues')]
    public function testResolvesFlagValue(string $method, mixed $value, mixed $default, mixed $expected): void
    {
        $provider = new InMemoryProvider(['flag' => $value]);

        $result = $provider->{$method}('flag', $default);
        $this->assertInstanceOf(ResolutionDetails::class, $result);

        $this->assertSame($expected, $result->getValue());
        $this->assertSame(Reason::DEFAULT, $result->getReason());
        $this->assertNull($result->getError());
    }

    /** @return array<string, array{string, mixed, mixed, mixed}> */
    public static function provideFlagValues(): array
    {
        return [
            'boolean' => ['resolveBooleanValue', false, true, false],
            'boolean from integer 1' => ['resolveBooleanValue', 1, false, true],
            'boolean from integer 0' => ['resolveBooleanValue', 0, true, false],
            'string' => ['resolveStringValue', 'dark', 'light', 'dark'],
            'integer' => ['resolveIntegerValue', 10, 5, 10],
            'float' => ['resolveFloatValue', 1.5, 0.0, 1.5],
            'float from integer' => ['resolveFloatValue', 2, 0.0, 2.0],
            'object' => ['resolveObjectValue', ['color' => 'blue'], [], ['color' => 'blue']],
            'object from list' => ['resolveObjectValue', [1, 2], [], [1, 2]],
        ];
    }

    #[DataProvider('provideDefaultValues')]
    public function testUnknownFlagReturnsFlagNotFound(string $method, mixed $default): void
    {
        $provider = new InMemoryProvider([]);

        $result = $provider->{$method}('unknown', $default);
        $this->assertInstanceOf(ResolutionDetails::class, $result);

        $this->assertSame($default, $result->getValue());
        $this->assertSame(Reason::ERROR, $result->getReason());
        $this->assertEquals(ErrorCode::FLAG_NOT_FOUND(), $result->getError()?->getResolutionErrorCode());
    }

    /** @return array<string, array{string, mixed}> */
    public static function provideDefaultValues(): array
    {
        return [
            'boolean' => ['resolveBooleanValue', true],
            'string' => ['resolveStringValue', 'default'],
            'integer' => ['resolveIntegerValue', 42],
            'float' => ['resolveFloatValue', 1.5],
            'object' => ['resolveObjectValue', ['key' => 'value']],
        ];
    }

    #[DataProvider('provideTypeMismatches')]
    public function testMismatchedValueReturnsTypeMismatch(string $method, mixed $value, mixed $default): void
    {
        $provider = new InMemoryProvider(['flag' => $value]);

        $result = $provider->{$method}('flag', $default);
        $this->assertInstanceOf(ResolutionDetails::class, $result);

        $this->assertSame($default, $result->getValue());
        $this->assertSame(Reason::ERROR, $result->getReason());
        $this->assertEquals(ErrorCode::TYPE_MISMATCH(), $result->getError()?->getResolutionErrorCode());
    }

    /** @return array<string, array{string, mixed, mixed}> */
    public static function provideTypeMismatches(): array
    {
        return [
            'boolean from integer 2' => ['resolveBooleanValue', 2, false],
            'boolean from string' => ['resolveBooleanValue', 'true', false],
            'boolean from null' => ['resolveBooleanValue', null, false],
            'string from boolean' => ['resolveStringValue', true, 'default'],
            'string from integer' => ['resolveStringValue', 42, 'default'],
            'integer from float' => ['resolveIntegerValue', 1.5, 0],
            'integer from string' => ['resolveIntegerValue', '10', 0],
            'integer from boolean' => ['resolveIntegerValue', true, 0],
            'float from string' => ['resolveFloatValue', '1.5', 0.0],
            'object from string' => ['resolveObjectValue', 'not-an-array', ['key' => 'value']],
        ];
    }

    public function testGetMetadataReturnsProviderName(): void
    {
        $provider = new InMemoryProvider([]);

        $this->assertSame('InMemoryProvider', $provider->getMetadata()->getName());
    }
}
