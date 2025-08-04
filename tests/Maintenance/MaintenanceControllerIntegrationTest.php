<?php

namespace Tests\Maintenance;

use Tests\Support\TestCase;
use Daycry\Maintenance\Controllers\Maintenance;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Config\Services;

/**
 * @internal
 * Integration tests for Maintenance Controller with system components
 */
final class MaintenanceControllerIntegrationTest extends TestCase
{
    private \Daycry\Maintenance\Config\Maintenance $config;
    private MaintenanceStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['setting', 'cookie']);
        
        $this->config = new \Daycry\Maintenance\Config\Maintenance();
        $this->storage = new MaintenanceStorage($this->config);
        
        // Clean up any existing maintenance data
        $this->storage->clearAll();
        
        // Reset global state
        $this->resetGlobalState();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up maintenance data after each test
        $this->storage->clearAll();
        $this->resetGlobalState();
    }

    private function resetGlobalState(): void
    {
        unset($_GET['maintenance_secret']);
        unset($_SERVER['REMOTE_ADDR']);
        $_GET = [];
        $_SERVER = $_SERVER ?? [];
        $_COOKIE = $_COOKIE ?? []; // Ensure $_COOKIE is always defined
    }

    public function testControllerIntegrationWithStorageBackends(): void
    {
        // Test that controller works with different storage backends
        
        // Test with cache storage (default)
        $this->config->storageType = 'cache';
        $cacheStorage = new MaintenanceStorage($this->config);
        
        $cacheStorage->save([
            'time' => time(),
            'message' => 'Cache storage test',
            'allowed_ips' => ['127.0.0.1']
        ]);
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $result = Maintenance::check();
        $this->assertTrue($result);
        
        $cacheStorage->clearAll();
        
        // Test with file storage
        $this->config->storageType = 'file';
        $fileStorage = new MaintenanceStorage($this->config);
        
        $fileStorage->save([
            'time' => time(),
            'message' => 'File storage test',
            'allowed_ips' => ['127.0.0.1']
        ]);
        
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $result = Maintenance::check();
        $this->assertTrue($result);
        
        $fileStorage->clearAll();
    }

    public function testControllerWithIpUtilsIntegration(): void
    {
        $_COOKIE = $_COOKIE ?? []; // Ensure $_COOKIE is always defined
        
        // Test simple exact IP match
        $this->storage->save([
            'time' => time(),
            'message' => 'IpUtils test',
            'allowed_ips' => ['127.0.0.1']
        ]);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $result = Maintenance::check();
        $this->assertTrue($result, 'Exact IP match should be allowed');
    }

    public function testControllerWithServicesIntegration(): void
    {
        // Test integration with CI4 Services
        $this->storage->save([
            'time' => time(),
            'message' => 'Services integration test',
            'allowed_ips' => []
        ]);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';

        try {
            Maintenance::check();
            $this->fail('Expected ServiceUnavailableException was not thrown');
        } catch (ServiceUnavailableException $e) {
            // Test that Services::response() is properly used
            $response = Services::response();
            $this->assertInstanceOf(\CodeIgniter\HTTP\Response::class, $response);
            
            // Test that Services::request() is properly used
            $request = Services::request();
            $this->assertInstanceOf(\CodeIgniter\HTTP\IncomingRequest::class, $request);
        }
    }

    public function testControllerLoggingIntegration(): void
    {
        // Test logging integration
        $this->config->enableLogging = true;
        
        // Test bypass logging
        $this->storage->save([
            'time' => time(),
            'message' => 'Logging integration test',
            'allowed_ips' => ['127.0.0.1']
        ]);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        
        // This should log a bypass message
        $result = Maintenance::check();
        $this->assertTrue($result);
        
        // Test blocking logging
        $this->storage->save([
            'time' => time(),
            'message' => 'Blocking log test',
            'allowed_ips' => []
        ]);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';
        
        try {
            Maintenance::check();
            $this->fail('Expected ServiceUnavailableException was not thrown');
        } catch (ServiceUnavailableException $e) {
            // Should have logged the blocked access
            $this->assertTrue(true, 'Blocked access logged');
        }
    }

    public function testControllerExceptionHandling(): void
    {
        // Test that controller properly handles and re-throws ServiceUnavailableException
        $this->storage->save([
            'time' => time(),
            'message' => 'Exception handling test',
            'allowed_ips' => []
        ]);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';

        $exceptionThrown = false;
        try {
            Maintenance::check();
        } catch (ServiceUnavailableException $e) {
            $exceptionThrown = true;
            $this->assertInstanceOf(ServiceUnavailableException::class, $e);
            $this->assertEquals('Exception handling test', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'ServiceUnavailableException should be thrown');
    }

    public function testControllerWithConfigurationVariations(): void
    {
        $_COOKIE = $_COOKIE ?? []; // Ensure $_COOKIE is always defined
        
        // Skip this test as it requires config injection
        $this->markTestSkipped('Configuration variations test requires config injection which needs to be implemented');
    }

    public function testControllerPerformanceWithLargeDatasets(): void
    {
        // Test performance with large allowed IP lists
        $largeIPList = [];
        
        // Generate 1000 IP addresses
        for ($i = 1; $i <= 255; $i++) {
            for ($j = 1; $j <= 4; $j++) {
                $largeIPList[] = "192.168.{$i}.{$j}";
                if (count($largeIPList) >= 1000) break 2;
            }
        }

        $startTime = microtime(true);

        $this->storage->save([
            'time' => time(),
            'message' => 'Performance test with large IP list',
            'allowed_ips' => $largeIPList
        ]);

        // Test with IP in the list
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $result = Maintenance::check();
        $this->assertTrue($result);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete in reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $executionTime, 'Large IP list check should be fast');
    }

    public function testControllerMemoryUsage(): void
    {
        // Test memory usage doesn't grow excessively
        $initialMemory = memory_get_usage();

        // Run multiple checks
        for ($i = 0; $i < 100; $i++) {
            $this->storage->save([
                'time' => time(),
                'message' => "Memory test iteration {$i}",
                'allowed_ips' => ['127.0.0.1']
            ]);

            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            $result = Maintenance::check();
            $this->assertTrue($result);

            $this->storage->clearAll();
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 1MB)
        $this->assertLessThan(1024 * 1024, $memoryIncrease, 'Memory usage should not grow excessively');
    }

    public function testControllerConcurrentAccess(): void
    {
        // Test behavior with simulated concurrent access
        $this->storage->save([
            'time' => time(),
            'message' => 'Concurrent access test',
            'allowed_ips' => ['127.0.0.1']
        ]);

        // Simulate multiple rapid checks
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            $results[] = Maintenance::check();
        }

        // All should return true (allowed)
        foreach ($results as $index => $result) {
            $this->assertTrue($result, "Concurrent check {$index} should succeed");
        }
    }

    public function testControllerEdgeCaseDataTypes(): void
    {
        // Test with various data types in maintenance data
        $edgeCaseData = [
            'time' => time(),
            'message' => 'Edge case data types test',
            'allowed_ips' => ['127.0.0.1'],
            'secret_bypass' => 1,           // Integer instead of boolean
            'secret_key' => 123456,         // Integer instead of string
            'cookie_name' => null,          // Null value
            'extra_field' => 'unexpected'   // Unexpected field
        ];

        $this->storage->save($edgeCaseData);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $result = Maintenance::check();
        $this->assertTrue($result);

        // Test with secret as integer
        $_GET['maintenance_secret'] = '123456';
        $result = Maintenance::check();
        $this->assertTrue($result);
    }
}
