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
     * Storage driver. One of: 'cache', 'file'. When set, takes precedence over
     * $useCache. Leave null to fall back to the legacy $useCache flag.
     */
    public ?string $driver = null;

    /**
     * Use cache instead of file storage for maintenance data.
     *
     * @deprecated since v3.0 — set $driver to 'cache' or 'file' instead.
     *             Kept for backwards compatibility while users migrate.
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

    // --------------------------------------------------------------------
    // Sprint 3 — feature parity with Laravel `artisan down`
    // --------------------------------------------------------------------

    /**
     * Routes that should bypass the filter entirely (healthchecks, webhooks).
     * Each entry is matched against the request URI path. Trailing `*`
     * wildcards are supported, e.g. '/api/webhooks/*'.
     *
     * @var list<string>
     */
    public array $bypassRoutes = [];

    /**
     * Routes (or path prefixes) that should always receive a JSON 503 response
     * instead of HTML when maintenance is active. Trailing `*` wildcards are
     * supported. The `Accept: application/json` request header also forces a
     * JSON response regardless of this list.
     *
     * @var list<string>
     */
    public array $jsonRoutes = [];

    /**
     * When true, a successful URL secret bypass also sets the bypass cookie
     * (HttpOnly, SameSite=Lax) so the rest of the session works without
     * dragging the secret through every URL. Cookie name and value come from
     * the active maintenance window data.
     */
    public bool $autoIssueBypassCookie = true;

    /**
     * Lifetime in seconds for the auto-issued bypass cookie. Default is 1 day.
     * Set to 0 to make it a session cookie (cleared when the browser closes).
     */
    public int $bypassCookieLifetime = 86400;

    /**
     * Optional per-environment view templates for the 503 page. Keyed by the
     * value of the `ENVIRONMENT` constant (typically 'production',
     * 'development', 'testing'). Falls back to $customTemplate, then to the
     * bundled default.
     *
     * @var array<string, string>
     */
    public array $templateByEnv = [];
}
