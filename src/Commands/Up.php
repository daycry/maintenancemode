<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Up extends BaseCommand
{
    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:up';
    protected $description = 'Bring the application out of maintenance mode';
    protected $usage       = 'mm:up';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        helper('setting');
        
        // Load configuration
        $maintenanceConfig = new \Daycry\Maintenance\Config\Maintenance();
        $maintenanceFile = setting('Maintenance.filePath') . setting('Maintenance.fileName');
        
        if (!file_exists($maintenanceFile)) {
            CLI::newLine(1);
            CLI::write('**** Application is already live. ****', 'green');
            CLI::newLine(1);
            return;
        }
        
        // Log the event before deleting the file
        if ($maintenanceConfig->enableLogging) {
            log_message('info', 'Maintenance mode deactivated by CLI command');
        }
        
        // Delete the maintenance file
        if (!@unlink($maintenanceFile)) {
            CLI::error('Failed to remove maintenance mode file. Please check file permissions.');
            return;
        }

        CLI::newLine(1);
        CLI::write('**** Application is now LIVE! ****', 'black', 'green');
        CLI::write('Users can now access the application normally.', 'green');
        CLI::newLine(1);
    }
}
