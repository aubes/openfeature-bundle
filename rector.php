<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php82: true)
    ->withSkip([
        // OpenFeature SDK uses MyCLabs\Enum, not native PHP enums
        Rector\Php81\Rector\MethodCall\MyCLabsMethodCallToEnumConstRector::class,
        NullToStrictStringFuncCallArgRector::class,
    ])
;
