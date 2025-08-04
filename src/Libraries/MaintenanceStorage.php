<?php

namespace Daycry\Maintenance\Libraries;

use CodeIgniter\Cache\CacheInterface;
use Config\Services;
use Daycry\Maintenance\Config\Maintenance as MaintenanceConfig;

class MaintenanceStorage
{
    private MaintenanceConfig $config;
    private ?CacheInterface $cache = null;

    public function __construct(?MaintenanceConfig $config = null)
    {
        $this->config = $config ?? new MaintenanceConfig();
        
        if ($this->config->useCache) {
            $this->cache = $this->config->cacheHandler 
                ? Services::cache($this->config->cacheHandler) 
                : Services::cache();
        }
    }

    /**
     * Check if maintenance mode is active
     */
    public function isActive(): bool
    {
        if ($this->config->useCache) {
            return $this->cache->get($this->config->cacheKey) !== null;
        }

        // Fallback to file system
        helper('setting');
        return file_exists(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
    }

    /**
     * Get maintenance mode data
     */
    public function getData(): ?object
    {
        if ($this->config->useCache) {
            $data = $this->cache->get($this->config->cacheKey);
            return $data ? (object) $data : null;
        }

        // Fallback to file system
        helper('setting');
        $filePath = setting('Maintenance.filePath') . setting('Maintenance.fileName');
        
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        return $content ? json_decode($content) : null;
    }

    /**
     * Save maintenance mode data
     */
    public function save(array $data): bool
    {
        if ($this->config->useCache) {
            $success = $this->cache->save(
                $this->config->cacheKey, 
                $data, 
                $this->config->cacheTTL
            );

            if ($success && $this->config->enableLogging) {
                log_message('info', 'Maintenance mode data saved to cache');
            }

            return $success;
        }

        // Fallback to file system
        helper('setting');
        $filePath = setting('Maintenance.filePath');
        
        // Create directory if it doesn't exist
        if (!is_dir($filePath)) {
            if (!mkdir($filePath, 0755, true)) {
                if ($this->config->enableLogging) {
                    log_message('error', 'Failed to create maintenance directory: ' . $filePath);
                }
                return false;
            }
        }

        $fullPath = $filePath . setting('Maintenance.fileName');
        $success = file_put_contents(
            $fullPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ) !== false;

        if ($success && $this->config->enableLogging) {
            log_message('info', 'Maintenance mode data saved to file: ' . $fullPath);
        } elseif (!$success && $this->config->enableLogging) {
            log_message('error', 'Failed to save maintenance mode data to file: ' . $fullPath);
        }

        return $success;
    }

    /**
     * Remove maintenance mode data
     */
    public function remove(): bool
    {
        if ($this->config->useCache) {
            $success = $this->cache->delete($this->config->cacheKey);

            if ($success && $this->config->enableLogging) {
                log_message('info', 'Maintenance mode data removed from cache');
            }

            return $success;
        }

        // Fallback to file system
        helper('setting');
        $filePath = setting('Maintenance.filePath') . setting('Maintenance.fileName');
        
        if (!file_exists($filePath)) {
            return true; // Already removed
        }

        $success = @unlink($filePath);

        if ($success && $this->config->enableLogging) {
            log_message('info', 'Maintenance mode file removed: ' . $filePath);
        } elseif (!$success && $this->config->enableLogging) {
            log_message('error', 'Failed to remove maintenance mode file: ' . $filePath);
        }

        return $success;
    }

    /**
     * Clear both cache and file storage (useful for migration)
     */
    public function clearAll(): bool
    {
        $cacheCleared = true;
        $fileCleared = true;

        // Clear cache
        if ($this->config->useCache) {
            $cacheCleared = $this->cache->delete($this->config->cacheKey);
        }

        // Clear file
        helper('setting');
        $filePath = setting('Maintenance.filePath') . setting('Maintenance.fileName');
        if (file_exists($filePath)) {
            $fileCleared = @unlink($filePath);
        }

        return $cacheCleared && $fileCleared;
    }

    /**
     * Migrate from file storage to cache storage
     */
    public function migrateToCache(): bool
    {
        if (!$this->config->useCache) {
            return false;
        }

        // Check if there's file data to migrate
        helper('setting');
        $filePath = setting('Maintenance.filePath') . setting('Maintenance.fileName');
        
        if (!file_exists($filePath)) {
            return true; // Nothing to migrate
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (!$data) {
            return false; // Invalid data
        }

        // Save to cache
        $success = $this->cache->save(
            $this->config->cacheKey,
            $data,
            $this->config->cacheTTL
        );

        if ($success) {
            // Remove old file
            @unlink($filePath);
            
            if ($this->config->enableLogging) {
                log_message('info', 'Maintenance mode data migrated from file to cache');
            }
        }

        return $success;
    }
}
