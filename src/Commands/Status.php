<?php

namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Daycry\Maintenance\Libraries\IpUtils;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Throwable;

class Status extends BaseCommand
{
    protected $group       = 'Maintenance Mode';
    protected $name        = 'mm:status';
    protected $description = 'Display the maintenance mode status';
    protected $usage       = 'mm:status [--show-public-ip]';
    protected $arguments   = [];
    protected $options     = [
        '-show-public-ip' => 'Try to detect the public IP via an external service (timeouts: 2s connect / 3s total)',
    ];

    public function run(array $params)
    {
        helper('setting');

        // Load configuration and storage
        $maintenanceConfig = config('Maintenance');
        $storage           = new MaintenanceStorage($maintenanceConfig);

        if (! $storage->isActive()) {
            CLI::newLine(1);
            CLI::write('✅ **** Application is LIVE ****', 'green');
            CLI::write('Users can access the application normally.', 'green');
            CLI::newLine(1);

            // Show storage method info
            $storageMethod = $maintenanceConfig->useCache ? 'Cache' : 'File System';
            CLI::write("Storage method: {$storageMethod}", 'cyan');
            CLI::newLine(1);

            return;
        }

        $data = $storage->getData();

        if ($data === null) {
            CLI::newLine(1);
            CLI::error('⚠️  Maintenance data is invalid or corrupted.');
            CLI::newLine(1);

            return;
        }

        CLI::newLine(1);
        CLI::error('🔧 Application is in MAINTENANCE MODE');

        // Show storage method info
        $storageMethod = $maintenanceConfig->useCache ? 'Cache' : 'File System';
        CLI::write("Storage method: {$storageMethod}", 'cyan');

        // Check current bypass status
        $this->showCurrentBypassStatus($maintenanceConfig, $data);

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
                        $endTime      = date('Y-m-d H:i:s', $value);
                        $remaining    = $value - time();
                        $remainingStr = $remaining > 0 ?
                            sprintf('%d minutes remaining', ceil($remaining / 60)) :
                            'Overdue';
                        $tbody[] = ['Estimated End', $endTime . " ({$remainingStr})"];
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
        if (isset($data->allowed_ips) && ! empty($data->allowed_ips)) {
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

    /**
     * Check and display current bypass status
     *
     * @param mixed $config
     * @param mixed $data
     */
    private function showCurrentBypassStatus($config, $data): void
    {
        CLI::newLine(1);
        CLI::write('🔍 Current Bypass Status:', 'yellow');

        $bypassMethods = [];
        $currentIP     = $this->getCurrentClientIP();

        $secretParam = $this->readGetParam('maintenance_secret');

        // Check config secret bypass
        if ($config->allowSecretBypass && ! empty($config->secretBypassKey)) {
            if ($secretParam !== '' && hash_equals((string) $config->secretBypassKey, $secretParam)) {
                $bypassMethods[] = '✅ Config Secret (via URL parameter)';
            } else {
                $bypassMethods[] = "🔑 Config Secret available (add ?maintenance_secret={$config->secretBypassKey} to URL)";
            }
        }

        // Check data secret bypass
        if (isset($data->secret_bypass) && $data->secret_bypass && isset($data->secret_key)) {
            if ($secretParam !== '' && hash_equals((string) $data->secret_key, $secretParam)) {
                $bypassMethods[] = '✅ Data Secret (via URL parameter)';
            } else {
                $bypassMethods[] = "🔑 Data Secret available (add ?maintenance_secret={$data->secret_key} to URL)";
            }
        }

        // Check IP bypass
        if (isset($data->allowed_ips) && ! empty($data->allowed_ips)) {
            $ipUtils = new IpUtils();
            if ($ipUtils->checkIp($currentIP, $data->allowed_ips)) {
                $bypassMethods[] = "✅ IP Address bypass (current IP: {$currentIP})";
            } else {
                $bypassMethods[] = "🌐 IP Address bypass configured (current IP {$currentIP} not in allowed list)";
            }
        }

        // Check cookie bypass
        if (isset($data->cookie_name) && ! empty($data->cookie_name)) {
            $cookieName    = (string) $data->cookie_name;
            $expectedValue = (string) ($data->cookie_value ?? '');
            $providedValue = $this->readCookie($cookieName);

            if ($expectedValue !== '' && $providedValue !== '' && hash_equals($expectedValue, $providedValue)) {
                $bypassMethods[] = '✅ Cookie bypass (active)';
            } else {
                $bypassMethods[] = '🍪 Cookie bypass configured (cookie not set or invalid)';
            }
        }

        // Display results
        if (empty($bypassMethods)) {
            CLI::write('   ❌ No bypass methods configured', 'red');
        } else {
            foreach ($bypassMethods as $method) {
                CLI::write("   {$method}");
            }
        }

        // Show access status
        CLI::newLine(1);
        $this->showAccessStatus($config, $data, $currentIP);
    }

    /**
     * Show current access status for this CLI session
     *
     * @param mixed $config
     * @param mixed $data
     * @param mixed $currentIP
     */
    private function showAccessStatus($config, $data, $currentIP): void
    {
        CLI::write('🚦 Access Status from CLI:', 'yellow');

        // Simulate the same logic as Maintenance::check()
        $hasAccess    = false;
        $accessReason = '';

        // Check CLI bypass first (always allowed in CLI unless testing)
        if (is_cli() && ENVIRONMENT !== 'testing') {
            $hasAccess    = true;
            $accessReason = 'CLI access (always allowed)';
        } else {
            $secretParam = $this->readGetParam('maintenance_secret');

            // Check config secret
            if ($config->allowSecretBypass && ! empty($config->secretBypassKey)
                && $secretParam !== ''
                && hash_equals((string) $config->secretBypassKey, $secretParam)) {
                $hasAccess    = true;
                $accessReason = 'Config secret bypass';
            }

            // Check data secret
            if (! $hasAccess && isset($data->secret_bypass) && $data->secret_bypass && isset($data->secret_key)
                && $secretParam !== ''
                && hash_equals((string) $data->secret_key, $secretParam)) {
                $hasAccess    = true;
                $accessReason = 'Data secret bypass';
            }

            // Check IP
            if (! $hasAccess && isset($data->allowed_ips) && ! empty($data->allowed_ips)) {
                $ipUtils = new IpUtils();
                if ($ipUtils->checkIp($currentIP, $data->allowed_ips)) {
                    $hasAccess    = true;
                    $accessReason = 'IP address bypass';
                }
            }

            // Check cookie
            if (! $hasAccess && isset($data->cookie_name) && ! empty($data->cookie_name)) {
                $cookieName    = (string) $data->cookie_name;
                $expectedValue = (string) ($data->cookie_value ?? '');
                $providedValue = $this->readCookie($cookieName);

                if ($expectedValue !== '' && $providedValue !== '' && hash_equals($expectedValue, $providedValue)) {
                    $hasAccess    = true;
                    $accessReason = 'Cookie bypass';
                }
            }
        }

        if ($hasAccess) {
            CLI::write("   ✅ Access ALLOWED: {$accessReason}", 'green');
        } else {
            CLI::write('   ❌ Access BLOCKED: No valid bypass method', 'red');
        }

        CLI::newLine(1);
        CLI::write('💡 Tips:', 'yellow');
        CLI::write('   • Add your IP: php spark mm:down --allow=' . $currentIP, 'cyan');
        CLI::write('   • Use secret: php spark mm:down --secret=your-key', 'cyan');
        CLI::write('   • Access URL: ' . (base_url() ?: 'https://yoursite.com') . '?maintenance_secret=your-key', 'cyan');
    }

    /**
     * Get current client IP (best effort for CLI).
     *
     * Reads from $_SERVER with type validation. Avoids implicit network calls;
     * the public-IP fallback is only attempted when explicitly enabled via the
     * --show-public-ip flag.
     */
    private function getCurrentClientIP(): string
    {
        $candidates = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($candidates as $key) {
            $value = $_SERVER[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            // X-Forwarded-For can be a comma-separated list; take the first hop.
            $first = trim(explode(',', $value)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        if (CLI::getOption('show-public-ip')) {
            return $this->fetchPublicIp() ?? '127.0.0.1';
        }

        return '127.0.0.1';
    }

    /**
     * Fetch the public IP from an external service with strict timeouts.
     * Returns null on any failure (timeout, network error, invalid response).
     */
    private function fetchPublicIp(): ?string
    {
        try {
            $client = Services::curlrequest([
                'timeout'         => 3,
                'connect_timeout' => 2,
                'http_errors'     => false,
            ]);
            $response = $client->get('https://api.ipify.org');
            $body     = trim($response->getBody());

            return filter_var($body, FILTER_VALIDATE_IP) ? $body : null;
        } catch (Throwable $e) {
            log_message('warning', 'Failed to fetch public IP: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Read a string GET parameter without trusting the superglobal type.
     */
    private function readGetParam(string $key): string
    {
        $value = $_GET[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * Read a cookie value as a string without trusting the superglobal type.
     */
    private function readCookie(string $key): string
    {
        $value = $_COOKIE[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
