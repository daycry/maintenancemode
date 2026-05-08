<?php

namespace Daycry\Maintenance\Storage;

use Daycry\Maintenance\DTO\MaintenanceData;

/**
 * Contract every maintenance-mode storage driver must satisfy.
 *
 * Drivers are responsible for persistence only — validation, bypass logic and
 * event dispatching live in {@see \Daycry\Maintenance\Services\MaintenanceService}.
 */
interface StorageInterface
{
    public function isActive(): bool;

    /**
     * Read the current maintenance window. Returns null if maintenance is not
     * active or if the persisted data is unreadable/corrupt.
     */
    public function getData(): ?MaintenanceData;

    public function save(MaintenanceData $data): bool;

    public function remove(): bool;

    /**
     * Wipe state from THIS driver. Useful for tests; production usually calls
     * {@see remove()} instead.
     */
    public function clearAll(): bool;
}
