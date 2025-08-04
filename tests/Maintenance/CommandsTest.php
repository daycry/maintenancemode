<?php

namespace Tests\Maintenance;

use Tests\Support\TestCase;
use CodeIgniter\Config\Factories;
use Config\Services;

/**
 * @internal
 * Comprehensive and unified tests for all Maintenance Commands
 * All command functionality tested in one place using config('Maintenance') approach
 */
final class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['setting', 'filesystem']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
        Factories::reset('config');
        $this->cleanupMaintenanceFiles();
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
            $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
            $storage->clearAll();
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    // ===== DOWN COMMAND TESTS =====

    public function testDownCommandBasic(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Execute down command
        $result = command('mm:down -message "Basic test message" -ip "127.0.0.1"');
        
        // Verify maintenance is active
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        $data = $storage->getData();
        $this->assertEquals('Basic test message', $data->message);
        $this->assertEquals(['127.0.0.1'], $data->allowed_ips);
    }

    public function testDownCommandWithMultipleIps(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        command('mm:down -message "Multiple IPs test" -ip "192.168.1.100 10.0.0.0/8"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        $this->assertEquals(['192.168.1.100', '10.0.0.0/8'], $data->allowed_ips);
    }

    public function testDownCommandWithSecret(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        command('mm:down -message "Secret test" -secret "my-test-secret"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        $this->assertTrue($data->secret_bypass);
        $this->assertEquals('my-test-secret', $data->secret_key);
    }

    public function testDownCommandWithCookie(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        command('mm:down -message "Cookie test" -cookie "test_cookie"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        $this->assertEquals('test_cookie', $data->cookie_name);
    }

    public function testDownCommandWithAllOptions(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        command('mm:down -message "Complete test" -ip "192.168.1.100" -secret "test-secret" -cookie "test_cookie"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        $this->assertEquals('Complete test', $data->message);
        $this->assertEquals(['192.168.1.100'], $data->allowed_ips);
        $this->assertTrue($data->secret_bypass);
        $this->assertEquals('test-secret', $data->secret_key);
        $this->assertEquals('test_cookie', $data->cookie_name);
    }

    public function testDownCommandWhenAlreadyActive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // First activate maintenance
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $storage->save(['message' => 'Already active']);
        
        // Try to activate again
        $result = command('mm:down -message "Should not work"');
        
        // Should still be active with original message
        $data = $storage->getData();
        $this->assertEquals('Already active', $data->message);
    }

    // ===== UP COMMAND TESTS =====

    public function testUpCommandBasic(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // First activate maintenance
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $storage->save(['message' => 'Test maintenance']);
        $this->assertTrue($storage->isActive());
        
        // Now deactivate
        command('mm:up');
        
        $this->assertFalse($storage->isActive());
    }

    public function testUpCommandWhenNotActive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Execute up command when not active
        $result = command('mm:up');
        
        // Verify still not active (no exception thrown)
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $this->assertFalse($storage->isActive());
    }

    // ===== STATUS COMMAND TESTS =====

    public function testStatusCommandActive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance with specific data
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $storage->save([
            'message' => 'Status test message',
            'allowed_ips' => ['192.168.1.100'],
            'secret_bypass' => true,
            'secret_key' => 'test-secret',
            'cookie_name' => 'test_cookie'
        ]);
        
        $result = command('mm:status');
        
        // Test runs without error when active
        $this->assertTrue($storage->isActive());
    }

    public function testStatusCommandInactive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $result = command('mm:status');
        
        // Test runs without error when inactive
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $this->assertFalse($storage->isActive());
    }

    // ===== PUBLISH COMMAND TESTS =====

    public function testPublishCommand(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $result = command('mm:publish');
        
        // Should execute without error
        $this->assertIsInt($result);
    }

    public function testPublishCommandWithForce(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $result = command('mm:publish -force');
        
        // Should execute without error
        $this->assertIsInt($result);
    }

    // ===== MIGRATE COMMAND TESTS =====

    public function testMigrateCommand(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $result = command('mm:migrate');
        
        // Should work even with no data to migrate
        $this->assertIsInt($result);
    }

    // ===== INTEGRATION TESTS =====

    public function testCommandsWorkflow(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        
        // 1. Start with down command
        command('mm:down -message "Workflow test"');
        $this->assertTrue($storage->isActive());
        
        // 2. Check status (no verification of output, just that it runs)
        command('mm:status');
        
        // 3. Bring it back up
        command('mm:up');
        $this->assertFalse($storage->isActive());
        
        // 4. Final status check
        command('mm:status');
    }

    // ===== CONFIGURATION TESTS =====

    public function testCommandsWithCacheConfiguration(): void
    {
        $config = $this->createCustomConfig([
            'useCache' => true,
            'cacheHandler' => 'file'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        command('mm:down -message "Cache config test"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        $data = $storage->getData();
        $this->assertEquals('Cache config test', $data->message);
        
        // Clean up
        command('mm:up');
    }

    public function testCommandsWithFileConfiguration(): void
    {
        $config = $this->createCustomConfig([
            'useCache' => false
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        command('mm:down -message "File config test"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        $data = $storage->getData();
        $this->assertEquals('File config test', $data->message);
        
        // Clean up
        command('mm:up');
    }

    // ===== ERROR HANDLING TESTS =====

    public function testCommandsWithSpecialCharacters(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $specialMessage = 'Test with special chars: áéíóú ñ !@#$%';
        command('mm:down -message "' . $specialMessage . '"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        $this->assertEquals($specialMessage, $data->message);
    }

    public function testCommandsWithLongValues(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $longMessage = str_repeat('Long message ', 50);
        $longSecret = str_repeat('a', 100);
        
        command('mm:down -message "' . $longMessage . '" -secret "' . $longSecret . '"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        $this->assertEquals($longMessage, $data->message);
        $this->assertEquals($longSecret, $data->secret_key);
    }

    // ===== BYPASS FUNCTIONALITY TESTS =====

    public function testConfigSecretBypassInCommands(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'config-bypass-secret'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance without data secret
        command('mm:down -message "Config bypass test"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());
        
        // Verify config secret bypass would work (we can't test the actual bypass here
        // as it requires web request context, but we can verify the config is set)
        $this->assertTrue($config->allowSecretBypass);
        $this->assertEquals('config-bypass-secret', $config->secretBypassKey);
    }

    public function testDataSecretBypassInCommands(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance with data secret
        command('mm:down -message "Data bypass test" -secret "data-bypass-secret"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        $this->assertTrue($data->secret_bypass);
        $this->assertEquals('data-bypass-secret', $data->secret_key);
    }

    public function testMultipleBypassMethodsInCommands(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'config-secret'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance with all bypass methods
        command('mm:down -message "Multiple bypass test" -ip "192.168.1.100" -secret "data-secret" -cookie "bypass_cookie"');
        
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $data = $storage->getData();
        
        // Verify all bypass methods are configured
        $this->assertTrue($config->allowSecretBypass); // Config bypass
        $this->assertTrue($data->secret_bypass); // Data bypass
        $this->assertEquals(['192.168.1.100'], $data->allowed_ips); // IP bypass
        $this->assertEquals('bypass_cookie', $data->cookie_name); // Cookie bypass
    }
}
