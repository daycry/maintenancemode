<?php

use Daycry\Maintenance\Services\MaintenanceService;

if (! function_exists('maintenance')) {
    /**
     * Convenience accessor for the maintenance service.
     *
     * Usage:
     *   if (maintenance()->isActive()) { ... }
     *
     * The service is built from the currently active Config\Maintenance via
     * config(). Tests that swap the config with Factories::injectMock() will
     * see the swapped version on subsequent calls (no stale state).
     */
    function maintenance(): MaintenanceService
    {
        return MaintenanceService::fromCurrentConfig();
    }
}
