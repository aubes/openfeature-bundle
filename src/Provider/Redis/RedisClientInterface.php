<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Provider\Redis;

interface RedisClientInterface
{
    /**
     * Returns the value stored at the given key, or null/false if the key does not exist.
     */
    public function get(string $key): string|false|null;
}
