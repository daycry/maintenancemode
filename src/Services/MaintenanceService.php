<?php

namespace Daycry\Maintenance\Services;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use Daycry\Maintenance\Config\Maintenance as MaintenanceConfig;
use Daycry\Maintenance\DTO\MaintenanceData;
use Daycry\Maintenance\Libraries\IpChecker;
use Daycry\Maintenance\Storage\StorageFactory;
use Daycry\Maintenance\Storage\StorageInterface;
use Throwable;

/**
 * Single source of truth for maintenance-mode logic.
 *
 * All bypass evaluation, timing-safe comparisons and event dispatching lives
 * here so Controllers, Filters and CLI commands can share one code path.
 */
class MaintenanceService
{
    private readonly StorageInterface $storage;
    private readonly IpChecker $ipChecker;

    public function __construct(
        private readonly MaintenanceConfig $config,
        ?StorageInterface $storage = null,
        ?IpChecker $ipChecker = null,
    ) {
        $this->storage   = $storage ?? StorageFactory::make($config);
        $this->ipChecker = $ipChecker ?? new IpChecker();
    }

    public function isActive(): bool
    {
        return $this->storage->isActive();
    }

    public function getData(): ?MaintenanceData
    {
        return $this->storage->getData();
    }

    /**
     * Evaluate whether the given request should be allowed through. Pure: it
     * neither throws nor sets headers. The caller (Filter/Controller) decides
     * how to react to a {@see CheckResult::denied()} outcome.
     */
    public function check(IncomingRequest $request): CheckResult
    {
        if (! $this->isActive()) {
            return CheckResult::inactive();
        }

        $data = $this->getData();
        if ($data === null) {
            // Corrupt data — fail open to avoid locking the site out.
            $this->log('error', 'Maintenance mode data is invalid or corrupted');

            return CheckResult::inactive();
        }

        $now = time();

        // Scheduled window: not started yet → let traffic through.
        if ($data->isPending($now)) {
            return CheckResult::pending();
        }

        // Scheduled window: ended → auto-deactivate and let traffic through.
        if ($data->isExpired($now)) {
            $this->storage->remove();
            $this->log('info', 'Scheduled maintenance window expired; auto-deactivated');
            Events::trigger('maintenance.scheduled.expired', ['data' => $data]);

            return CheckResult::inactive();
        }

        // Bypass routes (healthchecks, webhooks) — checked before any auth.
        if ($this->matchesBypassRoute($request)) {
            return CheckResult::bypassedRoute();
        }

        $clientIp = $this->resolveClientIp($request);

        // 1. Config-level secret bypass (?maintenance_secret=...)
        if ($this->config->allowSecretBypass && $this->config->secretBypassKey !== '') {
            $provided = (string) ($request->getGet('maintenance_secret') ?? '');
            if ($provided !== '' && hash_equals($this->config->secretBypassKey, $provided)) {
                $this->log('info', 'Maintenance bypassed via config secret from IP: ' . $clientIp);
                $this->dispatchBypass('config_secret', $clientIp);

                return CheckResult::bypassed('config_secret', $this->buildAutoCookie($data));
            }
        }

        // 2. Window-level secret stored in the maintenance data
        if ($data->secret_bypass && $data->secret_key !== '') {
            $provided = (string) ($request->getGet('maintenance_secret') ?? '');
            if ($provided !== '' && hash_equals($data->secret_key, $provided)) {
                $this->log('info', 'Maintenance bypassed via data secret from IP: ' . $clientIp);
                $this->dispatchBypass('data_secret', $clientIp);

                return CheckResult::bypassed('data_secret', $this->buildAutoCookie($data));
            }
        }

        // 3. Allow-listed IP / CIDR
        if ($data->allowed_ips !== [] && $this->ipChecker->checkIp($clientIp, $data->allowed_ips)) {
            $this->log('info', 'Maintenance bypassed for allowed IP: ' . $clientIp);
            $this->dispatchBypass('ip', $clientIp);

            return CheckResult::bypassed('ip');
        }

        // 4. Pre-issued bypass cookie
        if ($data->cookie_name !== '' && $data->cookie_value !== '') {
            $provided = (string) ($request->getCookie($data->cookie_name) ?? '');
            if ($provided !== '' && hash_equals($data->cookie_value, $provided)) {
                $this->log('info', 'Maintenance bypassed via cookie for IP: ' . $clientIp);
                $this->dispatchBypass('cookie', $clientIp);

                return CheckResult::bypassed('cookie');
            }
        }

        // No bypass matched — denied.
        $this->log('info', 'Maintenance blocking access from IP: ' . $clientIp);
        Events::trigger('maintenance.access_denied', [
            'ip'   => $clientIp,
            'data' => $data,
        ]);

        return CheckResult::denied();
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->config->retryAfterSeconds;
    }

