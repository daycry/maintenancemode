<?php

namespace Tests\Maintenance;

use CodeIgniter\Test\Filters\CITestStreamFilter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Events\Events;

class EventsTest extends CIUnitTestCase
{
    private $message = 'In maintenance';
    private $ip = '127.0.0.1';
    private $validIp = '0.0.0.0';

    /**
     * @var resource
     */
    private $streamFilter;

    protected function setUp(): void
    {
        parent::setUp();

        CITestStreamFilter::$buffer = '';
        $this->streamFilter = stream_filter_append(STDOUT, 'CITestStreamFilter');
        $this->streamFilter = stream_filter_append(STDERR, 'CITestStreamFilter');
        
        $this->config = new \Daycry\Maintenance\Config\Maintenance();
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
        $this->expectException(\Daycry\Maintenance\Exceptions\ServiceUnavailableException::class);

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
        $config = new \Daycry\Maintenance\Config\Maintenance();
        unlink($config->filePath . $config->fileName);
    }

    protected function tearDown(): void
    {
        Events::simulate(false);
    }
}