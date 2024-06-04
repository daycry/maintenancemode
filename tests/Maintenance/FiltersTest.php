<?php

namespace Tests\Maintenance;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\StreamFilterTrait;
use Daycry\Maintenance\Filters\Maintenance;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Tests\Support\TestCase;

class FiltersTest extends TestCase
{
    use FeatureTestTrait;
    use StreamFilterTrait;

    private string $message = 'In maintenance';
    private string $ip = '127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->expectException(ServiceUnavailableException::class);

        command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );

        $result = $this->call('get', 'hello');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
    }

    public static function tearDownAfterClass(): void
    {
        unlink(setting('Maintenance.filePath') . setting('Maintenance.fileName'));
    }
}
