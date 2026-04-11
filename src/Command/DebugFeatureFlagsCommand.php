<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Command;

use Aubes\OpenFeatureBundle\Attribute\FeatureFlag;
use Aubes\OpenFeatureBundle\Attribute\FeatureGate;
use OpenFeature\interfaces\flags\API;
use OpenFeature\interfaces\flags\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'debug:feature-flags',
    description: 'Display feature flags used in the application',
)]
class DebugFeatureFlagsCommand extends Command
{
    public function __construct(
        private readonly Client $client,
        private readonly API $api,
        private readonly RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->note('Flags are evaluated without HTTP request context (no authenticated user, no request attributes).');

        $this->displayProviderInfo($io);
        $this->displayFlags($io);
        $this->displayEvaluationContext($io);
        $this->displayHooks($io);

        return Command::SUCCESS;
    }

    private function displayProviderInfo(SymfonyStyle $io): void
    {
        $io->section('Provider');
        $io->text($this->api->getProviderMetadata()->getName());
    }

    private function displayFlags(SymfonyStyle $io): void
    {
        $io->section('Feature flags');

        $flags = $this->collectFlags();

        if ($flags === []) {
            $io->info('No #[FeatureFlag] or #[FeatureGate] attributes found in controllers.');

            return;
        }

        $rows = [];
        foreach ($flags as $flag) {
            $value = $this->evaluateFlag($flag['name'], $flag['type']);

            $rows[] = [
                $flag['name'],
                $flag['attribute'],
                $flag['type'],
                $this->formatValue($value),
                $flag['location'],
            ];
        }

        $io->table(['Flag', 'Attribute', 'Type', 'Value', 'Used in'], $rows);
    }

    private function displayEvaluationContext(SymfonyStyle $io): void
    {
        $context = $this->api->getEvaluationContext();

        $io->section('Evaluation context');

        if ($context === null) {
            $io->text('(none)');

            return;
        }

        $attributes = $context->getAttributes()->toArray();
        $targetingKey = $context->getTargetingKey();

        if ($targetingKey === null && $attributes === []) {
            $io->text('(empty)');

            return;
        }

        if ($targetingKey !== null) {
            $io->text(\sprintf('targeting_key: %s', $targetingKey));
        }

        foreach ($attributes as $key => $value) {
            $io->text(\sprintf('%s: %s', $key, $this->formatValue($value)));
        }
    }

    private function displayHooks(SymfonyStyle $io): void
    {
        $hooks = $this->api->getHooks();

        $io->section('Hooks');

        if ($hooks === []) {
            $io->text('(none)');

            return;
        }

        foreach ($hooks as $hook) {
            $io->text($hook::class);
        }
    }

    /**
     * @return list<array{name: string, attribute: string, type: string, location: string}>
     */
    private function collectFlags(): array
    {
        $flags = [];
        $seen = [];

        foreach ($this->router->getRouteCollection() as $route) {
            $defaults = $route->getDefaults();

            if (!isset($defaults['_controller'])) {
                continue;
            }

            $controller = $defaults['_controller'];

            if (!\is_string($controller)) {
                continue;
            }

            if (\str_contains($controller, '::')) {
                [$class, $method] = \explode('::', $controller, 2);
            } else {
                $class = $controller;
                $method = '__invoke';
            }

            if (!\class_exists($class)) {
                continue;
            }

            try {
                $reflMethod = new \ReflectionMethod($class, $method);
            } catch (\ReflectionException) {
                continue;
            }

            $location = \sprintf('%s::%s', $this->shortenClass($class), $method);

            foreach ($reflMethod->getAttributes(FeatureGate::class) as $attr) {
                /** @var FeatureGate $gate */
                $gate = $attr->newInstance();
                $key = $gate->flag . '|gate|' . $location;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $flags[] = [
                        'name' => $gate->flag,
                        'attribute' => 'FeatureGate',
                        'type' => 'bool',
                        'location' => $location,
                    ];
                }
            }

            foreach ($reflMethod->getParameters() as $param) {
                foreach ($param->getAttributes(FeatureFlag::class) as $attr) {
                    /** @var FeatureFlag $flag */
                    $flag = $attr->newInstance();
                    $type = $this->resolveParameterType($param);
                    $key = $flag->flag . '|flag|' . $location;
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $flags[] = [
                            'name' => $flag->flag,
                            'attribute' => 'FeatureFlag',
                            'type' => $type,
                            'location' => $location,
                        ];
                    }
                }
            }
        }

        \usort($flags, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $flags;
    }

    private function resolveParameterType(\ReflectionParameter $param): string
    {
        $type = $param->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return 'bool';
        }

        return match ($type->getName()) {
            'string' => 'string',
            'int' => 'int',
            'float' => 'float',
            'array' => 'array',
            default => 'bool',
        };
    }

    private function evaluateFlag(string $name, string $type): mixed
    {
        return match ($type) {
            'string' => $this->client->getStringValue($name, ''),
            'int' => $this->client->getIntegerValue($name, 0),
            'float' => $this->client->getFloatValue($name, 0.0),
            'array' => $this->client->getObjectValue($name, []),
            default => $this->client->getBooleanValue($name, false),
        };
    }

    private function formatValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_array($value)) {
            return \json_encode($value, \JSON_THROW_ON_ERROR);
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return \is_string($value) ? $value : '';
    }

    private function shortenClass(string $class): string
    {
        $parts = \explode('\\', $class);

        if (\count($parts) <= 3) {
            return $class;
        }

        return \sprintf('%s\\...\\%s', $parts[0], \end($parts));
    }
}
