<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Down extends BaseCommand
{
    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:down';
    protected $description = 'Put the application into maintenance mode';
    protected $usage       = 'mm:down [Options]';
    protected $arguments   = [];
    protected $options     = [
        '-message' => 'Set maintenance message', 
        '-ip' => 'Allowed IPs [example: 127.0.0.1 192.168.1.100]',
        '-duration' => 'Estimated duration in minutes',
        '-secret' => 'Enable secret bypass with custom key'
    ];

    public function run(array $params)
    {
        helper(['setting', 'text']);
        
        // Load configuration
        $maintenanceConfig = new \Daycry\Maintenance\Config\Maintenance();

        if (file_exists(setting('Maintenance.filePath') . setting('Maintenance.fileName'))) {
            CLI::newLine(1);
            CLI::error('**** Application is already in maintenance mode. ****');
            CLI::newLine(1);
            $this->call('mm:status');
            return;
        }

        // Get message
        $message = $params['message'] ?? CLI::getOption('message');
        if (empty($message)) {
            $message = CLI::prompt('Maintenance message', $maintenanceConfig->defaultMessage);
        }

        // Get allowed IPs
        $ips_str = $params['ip'] ?? CLI::getOption('ip');
        if (empty($ips_str)) {
            $ips_str = CLI::prompt('Allowed IPs [space-separated, e.g: 127.0.0.1 192.168.1.100]', '127.0.0.1');
        }

        // Validate and process IPs
        $ips_array = array_filter(array_map('trim', explode(' ', $ips_str)));
        $validIps = [];
        
        foreach ($ips_array as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
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
            $duration = CLI::prompt('Estimated duration in minutes', (string) $maintenanceConfig->defaultDurationMinutes);
        }
        $duration = (int) $duration;

        // Get secret bypass
        $secret = $params['secret'] ?? CLI::getOption('secret');
        $enableSecret = false;
        $secretKey = '';
        
        if (!empty($secret)) {
            $enableSecret = true;
            $secretKey = $secret;
        } elseif (CLI::prompt('Enable secret bypass? (y/n)', 'n') === 'y') {
            $enableSecret = true;
            $secretKey = CLI::prompt('Secret bypass key', random_string('alnum', 16));
        }

        // Create directory if it doesn't exist
        if (! is_dir(setting('Maintenance.filePath'))) {
            if (!mkdir(setting('Maintenance.filePath'), 0755, true)) {
                CLI::error('Failed to create maintenance directory.');
                return;
            }
        }

        // Prepare maintenance data
        $maintenanceData = [
            'time'        => time(),
            'message'     => $message,
            'cookie_name' => random_string('alnum', 8),
            'allowed_ips' => $validIps,
            'duration_minutes' => $duration,
            'estimated_end' => time() + ($duration * 60),
            'secret_bypass' => $enableSecret,
            'secret_key' => $secretKey,
        ];

        // Write maintenance file
        $success = file_put_contents(
            setting('Maintenance.filePath') . setting('Maintenance.fileName'),
            json_encode($maintenanceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        if ($success === false) {
            CLI::error('Failed to create maintenance mode file.');
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
            CLI::write("Secret bypass URL: " . base_url() . "?maintenance_secret={$secretKey}", 'yellow');
            CLI::newLine(1);
        }

        $this->call('mm:status');
    }
}
