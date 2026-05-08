<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Events\Events;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Daycry\Maintenance\Traits\ParsesCliOptions;

class Down extends BaseCommand
{
    use ParsesCliOptions;

    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:down';
    protected $description = 'Put the application into maintenance mode';
    protected $usage       = 'mm:down [Options]';
    protected $arguments   = [];
    protected $options     = [
        '-message'  => 'Set maintenance message',
        '-ip'       => 'Allowed IPs [example: 127.0.0.1 192.168.1.100]',
        '-duration' => 'Estimated duration in minutes',
        '-secret'   => 'Enable secret bypass with custom key',
        '-cookie'   => 'Set custom cookie name for bypass',
        '-start'    => 'Scheduled start time (ISO-8601 or any strtotime() value)',
        '-end'      => 'Scheduled end time (ISO-8601 or any strtotime() value)',
        '-render'   => 'View name to render for the 503 page (window-scoped)',
        '-redirect' => 'URL to 302-redirect to instead of returning 503',
    ];

    public function run(array $params)
    {
        helper(['setting', 'text']);

        // Load configuration and storage
        $maintenanceConfig = config('Maintenance');
        $storage           = new MaintenanceStorage($maintenanceConfig);

        if ($storage->isActive()) {
            CLI::newLine(1);
            CLI::error('**** Application is already in maintenance mode. ****');
            CLI::newLine(1);
            $this->call('mm:status');

            return;
        }

        // Check if we're in testing environment
        $isTesting = ENVIRONMENT === 'testing' || defined('PHPUNIT_COMPOSER_INSTALL');

        // Get message
        $message = $this->option($params, 'message');
        if (empty($message)) {
            if ($isTesting) {
                $message = $maintenanceConfig->defaultMessage;
            } else {
                $message = CLI::prompt('Maintenance message', $maintenanceConfig->defaultMessage);
            }
        }

        // Get allowed IPs
        $ips_str = $this->option($params, 'ip');
        if (empty($ips_str)) {
            if ($isTesting) {
                $ips_str = '127.0.0.1';
            } else {
                $ips_str = CLI::prompt('Allowed IPs [space-separated, e.g: 127.0.0.1 192.168.1.100]', '127.0.0.1');
            }
        }

        // Validate and process IPs
        $ips_array = array_filter(array_map('trim', explode(' ', $ips_str)));
        $validIps  = [];

        foreach ($ips_array as $ip) {
            // Check for CIDR notation
            if (str_contains($ip, '/')) {
                $parts = explode('/', $ip);
                if (count($parts) !== 2 || ! is_numeric($parts[1])) {
                    CLI::write("Warning: '{$ip}' is not a valid IP address and will be ignored.", 'yellow');

                    continue;
                }

                $address = $parts[0];
                $prefix  = (int) $parts[1];
                $isV4    = (bool) filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
                $isV6    = (bool) filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

                if ($isV4 && $prefix >= 0 && $prefix <= 32) {
                    $validIps[] = $ip;
                } elseif ($isV6 && $prefix >= 0 && $prefix <= 128) {
                    $validIps[] = $ip;
                } else {
                    CLI::write("Warning: '{$ip}' is not a valid IP address and will be ignored.", 'yellow');
                }
            } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
                $validIps[] = $ip;
            } else {
                CLI::write("Warning: '{$ip}' is not a valid IP address and will be ignored.", 'yellow');
            }
        }

        if (empty($validIps)) {
            CLI::error('No valid IP addresses provided. Adding 127.0.0.1 as default.');
            $validIps = ['127.0.0.1'];
        }

        // Get duration
        $duration = $this->option($params, 'duration');
        if (empty($duration)) {
            if ($isTesting) {
                $duration = (string) $maintenanceConfig->defaultDurationMinutes;
            } else {
                $duration = CLI::prompt('Estimated duration in minutes', (string) $maintenanceConfig->defaultDurationMinutes);
            }
        }
        $duration = (int) $duration;

        // Get secret bypass
        $secret       = $this->option($params, 'secret');
        $enableSecret = false;
        $secretKey    = '';

