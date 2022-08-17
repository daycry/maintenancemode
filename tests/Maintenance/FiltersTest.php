<?php

namespace Tests\Maintenance;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\Filters\CITestStreamFilter;

use Daycry\Maintenance\Filters\Maintenance;

class FiltersTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private $message = 'In maintenance';
    private $ip = '127.0.0.1';

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

        $filters = config('Filters');
        $filters->aliases['maintenance'] = Maintenance::class;
        Factories::injectMock('filters', 'filters', $filters);

        $routes = Services::routes();

        $routes->get('hello', '\Tests\Support\Controllers\Hello', ['filter' => "maintenance"], );
        Services::injectMock('routes', $routes);

    }

    public function testCallingFilterOk()
    {
        $result = $this->call('get', 'hello');

        $this->assertMatchesRegularExpression('/Hello/i', $result->getBody());
    }

    public function testCallingFilterKo()
    {
        $this->expectException(\Daycry\Maintenance\Exceptions\ServiceUnavailableException::class);

        command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );

        $result = $this->call('get', 'hello');
    }

    protected function tearDown(): void
    {
        stream_filter_remove($this->streamFilter);

        parent::tearDown();
        Services::reset();
    }

    public static function tearDownAfterClass(): void
    {
        $config = new \Daycry\Maintenance\Config\Maintenance();
        unlink($config->filePath . $config->fileName);
    }
}
