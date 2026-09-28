<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Provider;

use Aubes\OpenFeatureBundle\Provider\Redis\RedisClientInterface;
use Aubes\OpenFeatureBundle\Provider\RedisProvider;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Reason;
use OpenFeature\interfaces\provider\ResolutionDetails;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(RedisProvider::class)]
class RedisProviderTest extends TestCase
{
    #[DataProvider('provideRawValues')]
    public function testResolvesRawValue(string $method, string $raw, mixed $default, mixed $expected): void
    {
        $client = $this->createStub(RedisClientInterface::class);
        $client->method('get')->willReturn($raw);

        $result = (new RedisProvider($client))->{$method}('flag', $default);
        $this->assertInstanceOf(ResolutionDetails::class, $result);

        $this->assertSame($expected, $result->getValue());
        $this->assertSame(Reason::DEFAULT, $result->getReason());
        $this->assertNull($result->getError());
    }

    /** @return array<string, array{string, string, mixed, mixed}> */
    public static function provideRawValues(): array
    {
        return [
            'boolean true' => ['resolveBooleanValue', 'true', false, true],
            'boolean false' => ['resolveBooleanValue', 'off', true, false],
            'string' => ['resolveStringValue', 'dark', 'light', 'dark'],
            'integer' => ['resolveIntegerValue', '10', 5, 10],
            'float' => ['resolveFloatValue', '3.14', 1.0, 3.14],
            'object' => ['resolveObjectValue', '{"color":"blue","size":3}', [], ['color' => 'blue', 'size' => 3]],
        ];
    }

    #[DataProvider('provideMissingKeys')]
    public function testMissingKeyReturnsFlagNotFound(string $method, ?false $raw, mixed $default): void
    {
        $client = $this->createStub(RedisClientInterface::class);
        $client->method('get')->willReturn($raw);

        $result = (new RedisProvider($client))->{$method}('unknown', $default);
        $this->assertInstanceOf(ResolutionDetails::class, $result);

        $this->assertSame($default, $result->getValue());
        $this->assertSame(Reason::ERROR, $result->getReason());
        $this->assertEquals(ErrorCode::FLAG_NOT_FOUND(), $result->getError()?->getResolutionErrorCode());
    }

    /** @return array<string, array{string, null|false, mixed}> */
    public static function provideMissingKeys(): array
    {
        return [
            'boolean, client returns null' => ['resolveBooleanValue', null, true],
            'boolean, client returns false' => ['resolveBooleanValue', false, true],
            'string' => ['resolveStringValue', null, 'default'],
            'integer' => ['resolveIntegerValue', null, 42],
            'float' => ['resolveFloatValue', null, 1.5],
            'object' => ['resolveObjectValue', null, ['key' => 'value']],
        ];
    }

    #[DataProvider('provideUnparsableValues')]
    public function testUnparsableValueReturnsParseError(string $method, string $raw, mixed $default): void
    {
        $client = $this->createStub(RedisClientInterface::class);
        $client->method('get')->willReturn($raw);

        $result = (new RedisProvider($client))->{$method}('flag', $default);
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
            'float' => ['resolveFloatValue', 'abc', 1.5],
            'object' => ['resolveObjectValue', 'not-json', ['key' => 'value']],
        ];
    }

    public function testUsesConfiguredPrefix(): void
    {
        $client = $this->createMock(RedisClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with('flags:my_flag')
            ->willReturn('true');

        $provider = new RedisProvider($client, 'flags:');
        $provider->resolveBooleanValue('my_flag', false);
    }

    public function testDefaultPrefixIsFeatureColon(): void
    {
        $client = $this->createMock(RedisClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with('feature:my_flag')
            ->willReturn('true');

        $provider = new RedisProvider($client);
        $provider->resolveBooleanValue('my_flag', false);
    }

    public function testGetMetadataReturnsProviderName(): void
    {
        $client = $this->createStub(RedisClientInterface::class);
        $provider = new RedisProvider($client);

        $this->assertSame('RedisProvider', $provider->getMetadata()->getName());
    }

    public function testClientFailureReturnsGeneralError(): void
    {
        $exception = new \RuntimeException('Connection refused');
        $client = $this->createStub(RedisClientInterface::class);
        $client->method('get')->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'OpenFeature Redis provider failed to read flag "{flag}": {message}',
                ['flag' => 'flag', 'message' => 'Connection refused', 'exception' => $exception],
            );

        $provider = new RedisProvider($client);
        $provider->setLogger($logger);
        $result = $provider->resolveBooleanValue('flag', true);

        $this->assertTrue($result->getValue());
        $this->assertSame(Reason::ERROR, $result->getReason());
        $this->assertEquals(ErrorCode::GENERAL(), $result->getError()?->getResolutionErrorCode());
        $this->assertStringContainsString('Connection refused', (string) $result->getError()?->getResolutionErrorMessage());
    }
}
