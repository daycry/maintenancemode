<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Maintenance\Libraries\MaintenanceStorage;

class Down extends BaseCommand
{
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
        $message = $params['message'] ?? CLI::getOption('message');
        if (empty($message)) {
            if ($isTesting) {
                $message = $maintenanceConfig->defaultMessage;
            } else {
                $message = CLI::prompt('Maintenance message', $maintenanceConfig->defaultMessage);
            }
        }

        // Get allowed IPs
        $ips_str = $params['ip'] ?? CLI::getOption('ip');
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
                // CIDR notation - validate the IP part before the slash
                $parts = explode('/', $ip);
                if (count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_IP) && is_numeric($parts[1])) {
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
        $duration = $params['duration'] ?? CLI::getOption('duration');
        if (empty($duration)) {
            if ($isTesting) {
                $duration = (string) $maintenanceConfig->defaultDurationMinutes;
            } else {
                $duration = CLI::prompt('Estimated duration in minutes', (string) $maintenanceConfig->defaultDurationMinutes);
            }
        }
        $duration = (int) $duration;

        // Get secret bypass
        $secret       = $params['secret'] ?? CLI::getOption('secret');
        $enableSecret = false;
        $secretKey    = '';

        if (! empty($secret)) {
            $enableSecret = true;
            $secretKey    = $secret;
        } elseif (! $isTesting && CLI::prompt('Enable secret bypass? (y/n)', 'n') === 'y') {
            $enableSecret = true;
            $secretKey    = CLI::prompt('Secret bypass key', random_string('alnum', 16));
        }

        // Get cookie name
        $cookieName = $params['cookie'] ?? CLI::getOption('cookie');
        if (empty($cookieName)) {
            $cookieName = random_string('alnum', 8);
        }

        // Create directory if it doesn't exist
        if (! is_dir(setting('Maintenance.filePath'))) {
            if (! mkdir(setting('Maintenance.filePath'), 0755, true)) {
                CLI::error('Failed to create maintenance directory.');

                return;
            }
        }

        // Prepare maintenance data
        $maintenanceData = [
            'time'             => time(),
            'message'          => $message,
            'cookie_name'      => $cookieName,
            'allowed_ips'      => $validIps,
            'duration_minutes' => $duration,
            'estimated_end'    => time() + ($duration * 60),
            'secret_bypass'    => $enableSecret,
            'secret_key'       => $secretKey,
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

        CLI::newLine(1);
        CLI::write('**** Application is now in MAINTENANCE MODE ****', 'white', 'red');
        CLI::newLine(1);

        if ($enableSecret) {
            CLI::write('Secret bypass URL: ' . base_url() . "?maintenance_secret={$secretKey}", 'yellow');
            CLI::newLine(1);
        }

        $this->call('mm:status');
    }
}
