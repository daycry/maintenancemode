<?php

namespace Tests\Maintenance;

use CodeIgniter\Test\StreamFilterTrait;
use Tests\Support\TestCase;

/**
 * @internal
 * Tests for command integration and edge cases
 */
final class CommandsIntegrationTest extends TestCase
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

    public function testCommandWorkflowDownStatusUp(): void
    {
        // Test complete workflow: down -> status -> up
        
        // Step 1: Activate maintenance
        command('mm:down -message "Integration test" -duration 30 -secret workflow-key');
        $this->assertTrue($this->storage->isActive());
        
        // Step 2: Check status while active
        $this->resetStreamFilterBuffer();
        command('mm:status');
        $statusOutput = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('MAINTENANCE MODE', $statusOutput);
        $this->assertStringContainsString('Integration test', $statusOutput);
        $this->assertStringContainsString('workflow-key', $statusOutput);
        
        // Step 3: Deactivate maintenance
        $this->resetStreamFilterBuffer();
        command('mm:up');
        $upOutput = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('now LIVE', $upOutput);
        $this->assertFalse($this->storage->isActive());
        
        // Step 4: Check status when inactive
        $this->resetStreamFilterBuffer();
        command('mm:status');
        $finalStatusOutput = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Application is LIVE', $finalStatusOutput);
    }

    public function testCommandsWithInvalidOptions(): void
    {
        // Test commands with various invalid option combinations
        
        // Invalid duration (should handle gracefully)
        $this->resetStreamFilterBuffer();
        command('mm:down -message "Invalid duration test" -duration invalid');
        
        // Should still create maintenance mode (duration should default or be 0)
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        $this->assertNotNull($data);
        $this->assertEquals('Invalid duration test', $data->message);
        
        // Clean up
        command('mm:up');
        $this->assertFalse($this->storage->isActive());
    }

    public function testCommandsConcurrentExecution(): void
    {
        // Test what happens with overlapping command executions
        
        // Activate maintenance
        command('mm:down -message "Concurrent test" -ip 127.0.0.1');
        $this->assertTrue($this->storage->isActive());
        $originalData = $this->storage->getData();
        
        // Try to activate again (should be prevented)
        $this->resetStreamFilterBuffer();
        command('mm:down -message "Should not override" -ip 192.168.1.1');
        $overrideOutput = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('already in maintenance mode', $overrideOutput);
        
        // Verify original data is preserved
        $currentData = $this->storage->getData();
        $this->assertEquals($originalData->message, $currentData->message);
        $this->assertEquals($originalData->time, $currentData->time);
        
        // Cleanup
        command('mm:up');
    }

    public function testCommandsDataPersistence(): void
    {
        // Test data persistence across command calls
        
        // Create maintenance with comprehensive data
        command('mm:down -message "Persistence test" -ip "127.0.0.1 192.168.1.100" -duration 45 -secret persist-key');
        
        $originalData = $this->storage->getData();
        $this->assertNotNull($originalData);
        
        // Execute status command (should not modify data)
        command('mm:status');
        
        $afterStatusData = $this->storage->getData();
        $this->assertEquals($originalData->message, $afterStatusData->message);
        $this->assertEquals($originalData->time, $afterStatusData->time);
        $this->assertEquals($originalData->allowed_ips, $afterStatusData->allowed_ips);
        $this->assertEquals($originalData->secret_key, $afterStatusData->secret_key);
        $this->assertEquals($originalData->duration_minutes, $afterStatusData->duration_minutes);
        
        // Cleanup
        command('mm:up');
    }

    public function testCommandsErrorHandling(): void
    {
        // Test error handling in various scenarios
        
        // Try to go up when already up
        $this->storage->clearAll();
        $this->assertFalse($this->storage->isActive());
        
        $this->resetStreamFilterBuffer();
        command('mm:up');
        $upOutput = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('already live', $upOutput);
        
        // Try to activate with completely invalid IPs
        $this->resetStreamFilterBuffer();
        command('mm:down -message "Error test" -ip "completely.invalid.ip another.bad.ip"');
        
        // Should still activate but with default IP
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        $this->assertNotNull($data);
        $this->assertContains('127.0.0.1', $data->allowed_ips); // Should fall back to default
        
        $errorOutput = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Warning', $errorOutput);
        
        // Cleanup
        command('mm:up');
    }

    public function testCommandsWithSpecialCharacters(): void
    {
        // Test commands with special characters in messages and keys
        // Note: We'll use simpler special chars to avoid shell escaping issues
        
        $specialMessage = "Test with special chars: !@#$%^&*()_+-=";
        $specialSecret = "secret123!@#$%^";
        
        command('mm:down -message "' . $specialMessage . '" -secret "' . $specialSecret . '"');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        $this->assertNotNull($data);
        $this->assertEquals($specialMessage, $data->message);
        $this->assertEquals($specialSecret, $data->secret_key);
        
        // Test status display with special characters
        $this->resetStreamFilterBuffer();
        command('mm:status');
        $statusOutput = $this->getStreamFilterBuffer();
        $this->assertStringContainsString($specialSecret, $statusOutput);
        
        // Cleanup
        command('mm:up');
    }

    public function testCommandsLongRunningMaintenance(): void
    {
        // Test maintenance mode with very long duration
        
        $longDuration = 525600; // 1 year in minutes
        command('mm:down -message "Long maintenance" -duration ' . $longDuration);
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        $this->assertNotNull($data);
        $this->assertEquals($longDuration, $data->duration_minutes);
        
        // Verify estimated end time calculation
        $expectedEnd = $data->time + ($longDuration * 60);
        $this->assertEquals($expectedEnd, $data->estimated_end);
        
        // Cleanup
        command('mm:up');
    }

    public function testCommandsWithMultipleCIDRRanges(): void
    {
        // Test with CIDR notation IPs (if supported)
        
        command('mm:down -message "CIDR test" -ip "192.168.1.0/24 10.0.0.0/8 172.16.0.0/12"');
        
        $this->assertTrue($this->storage->isActive());
        $data = $this->storage->getData();
        $this->assertNotNull($data);
        
        // Check that CIDR ranges are stored (they might be validated differently)
        $this->assertNotEmpty($data->allowed_ips);
        
        $this->resetStreamFilterBuffer();
        command('mm:status');
        $statusOutput = $this->getStreamFilterBuffer();
        
        // Should show IP information in status
        $this->assertStringContainsString('IP', $statusOutput);
        
        // Cleanup
        command('mm:up');
    }

    public function testCommandsQuickSuccession(): void
    {
        // Test commands executed in quick succession
        
        $startTime = time();
        
        // Execute multiple down/up cycles quickly
        for ($i = 0; $i < 3; $i++) {
            command('mm:down -message "Quick test ' . $i . '"');
            $this->assertTrue($this->storage->isActive());
            
            command('mm:up');
            $this->assertFalse($this->storage->isActive());
        }
        
        $endTime = time();
        
        // Should complete in reasonable time
        $this->assertLessThan(10, $endTime - $startTime, 'Commands should execute quickly');
        
        // Final state should be up
        $this->assertFalse($this->storage->isActive());
    }
}
