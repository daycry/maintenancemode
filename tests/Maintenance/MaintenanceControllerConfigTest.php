<?php

namespace Tests\Maintenance;

use Tests\Support\TestCase;
use Daycry\Maintenance\Controllers\Maintenance;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Config\Services;
use CodeIgniter\Config\Factories;

class MaintenanceControllerConfigTest extends TestCase
{
    private $originalConfig;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original config
        $this->originalConfig = config('Maintenance');
        
        // Clean maintenance storage
        $storage = new MaintenanceStorage($this->originalConfig);
        $storage->save(['message' => 'Testing maintenance mode']);
    }

    protected function tearDown(): void
    {
        // Clean up
        $storage = new MaintenanceStorage($this->originalConfig);
        $storage->remove();
        
        parent::tearDown();
    }

    public function testConfigSecretBypassEnabled()
    {
        // Create a custom config with secret bypass enabled
        $customConfig = new \Daycry\Maintenance\Config\Maintenance();
        $customConfig->allowSecretBypass = true;
        $customConfig->secretBypassKey = 'test-secret-123';
        $customConfig->enableLogging = false;
        
        // Mock the Factories class to return our custom config
        $originalFactory = Factories::getOptions('config');
        Factories::injectMock('config', 'Maintenance', $customConfig);
        
        // Set up maintenance mode
        $storage = new MaintenanceStorage($customConfig);
        $storage->save(['message' => 'Testing maintenance mode']);
        
        // Mock request with secret parameter
        $request = $this->createMock(\CodeIgniter\HTTP\IncomingRequest::class);
        $request->method('getGet')
                ->with('maintenance_secret')
                ->willReturn('test-secret-123');
        $request->method('getIPAddress')
                ->willReturn('127.0.0.1');
        
        Services::injectMock('request', $request);
        
        // Test that bypass works
        $result = Maintenance::check();
        $this->assertTrue($result, 'Config secret bypass should allow access');
        
        // Clean up
        Services::reset();
        Factories::reset('config');
    }

    public function testConfigSecretBypassDisabled()
    {
        // Create a custom config with secret bypass disabled
        $customConfig = new \Daycry\Maintenance\Config\Maintenance();
        $customConfig->allowSecretBypass = false;
        $customConfig->secretBypassKey = 'test-secret-123';
        $customConfig->enableLogging = false;
        
        // Mock the Factories class
        Factories::injectMock('config', 'Maintenance', $customConfig);
        
        // Set up maintenance mode
        $storage = new MaintenanceStorage($customConfig);
        $storage->save(['message' => 'Testing maintenance mode']);
        
        // Mock request with secret parameter (should be ignored)
        $request = $this->createMock(\CodeIgniter\HTTP\IncomingRequest::class);
        $request->method('getGet')
                ->with('maintenance_secret')
                ->willReturn('test-secret-123');
        $request->method('getIPAddress')
                ->willReturn('127.0.0.1');
        
        Services::injectMock('request', $request);
        
        // Test that bypass is ignored when disabled
        $this->expectException(\Daycry\Maintenance\Exceptions\ServiceUnavailableException::class);
        Maintenance::check();
        
        // Clean up
        Services::reset();
        Factories::reset('config');
    }

    public function testConfigSecretBypassWrongSecret()
    {
        // Create a custom config with secret bypass enabled
        $customConfig = new \Daycry\Maintenance\Config\Maintenance();
        $customConfig->allowSecretBypass = true;
        $customConfig->secretBypassKey = 'test-secret-123';
        $customConfig->enableLogging = false;
        
        // Mock the Factories class
        Factories::injectMock('config', 'Maintenance', $customConfig);
        
        // Set up maintenance mode
        $storage = new MaintenanceStorage($customConfig);
        $storage->save(['message' => 'Testing maintenance mode']);
        
        // Mock request with wrong secret
        $request = $this->createMock(\CodeIgniter\HTTP\IncomingRequest::class);
        $request->method('getGet')
                ->with('maintenance_secret')
                ->willReturn('wrong-secret');
        $request->method('getIPAddress')
                ->willReturn('127.0.0.1');
        
        Services::injectMock('request', $request);
        
        // Test that wrong secret doesn't bypass
        $this->expectException(\Daycry\Maintenance\Exceptions\ServiceUnavailableException::class);
        Maintenance::check();
        
        // Clean up
        Services::reset();
        Factories::reset('config');
    }

    public function testConfigSecretBypassEmptyKey()
    {
        // Create a custom config with empty secret key
        $customConfig = new \Daycry\Maintenance\Config\Maintenance();
        $customConfig->allowSecretBypass = true;
        $customConfig->secretBypassKey = '';
        $customConfig->enableLogging = false;
        
        // Mock the Factories class
        Factories::injectMock('config', 'Maintenance', $customConfig);
        
        // Set up maintenance mode
        $storage = new MaintenanceStorage($customConfig);
        $storage->save(['message' => 'Testing maintenance mode']);
        
        // Mock request
        $request = $this->createMock(\CodeIgniter\HTTP\IncomingRequest::class);
        $request->method('getGet')
                ->with('maintenance_secret')
                ->willReturn('any-secret');
        $request->method('getIPAddress')
                ->willReturn('127.0.0.1');
        
        Services::injectMock('request', $request);
        
        // Test that empty key doesn't allow bypass
        $this->expectException(\Daycry\Maintenance\Exceptions\ServiceUnavailableException::class);
        Maintenance::check();
        
        // Clean up
        Services::reset();
        Factories::reset('config');
    }
}
