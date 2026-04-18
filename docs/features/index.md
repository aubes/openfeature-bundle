# Features

The bundle provides several Symfony-native ways to use feature flags:

| Feature | Description |
|---|---|
| [#[FeatureFlag]](feature-flag.md) | Inject resolved flag values into controller parameters |
| [#[FeatureGate]](feature-gate.md) | Block access to a controller action based on a flag |
| [Twig](twig.md) | `feature()` and `feature_value()` template helpers |
| [EvaluationContext](evaluation-context.md) | Pass targeting information to providers |
| [Hooks](hooks.md) | Run logic around every flag evaluation |
