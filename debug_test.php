<?php

// Temporary debug script
require_once 'vendor/autoload.php';

use Daycry\Maintenance\Controllers\Maintenance;
use Daycry\Maintenance\Libraries\MaintenanceStorage;

// Initialize globals like in tests
$_COOKIE = $_COOKIE ?? [];
$_GET = [];
$_SERVER = ['REMOTE_ADDR' => '10.0.0.1'];

// Create storage and activate maintenance
$config = new \Daycry\Maintenance\Config\Maintenance();
$storage = new MaintenanceStorage($config);
$storage->clearAll();

$storage->save([
    'time' => time(),
    'message' => 'Debug test',
    'secret_bypass' => true,
    'secret_key' => 'debug-secret',
    'allowed_ips' => [],
    'cookie_name' => null
]);

// Test with wrong secret
$_GET['maintenance_secret'] = 'wrong-secret';

echo "Maintenance active: " . ($storage->isActive() ? 'yes' : 'no') . "\n";
echo "Secret in GET: " . $_GET['maintenance_secret'] . "\n";

try {
    $result = Maintenance::check();
    echo "Result: " . ($result ? 'true' : 'false') . "\n";
    echo "No exception thrown!\n";
} catch (Exception $e) {
    echo "Exception: " . get_class($e) . " - " . $e->getMessage() . "\n";
}
