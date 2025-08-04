<?php

namespace Tests\Maintenance;

use CodeIgniter\Test\StreamFilterTrait;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class CommandsExtendedTest extends TestCase
{
    use StreamFilterTrait;

    private \Daycry\Maintenance\Config\Maintenance $config;
    private \Daycry\Maintenance\Libraries\MaintenanceStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        helper('setting');
        
        $this->config = new \Daycry\Maintenance\Config\Maintenance();
        $this->storage = new \Daycry\Maintenance\Libraries\MaintenanceStorage($this->config);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up maintenance data after each test
        $this->storage->clearAll();
    }

    public function testDownCommandWithSecretOption(): void
    {
        // Test down command with secret option
        command('mm:down -message "Testing with secret" -secret custom-secret-123');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        $this->assertNotNull($data);
        $this->assertEquals('Testing with secret', $data->message);
        $this->assertTrue($data->secret_bypass);
        $this->assertEquals('custom-secret-123', $data->secret_key);
    }

    public function testDownCommandWithDurationOption(): void
    {
        // Test down command with duration option
        command('mm:down -message "Testing duration" -duration 120');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        $this->assertNotNull($data);
        $this->assertEquals('Testing duration', $data->message);
        $this->assertEquals(120, $data->duration_minutes);
        
        // Check that estimated_end is set correctly
        $expectedEnd = $data->time + (120 * 60);
        $this->assertEquals($expectedEnd, $data->estimated_end);
    }

    public function testDownCommandWithMultipleIPs(): void
    {
        // Test down command with multiple IP addresses
        command('mm:down -message "Testing multiple IPs" -ip "127.0.0.1 192.168.1.100 10.0.0.1"');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        $this->assertNotNull($data);
        $this->assertEquals('Testing multiple IPs', $data->message);
        $this->assertContains('127.0.0.1', $data->allowed_ips);
        $this->assertContains('192.168.1.100', $data->allowed_ips);
        $this->assertContains('10.0.0.1', $data->allowed_ips);
    }

    public function testDownCommandWithAllOptions(): void
    {
        // Test down command with all options combined
        command('mm:down -message "Full test" -ip "127.0.0.1 192.168.1.1" -duration 60 -secret full-secret-key');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        $this->assertNotNull($data);
        $this->assertEquals('Full test', $data->message);
        $this->assertContains('127.0.0.1', $data->allowed_ips);
        $this->assertContains('192.168.1.1', $data->allowed_ips);
        $this->assertEquals(60, $data->duration_minutes);
        $this->assertTrue($data->secret_bypass);
        $this->assertEquals('full-secret-key', $data->secret_key);
    }

    public function testUpCommandWhenAlreadyUp(): void
    {
        // Ensure maintenance is not active
        $this->storage->clearAll();
        $this->assertFalse($this->storage->isActive());
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Try to bring application up
        command('mm:up');
        
        // Should show that application is already live
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Application is already live', $output);
        $this->assertFalse($this->storage->isActive());
    }

    public function testUpCommandFromActiveState(): void
    {
        // First activate maintenance mode
        command('mm:down -message "Test for up command"');
        $this->assertTrue($this->storage->isActive());
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Bring application up
        command('mm:up');
        
        // Should show that application is now live
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Application is now LIVE', $output);
        $this->assertFalse($this->storage->isActive());
    }

    public function testStatusCommandWithDetailedInfo(): void
    {
        // Activate maintenance with comprehensive data
        command('mm:down -message "Detailed status test" -ip "127.0.0.1 192.168.1.100" -duration 90 -secret status-secret');
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Check status
        command('mm:status');
        
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('MAINTENANCE MODE', $output);
        $this->assertStringContainsString('Detailed status test', $output);
        $this->assertStringContainsString('127.0.0.1', $output);
        $this->assertStringContainsString('192.168.1.100', $output);
        $this->assertStringContainsString('90', $output); // Duration
        $this->assertStringContainsString('status-secret', $output); // Secret
    }

    public function testStatusCommandWhenNotInMaintenance(): void
    {
        // Ensure no maintenance mode
        $this->storage->clearAll();
        $this->assertFalse($this->storage->isActive());
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Check status
        command('mm:status');
        
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Application is LIVE', $output);
        $this->assertStringContainsString('normally', $output);
    }

    public function testDownCommandWithInvalidIPs(): void
    {
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Test down command with some invalid IPs mixed with valid ones
        command('mm:down -message "Testing invalid IPs" -ip "127.0.0.1 invalid-ip 192.168.1.100 999.999.999.999"');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        // Should contain only valid IPs
        $this->assertContains('127.0.0.1', $data->allowed_ips);
        $this->assertContains('192.168.1.100', $data->allowed_ips);
        $this->assertNotContains('invalid-ip', $data->allowed_ips);
        $this->assertNotContains('999.999.999.999', $data->allowed_ips);
        
        // Should show warnings about invalid IPs
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Warning', $output);
        $this->assertStringContainsString('invalid-ip', $output);
        $this->assertStringContainsString('999.999.999.999', $output);
    }

    public function testDownCommandWithEmptyMessage(): void
    {
        // Test that default message is used when no message provided
        command('mm:down -ip 127.0.0.1');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        $this->assertNotNull($data);
        // Should use default message from config
        $this->assertNotEmpty($data->message);
    }

    public function testDownCommandGeneratesRandomCookieName(): void
    {
        // Test that cookie name is generated automatically
        command('mm:down -message "Cookie test"');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        $this->assertNotNull($data);
        $this->assertNotEmpty($data->cookie_name);
        $this->assertEquals(8, strlen($data->cookie_name)); // Should be 8 characters
    }

    public function testDownCommandSetsCorrectTimestamps(): void
    {
        $beforeTime = time();
        
        command('mm:down -message "Timestamp test" -duration 30');
        
        $afterTime = time();
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        
        $this->assertNotNull($data);
        $this->assertGreaterThanOrEqual($beforeTime, $data->time);
        $this->assertLessThanOrEqual($afterTime, $data->time);
        
        // Check estimated end time
        $expectedEnd = $data->time + (30 * 60);
        $this->assertEquals($expectedEnd, $data->estimated_end);
    }

    public function testStatusCommandShowsBypassInformation(): void
    {
        // Configure maintenance with bypass methods
        command('mm:down -message "Bypass info test" -ip "127.0.0.1" -secret bypass-key');
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Check status
        command('mm:status');
        
        $output = $this->getStreamFilterBuffer();
        
        // Should show bypass information
        $this->assertStringContainsString('Secret Bypass Information', $output);
        $this->assertStringContainsString('maintenance_secret=bypass-key', $output);
        $this->assertStringContainsString('IP Address', $output); // Changed from "Allowed IPs"
        $this->assertStringContainsString('127.0.0.1', $output);
    }

    public function testSequentialMaintenanceActivation(): void
    {
        // Test multiple sequential maintenance activations
        
        // First maintenance
        command('mm:down -message "First maintenance" -duration 30');
        $this->assertTrue($this->storage->isActive());
        $data1 = $this->storage->getData();
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Try to activate again (should show already active message)
        command('mm:down -message "Second maintenance"');
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('already in maintenance mode', $output);
        
        // Data should remain unchanged
        $data2 = $this->storage->getData();
        $this->assertEquals($data1->message, $data2->message);
        $this->assertEquals($data1->time, $data2->time);
        
        // Bring up
        command('mm:up');
        $this->assertFalse($this->storage->isActive());
        
        // Now can activate again
        command('mm:down -message "Third maintenance"');
        $this->assertTrue($this->storage->isActive());
        $data3 = $this->storage->getData();
        $this->assertEquals('Third maintenance', $data3->message);
    }
}
