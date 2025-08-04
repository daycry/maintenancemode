<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Maintenance\Libraries\MaintenanceStorage;

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
        
        // Load configuration and storage
        $maintenanceConfig = config('Maintenance');
        $storage = new MaintenanceStorage($maintenanceConfig);
        
        if (!$storage->isActive()) {
            CLI::newLine(1);
            CLI::write('**** Application is already live. ****', 'green');
            CLI::newLine(1);
            return;
        }
        
        // Log the event before removing data
        if ($maintenanceConfig->enableLogging) {
            log_message('info', 'Maintenance mode deactivated by CLI command');
        }
        
        // Remove maintenance data
        if (!$storage->remove()) {
            CLI::error('Failed to remove maintenance mode data. Please check permissions.');
            return;
        }

        CLI::newLine(1);
        CLI::write('**** Application is now LIVE! ****', 'black', 'green');
        CLI::write('Users can now access the application normally.', 'green');
        CLI::newLine(1);
    }
}
