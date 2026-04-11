<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Twig;

use OpenFeature\interfaces\flags\Client;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OpenFeatureExtension extends AbstractExtension
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('feature', $this->isEnabled(...)),
            new TwigFunction('feature_value', $this->getValue(...)),
        ];
    }

    private function isEnabled(string $flagKey, bool $default = false): bool
    {
        return $this->client->getBooleanValue($flagKey, $default) ?? $default;
    }

    /**
     * @param bool|float|int|mixed[]|string $default
     *
     * @return bool|float|int|mixed[]|string
     */
    private function getValue(string $flagKey, string|int|float|bool|array $default = ''): string|int|float|bool|array
    {
        return match (true) {
            \is_bool($default) => $this->client->getBooleanValue($flagKey, $default) ?? $default,
            \is_int($default) => $this->client->getIntegerValue($flagKey, $default),
            \is_float($default) => $this->client->getFloatValue($flagKey, $default),
            \is_array($default) => $this->client->getObjectValue($flagKey, $default),
            default => $this->client->getStringValue($flagKey, $default) ?? $default,
        };
    }
}
