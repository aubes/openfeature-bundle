<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class FeatureGate
{
    public function __construct(public readonly string $flag)
    {
    }
}
