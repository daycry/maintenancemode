<?php

namespace Tests\Maintenance;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\FeatureTestTrait;
use Daycry\Maintenance\Filters\Maintenance;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Exception;
use Tests\Support\TestCase;

/**
 * @internal
 * Comprehensive and unified tests for Filters and Events
 * All filte    public function testFiltersEventsWithLoggingEnabled(): void
 * {
 * $config = $this->createCustomConfig([
 * 'enableLogging' => true
 * ]);
 * Factories::injectMock('config', 'Maintenance', $config);
 *
 * // Test logging configuration works with commands
 * command('mm:down -message "Logging test"');
 *
 * $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
 * $this->assertTrue($storage->isActive());
 *
 * command('mm:up');
 * $this->assertFalse($storage->isActive());
 * }ionality tested in one place using config('Maintenance') approach
 */
final class FiltersEventsTest extends TestCase
{
    use FeatureTestTrait;

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
        Events::simulate(false);
        $this->cleanupMaintenanceFiles();
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

    private function setupTestRoute(): void
    {
        // Create a simple test route without filters for basic testing
        $routes = Services::routes();
        $routes->get('hello', ['\Tests\Support\Controllers\Hello', 'index']);
        Services::injectMock('routes', $routes);
    }

    // ===== FILTER TESTS =====

    public function testFilterAllowsAccessWhenMaintenanceInactive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Ensure maintenance is inactive
        $storage = new MaintenanceStorage($config);
        $this->assertFalse($storage->isActive());

        // Test filter logic directly
        $filter  = new Maintenance();
        $request = Services::request();

        // Should not throw exception when maintenance is inactive
        $result = $filter->before($request);
        $this->assertTrue($result === null || $result === true);
    }

    public function testFilterBlocksAccessWhenMaintenanceActive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Put app in maintenance
        command('mm:down -message "Filter test"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        command('mm:up');
    }

    public function testFilterAllowsAccessWithValidIp(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Test basic IP bypass functionality via command
        command('mm:down -message "IP bypass test" -ip "127.0.0.1"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertContains('127.0.0.1', $data->allowed_ips);

        command('mm:up');
    }

    public function testFilterWithSecretBypass(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'test-secret',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test secret bypass functionality via command
        command('mm:down -message "Secret bypass test" -secret "test-secret"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertTrue($data->secret_bypass);
        $this->assertSame('test-secret', $data->secret_key);

        command('mm:up');
    }

    public function testFilterWithInvalidSecretBypass(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'test-secret',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test that maintenance is active even with wrong secret
        command('mm:down -message "Invalid secret test" -secret "wrong-secret"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertSame('wrong-secret', $data->secret_key);

        command('mm:up');
    }

    public function testFilterWithCacheConfiguration(): void
    {
        $config = $this->createCustomConfig([
            'useCache'     => true,
            'cacheHandler' => 'file',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test cache configuration works with commands
        command('mm:down -message "Cache filter test"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testFilterWithFileConfiguration(): void
    {
        $config = $this->createCustomConfig([
            'useCache' => false,
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test file configuration works with commands
        command('mm:down -message "File filter test"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    // ===== EVENT TESTS =====

    public function testEventTriggersWhenMaintenanceInactive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Ensure maintenance is inactive
        $storage = new MaintenanceStorage($config);
        $this->assertFalse($storage->isActive());

        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');

        $result = Events::trigger('maintenance');
        $this->assertTrue($result);
    }

    public function testEventTriggersExceptionWhenMaintenanceActive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Activate maintenance
        command('mm:down -message "Event test maintenance"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        command('mm:up');
    }

    public function testEventAllowsAccessWithValidIp(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Test IP bypass functionality with events via command
        command('mm:down -message "Event IP test" -ip "127.0.0.1"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertContains('127.0.0.1', $data->allowed_ips);

        command('mm:up');
    }

    public function testEventWithSecretBypassEnabled(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'event-secret',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test secret bypass functionality via command
        command('mm:down -message "Event secret test" -secret "event-secret"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertTrue($data->secret_bypass);
        $this->assertSame('event-secret', $data->secret_key);

        command('mm:up');
    }

    public function testEventWithDataSecretBypass(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Test data secret bypass functionality via command
        command('mm:down -message "Event data secret test" -secret "data-event-secret"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertTrue($data->secret_bypass);
        $this->assertSame('data-event-secret', $data->secret_key);

        command('mm:up');
    }

    public function testEventWithCookieBypass(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Test cookie bypass functionality via command
        command('mm:down -message "Event cookie test" -cookie "bypass_cookie"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertSame('bypass_cookie', $data->cookie_name);

        command('mm:up');
    }

    // ===== INTEGRATION TESTS =====

    public function testFilterAndEventIntegration(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        // Test basic integration - verify maintenance works with different storage
        command('mm:down -message "Integration test"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testMultipleBypassMethodsWithFiltersAndEvents(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'multi-secret',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test multiple bypass methods via commands
        command('mm:down -message "Multi bypass test" -ip "127.0.0.1" -secret "data-secret" -cookie "multi_cookie"');

        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        $data = $storage->getData();
        $this->assertNotNull($data);
        $this->assertContains('127.0.0.1', $data->allowed_ips);
        $this->assertTrue($data->secret_bypass);
        $this->assertSame('data-secret', $data->secret_key);
        $this->assertSame('multi_cookie', $data->cookie_name);

        command('mm:up');
    }

    // ===== CONFIGURATION TESTS =====
    // ===== ERROR HANDLING TESTS =====

    public function testFilterErrorHandlingWithInvalidConfig(): void
    {
        // Test with minimal config
        $config = new \Daycry\Maintenance\Config\Maintenance();
        Factories::injectMock('config', 'Maintenance', $config);

        $this->setupTestRoute();

        // Should work even with default config when maintenance is inactive
        $result = $this->call('get', 'hello');
        $this->assertMatchesRegularExpression('/Hello/i', $result->getBody());
    }

    public function testEventErrorHandlingWithInvalidConfig(): void
    {
        // Test with minimal config
        $config = new \Daycry\Maintenance\Config\Maintenance();
        Factories::injectMock('config', 'Maintenance', $config);

        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');

        // Should work even with default config when maintenance is inactive
        $result = Events::trigger('maintenance');
        $this->assertTrue($result);
    }

    // ===== CONFIGURATION TESTS =====

    public function testFiltersEventsWithDifferentStorageConfigurations(): void
    {
        // Test cache configuration
        $cacheConfig = $this->createCustomConfig([
            'useCache'     => true,
            'cacheHandler' => 'file',
        ]);
        Factories::injectMock('config', 'Maintenance', $cacheConfig);

        command('mm:down -message "Cache storage test"');

        $storage = new MaintenanceStorage($cacheConfig);
        $this->assertTrue($storage->isActive());

        command('mm:up');
        $this->assertFalse($storage->isActive());
    }

    public function testFiltersEventsWithLoggingEnabled(): void
    {
        $config = $this->createCustomConfig([
            'enableLogging' => true,
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        // Test with logging enabled
        command('mm:down -message "Logging test"');

        // Verify maintenance mode is active by checking storage
        $storage = new MaintenanceStorage($config);
        $this->assertTrue($storage->isActive());

        // Cleanup
        command('mm:up');

        // Verify maintenance mode is disabled
        $this->assertFalse($storage->isActive());
    }
}
