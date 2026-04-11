<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\ArgumentResolver;

use Aubes\OpenFeatureBundle\Attribute\FeatureFlag;
use OpenFeature\interfaces\flags\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class FeatureFlagValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @return iterable<mixed> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        /** @var FeatureFlag[] $attributes */
        $attributes = $argument->getAttributesOfType(FeatureFlag::class, ArgumentMetadata::IS_INSTANCEOF);

        if ($attributes === []) {
            return [];
        }

        $flag = $attributes[0]->flag;
        $default = $argument->hasDefaultValue() ? $argument->getDefaultValue() : null;

        $type = \ltrim((string) $argument->getType(), '?');

        yield match ($type) {
            'bool', 'mixed', '' => $this->client->getBooleanValue($flag, \is_bool($default) ? $default : false),
            'string' => $this->client->getStringValue($flag, \is_string($default) ? $default : ''),
            'int' => $this->client->getIntegerValue($flag, \is_int($default) ? $default : 0),
            'float' => $this->client->getFloatValue($flag, \is_float($default) ? $default : 0.0),
            'array' => $this->client->getObjectValue($flag, \is_array($default) ? $default : []),
            default => throw new \LogicException(\sprintf('Unsupported type "%s" for #[FeatureFlag] parameter "$%s". Supported types: bool, string, int, float, array.', $type, $argument->getName())),
        };
    }
}
