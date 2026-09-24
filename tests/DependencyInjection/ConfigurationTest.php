<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\DependencyInjection;

use Aubes\OpenFeatureBundle\OpenFeatureBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * Configuration tree only: cross-field validations run in loadExtension() and are covered by OpenFeatureBundleTest.
 */
#[CoversClass(OpenFeatureBundle::class)]
class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $this->assertSame([
            'provider' => null,
            'providers' => [],
            'strategy' => ['type' => 'first_match', 'fallback' => null],
            'flags' => [],
            'evaluation_context' => ['user_provider' => 'auto'],
            'feature_flag' => ['on_disabled' => 'auto', 'status_code' => 403],
        ], $this->process([]));
    }

    public function testStrategyShorthandSetsTheType(): void
    {
        $config = $this->process(['strategy' => 'first_successful']);

        $this->assertSame(['type' => 'first_successful', 'fallback' => null], $config['strategy']);
    }

    public function testProviderNamesAreNotNormalized(): void
    {
        $config = $this->process(['providers' => ['my-local' => 'app.provider']]);

        $this->assertSame(['my-local' => 'app.provider'], $config['providers']);
    }

    public function testFlagKeysAreNotNormalized(): void
    {
        $config = $this->process(['flags' => ['new-checkout' => true]]);

        $this->assertSame(['new-checkout' => true], $config['flags']);
    }

    public function testObjectFlagWithANameKeyIsKeptAsIs(): void
    {
        $config = $this->process(['flags' => ['banner' => ['name' => 'Summer sale', 'color' => 'red']]]);

        $this->assertSame(['banner' => ['name' => 'Summer sale', 'color' => 'red']], $config['flags']);
    }

    public function testFlagsAreMergedPerKeyAcrossConfigs(): void
    {
        $config = $this->process(
            ['flags' => ['dark_mode' => false, 'banner' => ['color' => 'red']]],
            ['flags' => ['banner' => ['size' => 'xl']]],
        );

        $this->assertSame(['dark_mode' => false, 'banner' => ['size' => 'xl']], $config['flags']);
    }

    #[DataProvider('provideUserProviderValues')]
    public function testUserProviderAcceptsBooleans(bool|string $value, string $expected): void
    {
        $config = $this->process(['evaluation_context' => ['user_provider' => $value]]);

        $this->assertSame(['user_provider' => $expected], $config['evaluation_context']);
    }

    /** @return array<string, array{bool|string, string}> */
    public static function provideUserProviderValues(): array
    {
        return [
            'true' => [true, 'true'],
            'false' => [false, 'false'],
            'auto' => ['auto', 'auto'],
        ];
    }

    public function testRedisPrefixHasADefault(): void
    {
        $config = $this->process(['redis' => ['client' => 'app.redis']]);

        $this->assertSame(['client' => 'app.redis', 'prefix' => 'feature:'], $config['redis']);
    }

    public function testStatusCodeIsRejectedWithAccessDenied(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "open_feature.feature_flag": "status_code" has no effect when "on_disabled" is "access_denied".');

        $this->process(['feature_flag' => ['on_disabled' => 'access_denied', 'status_code' => 404]]);
    }

    /**
     * @param array<string, mixed> ...$configs
     *
     * @return array<string, mixed>
     */
    private function process(array ...$configs): array
    {
        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(new OpenFeatureBundle(), null, 'open_feature'), $configs);

        return $processed;
    }
}
