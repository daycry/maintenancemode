<?php

namespace Daycry\Maintenance\DTO;

use JsonException;

/**
 * Immutable representation of one maintenance window's data.
 *
 * Property names use snake_case to mirror the legacy stdClass shape that
 * Controllers, Commands and Views already access (e.g. $data->cookie_name).
 * This keeps backward compatibility while gaining type safety.
 */
final class MaintenanceData
{
    /**
     * @param list<string> $allowed_ips
     */
    public function __construct(
        public readonly int $time,
        public readonly string $message,
        public readonly string $cookie_name,
        public readonly string $cookie_value,
        public readonly array $allowed_ips,
        public readonly int $duration_minutes,
        public readonly ?int $estimated_end,
        public readonly bool $secret_bypass,
        public readonly string $secret_key,
        public readonly ?int $scheduled_start = null,
        public readonly ?int $scheduled_end = null,
        public readonly string $render_template = '',
        public readonly string $redirect_url = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $allowed = $data['allowed_ips'] ?? [];
        if (! is_array($allowed)) {
            $allowed = [];
        }

        return new self(
            time: (int) ($data['time'] ?? time()),
            message: (string) ($data['message'] ?? ''),
            cookie_name: (string) ($data['cookie_name'] ?? ''),
            cookie_value: (string) ($data['cookie_value'] ?? ''),
            allowed_ips: array_values(array_map('strval', $allowed)),
            duration_minutes: (int) ($data['duration_minutes'] ?? 0),
            estimated_end: isset($data['estimated_end']) ? (int) $data['estimated_end'] : null,
            secret_bypass: (bool) ($data['secret_bypass'] ?? false),
            secret_key: (string) ($data['secret_key'] ?? ''),
            scheduled_start: isset($data['scheduled_start']) ? (int) $data['scheduled_start'] : null,
            scheduled_end: isset($data['scheduled_end']) ? (int) $data['scheduled_end'] : null,
            render_template: (string) ($data['render_template'] ?? ''),
            redirect_url: (string) ($data['redirect_url'] ?? ''),
        );
    }

    public static function fromObject(object $obj): self
    {
        return self::fromArray((array) $obj);
    }

    /**
     * Decode a JSON string into a MaintenanceData. Throws on invalid JSON.
     */
    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new JsonException('Maintenance data JSON must decode to an object/array');
        }

        return self::fromArray($decoded);
    }

    /**
     * Returns true when a scheduled-start timestamp is set and lies in the
     * future relative to $now. Until that moment the filter should let
     * traffic through.
     */
    public function isPending(int $now): bool
    {
        return $this->scheduled_start !== null && $now < $this->scheduled_start;
    }

    /**
     * Returns true when a scheduled-end timestamp is set and has already
     * passed. Callers should auto-deactivate the window when this is true.
     */
    public function isExpired(int $now): bool
    {
        return $this->scheduled_end !== null && $now >= $this->scheduled_end;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'time'             => $this->time,
            'message'          => $this->message,
            'cookie_name'      => $this->cookie_name,
            'cookie_value'     => $this->cookie_value,
            'allowed_ips'      => $this->allowed_ips,
            'duration_minutes' => $this->duration_minutes,
            'estimated_end'    => $this->estimated_end,
            'secret_bypass'    => $this->secret_bypass,
            'secret_key'       => $this->secret_key,
            'scheduled_start'  => $this->scheduled_start,
            'scheduled_end'    => $this->scheduled_end,
            'render_template'  => $this->render_template,
            'redirect_url'     => $this->redirect_url,
        ];
    }
}
