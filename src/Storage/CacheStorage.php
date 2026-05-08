<?php

namespace Daycry\Maintenance\Storage;

use CodeIgniter\Cache\CacheInterface;
use Config\Services;
use Daycry\Maintenance\Config\Maintenance as MaintenanceConfig;
use Daycry\Maintenance\DTO\MaintenanceData;

/**
 * Cache (Redis/Memcached/File) backed storage. Atomic via the cache backend
 * itself; suitable for distributed deployments where a file on disk would only
 * cover one node.
 */
final class CacheStorage implements StorageInterface
{
    private readonly CacheInterface $cache;

    public function __construct(private readonly MaintenanceConfig $config, ?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? Services::cache();
    }

    public function isActive(): bool
    {
        return $this->cache->get($this->config->cacheKey) !== null;
    }

    public function getData(): ?MaintenanceData
    {
        $raw = $this->cache->get($this->config->cacheKey);
        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return MaintenanceData::fromArray($raw);
        }

        if (is_object($raw)) {
            return MaintenanceData::fromObject($raw);
        }

        return null;
    }

    public function save(MaintenanceData $data): bool
    {
        $success = $this->cache->save(
            $this->config->cacheKey,
            $data->toArray(),
            $this->config->cacheTTL,
        );

        $this->log($success ? 'info' : 'error', $success
            ? 'Maintenance data saved to cache'
            : 'Failed to save maintenance data to cache');

        return (bool) $success;
    }

    public function remove(): bool
    {
        $success = (bool) $this->cache->delete($this->config->cacheKey);

        $this->log($success ? 'info' : 'error', $success
            ? 'Maintenance data removed from cache'
            : 'Failed to remove maintenance data from cache');

        return $success;
    }

    public function clearAll(): bool
    {
        return $this->remove();
    }

    private function log(string $level, string $message): void
    {
        if ($this->config->enableLogging) {
            log_message($level, $message);
        }
    }
}
