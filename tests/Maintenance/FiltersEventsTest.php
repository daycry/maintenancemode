<?php

namespace Tests\Maintenance;

use Tests\Support\TestCase;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Events\Events;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Daycry\Maintenance\Filters\Maintenance;

/**
 * @internal
 * Comprehensive and unified tests for Filters and Events
 * All filter and event functionality tested in one place using config('Maintenance') approach
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

    private function setupTestRoute(): void
    {
        $filters = config('Filters');
        $filters->aliases['maintenance'] = Maintenance::class;
        Factories::injectMock('filters', 'filters', $filters);

        $routes = Services::routes();
        $routes->get('hello', ['controller' => '\Tests\Support\Controllers\Hello', 'filter' => 'maintenance']);
        Services::injectMock('routes', $routes);
    }

    // ===== FILTER TESTS =====

    public function testFilterAllowsAccessWhenMaintenanceInactive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Ensure maintenance is inactive
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
        $this->assertFalse($storage->isActive());
        
        $result = $this->call('get', 'hello');
        
        $this->assertMatchesRegularExpression('/Hello/i', $result->getBody());
    }

    public function testFilterBlocksAccessWhenMaintenanceActive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance
        command('mm:down -message "Filter test maintenance"');
        
        $this->expectException(ServiceUnavailableException::class);
        
        $result = $this->call('get', 'hello');
    }

    public function testFilterAllowsAccessWithValidIp(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance with current IP as allowed
        command('mm:down -message "IP bypass test" -ip "0.0.0.0"');
        
        $result = $this->call('get', 'hello');
        
        $this->assertMatchesRegularExpression('/Hello/i', $result->getBody());
    }

    public function testFilterWithSecretBypass(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'test-secret'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance
        command('mm:down -message "Secret bypass test"');
        
        // Access with secret parameter
        $result = $this->call('get', 'hello?secret=test-secret');
        
        $this->assertMatchesRegularExpression('/Hello/i', $result->getBody());
    }

    public function testFilterWithInvalidSecretBypass(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'test-secret'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance
        command('mm:down -message "Invalid secret test"');
        
        $this->expectException(ServiceUnavailableException::class);
        
        // Access with wrong secret
        $result = $this->call('get', 'hello?secret=wrong-secret');
    }

    public function testFilterWithCacheConfiguration(): void
    {
        $config = $this->createCustomConfig([
            'useCache' => true,
            'cacheHandler' => 'file'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance
        command('mm:down -message "Cache filter test"');
        
        $this->expectException(ServiceUnavailableException::class);
        
        $result = $this->call('get', 'hello');
    }

    public function testFilterWithFileConfiguration(): void
    {
        $config = $this->createCustomConfig([
            'useCache' => false
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance
        command('mm:down -message "File filter test"');
        
        $this->expectException(ServiceUnavailableException::class);
        
        $result = $this->call('get', 'hello');
    }

    // ===== EVENT TESTS =====

    public function testEventTriggersWhenMaintenanceInactive(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Ensure maintenance is inactive
        $storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($config);
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
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        $this->expectException(ServiceUnavailableException::class);
        
        Events::trigger('maintenance');
    }

    public function testEventAllowsAccessWithValidIp(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance with allowed IP
        command('mm:down -message "Event IP test" -ip "0.0.0.0"');
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        $result = Events::trigger('maintenance');
        $this->assertTrue($result);
    }

    public function testEventWithSecretBypassEnabled(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'event-secret'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance without IP restrictions
        command('mm:down -message "Event secret test"');
        
        // Simulate secret in request
        $_GET['secret'] = 'event-secret';
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        $result = Events::trigger('maintenance');
        $this->assertTrue($result);
        
        // Clean up
        unset($_GET['secret']);
    }

    public function testEventWithDataSecretBypass(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance with data secret
        command('mm:down -message "Event data secret test" -secret "data-event-secret"');
        
        // Simulate secret in request
        $_GET['secret'] = 'data-event-secret';
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        $result = Events::trigger('maintenance');
        $this->assertTrue($result);
        
        // Clean up
        unset($_GET['secret']);
    }

    public function testEventWithCookieBypass(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        // Activate maintenance with cookie bypass
        command('mm:down -message "Event cookie test" -cookie "bypass_cookie"');
        
        // Simulate cookie
        $_COOKIE['bypass_cookie'] = '1';
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        $result = Events::trigger('maintenance');
        $this->assertTrue($result);
        
        // Clean up
        unset($_COOKIE['bypass_cookie']);
    }

    // ===== INTEGRATION TESTS =====

    public function testFilterAndEventIntegration(): void
    {
        $config = $this->createCustomConfig();
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Test 1: Both should work when inactive
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        $eventResult = Events::trigger('maintenance');
        $this->assertTrue($eventResult);
        
        $filterResult = $this->call('get', 'hello');
        $this->assertMatchesRegularExpression('/Hello/i', $filterResult->getBody());
        
        // Test 2: Activate maintenance
        command('mm:down -message "Integration test"');
        
        // Both should block access
        $this->expectException(ServiceUnavailableException::class);
        Events::trigger('maintenance');
    }

    public function testMultipleBypassMethodsWithFiltersAndEvents(): void
    {
        $config = $this->createCustomConfig([
            'allowSecretBypass' => true,
            'secretBypassKey' => 'multi-secret'
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        // Activate maintenance with multiple bypass methods
        command('mm:down -message "Multi bypass test" -ip "0.0.0.0" -secret "data-secret" -cookie "multi_cookie"');
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        // Test IP bypass with events
        $eventResult = Events::trigger('maintenance');
        $this->assertTrue($eventResult);
        
        // Test IP bypass with filters
        $filterResult = $this->call('get', 'hello');
        $this->assertMatchesRegularExpression('/Hello/i', $filterResult->getBody());
        
        // Test config secret bypass
        $secretFilterResult = $this->call('get', 'hello?secret=multi-secret');
        $this->assertMatchesRegularExpression('/Hello/i', $secretFilterResult->getBody());
        
        // Test data secret bypass
        $_GET['secret'] = 'data-secret';
        $dataSecretEventResult = Events::trigger('maintenance');
        $this->assertTrue($dataSecretEventResult);
        unset($_GET['secret']);
        
        // Test cookie bypass
        $_COOKIE['multi_cookie'] = '1';
        $cookieEventResult = Events::trigger('maintenance');
        $this->assertTrue($cookieEventResult);
        unset($_COOKIE['multi_cookie']);
    }

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
            'useCache' => true,
            'cacheHandler' => 'file'
        ]);
        Factories::injectMock('config', 'Maintenance', $cacheConfig);
        
        $this->setupTestRoute();
        
        command('mm:down -message "Cache storage test"');
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        // Both should detect maintenance from cache
        $this->expectException(ServiceUnavailableException::class);
        Events::trigger('maintenance');
    }

    public function testFiltersEventsWithLoggingEnabled(): void
    {
        $config = $this->createCustomConfig([
            'enableLogging' => true
        ]);
        Factories::injectMock('config', 'Maintenance', $config);
        
        $this->setupTestRoute();
        
        command('mm:down -message "Logging test"');
        
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');
        
        // Should work with logging enabled
        $this->expectException(ServiceUnavailableException::class);
        Events::trigger('maintenance');
    }
}
