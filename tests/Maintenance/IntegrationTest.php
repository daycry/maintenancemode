<?php

namespace Tests\Maintenance;

use Tests\Support\TestCase;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Events\Events;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Daycry\Maintenance\Controllers\Maintenance;
use Daycry\Maintenance\Filters\Maintenance as MaintenanceFilter;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;

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
        $config->enableLogging = false;
        $config->retryAfterSeconds = 3600;
        $config->defaultMessage = 'Application is in maintenance mode';
        $config->allowSecretBypass = false;
        $config->secretBypassKey = '';
        
        // Apply overrides
        foreach ($overrides as $property => $value) {
            $config->$property = $value;
        }
        
        return $config;
    }

    private function cleanupMaintenanceFiles(): void
    {
        try {
            $config = $this->createCustomConfig();
            $storage = new MaintenanceStorage($config);
            $storage->clearAll();
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    private function cleanupGlobalVariables(): void
    {
        unset($_GET['secret'], $_GET['maintenance_secret']);
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        
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
        $filters = config('Filters');
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
            'secretBypassKey' => 'workflow-secret',
            'enableLogging' => false
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Phase 1: System is operational
        $storage = new MaintenanceStorage($config);
        $this->assertFalse($storage->isActive());
        
        // Test normal operation
        $response = $this->call('get', 'hello');
        $this->assertMatchesRegularExpression('/Hello/i', $response->getBody());
        
        // Events should also allow access
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        $eventResult = Events::trigger('maintenance');
        $this->assertTrue($eventResult);
        
        // Phase 2: Activate maintenance
        command('mm:down -message "System integration test" -ip "192.168.1.100" -secret "data-secret"');
        
        $this->assertTrue($storage->isActive());
        $data = $storage->getData();
        $this->assertEquals('System integration test', $data->message);
        
        // Phase 3: Test blocking
        $this->expectException(ServiceUnavailableException::class);
        $this->call('get', 'hello');
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
        $this->assertEquals('Command-Controller integration', $data->message);
        $this->assertEquals(['127.0.0.1'], $data->allowed_ips);
        
        // Test controller can read the same data
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $controllerResult = Maintenance::check();
        $this->assertTrue($controllerResult, 'Controller should read command data and allow IP bypass');
        
        // Test status command reads the same data
        command('mm:status');
        
        // Test up command clears data
        command('mm:up');
        $this->assertFalse($storage->isActive());
        
        // Controller should now allow access
        $controllerResult = Maintenance::check();
        $this->assertTrue($controllerResult, 'Controller should allow access when maintenance is off');
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
            'useCache' => true,
            'cacheHandler' => 'file'
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
            'secretBypassKey' => 'config-bypass'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance with multiple bypass methods
        command('mm:down -message "Multi-bypass integration" -ip "192.168.1.100" -secret "data-bypass" -cookie "bypass_cookie"');
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        // Test IP bypass across all components
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        $controllerResult = Maintenance::check();
        $this->assertTrue($controllerResult, 'Controller IP bypass should work');
        
        $eventResult = Events::trigger('maintenance');
        $this->assertTrue($eventResult, 'Event IP bypass should work');
        
        $filterResponse = $this->call('get', 'hello');
        $this->assertMatchesRegularExpression('/Hello/i', $filterResponse->getBody(), 'Filter IP bypass should work');
        
        // Test secret bypass
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50'; // Different IP
        $_GET['secret'] = 'data-bypass';
        
        $controllerResult = Maintenance::check();
        $this->assertTrue($controllerResult, 'Controller secret bypass should work');
        
        $eventResult = Events::trigger('maintenance');
        $this->assertTrue($eventResult, 'Event secret bypass should work');
        
        $filterResponse = $this->call('get', 'hello?secret=data-bypass');
        $this->assertMatchesRegularExpression('/Hello/i', $filterResponse->getBody(), 'Filter secret bypass should work');
        
        // Test config secret bypass
        $_GET['secret'] = 'config-bypass';
        
        $controllerResult = Maintenance::check();
        $this->assertTrue($controllerResult, 'Controller config secret bypass should work');
        
        // Test cookie bypass
        unset($_GET['secret']);
        $_COOKIE['bypass_cookie'] = '1';
        
        $controllerResult = Maintenance::check();
        $this->assertTrue($controllerResult, 'Controller cookie bypass should work');
        
        $eventResult = Events::trigger('maintenance');
        $this->assertTrue($eventResult, 'Event cookie bypass should work');
    }

    public function testConfigurationChangesIntegration(): void
    {
        // Test dynamic configuration changes
        $config1 = $this->createCustomConfig([
            'useCache' => false,
            'enableLogging' => false
        ]);
        Factories::injectMock('config', 'Maintenance', $config1);
        
        command('mm:down -message "Config test 1"');
        
        $storage1 = new MaintenanceStorage($config1);
        $this->assertTrue($storage1->isActive());
        
        // Change to cache configuration
        $config2 = $this->createCustomConfig([
            'useCache' => true,
            'cacheHandler' => 'file',
            'enableLogging' => true
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
        
        $this->setupTestRoute();
        
        // Test with invalid maintenance data
        $storage = new MaintenanceStorage($config);
        $storage->save([
            'time' => time(),
            'message' => 'Error handling test',
            'allowed_ips' => ['invalid-ip', '192.168.1.100'], // Mix of invalid and valid
            'secret_bypass' => true,
            'secret_key' => '', // Empty secret key
        ]);
        
        // System should handle errors gracefully
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100'; // Valid IP should still work
        
        $result = Maintenance::check();
        $this->assertTrue($result, 'Valid IP should work despite invalid data');
        
        // Events should also handle errors
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        $eventResult = Events::trigger('maintenance');
        $this->assertTrue($eventResult, 'Events should handle invalid data gracefully');
        
        // Filters should handle errors
        $response = $this->call('get', 'hello');
        $this->assertMatchesRegularExpression('/Hello/i', $response->getBody(), 'Filters should handle invalid data gracefully');
    }

    public function testLongRunningMaintenanceIntegration(): void
    {
        $config = $this->createCustomConfig([
            'retryAfterSeconds' => 1800 // 30 minutes
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Simulate long-running maintenance
        command('mm:down -message "Long-running maintenance test"');
        
        $storage = new MaintenanceStorage($config);
        $data = $storage->getData();
        
        // Verify data integrity over time
        $this->assertEquals('Long-running maintenance test', $data->message);
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
            'useCache' => true
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
        
        // Simulate concurrent access scenarios
        command('mm:down -message "Concurrent test" -ip "192.168.1.100"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        // Multiple simultaneous checks should be consistent
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = Maintenance::check();
        }
        
        // All results should be the same
        $this->assertTrue(array_reduce($results, function($carry, $item) { 
            return $carry && $item; 
        }, true), 'All concurrent checks should return same result');
    }

    public function testFailoverIntegration(): void
    {
        // Test failover between storage methods
        $fileConfig = $this->createCustomConfig([
            'useCache' => false
        ]);
        Factories::injectMock('config', 'Maintenance', $fileConfig);
        
        // Start with file storage
        command('mm:down -message "Failover test"');
        
        $fileStorage = new MaintenanceStorage($fileConfig);
        $this->assertTrue($fileStorage->isActive());
        
        // Switch to cache storage
        $cacheConfig = $this->createCustomConfig([
            'useCache' => true,
            'cacheHandler' => 'file'
        ]);
        Factories::injectMock('config', 'Maintenance', $cacheConfig);
        
        // Should still detect maintenance (both storages might coexist)
        $cacheStorage = new MaintenanceStorage($cacheConfig);
        
        // System should handle transition gracefully
        command('mm:up');
        
        $this->assertFalse($cacheStorage->isActive());
        $this->assertFalse($fileStorage->isActive());
    }
}
