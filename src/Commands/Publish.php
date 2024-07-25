<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload;
use Exception;

class Publish extends BaseCommand
{
    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:publish';
    protected $description = 'Publish 503 Error code view into the current application folder.';
    protected $usage       = 'mm:publish';
    protected $arguments   = [];
    protected $options     = [];
    protected $sourcePath;

    // --------------------------------------------------------------------

    /**
     * Displays the help for the spark cli script itself.
     */
    public function run(array $params)
    {
        $this->determineSourcePath();

        // Views
        if (CLI::prompt('Publish Views?', ['y', 'n']) === 'y') {
            $map = false;
            $map = directory_map($this->sourcePath . '/Views/errors/cli');
            $this->publishViews($map, 'errors/cli/');

            $map = false;
            $map = directory_map($this->sourcePath . '/Views/errors/html');
            $this->publishViews($map, 'errors/html/');
        }

        // Config
        if (CLI::prompt('Publish Config file?', ['y', 'n']) === 'y') {
            $this->publishConfig();
        }
    }

    protected function publishViews($map, $subfolder)
    {
        $prefix = '';

        foreach ($map as $key => $view) {
            if (is_array($view)) {
                $oldPrefix = $prefix;
                $prefix .= $key;

                foreach ($view as $file) {
                    $this->publishView($file, $prefix, $subfolder);
                }

                $prefix = $oldPrefix;

                continue;
            }

            $this->publishView($view, $prefix, $subfolder);
        }
    }

    protected function publishView($view, string $prefix = '', string $subfolder = '')
    {
        $path      = "{$this->sourcePath}/Views/{$subfolder}{$prefix}{$view}";
        $namespace = defined('APP_NAMESPACE') ? APP_NAMESPACE : 'App';

        $content = file_get_contents($path);

        $this->writeFile("Views/{$subfolder}{$prefix}{$view}", $content);
    }

    protected function publishConfig()
    {
        $path = "{$this->sourcePath}/Config/Maintenance.php";

        $content = file_get_contents($path);
        $content = str_replace('namespace Daycry\Maintenance\Config', 'namespace Config', $content);
        $content = str_replace('extends BaseConfig', 'extends \\Daycry\\Maintenance\\Config\\Maintenance', $content);

        $this->writeFile('Config/Maintenance.php', $content);
    }

    /**
     * Determines the current source path from which all other files are located.
     */
    protected function determineSourcePath()
    {
        $this->sourcePath = realpath(__DIR__ . '/../');

        if ($this->sourcePath === '/' || empty($this->sourcePath)) {
            CLI::error('Unable to determine the correct source directory. Bailing.');

            exit();
        }
    }

    /**
     * Write a file, catching any exceptions and showing a
     * nicely formatted error.
     */
    protected function writeFile(string $path, string $content)
    {
        $config  = new Autoload();
        $appPath = $config->psr4[APP_NAMESPACE];

        $directory = dirname($appPath . $path);

        if (! is_dir($directory)) {
            mkdir($directory);
        }

        try {
            write_file($appPath . $path, $content);
        } catch (Exception $e) {
            $this->showError($e);

            exit();
        }

        $path = str_replace($appPath, '', $path);

        CLI::write(CLI::color('  created: ', 'green') . $path);
    }
}
