<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class FeatureFlag
{
    public function __construct(public readonly string $flag)
    {
    }
}
