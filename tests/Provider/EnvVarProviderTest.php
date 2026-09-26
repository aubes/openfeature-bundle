<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Provider;

use Aubes\OpenFeatureBundle\Provider\EnvVarProvider;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Reason;
use OpenFeature\interfaces\provider\ResolutionDetails;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvVarProvider::class)]
class EnvVarProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        \putenv('FEATURE_MY_FLAG');
        \putenv('FEATURE_MY_STRING');
        \putenv('FEATURE_MY_INT');
        \putenv('FEATURE_MY_FLOAT');
        \putenv('FEATURE_MY_OBJECT');
        \putenv('APP_MY_FLAG');
    }

    public function testResolveBooleanValueReturnsDefaultWhenEnvNotSet(): void
    {
        $provider = new EnvVarProvider();

        $result = $provider->resolveBooleanValue('my_flag', true);

        $this->assertTrue($result->getValue());
        $this->assertSame(Reason::ERROR, $result->getReason());
        $this->assertNotNull($result->getError());
        $this->assertEquals(ErrorCode::FLAG_NOT_FOUND(), $result->getError()->getResolutionErrorCode());
    }

    #[DataProvider('provideTruthyValues')]
    public function testResolveBooleanValueReturnsTrueForTruthyEnvVar(string $value): void
    {
        \putenv("FEATURE_MY_FLAG={$value}");
        $provider = new EnvVarProvider();

        $result = $provider->resolveBooleanValue('my_flag', false);

        $this->assertTrue($result->getValue());
    }

    /** @return array<string, array{string}> */
    public static function provideTruthyValues(): array
    {
        return [
            'true string' => ['true'],
            'TRUE string' => ['TRUE'],
            'one string' => ['1'],
            'yes string' => ['yes'],
            'on string' => ['on'],
        ];
    }

    #[DataProvider('provideFalsyValues')]
    public function testResolveBooleanValueReturnsFalseForFalsyEnvVar(string $value): void
    {
        \putenv("FEATURE_MY_FLAG={$value}");
        $provider = new EnvVarProvider();

        $result = $provider->resolveBooleanValue('my_flag', true);

        $this->assertFalse($result->getValue());
    }

    /** @return array<string, array{string}> */
    public static function provideFalsyValues(): array
    {
        return [
            'false string' => ['false'],
            'zero string' => ['0'],
            'no string' => ['no'],
            'off string' => ['off'],
            'empty string' => [''],
        ];
    }

    public function testResolveStringValueReturnsEnvVar(): void
    {
        \putenv('FEATURE_MY_STRING=hello');
        $provider = new EnvVarProvider();

        $result = $provider->resolveStringValue('my_string', 'default');

        $this->assertSame('hello', $result->getValue());
        $this->assertSame(Reason::DEFAULT, $result->getReason());
        $this->assertNull($result->getError());
    }

    #[DataProvider('provideIntegerValues')]
    public function testResolveIntegerValueParsesValidNotations(string $raw, int $expected): void
    {
        \putenv("FEATURE_MY_INT={$raw}");
        $provider = new EnvVarProvider();

        $result = $provider->resolveIntegerValue('my_int', 99);

        $this->assertSame($expected, $result->getValue());
        $this->assertNull($result->getError());
    }

    /** @return array<string, array{string, int}> */
    public static function provideIntegerValues(): array
    {
        return [
            'plain' => ['42', 42],
            'leading zero' => ['08', 8],
            'several leading zeros' => ['007', 7],
            'negative with leading zero' => ['-08', -8],
            'explicit plus sign' => ['+8', 8],
            'zero' => ['0', 0],
            'double zero' => ['00', 0],
            'surrounding spaces' => [' 8 ', 8],
        ];
    }

    #[DataProvider('provideFloatValues')]
    public function testResolveFloatValueParsesNumericNotations(string $raw, float $expected): void
    {
        \putenv("FEATURE_MY_FLOAT={$raw}");
        $provider = new EnvVarProvider();

        $result = $provider->resolveFloatValue('my_float', 0.0);

        $this->assertSame($expected, $result->getValue());
        $this->assertNull($result->getError());
    }

    /** @return array<string, array{string, float}> */
    public static function provideFloatValues(): array
    {
        return [
            'decimal' => ['3.14', 3.14],
            'integer with leading zero' => ['08', 8.0],
            'exponent notation' => ['1e3', 1000.0],
        ];
    }

    public function testResolveObjectValueReturnsDecodedJsonEnvVar(): void
    {
        \putenv('FEATURE_MY_OBJECT={"key":"value"}');
        $provider = new EnvVarProvider();

        $result = $provider->resolveObjectValue('my_object', []);

        $this->assertSame(['key' => 'value'], $result->getValue());
    }

    public function testResolveObjectValueAcceptsJsonArray(): void
    {
        \putenv('FEATURE_MY_OBJECT=[1,2]');
        $provider = new EnvVarProvider();

        $result = $provider->resolveObjectValue('my_object', []);

        $this->assertSame([1, 2], $result->getValue());
    }

    public function testCustomPrefixIsUsed(): void
    {
        \putenv('APP_MY_FLAG=true');
        $provider = new EnvVarProvider('APP_');

        $result = $provider->resolveBooleanValue('my_flag', false);

        $this->assertTrue($result->getValue());
    }

    #[DataProvider('provideFlagKeys')]
    public function testFlagKeyIsMappedToTheEnvVarName(string $flagKey): void
    {
        \putenv('FEATURE_MY_FLAG=true');
        $provider = new EnvVarProvider();

        $result = $provider->resolveBooleanValue($flagKey, false);

        $this->assertTrue($result->getValue());
    }

    /** @return array<string, array{string}> */
    public static function provideFlagKeys(): array
    {
        return [
            'hyphen' => ['my-flag'],
            'dot' => ['my.flag'],
            'uppercase' => ['MY_FLAG'],
        ];
    }

    public function testGetMetadataReturnsProviderName(): void
    {
        $provider = new EnvVarProvider();

        $this->assertSame('EnvVarProvider', $provider->getMetadata()->getName());
    }

    #[DataProvider('provideUnparsableValues')]
    public function testUnparsableValueReturnsParseError(string $method, string $raw, mixed $default): void
    {
        \putenv("FEATURE_MY_FLAG={$raw}");
        $provider = new EnvVarProvider();

        $result = $provider->{$method}('my_flag', $default);
        $this->assertInstanceOf(ResolutionDetails::class, $result);

        $this->assertSame($default, $result->getValue());
        $this->assertSame(Reason::ERROR, $result->getReason());
        $this->assertEquals(ErrorCode::PARSE_ERROR(), $result->getError()?->getResolutionErrorCode());
    }

    /** @return array<string, array{string, string, mixed}> */
    public static function provideUnparsableValues(): array
    {
        return [
            'boolean' => ['resolveBooleanValue', 'banana', true],
            'integer' => ['resolveIntegerValue', 'abc', 7],
            'integer with decimals' => ['resolveIntegerValue', '1.5', 7],
            'integer with decimal zero' => ['resolveIntegerValue', '10.0', 7],
            'integer in exponent notation' => ['resolveIntegerValue', '1e3', 7],
            'integer overflow' => ['resolveIntegerValue', '9223372036854775808', 7],
            'hexadecimal integer' => ['resolveIntegerValue', '0x1A', 7],
            'float' => ['resolveFloatValue', 'abc', 1.5],
            'object' => ['resolveObjectValue', 'not-json', ['fallback' => true]],
            'object from JSON scalar' => ['resolveObjectValue', '42', ['fallback' => true]],
        ];
    }
}
