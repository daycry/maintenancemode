<?php

namespace Tests\Maintenance;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use Daycry\Maintenance\Config\Maintenance;
use Daycry\Maintenance\Controllers\Maintenance as MaintenanceController;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Tests\Support\TestCase;
use Throwable;

/**
 * @internal
 *
 * Regression tests for Sprint 1 security fixes:
 *  - hash_equals() comparison of secret bypass keys
 *  - cookie bypass requires the random cookie_value (not the cookie name)
 *  - CIDR prefix range validation in mm:down
 */
final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['setting', 'filesystem', 'cookie', 'text']);
        $this->nukeAllStorage();
        $this->resetSuperglobals();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
        Factories::reset('config');
        $this->nukeAllStorage();
        $this->resetSuperglobals();
    }

    /**
     * Aggressively clear maintenance state from BOTH cache and file storage,
     * regardless of which backend the previous test happened to use.
     */
    private function nukeAllStorage(): void
    {
        try {
            $fileConfig           = $this->makeConfig(['useCache' => false]);
            $fileConfig->filePath = WRITEPATH . 'maintenance/';
            (new MaintenanceStorage($fileConfig))->clearAll();
        } catch (Throwable) {
            // ignore
        }

        try {
            $cacheConfig = $this->makeConfig(['useCache' => true]);
            (new MaintenanceStorage($cacheConfig))->clearAll();
        } catch (Throwable) {
            // ignore
        }

        $leftover = WRITEPATH . 'maintenance/down';
        if (file_exists($leftover)) {
            @unlink($leftover);
        }
    }

    private function resetSuperglobals(): void
    {
        unset($_GET['maintenance_secret']);
        if (isset($_COOKIE)) {
            foreach ($_COOKIE as $key => $value) {
                if (str_contains($key, 'maintenance') || str_contains($key, 'bypass') || str_contains($key, 'cookie')) {
                    unset($_COOKIE[$key]);
                }
            }
        }
    }

    private function makeConfig(array $overrides = []): Maintenance
    {
        $config                    = new Maintenance();
        $config->enableLogging     = false;
        $config->retryAfterSeconds = 3600;
        $config->allowSecretBypass = false;
        $config->secretBypassKey   = '';
        $config->useCache          = false;
        $config->filePath          = WRITEPATH . 'maintenance/';

        foreach ($overrides as $property => $value) {
            $config->{$property} = $value;
        }

        return $config;
    }

    /**
     * Inject a mocked request so the controller sees the GET params and IP we
     * want — bypassing the static request singleton that command() builds with
     * empty globals.
     */
    private function mockRequest(array $getParams = [], string $ip = '203.0.113.99', array $cookies = []): void
    {
        $request = $this->createMock(IncomingRequest::class);
        $request->method('getGet')->willReturnCallback(static fn ($key) => $getParams[$key] ?? null);
        $request->method('getIPAddress')->willReturn($ip);
        $request->method('getCookie')->willReturnCallback(static fn ($key) => $cookies[$key] ?? null);
        Services::injectMock('request', $request);
    }

    // ========== SECRET BYPASS — TIMING-SAFE ==========

    public function testCorrectConfigSecretAllowsAccess(): void
    {
        $config = $this->makeConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'correct-config-secret',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Secret regression" -ip "192.168.1.100"');

        $this->mockRequest(['maintenance_secret' => 'correct-config-secret']);
        $this->assertTrue(MaintenanceController::check());
    }

    public function testWrongConfigSecretDeniesAccess(): void
    {
        $config = $this->makeConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => 'correct-config-secret',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Secret regression" -ip "192.168.1.100"');

        $this->mockRequest(['maintenance_secret' => 'wrong-secret']);
        $this->expectException(ServiceUnavailableException::class);
        MaintenanceController::check();
    }

    public function testEmptyProvidedSecretDoesNotBypassEvenWhenConfigKeyIsEmpty(): void
    {
        // Regression: with === and an empty config key, an empty provided value
        // would have evaluated as "matching". hash_equals + non-empty guard prevents this.
        $config = $this->makeConfig([
            'allowSecretBypass' => true,
            'secretBypassKey'   => '',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Empty key regression" -ip "192.168.1.100"');

        $this->mockRequest(['maintenance_secret' => '']);
        $this->expectException(ServiceUnavailableException::class);
        MaintenanceController::check();
    }

    public function testCorrectDataSecretAllowsAccess(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Data secret" -ip "192.168.1.100" -secret "data-secret-xyz"');

        $this->mockRequest(['maintenance_secret' => 'data-secret-xyz']);
        $this->assertTrue(MaintenanceController::check());
    }

    public function testWrongDataSecretDeniesAccess(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Data secret" -ip "192.168.1.100" -secret "data-secret-xyz"');

        $this->mockRequest(['maintenance_secret' => 'data-secret-XXX']);
        $this->expectException(ServiceUnavailableException::class);
        MaintenanceController::check();
    }

    // ========== COOKIE BYPASS — VALUE-BASED, NOT NAME-BASED ==========

    public function testCookieBypassRequiresCookieValueNotCookieName(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Cookie regression" -ip "192.168.1.100" -cookie "bypass_cookie_42"');

        $storage = new MaintenanceStorage($config);
        $data    = $storage->getData();
        $this->assertNotNull($data);
        $this->assertSame('bypass_cookie_42', $data->cookie_name);
        $this->assertNotEmpty($data->cookie_value ?? '');

        // Old broken behaviour used to bypass when cookie value == cookie name.
        // After the fix, this MUST be rejected.
        $this->mockRequest([], '203.0.113.99', ['bypass_cookie_42' => 'bypass_cookie_42']);

        $this->expectException(ServiceUnavailableException::class);
        MaintenanceController::check();
    }

    public function testCookieBypassWithCorrectValueAllowsAccess(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Cookie regression" -ip "192.168.1.100" -cookie "bypass_cookie_42"');

        $storage  = new MaintenanceStorage($config);
        $expected = (string) $storage->getData()->cookie_value;

        $this->mockRequest([], '203.0.113.99', ['bypass_cookie_42' => $expected]);

        $this->assertTrue(MaintenanceController::check());
    }

    public function testCookieValueIsHighEntropyHex(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Cookie entropy" -ip "192.168.1.100"');

        $value = (string) (new MaintenanceStorage($config))->getData()->cookie_value;
        // bin2hex(random_bytes(32)) → 64 hex chars
        $this->assertSame(64, strlen($value), 'cookie_value should be 32 random bytes hex-encoded');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $value);
    }

    // ========== CIDR PREFIX VALIDATION ==========

    public function testCidrWithOutOfRangeIPv4PrefixIsRejected(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "CIDR" -ip "10.0.0.0/40"');

        $data = (new MaintenanceStorage($config))->getData();
        $this->assertNotNull($data);
        $this->assertNotContains('10.0.0.0/40', $data->allowed_ips);
    }

    public function testCidrWithValidIPv4PrefixIsAccepted(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "CIDR" -ip "10.0.0.0/24"');

        $data = (new MaintenanceStorage($config))->getData();
        $this->assertNotNull($data);
        $this->assertContains('10.0.0.0/24', $data->allowed_ips);
    }

    public function testCidrWithValidIPv6PrefixIsAccepted(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "CIDR v6" -ip "2001:db8::/32"');

        $data = (new MaintenanceStorage($config))->getData();
        $this->assertNotNull($data);
        $this->assertContains('2001:db8::/32', $data->allowed_ips);
    }

    public function testCidrWithOutOfRangeIPv6PrefixIsRejected(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "CIDR v6" -ip "2001:db8::/200"');

        $data = (new MaintenanceStorage($config))->getData();
        $this->assertNotNull($data);
        $this->assertNotContains('2001:db8::/200', $data->allowed_ips);
    }
}
