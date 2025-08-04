<?php

namespace Tests\Maintenance;

use CodeIgniter\Test\StreamFilterTrait;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class CommandsTest extends TestCase
{
    use StreamFilterTrait;

    private string $message = 'In maintenance';
    private string $ip      = '127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        helper('setting');
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testCommandInMaintenanceDown(): void
    {
        command('mm:down -message "' . $this->message . '" -ip ' . $this->ip);
        // $result = CITestStreamFilter::$buffer;
        $data = json_decode(file_get_contents(setting('Maintenance.filePath') . setting('Maintenance.fileName')));

        $this->assertFileExists(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
        $this->assertSame($this->message, $data->message);
        $this->assertTrue($this->_arrays_are_similar(\explode(' ', $this->ip), (array) $data->allowed_ips));
    }

    public function testCommandInMaintenanceAlreadyDown(): void
    {
        command('mm:down -message "' . $this->message . '" -ip ' . $this->ip);

        $this->assertStringContainsString('Application is already in maintenance mode', $this->getStreamFilterBuffer());
    }

    public function testCommandCheckStatusDown()
    {
        command('mm:status');

        $data = \json_decode(file_get_contents(setting('Maintenance.filePath') . setting('Maintenance.fileName')));

        $this->assertFileExists(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
        $this->assertSame($this->message, $data->message);
        $this->assertTrue($this->_arrays_are_similar(\explode(' ', $this->ip), (array) $data->allowed_ips));
    }

    public function testCommandMaintenanceUp()
    {
        command('mm:up');

        $this->assertFileDoesNotExist(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
    }

    public function testCommandMaintenanceAlreadyUp()
    {
        $output = command('mm:status');

        $this->assertFileDoesNotExist(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
    }

    public function testCommandInMaintenanceDownWithoutMessage()
    {
        command('mm:down');

        $this->assertFileExists(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
    }

    /**
     * Determine if two associative arrays are similar
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     *
     * @param array $a
     * @param array $b
     */
    private function _arrays_are_similar($a, $b): bool
    {
        // if the indexes don't match, return immediately
        if (count(array_diff_assoc($a, $b))) {
            return false;
        }

        // we know that the indexes, but maybe not values, match.
        // compare the values between the two arrays
        foreach ($a as $k => $v) {
            if ($v !== $b[$k]) {
                return false;
            }
        }

        // we have identical indexes, and no unequal values
        return true;
    }

    public static function tearDownAfterClass(): void
    {
        $config = new \Daycry\Maintenance\Config\Maintenance();
        unlink($config->filePath . $config->fileName);
    }
}
