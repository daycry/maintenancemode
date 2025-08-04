<?php

namespace Daycry\Maintenance\Config;

use CodeIgniter\Config\BaseConfig;

class Maintenance extends BaseConfig
{
    // --------------------------------------------------------------------
    // Maintenance mode file configuration
    // --------------------------------------------------------------------

    /**
     * Path where the maintenance mode file will be stored (legacy support)
     */
    public string $filePath = WRITEPATH . 'maintenance/';

    /**
     * Name of the maintenance mode file (legacy support)
     */
    public string $fileName = 'down';

    // --------------------------------------------------------------------
    // Cache configuration options
    // --------------------------------------------------------------------

    /**
     * Use cache instead of file storage for maintenance data
     */
    public bool $useCache = true;

    /**
     * Cache key for maintenance mode data
     */
    public string $cacheKey = 'maintenance_mode_data';

    /**
     * Cache TTL in seconds (0 = no expiration)
     */
    public int $cacheTTL = 0;

    /**
     * Cache handler to use (null = default)
     */
    public ?string $cacheHandler = null;

    // --------------------------------------------------------------------
    // Advanced configuration options
    // --------------------------------------------------------------------

    /**
     * Enable logging of maintenance mode events
     */
    public bool $enableLogging = true;

    /**
     * Default maintenance message when none is provided
     */
    public string $defaultMessage = 'We are currently performing scheduled maintenance. Please try again later.';

    /**
     * Show estimated time when maintenance will be completed
     */
    public bool $showEstimatedTime = false;

    /**
     * Default estimated duration in minutes
     */
    public int $defaultDurationMinutes = 60;

    /**
     * Custom template for maintenance page (optional)
     * Leave empty to use default template
     */
    public string $customTemplate = '';

    /**
     * Retry-After header value in seconds
     */
    public int $retryAfterSeconds = 3600;

    /**
     * Allow bypass via secret parameter in URL
     * Example: ?maintenance_secret=your_secret_key
     */
    public bool $allowSecretBypass = false;

    /**
     * Secret key for URL bypass (only used if allowSecretBypass is true)
     */
    public string $secretBypassKey = '';
}
