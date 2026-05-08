<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\If_\CombineIfRector;
use Rector\CodeQuality\Rector\If_\ShortenElseIfRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\CodeQuality\Rector\Ternary\UnnecessaryTernaryExpressionRector;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\EarlyReturn\Rector\Foreach_\ChangeNestedForeachIfsToEarlyContinueRector;
use Rector\EarlyReturn\Rector\If_\ChangeIfElseValueAssignToEarlyReturnRector;
use Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector;
use Rector\EarlyReturn\Rector\Return_\PreparedValueToEarlyReturnRector;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

/**
 * Conservative Rector config for the maintenancemode package.
 *
 * Scope:  src/ only — tests/ stays as-is to keep BC and to avoid churn in
 *         test files that already pass PHPUnit and PHPStan.
 * Sets:   DEAD_CODE only — automatic upgrades up to PHP 8.2 are not enabled
 *         to avoid surprise refactors when a contributor runs `composer rector`.
 *
 * The intent is "Rector verde en CI" — every PR sees a passing dry-run. Add
 * more rules deliberately as the codebase matures.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::DEAD_CODE,
    ]);

    $rectorConfig->parallel();

    $rectorConfig->cacheClass(FileCacheStorage::class);
    if (is_dir('/tmp')) {
        $rectorConfig->cacheDirectory('/tmp/rector');
    }

    $rectorConfig->paths([
        __DIR__ . '/src/',
    ]);

    $rectorConfig->autoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ]);

    $rectorConfig->bootstrapFiles([
        __DIR__ . '/vendor/codeigniter4/framework/system/Test/bootstrap.php',
    ]);

    if (is_file(__DIR__ . '/phpstan.neon.dist')) {
        $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon.dist');
    }

    $rectorConfig->phpVersion(PhpVersion::PHP_82);

    $rectorConfig->importNames();

    $rectorConfig->skip([
        __DIR__ . '/src/Views',
        __DIR__ . '/src/Language',

        // BC: legacy IpUtils wrapper kept on purpose
        __DIR__ . '/src/Libraries/IpUtils.php',

        // Pre-existing legacy command outside the v3 refactor scope
        __DIR__ . '/src/Commands/Publish.php',

        RemoveUnusedPromotedPropertyRector::class,
    ]);

    // High-signal hand-picked rules — easy wins that read well in PRs.
    $rectorConfig->rule(RemoveAlwaysElseRector::class);
    $rectorConfig->rule(ChangeNestedForeachIfsToEarlyContinueRector::class);
    $rectorConfig->rule(ChangeIfElseValueAssignToEarlyReturnRector::class);
    $rectorConfig->rule(CombineIfRector::class);
    $rectorConfig->rule(SimplifyIfReturnBoolRector::class);
    $rectorConfig->rule(PreparedValueToEarlyReturnRector::class);
    $rectorConfig->rule(ShortenElseIfRector::class);
    $rectorConfig->rule(UnnecessaryTernaryExpressionRector::class);
    $rectorConfig->rule(MakeInheritedMethodVisibilitySameAsParentRector::class);
};