        if (! empty($secret)) {
            $enableSecret = true;
            $secretKey    = $secret;
        } elseif (! $isTesting && CLI::prompt('Enable secret bypass? (y/n)', 'n') === 'y') {
            $enableSecret = true;
            $secretKey    = CLI::prompt('Secret bypass key', random_string('alnum', 16));
        }

        // Get cookie name and generate a high-entropy cookie value for bypass
        $cookieName = $this->option($params, 'cookie');
        if (empty($cookieName)) {
            $cookieName = random_string('alnum', 8);
        }
        $cookieValue = bin2hex(random_bytes(32));

        // Create directory if it doesn't exist
        if (! is_dir(setting('Maintenance.filePath')) && ! mkdir(setting('Maintenance.filePath'), 0755, true)) {
            CLI::error('Failed to create maintenance directory.');

            return;
        }

        // Get optional Sprint 3 fields (scheduled window, render override, redirect)
        $startRaw    = $this->option($params, 'start');
        $endRaw      = $this->option($params, 'end');
        $renderTpl   = (string) ($this->option($params, 'render') ?? '');
        $redirectUrl = (string) ($this->option($params, 'redirect') ?? '');

        $scheduledStart = $this->parseTimestamp($startRaw, 'start');
        $scheduledEnd   = $this->parseTimestamp($endRaw, 'end');
        if ($scheduledStart === false || $scheduledEnd === false) {
            return;
        }
        if ($scheduledStart !== null && $scheduledEnd !== null && $scheduledEnd <= $scheduledStart) {
            CLI::error('--end must be after --start.');

            return;
        }

        // Prepare maintenance data
        $maintenanceData = [
            'time'             => time(),
            'message'          => $message,
            'cookie_name'      => $cookieName,
            'cookie_value'     => $cookieValue,
            'allowed_ips'      => $validIps,
            'duration_minutes' => $duration,
            'estimated_end'    => time() + ($duration * 60),
            'secret_bypass'    => $enableSecret,
            'secret_key'       => $secretKey,
            'scheduled_start'  => $scheduledStart,
            'scheduled_end'    => $scheduledEnd,
            'render_template'  => $renderTpl,
            'redirect_url'     => $redirectUrl,
        ];

        // Save maintenance data using storage system
        $success = $storage->save($maintenanceData);

        if (! $success) {
            CLI::error('Failed to save maintenance mode data.');

            return;
        }

        // Log the event
        if ($maintenanceConfig->enableLogging) {
            log_message('info', 'Maintenance mode activated by CLI command');
        }

        Events::trigger('maintenance.activated', [
            'data' => $maintenanceData,
        ]);

        CLI::newLine(1);
        CLI::write('**** Application is now in MAINTENANCE MODE ****', 'white', 'red');
        CLI::newLine(1);

        if ($enableSecret) {
            CLI::write('Secret bypass URL: ' . base_url() . "?maintenance_secret={$secretKey}", 'yellow');
            CLI::newLine(1);
        }

        CLI::write("Cookie bypass: set cookie '{$cookieName}' with value '{$cookieValue}'", 'yellow');
        CLI::newLine(1);

        if ($scheduledStart !== null) {
            CLI::write('Scheduled start: ' . date('Y-m-d H:i:s', $scheduledStart), 'cyan');
        }
        if ($scheduledEnd !== null) {
            CLI::write('Scheduled end:   ' . date('Y-m-d H:i:s', $scheduledEnd), 'cyan');
        }
        if ($renderTpl !== '') {
            CLI::write("Render template: {$renderTpl}", 'cyan');
        }
        if ($redirectUrl !== '') {
            CLI::write("Redirect URL:    {$redirectUrl}", 'cyan');
        }

        $this->call('mm:status');
    }

    /**
     * Parse a CLI timestamp option (ISO-8601, "+1 hour", "2026-05-10 02:00", ...)
     * into a Unix timestamp. Returns null when the option is empty, an int on
     * success, or false on parse failure (and prints an error).
     */
    private function parseTimestamp(mixed $value, string $flag): false|int|null
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $ts = strtotime((string) $value);
        if ($ts === false) {
            CLI::error("--{$flag}: cannot parse '" . $value . "' as a date/time.");

            return false;
        }

        return $ts;
    }
}
