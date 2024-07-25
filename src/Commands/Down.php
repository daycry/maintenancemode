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
    protected $options     = ['-message' => 'Set Message', '-ip' => 'Allowed ips [example: 0.0.0.0 127.0.0.1]'];

    public function run(array $params)
    {
        helper(['setting', 'text']);

        if (! file_exists(setting('Maintenance.filePath') . setting('Maintenance.fileName'))) {
            $message = $params['message'] ?? CLI::getOption('message');

            if (empty($message)) {
                $message = CLI::prompt('Message');
            }

            $ips_str = $params['ip'] ?? CLI::getOption('ip');

            if (empty($ips_str)) {
                $ips_str = CLI::prompt('Allowed ips [example: 0.0.0.0 127.0.0.1]');
            }

            $ips_array = explode(' ', $ips_str);

            // dir doesn't exist, make it
            if (! is_dir(setting('Maintenance.filePath'))) {
                mkdir(setting('Maintenance.filePath'));
            }

            // write the file with json content
            file_put_contents(
                setting('Maintenance.filePath') . setting('Maintenance.fileName'),
                json_encode(
                    [
                        'time'        => strtotime('now'),
                        'message'     => $message,
                        'cookie_name' => random_string('alnum', 8),
                        'allowed_ips' => $ips_array,
                    ],
                    JSON_PRETTY_PRINT
                )
            );

            CLI::newLine(1);
            CLI::write('**** Application is now DOWN. ****', 'white', 'red');
            CLI::newLine(1);

            $this->call('mm:status');
        } else {
            CLI::newLine(1);
            CLI::error('**** Application is already DOWN. ****');
            CLI::newLine(1);
        }
    }
}
