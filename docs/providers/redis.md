# Redis provider

Resolves flags from Redis string keys. Fits shared on/off toggles when Redis is already in your stack and you don't want to introduce a dedicated feature flag backend.

> **Bootstrap only:** this provider is a simple key/value store. It ignores the `EvaluationContext` (no targeting, no percentage rollouts, no A/B testing) and has no management UI, no audit log, no scheduled rollouts. For anything beyond a shared kill switch, use a real provider like Flagd or ConfigCat. See [Providers](index.md#real-providers-recommended-for-production).

## Configuration

```yaml
open_feature:
    provider: Aubes\OpenFeatureBundle\Provider\RedisProvider
    redis:
        client: App\OpenFeature\MyRedisClient
        prefix: 'feature:'    # default
```

## RedisClientInterface

The provider depends on a `RedisClientInterface` service. Implement it to wrap your Redis connection:

```php
use Aubes\OpenFeatureBundle\Provider\Redis\RedisClientInterface;

class MyRedisClient implements RedisClientInterface
{
    public function __construct(private readonly \Redis $redis) {}

    public function get(string $key): string|false|null
    {
        return $this->redis->get($key);
    }
}
```

This works with `ext-redis`, `Predis\Client`, or any other Redis library.

## Redis keys

Each flag is stored as a plain Redis string:

```
feature:new_checkout  ->  "true"
feature:max_items     ->  "10"
feature:config        ->  '{"color":"blue"}'
```

## Values

Boolean truthy values: `true`, `1`, `yes`, `on`.

Object values must be JSON-encoded. Invalid JSON returns the default value with `ErrorCode::PARSE_ERROR`.

## Error handling

If Redis is unavailable, the provider returns the default value with an error reason (`ErrorCode::GENERAL`) instead of throwing an exception. This makes it safe to use in production without risking a full outage if Redis goes down.
