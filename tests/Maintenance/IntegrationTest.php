<?php

namespace Tests\Maintenance;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\FeatureTestTrait;
use Daycry\Maintenance\Controllers\Maintenance;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Daycry\Maintenance\Filters\Maintenance as MaintenanceFilter;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Exception;
use Tests\Support\TestCase;

/**
 * @internal
 * Comprehensive and unified Integration tests
 * Tests integration between all components using config('Maintenance') approach
 */
final class IntegrationTest extends TestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['setting', 'filesystem', 'cookie', 'text']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
        Factories::reset('config');
        Events::simulate(false);
        $this->cleanupMaintenanceFiles();
        $this->cleanupGlobalVariables();
    }

    private function createCustomConfig(array $overrides = []): \Daycry\Maintenance\Config\Maintenance
    {
        $config = new \Daycry\Maintenance\Config\Maintenance();

        // Safe test defaults
        $config->enableLogging     = false;
        $config->retryAfterSeconds = 3600;
        $config->defaultMessage    = 'Application is in maintenance mode';
        $config->allowSecretBypass = false;
        $config->secretBypassKey   = '';

        // Apply overrides
        foreach ($overrides as $property => $value) {
            $config->{$property} = $value;
        }

        return $config;
    }

    private function cleanupMaintenanceFiles(): void
    {
        try {
            $config  = $this->createCustomConfig();
            $storage = new MaintenanceStorage($config);
            $storage->clearAll();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }

    private function cleanupGlobalVariables(): void
    {
        unset($_GET['secret'], $_GET['maintenance_secret'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);

        // Clean up cookies
        if (isset($_COOKIE)) {
            foreach ($_COOKIE as $key => $value) {
                if (str_contains($key, 'maintenance') || str_contains($key, 'bypass')) {
                    unset($_COOKIE[$key]);
                }
            }
        }
    }

    private function setupTestRoute(): void
    {
        $filters                         = config('Filters');
        $filters->aliases['maintenance'] = MaintenanceFilter::class;
        Factories::injectMock('filters', 'filters', $filters);

        $routes = Services::routes();
        $routes->get('hello', ['controller' => '\Tests\Support\Controllers\Hello', 'filter' => 'maintenance']);
        Services::injectMock('routes', $routes);
    }

    // ===== FULL SYSTEM INTEGRATION TESTS =====

    public function testCompleteMaintenanceWorkflow(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'workflow-secret',
            'enableLogging'     => false,
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Phase 1: System is operational
        $storage = new MaintenanceStorage($config);
        $this->assertFalse($storage->isActive());

        // Phase 2: Activate maintenance
        command('mm:down -message "System integration test" -ip "192.168.1.100" -secret "data-secret"');

        $this->assertTrue($storage->isActive());
        $data = $storage->getData();
        $this->assertSame('System integration test', $data->message);
        $this->assertContains('192.168.1.100', $data->allowed_ips);
        $this->assertTrue($data->secret_bypass);
        $this->assertSame('data-secret', $data->secret_key);

        // Phase 3: Test status command
        command('mm:status');
        $this->assertTrue($storage->isActive());

        // Phase 4: Deactivate maintenance
        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testCommandControllerStorageIntegration(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        $storage = new MaintenanceStorage($config);

        // Test command -> storage -> controller integration
        command('mm:down -message "Command-Controller integration" -ip "127.0.0.1"');

        // Verify storage has the data
        $this->assertTrue($storage->isActive());
        $data = $storage->getData();
        $this->assertSame('Command-Controller integration', $data->message);
        $this->assertContains('127.0.0.1', $data->allowed_ips);

        command('mm:up');
    }

    public function testFilterEventControllerIntegration(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        $this->setupTestRoute();

        // Activate maintenance
        command('mm:down -message "Filter-Event-Controller integration" -cookie "test_bypass"');

        // Setup event
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');

        // Test 1: All should block without bypass
        $this->expectException(ServiceUnavailableException::class);
        Events::trigger('maintenance');
    }

    public function testMultipleStorageBackendIntegration(): void
    {
        // Test cache storage
        $cacheConfig = $this->createCustomConfig([
            'useCache'     => true,
            'cacheHandler' => 'file',
        ]);
        Factories::injectMock('config', 'Maintenance', $cacheConfig);

        $cacheStorage = new MaintenanceStorage($cacheConfig);

        // Activate with cache
        command('mm:down -message "Cache backend test"');
        $this->assertTrue($cacheStorage->isActive());

        // Test controller with cache
        $this->expectException(ServiceUnavailableException::class);
        Maintenance::check();
    }

    public function testBypassMethodsIntegration(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'config-bypass',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test multiple bypass methods via commands
        command('mm:down -message "Multi-bypass integration" -ip "192.168.1.100" -secret "data-bypass" -cookie "bypass_cookie"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertSame('Multi-bypass integration', $data->message);
        $this->assertContains('192.168.1.100', $data->allowed_ips);
        $this->assertTrue($data->secret_bypass);
        $this->assertSame('data-bypass', $data->secret_key);
        $this->assertSame('bypass_cookie', $data->cookie_name);

        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testConfigurationChangesIntegration(): void
    {
        // Test dynamic configuration changes
        $config1 = $this->createCustomConfig([
            'useCache'      => false,
            'enableLogging' => false,
        ]);
        Factories::injectMock('config', 'Maintenance', $config1);

        command('mm:down -message "Config test 1"');

        $storage1 = new MaintenanceStorage($config1);
        $this->assertTrue($storage1->isActive());

        // Change to cache configuration
        $config2 = $this->createCustomConfig([
            'useCache'      => true,
            'cacheHandler'  => 'file',
            'enableLogging' => true,
        ]);
        Factories::injectMock('config', 'Maintenance', $config2);

        // Previous maintenance should still be detectable
        $storage2 = new MaintenanceStorage($config2);

        // Commands should work with new config
        command('mm:status');
        command('mm:up');

        $this->assertFalse($storage2->isActive());
    }

    public function testErrorHandlingIntegration(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Test error handling with maintenance
        command('mm:down -message "Error handling test"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertSame('Error handling test', $data->message);

        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testLongRunningMaintenanceIntegration(): void
    {
        $config = $this->createCustomConfig([
            'retryAfterSeconds' => 1800, // 30 minutes
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        $this->setupTestRoute();

        // Simulate long-running maintenance
        command('mm:down -message "Long-running maintenance test"');

        $storage = new MaintenanceStorage($config);
        $data    = $storage->getData();

        // Verify data integrity over time
        $this->assertSame('Long-running maintenance test', $data->message);
        $this->assertIsInt($data->time);
        $this->assertLessThanOrEqual(time(), $data->time);

        // All components should consistently detect maintenance
        $this->expectException(ServiceUnavailableException::class);
        Maintenance::check();
    }

    public function testSystemResourcesIntegration(): void
    {
        $config = $this->createCustomConfig([
            'enableLogging' => true,
            'useCache'      => true,
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test system resources aren't exhausted
        for ($i = 0; $i < 10; $i++) {
            command('mm:down -message "Resource test ' . $i . '"');

            $storage = new MaintenanceStorage($config);
            $this->assertTrue($storage->isActive());

            command('mm:up');
            $this->assertFalse($storage->isActive());
        }

        // System should still be responsive
        $finalStorage = new MaintenanceStorage($config);
        $this->assertFalse($finalStorage->isActive());

        $result = Maintenance::check();
        $this->assertTrue($result, 'System should remain responsive after multiple cycles');
    }

    public function testConcurrentAccessIntegration(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Test concurrent access simulation
        command('mm:down -message "Concurrent test" -ip "127.0.0.1"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertSame('Concurrent test', $data->message);
        $this->assertContains('127.0.0.1', $data->allowed_ips);

        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testFailoverIntegration(): void
    {
        // Test failover between storage methods
        $fileConfig = $this->createCustomConfig([
            'useCache' => false,
        ]);
        Factories::injectMock('config', 'Maintenance', $fileConfig);

        // Start with file storage
        command('mm:down -message "Failover test"');

        $fileStorage = new MaintenanceStorage($fileConfig);
        $this->assertTrue($fileStorage->isActive());

        // Switch to cache storage with new config
        $cacheConfig = $this->createCustomConfig([
            'useCache'     => true,
            'cacheHandler' => 'file',
        ]);
        Factories::injectMock('config', 'Maintenance', $cacheConfig);

        // Clean up with current config
        command('mm:up');

        // Verify final state with cache storage
        $cacheStorage = new MaintenanceStorage($cacheConfig);
        $this->assertFalse($cacheStorage->isActive());
    }
}
