<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Maintenance\Config\Maintenance;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Daycry\Maintenance\Traits\ParsesCliOptions;

class Migrate extends BaseCommand
{
    use ParsesCliOptions;

    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:migrate';
    protected $description = 'Migrate maintenance data from file storage to cache storage';
    protected $usage       = 'mm:migrate [Options]';
    protected $arguments   = [];
    protected $options     = [
        '-force' => 'Force migration even if cache is disabled',
        '-clear' => 'Clear all maintenance data (both cache and files)',
    ];

    public function run(array $params)
    {
        helper('setting');

        // Load configuration and storage
        $maintenanceConfig = new Maintenance();
        $storage           = new MaintenanceStorage($maintenanceConfig);

        $force = $this->option($params, 'force', false);
        $clear = $this->option($params, 'clear', false);

        CLI::newLine(1);
        CLI::write('🔄 Maintenance Mode Migration Tool', 'yellow');
        CLI::newLine(1);

        // Check if we're in testing environment
        $isTesting = ENVIRONMENT === 'testing' || defined('PHPUNIT_COMPOSER_INSTALL');

        // Clear all data if requested
        if ($clear) {
            $confirm = $isTesting ? 'yes' : CLI::prompt('This will clear ALL maintenance data. Are you sure? (yes/no)', 'no');
            if ($confirm === 'yes') {
                if ($storage->clearAll()) {
                    CLI::write('✅ All maintenance data cleared successfully.', 'green');
                } else {
                    CLI::error('❌ Failed to clear maintenance data.');
                }
            } else {
                CLI::write('Operation cancelled.', 'yellow');
            }
            CLI::newLine(1);

            return;
        }

        // Check if cache is enabled
        if (! $maintenanceConfig->useCache && ! $force) {
            CLI::error('❌ Cache storage is disabled in configuration.');
            CLI::write('Use --force flag to proceed anyway or enable cache in configuration.', 'yellow');
            CLI::newLine(1);

            return;
        }

        // Check if there's file data to migrate
        $filePath = setting('Maintenance.filePath') . setting('Maintenance.fileName');

        if (! file_exists($filePath)) {
            CLI::write('ℹ️  No file data found to migrate.', 'cyan');
            CLI::newLine(1);

            return;
        }

        // Show current status
        CLI::write('Current status:', 'white');
        CLI::write('  📁 File storage: ' . (file_exists($filePath) ? 'EXISTS' : 'NOT FOUND'), 'cyan');
        CLI::write('  💾 Cache storage: ' . ($maintenanceConfig->useCache ? 'ENABLED' : 'DISABLED'), 'cyan');
        CLI::newLine(1);

        // Perform migration
        CLI::write('Starting migration...', 'yellow');

        if ($storage->migrateToCache()) {
            CLI::write('✅ Migration completed successfully!', 'green');
            CLI::write('  ✓ Data migrated from file to cache', 'green');
            CLI::write('  ✓ Old file removed', 'green');
        } else {
            CLI::error('❌ Migration failed.');
            CLI::write('Please check the logs for more details.', 'yellow');
        }

        CLI::newLine(1);
    }
}
