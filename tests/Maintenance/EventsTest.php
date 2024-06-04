<?php

namespace Tests\Maintenance;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\StreamFilterTrait;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Tests\Support\TestCase;

class EventsTest extends TestCase
{
    use StreamFilterTrait;

    private string $message = 'In maintenance';
    private string $ip = '127.0.0.1';
    private string $validIp = '0.0.0.0';

    protected function setUp(): void
    {
        parent::setUp();

        helper('setting');
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testRunEventUp()
    {
        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');

        $result = Events::trigger('maintenance');
        $this->assertTrue(Events::trigger('maintenance'));
    }

    public function testRunEventDown()
    {
        $this->expectException(ServiceUnavailableException::class);

        command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );

        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');

        Events::trigger('maintenance');
    }

    public function testRunEventDownWithAccessValidIp()
    {
        command( 'mm:up' );

        command( 'mm:down -message "'. $this->message .'" -ip ' . $this->validIp );

        Events::on('maintenance', 'Daycry\Maintenance\Controllers\Maintenance::check');

        $this->assertTrue(Events::trigger('maintenance'));
    }

    public static function tearDownAfterClass(): void
    {
        unlink(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
    }

    protected function tearDown(): void
    {
        Events::simulate(false);
    }
}