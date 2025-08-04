<?php
require_once 'vendor/autoload.php';

if (!defined('WRITEPATH')) {
    define('WRITEPATH', __DIR__ . '/tests/writable/');
}

// Create test maintenance with bypass methods
$config = new \Daycry\Maintenance\Config\Maintenance();
$storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);

$data = [
    'time' => time(),
    'message' => 'Testing bypass detection',
    'allowed_ips' => ['127.0.0.1', '192.168.1.100'],
    'secret_bypass' => true,
    'secret_key' => 'test-secret-123',
    'cookie_name' => 'bypass_cookie_xyz',
    'duration_minutes' => 30,
    'estimated_end' => time() + (30 * 60)
];

$result = $storage->save($data);
echo "Maintenance mode activated: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
echo "Storage active: " . ($storage->isActive() ? 'YES' : 'NO') . "\n";
echo "Data saved with bypass methods configured.\n";
