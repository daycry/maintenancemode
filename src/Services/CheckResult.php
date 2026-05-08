<?php

namespace Daycry\Maintenance\Services;

/**
 * Outcome of a {@see MaintenanceService::check()} call.
 *
 * - allowed = true means the request should proceed (either maintenance is
 *   inactive, the request was a CLI invocation, or a bypass matched).
 * - allowed = false means the caller should respond with 503 (or redirect).
 *
 * The reason string is informational (used by `mm:status`, logs, events).
 *
 * The optional `setCookie` array signals that the caller should set a bypass
 * cookie on the outgoing response (Sprint 3 auto-cookie feature).
 */
final class CheckResult
{
    /**
     * @param array{name: string, value: string, lifetime: int}|null $setCookie
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly ?array $setCookie = null,
    ) {
    }

    public static function inactive(): self
    {
        return new self(true, 'maintenance_inactive');
    }

    public static function cli(): self
    {
        return new self(true, 'cli_request');
    }

    /**
     * @param array{name: string, value: string, lifetime: int}|null $setCookie
     */
    public static function bypassed(string $method, ?array $setCookie = null): self
    {
        return new self(true, 'bypassed_' . $method, $setCookie);
    }

    public static function pending(): self
    {
        return new self(true, 'scheduled_pending');
    }

    public static function bypassedRoute(): self
    {
        return new self(true, 'bypass_route');
    }

    public static function denied(): self
    {
        return new self(false, 'access_denied');
    }
}
