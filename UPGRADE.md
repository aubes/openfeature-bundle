# Upgrade guide

## Upgrading from 0.3 to 0.4

0.4 is a minor release with breaking changes, as allowed for 0.x versions by [Semantic Versioning][semver-4]. This guide lists what you may have to change, grouped by how you use the bundle. The [changelog][changelog] has the complete list of changes.

### Everyone

**The bundle requires `open-feature/sdk` 2.3.** Update it if your lock file pins an older version:

```bash
composer update open-feature/sdk
```

**The `API` service is an isolated instance.** It no longer shares its provider, hooks, and evaluation context with the `OpenFeatureAPI::getInstance()` singleton. Code calling `getInstance()` directly now gets a different, unconfigured instance: inject the `API` or `Client` service instead.

```php
// Before
$client = OpenFeatureAPI::getInstance()->getClient();

// After
public function __construct(private readonly Client $client) {}
```

### If you configure the bundle

**`evaluation_context.user_provider: auto` now takes effect.** It used to resolve to `false` in every application. With SecurityBundle enabled, the identifier of the authenticated user now becomes the targeting key, and it is sent to your flag provider. To keep the previous behavior, disable it:

```yaml
open_feature:
    evaluation_context:
        user_provider: false
```

If the user identifier is personal data (an email address, for example), read the note in [Evaluation context][evaluation-context].

**`feature_flag.on_disabled: auto` depends on SecurityBundle.** It picks `access_denied` only when SecurityBundle is enabled, and `http_exception` otherwise. Only an application with `symfony/security-core` installed but no SecurityBundle is affected: a closed `#[FeatureGate]` now returns a 403 instead of a 500. To keep throwing an `AccessDeniedException`, set it explicitly:

```yaml
open_feature:
    feature_flag:
        on_disabled: access_denied
```

**Keys under `flags` and `providers` are kept as declared.** Dashes were converted to underscores, so a flag declared as `new-checkout` could only be evaluated as `new_checkout`. Evaluate it with its declared key, or rename it. The same applies to provider names referenced by `strategy.fallback`.

The undocumented list form of `flags` is no longer supported. Use a map:

```yaml
# Before
open_feature:
    flags:
        - { name: dark_mode, value: true }

# After
open_feature:
    flags:
        dark_mode: true
```

**Provider services must declare their class.** A provider created by a factory, or defined through `parent` under an id that is not a class name, needs an explicit `class` option. Without it, the container fails to compile with `Class "" used for OpenFeature provider service "..." cannot be found`.

```yaml
services:
    app.feature_provider:
        class: App\FeatureFlag\MyProvider
        factory: ['@App\FeatureFlag\ProviderFactory', 'create']
```

When the concrete class is unknown, `class: OpenFeature\interfaces\provider\Provider` is accepted.

### If you use the built-in providers

**`EnvVarProvider` and `RedisProvider` no longer cast raw values.** A value that does not match the requested type now resolves to the default value with a `PARSE_ERROR`, instead of being silently cast. Check your environment variables and Redis keys against the accepted values listed in [EnvVar provider][env-var] and [Redis provider][redis]:

```bash
# Before: resolved to false and 10
FEATURE_NEW_CHECKOUT=enabled
FEATURE_MAX_ITEMS=10.0

# After
FEATURE_NEW_CHECKOUT=true
FEATURE_MAX_ITEMS=10
```

**`InMemoryProvider` checks the type of each flag.** A flag read with a method that does not match its YAML type now resolves to the default value with a `TYPE_MISMATCH`, as with typed providers such as flagd. The only conversions left are `0`/`1` read as booleans and integers read as floats. See the table in [InMemory provider][in-memory]:

```yaml
open_feature:
    flags:
        max_items: 10        # was 1.5, read as an integer
        label: '42'          # was 42, read as a string
```

**In Twig, pass a default of the flag's type to `feature_value()`.** Without a default, the flag is read as a string, so a boolean or integer flag now renders `''`:

```twig
{# Before #}
{{ feature_value('max_items') }}

{# After #}
{{ feature_value('max_items', 10) }}
```

These errors are not logged by the SDK. In dev, the error column of the `open_feature` profiler panel shows them.

**`RedisProvider` logs client failures** at `error` level, once per flag evaluation while Redis is unavailable. See [Redis provider][redis] to limit the volume.

### If you wrote code around the bundle

**Evaluation context providers run on the first flag evaluation**, not on `kernel.request`. A request that evaluates no flag never runs them. Logic that must run at the start of every request (side effects, timing) belongs in its own `kernel.request` listener. Two related changes:

- An exception thrown by a context provider, or by a listener of `EvaluationContextContributedEvent`, is now logged instead of failing the request.
- A flag evaluated from inside a context provider gets an empty context.

**The API-level evaluation context is lazy.** `API::getEvaluationContext()` now returns an internal lazy context: an `instanceof MutableEvaluationContext` check no longer matches, and reading its targeting key or attributes runs the context providers. To add data to the context, implement `EvaluationContextProviderInterface`, or pass an invocation context to the evaluation.

**`ResolutionDetailsTrait::toBool()` is removed.** Custom providers using the trait switch to `parseBool()`, which returns the `ResolutionDetails` directly. `parseInt()`, `parseFloat()`, and `parseObject()` follow the same pattern:

```php
// Before
return $this->found($this->toBool($raw));

// After
return $this->parseBool($flagKey, $raw, $defaultValue);
```

**Provider validation messages changed.** If your tests assert them, a class that does not exist now reports `Class "..." used for OpenFeature provider service "..." cannot be found`, and a class that is not a provider reports `OpenFeature provider service "..." (class "...") must implement interface "OpenFeature\interfaces\provider\Provider"`.

[semver-4]: https://semver.org/#spec-item-4
[changelog]: CHANGELOG.md
[evaluation-context]: docs/features/evaluation-context.md
[env-var]: docs/providers/env-var.md
[redis]: docs/providers/redis.md
[in-memory]: docs/providers/in-memory.md
