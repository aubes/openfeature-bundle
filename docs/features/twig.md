# Twig helpers

The bundle registers two Twig functions for use in templates.

## `feature()` : boolean check

```twig
{% if feature('new_checkout') %}
    {# shown when the flag is true #}
{% endif %}
```

Returns `true` or `false`. Equivalent to `Client::getBooleanValue('new_checkout', false)`.

## `feature_value()` : typed value

```twig
{{ feature_value('theme', 'light') }}
{{ feature_value('max_items', 10) }}
{{ feature_value('ratio', 0.5) }}
```

The SDK method is dispatched based on the type of the default value:

| Default value type | SDK method called |
|---|---|
| `bool` | `getBooleanValue()` |
| `string` | `getStringValue()` |
| `int` | `getIntegerValue()` |
| `float` | `getFloatValue()` |
| `array` | `getObjectValue()` |

> **Note:** The Twig extension is only registered when `twig/twig` is available in the project.
