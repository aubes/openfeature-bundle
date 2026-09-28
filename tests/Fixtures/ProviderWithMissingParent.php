<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\Fixtures;

/**
 * Provider whose parent class is not installed, as when a provider package misses a dependency.
 */
class ProviderWithMissingParent extends Missing\ParentProvider
{
}
