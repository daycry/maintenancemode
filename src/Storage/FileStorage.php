<?php

namespace Daycry\Maintenance\Storage;

use Daycry\Maintenance\Config\Maintenance as MaintenanceConfig;
use Daycry\Maintenance\DTO\MaintenanceData;
use JsonException;

/**
 * File-system backed storage. Writes are atomic via flock(LOCK_EX) and JSON
 * encode/decode use JSON_THROW_ON_ERROR so corrupt payloads fail loudly.
 */
final class FileStorage implements StorageInterface
{
    public function __construct(private readonly MaintenanceConfig $config)
    {
    }

    public function isActive(): bool
    {
        return file_exists($this->path());
    }

    public function getData(): ?MaintenanceData
    {
        $path = $this->path();

        if (! file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        try {
            return MaintenanceData::fromJson($content);
        } catch (JsonException $e) {
            $this->log('error', 'Maintenance file is not valid JSON: ' . $e->getMessage());

            return null;
        }
    }

    public function save(MaintenanceData $data): bool
    {
        $dir = $this->dir();
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->log('error', 'Failed to create maintenance directory: ' . $dir);

            return false;
        }

        try {
            $payload = json_encode(
                $data->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $e) {
            $this->log('error', 'Failed to encode maintenance data: ' . $e->getMessage());

            return false;
        }

        $path    = $this->path();
        $success = $this->writeExclusive($path, $payload);

        $this->log(
            $success ? 'info' : 'error',
            ($success ? 'Maintenance data saved to file: ' : 'Failed to save maintenance data to file: ') . $path,
        );

        return $success;
    }

    public function remove(): bool
    {
        $path = $this->path();

        if (! file_exists($path)) {
            return true;
        }

        $success = @unlink($path);

        $this->log(
            $success ? 'info' : 'error',
            ($success ? 'Maintenance file removed: ' : 'Failed to remove maintenance file: ') . $path,
        );

        return $success;
    }

    public function clearAll(): bool
    {
        return $this->remove();
    }

    private function dir(): string
    {
        helper('setting');

        return setting('Maintenance.filePath') ?? $this->config->filePath;
    }

    private function path(): string
    {
        helper('setting');

        return $this->dir() . (setting('Maintenance.fileName') ?? $this->config->fileName);
    }

    private function writeExclusive(string $path, string $contents): bool
    {
        $fp = @fopen($path, 'c+b');
        if ($fp === false) {
            return false;
        }

        try {
            if (! flock($fp, LOCK_EX)) {
                return false;
            }

            ftruncate($fp, 0);
            rewind($fp);

            if (fwrite($fp, $contents) === false) {
                return false;
            }

            fflush($fp);
            flock($fp, LOCK_UN);

            return true;
        } finally {
            fclose($fp);
        }
    }

    private function log(string $level, string $message): void
    {
        if ($this->config->enableLogging) {
            log_message($level, $message);
        }
    }
}
