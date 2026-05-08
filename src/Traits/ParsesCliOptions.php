<?php

namespace Daycry\Maintenance\Traits;

use CodeIgniter\CLI\CLI;

/**
 * Helper for spark commands that accept the same option both as a positional
 * key in $params (when invoked via command()) and as a named --option on the
 * actual CLI. Eliminates the
 *
 *     $value = $params['x'] ?? CLI::getOption('x');
 *
 * boilerplate scattered across mm:down, mm:up, mm:status, mm:migrate.
 */
trait ParsesCliOptions
{
    /**
     * Read an option from $params first, falling back to CLI::getOption(),
     * and finally to $default if neither is provided.
     *
     * @param array<array-key, mixed> $params
     */
    protected function option(array $params, string $key, mixed $default = null): mixed
    {
        return $params[$key] ?? CLI::getOption($key) ?? $default;
    }
}