    public function getDefaultMessage(): string
    {
        return $this->config->defaultMessage;
    }

    public function getConfig(): MaintenanceConfig
    {
        return $this->config;
    }

    /**
     * Pick the right view template for the active environment + window.
     * Priority: window override → templateByEnv → customTemplate → bundled.
     */
    public function resolveTemplate(?MaintenanceData $data = null): string
    {
        $data ??= $this->getData();

        if ($data !== null && $data->render_template !== '') {
            return $data->render_template;
        }

        $envKey = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';
        if (isset($this->config->templateByEnv[$envKey])
            && $this->config->templateByEnv[$envKey] !== '') {
            return $this->config->templateByEnv[$envKey];
        }

        return $this->config->customTemplate;
    }

    /**
     * Decide whether to respond with JSON for this request. True when the
     * request asks for JSON (Accept) or when the URI matches one of the
     * configured $jsonRoutes patterns.
     */
    public function shouldRespondJson(IncomingRequest $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        if ($accept !== '' && str_contains(strtolower($accept), strtolower('application/json'))) {
            return true;
        }

        $path = $this->safePath($request);
        if ($path === '') {
            return false;
        }

        foreach ($this->config->jsonRoutes as $pattern) {
            if ($this->pathMatches($path, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match the current request URI against $config->bypassRoutes patterns.
     * Trailing `*` wildcards are supported.
     */
    private function matchesBypassRoute(IncomingRequest $request): bool
    {
        if ($this->config->bypassRoutes === []) {
            return false;
        }

        $path = $this->safePath($request);
        if ($path === '') {
            return false;
        }

        foreach ($this->config->bypassRoutes as $pattern) {
            if ($this->pathMatches($path, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the request path defensively. Returns '' when no URI is available
     * (e.g. mocked test requests that don't stub getUri()).
     */
    private function safePath(IncomingRequest $request): string
    {
        try {
            $uri = $request->getUri();
        } catch (Throwable) {
            return '';
        }

        if ($uri === null) {
            return '';
        }

        return '/' . ltrim((string) $uri->getPath(), '/');
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        $pattern = '/' . ltrim($pattern, '/');

        if (str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);

            return str_starts_with($path, $prefix);
        }

        return $path === $pattern;
    }

    /**
     * Build the auto-issued bypass cookie payload, or null if disabled.
     *
     * @return array{name: string, value: string, lifetime: int}|null
     */
    private function buildAutoCookie(MaintenanceData $data): ?array
    {
        if (! $this->config->autoIssueBypassCookie) {
            return null;
        }

        if ($data->cookie_name === '' || $data->cookie_value === '') {
            return null;
        }

        return [
            'name'     => $data->cookie_name,
            'value'    => $data->cookie_value,
            'lifetime' => $this->config->bypassCookieLifetime,
        ];
    }

    private function resolveClientIp(IncomingRequest $request): string
    {
        $ip = $request->getIPAddress();

        return $ip !== '' ? $ip : '0.0.0.0';
    }

    private function dispatchBypass(string $method, string $ip): void
    {
        Events::trigger('maintenance.bypassed', [
            'method' => $method,
            'ip'     => $ip,
        ]);
    }

    private function log(string $level, string $message): void
    {
        if ($this->config->enableLogging) {
            log_message($level, $message);
        }
    }

    /**
     * Convenience helper: build a service for the currently active config.
     */
    public static function fromCurrentConfig(): self
    {
        /** @var MaintenanceConfig $config */
        $config = config('Maintenance');

        return new self($config);
    }
}
