<?php

namespace Daycry\Maintenance\Storage;

use Daycry\Maintenance\Config\Maintenance as MaintenanceConfig;

/**
 * Resolves the right storage driver from the supplied {@see MaintenanceConfig}.
 *
 * Selection order:
 *   1. $config->driver — explicit string ('cache' | 'file'). Wins if set.
 *   2. $config->useCache (legacy) — true → cache, false → file.
 *
 * The factory never caches the driver — callers should hold their own
 * reference. This keeps tests deterministic when configs are swapped.
 */
final class StorageFactory
{
    public static function make(MaintenanceConfig $config): StorageInterface
    {
        $driver = self::resolveDriver($config);

        return match ($driver) {
            'cache' => new CacheStorage($config),
            'file'  => new FileStorage($config),
            default => new FileStorage($config),
        };
    }

    private static function resolveDriver(MaintenanceConfig $config): string
    {
        if (isset($config->driver) && is_string($config->driver) && $config->driver !== '') {
            return strtolower($config->driver);
        }

        return $config->useCache ? 'cache' : 'file';
    }
}
