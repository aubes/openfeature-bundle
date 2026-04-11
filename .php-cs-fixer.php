<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.cache')
    ->setRules(
        [
            '@Symfony' => true,
            '@Symfony:risky' => true,

            'concat_space' => ['spacing' => 'one'],
            'native_function_invocation' => ['include' => ['@internal', 'str_start_with', 'str_contains']],
            'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
            'declare_strict_types' => true,

            'phpdoc_types_order' => true,
            'phpdoc_order' => true,
        ]
    )
;