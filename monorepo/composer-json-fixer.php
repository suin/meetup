#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace Suin\ComposerJsonFixer;

const ISSUE_URL = 'https://github.com/suin/php/issues';
const HOMEPAGE = 'https://github.com/suin/php';
const AUTHORS = [
    [
        'name' => 'suin',
        'email' => 'suinyeze@gmail.com',
        'homepage' => 'https://github.com/suin',
        'role' => 'Developer',
    ],
];
const KEY_ORDERS = [
    'name',
    'type',
    'description',
    'keywords',
    'homepage',
    'license',
    'authors',
    'minimum-stability',
    'prefer-stable',
    'support',
    'require',
    'require-dev',
    'conflict',
    'replace',
    'provide',
    'suggest',
    'autoload',
    'autoload-dev',
    'repositories',
    'config',
    'scripts',
    'scripts-descriptions',
    'extra',
];

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

require __DIR__ . '/../vendor/autoload.php';

$default_value = function (array $json, string $key, $defaultValue): array {
    return $json + [$key => $json[$key] ?? $defaultValue];
};

$name_must_exist = function (array $json) use ($default_value): array {
    return $default_value($json, 'name', '');
};

$type_must_exist = function (array $json) use ($default_value): array {
    return $default_value($json, 'type', 'library');
};

$description_must_exist = function (array $json) use ($default_value): array {
    return $default_value($json, 'description', '');
};

$keyword_must_exist = function (array $json) use ($default_value): array {
    return $default_value($json, 'keywords', []);
};

$keyword_must_be_sorted_alphabetically = function (array $json): array {
    if (\array_key_exists('keywords', $json)) {
        sort($json['keywords']);
    }
    return $json;
};

$homepage_must_be = function (string $homepage): callable {
    return function (array $json) use ($homepage): array {
        $json['homepage'] = $homepage;
        return $json;
    };
};

$license_must_be_MIT = function (array $json): array {
    $json['license'] = 'MIT';
    return $json;
};

$authors_must_exist = function (array $authors): callable {
    return function (array $json) use ($authors): array {
        $json['authors'] = $authors;
        return $json;
    };
};

$minimum_stability_must_be_stable = function (array $json): array {
    $json['minimum-stability'] = 'stable';
    return $json;
};

$must_prefer_stable = function (array $json): array {
    $json['prefer-stable'] = true;
    return $json;
};

$sort_packages_must_be_enabled = function (array $json): array {
    $json['config']['sort-packages'] = true;
    return $json;
};

$support_must_exist = function (array $json): array {
    $json['support'] = ['issues' => ISSUE_URL];
    return $json;
};

$package_must_be_sorted = function (array $json): array {
    $sort_packages = function (array &$packages): void {
        \ksort($packages);
        \uksort(
            $packages,
            function (string $a, string $b): int {
                return (\strncmp($a, 'ext-', 4) !== 0) <=>
                    (\strncmp($b, 'ext-', 4) !== 0);
            }
        );
        \uksort(
            $packages,
            function (string $a, string $b): int {
                return ($a !== 'php') <=> ($b !== 'php');
            }
        );
    };
    if (\array_key_exists('require', $json)) {
        $sort_packages($json['require']);
    }
    if (\array_key_exists('require-dev', $json)) {
        $sort_packages($json['require-dev']);
    }
    return $json;
};

$autoload_must_be_sorted = function (array $json): array {
    if (isset($json['autoload']['psr-4'])) {
        \ksort($json['autoload']['psr-4']);
    }
    if (isset($json['autoload']['psr-0'])) {
        \ksort($json['autoload']['psr-0']);
    }
    if (isset($json['autoload']['classmap'])) {
        sort($json['autoload']['classmap']);
    }
    if (isset($json['autoload']['files'])) {
        sort($json['autoload']['files']);
    }
    if (isset($json['autoload']['exclude-from-classmap'])) {
        sort($json['autoload']['exclude-from-classmap']);
    }
    if (isset($json['autoload-dev']['psr-4'])) {
        \ksort($json['autoload-dev']['psr-4']);
    }
    if (isset($json['autoload-dev']['psr-0'])) {
        \ksort($json['autoload-dev']['psr-0']);
    }
    if (isset($json['autoload-dev']['classmap'])) {
        sort($json['autoload-dev']['classmap']);
    }
    if (isset($json['autoload-dev']['files'])) {
        sort($json['autoload-dev']['files']);
    }
    if (isset($json['autoload-dev']['exclude-from-classmap'])) {
        sort($json['autoload-dev']['exclude-from-classmap']);
    }
    return $json;
};

$php_requirements_must_be = function (string $phpVersion): callable {
    return function (array $json) use ($phpVersion): array {
        $json['require']['php'] = $phpVersion;
        return $json;
    };
};

$keys_must_be_arranged = function (array $keyOrder): callable {
    return function (array $json) use ($keyOrder): array {
        $result = [];
        foreach ($keyOrder as $key) {
            if (\array_key_exists($key, $json)) {
                $result[$key] = $json[$key];
                unset($json[$key]);
            }
        }
        $result = \array_merge($result, $json);
        return $result;
    };
};

$fixerCombinator = function (callable ...$fixers): callable {
    return function (array $json) use ($fixers): array {
        foreach ($fixers as $fixer) {
            $json = $fixer($json);
        }
        return $json;
    };
};

$fixer = $fixerCombinator(
    $name_must_exist,
    $type_must_exist,
    $description_must_exist,
    $keyword_must_exist,
    $keyword_must_be_sorted_alphabetically,
    $homepage_must_be(HOMEPAGE),
    $license_must_be_MIT,
    $authors_must_exist(AUTHORS),
    $minimum_stability_must_be_stable,
    $must_prefer_stable,
    $sort_packages_must_be_enabled,
    $support_must_exist,
    $php_requirements_must_be('>=7.1 <7.4.0'),
    $autoload_must_be_sorted,
    $package_must_be_sorted,
    $keys_must_be_arranged(KEY_ORDERS)
);

/** @var Finder $finder */
$finder = Finder::create()
    ->files()
    ->in(__DIR__ . '/../packages')
    ->depth(1)
    ->name('composer.json');

/** @var SplFileInfo $composerFile */
foreach ($finder as $composerFile) {
    $contents = $composerFile->getContents();
    /** @noinspection PhpComposerExtensionStubsInspection */
    $json = \json_decode($contents, true);
    $fixedJson = $fixer($json);
    $fixed = 0;
    if ($json !== $fixedJson) {
        /** @noinspection PhpComposerExtensionStubsInspection */
        \file_put_contents(
            $composerFile->getRealPath(),
            \json_encode(
                $fixedJson,
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
            ) . "\n"
        );
        echo "Fixed: {$composerFile->getRealPath()}\n";
        $fixed++;
    }
    echo "{$fixed} composer.json files have been fixed.\n";
}
