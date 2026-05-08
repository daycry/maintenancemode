<?php

namespace Daycry\Maintenance\Libraries;

use Daycry\Maintenance\Config\Maintenance as MaintenanceConfig;
use Daycry\Maintenance\DTO\MaintenanceData;
use Daycry\Maintenance\Storage\CacheStorage;
use Daycry\Maintenance\Storage\FileStorage;
use Daycry\Maintenance\Storage\StorageFactory;

/**
 * Backwards-compatible facade over the new Storage drivers.
 *
 * Existing callers (Controllers, Commands, tests) keep working unchanged:
 *   isActive(), getData(), save(array), remove(), clearAll(), migrateToCache()
 *
 * Internally this delegates to the right StorageInterface implementation
 * picked from $config (driver / useCache).
 */
class MaintenanceStorage
{
    private MaintenanceConfig $config;

    public function __construct(?MaintenanceConfig $config = null)
    {
        $this->config = $config ?? new MaintenanceConfig();
    }

    public function isActive(): bool
    {
        return StorageFactory::make($this->config)->isActive();
    }

    /**
     * Returns the maintenance window as a stdClass-style object so legacy
     * callers (e.g. Controllers/Maintenance.php) can keep using
     * `$data->cookie_name` etc. unchanged.
     */
    public function getData(): ?object
    {
        $data = StorageFactory::make($this->config)->getData();

        return $data === null ? null : (object) $data->toArray();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): bool
    {
        return StorageFactory::make($this->config)->save(MaintenanceData::fromArray($data));
    }

    public function remove(): bool
    {
        return StorageFactory::make($this->config)->remove();
    }

    /**
     * Clear state from BOTH backends — useful when migrating between drivers
     * or when tests swap config mid-run.
     */
    public function clearAll(): bool
    {
        $cacheCleared = (new CacheStorage($this->config))->clearAll();
        $fileCleared  = (new FileStorage($this->config))->clearAll();

        return $cacheCleared && $fileCleared;
    }

    /**
     * Read maintenance data from FILE storage and write it to CACHE storage.
     * Returns false if the file does not exist or is corrupt.
     */
    public function migrateToCache(): bool
    {
        if (! $this->config->useCache) {
            return false;
        }

        $file = new FileStorage($this->config);
        $data = $file->getData();

        if ($data === null) {
            return file_exists($this->filePath()) === false;
        }

        $cache = new CacheStorage($this->config);
        if (! $cache->save($data)) {
            return false;
        }

        $file->remove();

        if ($this->config->enableLogging) {
            log_message('info', 'Maintenance data migrated from file to cache');
        }

        return true;
    }

    private function filePath(): string
    {
        helper('setting');

        return (setting('Maintenance.filePath') ?? $this->config->filePath)
            . (setting('Maintenance.fileName') ?? $this->config->fileName);
    }
}
