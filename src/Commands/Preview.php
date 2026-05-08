<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Maintenance\Config\Maintenance as MaintenanceConfig;
use Daycry\Maintenance\DTO\MaintenanceData;
use Daycry\Maintenance\Services\MaintenanceService;
use Daycry\Maintenance\Traits\ParsesCliOptions;

/**
 * Render the 503 view with stub data WITHOUT activating maintenance.
 * Useful for designing custom templates and verifying their integration.
 */
class Preview extends BaseCommand
{
    use ParsesCliOptions;

    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:preview';
    protected $description = 'Render the 503 view with stub data without activating maintenance.';
    protected $usage       = 'mm:preview [--message "..."] [--template "errors/html/x"] [--output path]';
    protected $arguments   = [];
    protected $options     = [
        '-message'  => 'Override the message displayed on the preview',
        '-template' => 'View name to render (overrides the resolved template)',
        '-output'   => 'Write the rendered HTML to this file path instead of stdout',
    ];

    public function run(array $params)
    {
        helper('setting');

        /** @var MaintenanceConfig $config */
        $config = config('Maintenance');

        $message = (string) ($this->option($params, 'message') ?? $config->defaultMessage);

        $stub = MaintenanceData::fromArray([
            'time'             => time(),
            'message'          => $message,
            'cookie_name'      => 'preview_cookie',
            'cookie_value'     => str_repeat('0', 64),
            'allowed_ips'      => ['127.0.0.1'],
            'duration_minutes' => 60,
            'estimated_end'    => time() + 3600,
            'secret_bypass'    => false,
            'secret_key'       => '',
        ]);

        $service = new MaintenanceService($config);
        $tpl     = (string) ($this->option($params, 'template') ?? $service->resolveTemplate($stub));
        if ($tpl === '') {
            // Fall back to the bundled template path if no override is set.
            $tpl = 'errors/html/error_503';
        }

        $rendered = view($tpl, [
            'message' => $message,
            'data'    => $stub,
            'config'  => $config,
        ]);

        $output = (string) ($this->option($params, 'output') ?? '');
        if ($output !== '') {
            $bytes = file_put_contents($output, $rendered);
            if ($bytes === false) {
                CLI::error("Failed to write preview to {$output}");

                return;
            }
            CLI::write("Preview written to {$output} ({$bytes} bytes)", 'green');

            return;
        }

        CLI::newLine(1);
        CLI::write('--- Maintenance preview (' . $tpl . ') ---', 'yellow');
        CLI::newLine(1);
        CLI::write($rendered);
        CLI::newLine(1);
    }
}
