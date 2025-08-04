<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Status extends BaseCommand
{
    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:status';
    protected $description = 'Display the maintenance mode status';
    protected $usage       = 'mm:status';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        helper('setting');
        $maintenanceFile = setting('Maintenance.filePath') . setting('Maintenance.fileName');
        
        if (!file_exists($maintenanceFile)) {
            CLI::newLine(1);
            CLI::write('✅ **** Application is LIVE ****', 'green');
            CLI::write('Users can access the application normally.', 'green');
            CLI::newLine(1);
            return;
        }

        $data = json_decode(file_get_contents($maintenanceFile));
        
        if ($data === null) {
            CLI::newLine(1);
            CLI::error('⚠️  Maintenance file contains invalid JSON data.');
            CLI::newLine(1);
            return;
        }

        CLI::newLine(1);
        CLI::error('🔧 Application is in MAINTENANCE MODE');
        CLI::newLine(1);

        // Main information table
        $thead = ['Property', 'Value'];
        $tbody = [];

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'allowed_ips':
                case 'secret_key':
                    // These will be shown separately
                    break;
                    
                case 'time':
                    $tbody[] = ['Started', date('Y-m-d H:i:s', $value)];
                    break;
                    
                case 'estimated_end':
                    if (isset($value)) {
                        $endTime = date('Y-m-d H:i:s', $value);
                        $remaining = $value - time();
                        $remainingStr = $remaining > 0 ? 
                            sprintf('%d minutes remaining', ceil($remaining / 60)) : 
                            'Overdue';
                        $tbody[] = ['Estimated End', $endTime . " ($remainingStr)"];
                    }
                    break;
                    
                case 'duration_minutes':
                    $tbody[] = ['Duration', $value . ' minutes'];
                    break;
                    
                case 'secret_bypass':
                    $tbody[] = ['Secret Bypass', $value ? 'Enabled' : 'Disabled'];
                    break;
                    
                case 'cookie_name':
                    $tbody[] = ['Cookie Name', $value];
                    break;
                    
                default:
                    $tbody[] = [ucfirst(str_replace('_', ' ', $key)), $value];
            }
        }

        CLI::table($tbody, $thead);

        // Show allowed IPs
        if (isset($data->allowed_ips) && !empty($data->allowed_ips)) {
            CLI::newLine(1);
            CLI::write('🌐 Allowed IP Addresses:', 'yellow');
            
            $ipThead = ['IP Address', 'Type'];
            $ipTbody = [];

            foreach ($data->allowed_ips as $ip) {
                $type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 
                       (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'IPv6' : 'Invalid');
                $ipTbody[] = [$ip, $type];
            }

            CLI::table($ipTbody, $ipThead);
        }

        // Show secret key if enabled
        if (isset($data->secret_bypass) && $data->secret_bypass && isset($data->secret_key)) {
            CLI::newLine(1);
            CLI::write('🔑 Secret Bypass Information:', 'yellow');
            CLI::write('   URL: ' . base_url() . '?maintenance_secret=' . $data->secret_key, 'cyan');
        }

        CLI::newLine(1);
    }
}
