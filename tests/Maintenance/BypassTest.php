<?php

namespace Tests\Maintenance;

use Tests\Support\TestCase;
use CodeIgniter\Config\Factories;
use Daycry\Maintenance\Config\Maintenance;
use Daycry\Maintenance\Controllers\Maintenance as MaintenanceController;
use Daycry\Maintenance\Libraries\MaintenanceStorage;

/**
 * @internal
 * Comprehensive tests for Maintenance Bypass functionality
 * Tests bypass methods: Secret, IP, Cookie, and combinations
 */
final class BypassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['setting', 'filesystem']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Factories::reset('config');
        $this->cleanupMaintenanceFiles();
    }

    private function createCustomConfig(array $overrides = []): Maintenance
    {
        $config = new Maintenance();
        
        // Safe test defaults
        $config->enableLogging = false;
        $config->retryAfterSeconds = 3600;
        $config->defaultMessage = 'Application is in maintenance mode';
        $config->allowSecretBypass = false;
        $config->secretBypassKey = '';
        $config->allowedIpAddresses = [];
        $config->allowCookieBypass = false;
        $config->cookieName = 'maintenance_bypass';
        $config->storage = 'file';
        $config->filePath = WRITEPATH . 'maintenance/';
        
        // Apply overrides
        foreach ($overrides as $property => $value) {
            $config->$property = $value;
        }
        
        return $config;
    }

    private function cleanupMaintenanceFiles(): void
    {
        $maintenanceDir = WRITEPATH . 'maintenance/';
        if (is_dir($maintenanceDir)) {
            $files = glob($maintenanceDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    // ========== SECRET BYPASS TESTS ==========

    public function testSecretBypassWithConfigKey(): void
    {
        // Test using commands with secret bypass
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'config-secret-123'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Put app in maintenance with secret
        command('mm:down -message "Secret test" -secret "data-secret-456"');
        
        // Check that maintenance is active
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        // Verify data has the secret
        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertTrue($data->secret_bypass);
        $this->assertEquals('data-secret-456', $data->secret_key);
        
        command('mm:up');
    }

    public function testSecretBypassBasicFunctionality(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'test-secret-key'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Put app in maintenance
        command('mm:down -message "Test with secret" -secret "bypass-key"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        // Test the secret bypass via static method
        $_GET['maintenance_secret'] = 'test-secret-key';
        $result = MaintenanceController::check();
        $this->assertTrue($result); // Should be bypassed
        
        unset($_GET['maintenance_secret']);
        command('mm:up');
    }

    // ========== IP BYPASS TESTS ==========

    public function testIpBypassWithMultipleIPs(): void
    {
        $config = $this->createCustomConfig([
            'allowedIpAddresses' => ['127.0.0.1', '192.168.1.100']
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Test down command with multiple IPs
        command('mm:down -message "IP test" -ip "127.0.0.1 192.168.1.100"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertContains('127.0.0.1', $data->allowed_ips);
        $this->assertContains('192.168.1.100', $data->allowed_ips);
        
        command('mm:up');
    }

    public function testIpBypassWithCIDR(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Test CIDR notation
        command('mm:down -message "CIDR test" -ip "192.168.1.0/24"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertContains('192.168.1.0/24', $data->allowed_ips);
        
        command('mm:up');
    }

    // ========== COOKIE BYPASS TESTS ==========

    public function testCookieBypassCommand(): void
    {
        $config = $this->createCustomConfig([
            'allowCookieBypass' => true,
            'cookieName' => 'test_bypass_cookie'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Down command should work with cookie parameter
        command('mm:down -message "Cookie test" -cookie "test_cookie"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertEquals('test_cookie', $data->cookie_name);
        
        command('mm:up');
    }

    // ========== INTEGRATION TESTS ==========

    public function testCompleteWorkflowWithBypass(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'master-secret',
            'allowCookieBypass' => true
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Complete workflow test
        command('mm:down -message "Complete test" -ip "127.0.0.1" -secret "data-secret" -cookie "bypass_cookie"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        // Check status
        command('mm:status');
        // Just verify that maintenance is still active after status command
        $this->assertTrue($storage->isActive());
        
        // Verify data structure
        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertEquals('Complete test', $data->message);
        $this->assertContains('127.0.0.1', $data->allowed_ips);
        $this->assertTrue($data->secret_bypass);
        $this->assertEquals('data-secret', $data->secret_key);
        $this->assertEquals('bypass_cookie', $data->cookie_name);
        
        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testBypassWithDifferentStorageBackends(): void
    {
        // Test with file storage
        $config = $this->createCustomConfig([
            'storage' => 'file',
            'allowSecretBypass' => true
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        command('mm:down -message "File storage test" -secret "file-secret"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertTrue($data->secret_bypass);
        
        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    // ========== EDGE CASES ==========

    public function testBypassWithInvalidIPFormats(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Test with invalid IP format - should handle gracefully
        command('mm:down -message "Invalid IP test" -ip "invalid.ip.format 999.999.999.999"');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        // Should still have maintenance active but with default IPs
        $data = $storage->getData();
        $this->assertNotNull($data);
        
        command('mm:up');
    }

    public function testBypassWithEmptySecretKey(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => ''
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Test with empty secret - should work
        command('mm:down -message "Empty secret test" -secret ""');
        
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        command('mm:up');
    }

    public function testMaintenanceControllerStaticMethod(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Test when maintenance is not active
        $result = MaintenanceController::check();
        $this->assertTrue($result); // Should allow access
        
        // Put in maintenance mode
        command('mm:down -message "Static method test"');
        
        // Test when maintenance is active
        try {
            $result = MaintenanceController::check();
            $this->fail('Should have thrown ServiceUnavailableException');
        } catch (\Daycry\Maintenance\Exceptions\ServiceUnavailableException $e) {
            $this->assertStringContainsString('Static method test', $e->getMessage());
        }
        
        command('mm:up');
    }
}
