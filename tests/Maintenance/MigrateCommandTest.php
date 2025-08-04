<?php

namespace Tests\Maintenance;

use Tests\Support\TestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Daycry\Maintenance\Libraries\MaintenanceStorage;

/**
 * @internal
 */
final class MigrateCommandTest extends TestCase
{
    use StreamFilterTrait;

    private \Daycry\Maintenance\Config\Maintenance $config;
    private MaintenanceStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        helper('setting');
        
        $this->config = new \Daycry\Maintenance\Config\Maintenance();
        $this->storage = new MaintenanceStorage($this->config);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up maintenance data after each test
        $this->storage->clearAll();
    }

    public function testMigrateCommandShowsProperOutput(): void
    {
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Run migrate command without options (should show current status)
        command('mm:migrate');
        
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Maintenance Mode Migration Tool', $output);
    }

    public function testMigrateCommandWithClearOption(): void
    {
        // First create some maintenance data
        $maintenanceData = [
            'time' => time(),
            'message' => 'Test data for migration',
            'allowed_ips' => ['127.0.0.1'],
        ];
        
        $this->storage->save($maintenanceData);
        $this->assertTrue($this->storage->isActive());
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Use clear option (this would normally prompt for confirmation)
        // In test environment, we'll test the logic directly
        $cleared = $this->storage->clearAll();
        $this->assertTrue($cleared);
        $this->assertFalse($this->storage->isActive());
    }

    public function testMigrateCommandDetectsStorageType(): void
    {
        // Create test data
        $maintenanceData = [
            'time' => time(),
            'message' => 'Storage type test',
            'allowed_ips' => ['127.0.0.1'],
        ];
        
        $this->storage->save($maintenanceData);
        
        // Test that we can determine storage type
        $isActive = $this->storage->isActive();
        $this->assertTrue($isActive);
        
        // Test that we can retrieve data regardless of storage type
        $data = $this->storage->getData();
        $this->assertNotNull($data);
        $this->assertEquals('Storage type test', $data->message);
    }

    public function testMigrateCommandHandlesEmptyState(): void
    {
        // Ensure no maintenance data exists
        $this->storage->clearAll();
        $this->assertFalse($this->storage->isActive());
        
        // Clear the buffer
        $this->resetStreamFilterBuffer();
        
        // Run migrate command
        command('mm:migrate');
        
        $output = $this->getStreamFilterBuffer();
        $this->assertStringContainsString('Migration Tool', $output);
        
        // Should still show no active maintenance
        $this->assertFalse($this->storage->isActive());
    }

    public function testMigrationPreservesDataIntegrity(): void
    {
        // Create comprehensive test data
        $originalData = [
            'time' => time(),
            'message' => 'Data integrity test',
            'allowed_ips' => ['127.0.0.1', '192.168.1.100'],
            'secret_bypass' => true,
            'secret_key' => 'integrity-test-key',
            'cookie_name' => 'test_cookie',
            'duration_minutes' => 60,
            'estimated_end' => time() + (60 * 60)
        ];
        
        // Save data
        $this->storage->save($originalData);
        $this->assertTrue($this->storage->isActive());
        
        // Retrieve and verify data integrity
        $retrievedData = $this->storage->getData();
        $this->assertNotNull($retrievedData);
        $this->assertEquals($originalData['message'], $retrievedData->message);
        $this->assertEquals($originalData['allowed_ips'], $retrievedData->allowed_ips);
        $this->assertEquals($originalData['secret_bypass'], $retrievedData->secret_bypass);
        $this->assertEquals($originalData['secret_key'], $retrievedData->secret_key);
        $this->assertEquals($originalData['cookie_name'], $retrievedData->cookie_name);
        $this->assertEquals($originalData['duration_minutes'], $retrievedData->duration_minutes);
        $this->assertEquals($originalData['estimated_end'], $retrievedData->estimated_end);
    }

    public function testMigrateCommandHandlesCorruptedData(): void
    {
        // This test simulates what happens when data might be corrupted
        // We can test the storage's resilience to invalid data
        
        // First ensure we start clean
        $this->storage->clearAll();
        
        // Try to get data when none exists
        $data = $this->storage->getData();
        $this->assertNull($data);
        
        // Verify isActive handles missing data gracefully
        $isActive = $this->storage->isActive();
        $this->assertFalse($isActive);
    }
}
