<?php

namespace Tests\Maintenance;

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use Daycry\Maintenance\Config\Maintenance;
use Daycry\Maintenance\Controllers\Maintenance as MaintenanceController;
use Daycry\Maintenance\DTO\MaintenanceData;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Daycry\Maintenance\Libraries\MaintenanceStorage;
use Daycry\Maintenance\Services\MaintenanceService;
use Daycry\Maintenance\Storage\FileStorage;
use Tests\Support\TestCase;
use Throwable;

/**
 * @internal
 *
 * Coverage for Sprint 3 features:
 *   - Scheduled maintenance windows (--start / --end)
 *   - JSON response negotiation
 *   - Bypass routes whitelist
 *   - Auto secret-cookie issuance
 *   - --render / --redirect window overrides
 */
final class Sprint3FeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['setting', 'filesystem', 'cookie', 'text']);
        $this->nukeAllStorage();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset();
        Factories::reset('config');
        $this->nukeAllStorage();
    }

    private function makeConfig(array $overrides = []): Maintenance
    {
        $config                    = new Maintenance();
        $config->enableLogging     = false;
        $config->retryAfterSeconds = 3600;
        $config->allowSecretBypass = false;
        $config->secretBypassKey   = '';
        $config->useCache          = false;
        $config->driver            = 'file';
        $config->filePath          = WRITEPATH . 'maintenance/';

        foreach ($overrides as $property => $value) {
            $config->{$property} = $value;
        }

        return $config;
    }

    private function nukeAllStorage(): void
    {
        try {
            (new MaintenanceStorage($this->makeConfig(['driver' => 'file', 'useCache' => false])))->clearAll();
        } catch (Throwable) {
        }

        try {
            (new MaintenanceStorage($this->makeConfig(['driver' => 'cache', 'useCache' => true])))->clearAll();
        } catch (Throwable) {
        }
        $leftover = WRITEPATH . 'maintenance/down';
        if (file_exists($leftover)) {
            @unlink($leftover);
        }
    }

    private function mockRequest(
        array $get = [],
        string $ip = '203.0.113.99',
        array $cookies = [],
        string $path = '/',
        string $accept = '',
    ): IncomingRequest {
        $request = $this->createMock(IncomingRequest::class);
        $request->method('getGet')->willReturnCallback(static fn ($k) => $get[$k] ?? null);
        $request->method('getCookie')->willReturnCallback(static fn ($k) => $cookies[$k] ?? null);
        $request->method('getIPAddress')->willReturn($ip);
        $request->method('getHeaderLine')->willReturnCallback(
            static fn ($name) => strcasecmp($name, 'Accept') === 0 ? $accept : '',
        );

        $uri = new URI('http://localhost' . $path);
        $request->method('getUri')->willReturn($uri);
        $request->method('isSecure')->willReturn(false);

        Services::injectMock('request', $request);

        return $request;
    }

    // ========== S3.1 SCHEDULED WINDOWS ==========

    public function testWindowPendingBeforeStartLetsTrafficThrough(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        $start = time() + 600;
        $end   = $start + 3600;

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'         => 'Soon',
            'allowed_ips'     => [],
            'scheduled_start' => $start,
            'scheduled_end'   => $end,
        ])->toArray());

        $request = $this->mockRequest();
        $service = new MaintenanceService($config);

        $result = $service->check($request);
        $this->assertTrue($result->allowed);
        $this->assertSame('scheduled_pending', $result->reason);
    }

    public function testWindowExpiredAfterEndAutoDeactivates(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'         => 'Was earlier',
            'allowed_ips'     => [],
            'scheduled_start' => time() - 7200,
            'scheduled_end'   => time() - 60,
        ])->toArray());

        $service = new MaintenanceService($config);
        $this->assertTrue($service->isActive());

        $result = $service->check($this->mockRequest());
        $this->assertTrue($result->allowed);
        $this->assertSame('maintenance_inactive', $result->reason);

        // After the auto-deactivation, isActive() should return false.
        $this->assertFalse($service->isActive());
    }

    public function testActiveWindowWithinBoundsBehavesNormally(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'         => 'In progress',
            'allowed_ips'     => [],
            'scheduled_start' => time() - 60,
            'scheduled_end'   => time() + 3600,
        ])->toArray());

        $result = (new MaintenanceService($config))->check($this->mockRequest());
        $this->assertFalse($result->allowed);
        $this->assertSame('access_denied', $result->reason);
    }

    // ========== S3.2 JSON RESPONSE ==========

    public function testJsonAcceptHeaderTriggersJsonResponse(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        $service = new MaintenanceService($config);
        $request = $this->mockRequest(accept: 'application/json');

        $this->assertTrue($service->shouldRespondJson($request));
    }

    public function testJsonRoutesPatternTriggersJsonResponse(): void
    {
        $config = $this->makeConfig(['jsonRoutes' => ['/api/*']]);
        Factories::injectMock('config', 'Maintenance', $config);

        $service = new MaintenanceService($config);

        $this->assertTrue($service->shouldRespondJson(
            $this->mockRequest(path: '/api/users'),
        ));
        $this->assertFalse($service->shouldRespondJson(
            $this->mockRequest(path: '/dashboard'),
        ));
    }

    public function testJsonNegotiationOff(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        $service = new MaintenanceService($config);

        $this->assertFalse($service->shouldRespondJson(
            $this->mockRequest(accept: 'text/html'),
        ));
    }

    // ========== S3.3 BYPASS ROUTES ==========

    public function testBypassRouteSkipsMaintenance(): void
    {
        $config = $this->makeConfig(['bypassRoutes' => ['/health', '/api/webhooks/*']]);
        Factories::injectMock('config', 'Maintenance', $config);

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'     => 'Down',
            'allowed_ips' => [],
        ])->toArray());

        $service = new MaintenanceService($config);

        $this->assertSame(
            'bypass_route',
            $service->check($this->mockRequest(path: '/health'))->reason,
        );
        $this->assertSame(
            'bypass_route',
            $service->check($this->mockRequest(path: '/api/webhooks/stripe'))->reason,
        );
        $this->assertSame(
            'access_denied',
            $service->check($this->mockRequest(path: '/dashboard'))->reason,
        );
    }

    // ========== S3.4 AUTO SECRET-COOKIE ==========

    public function testSecretBypassReturnsCookieToSet(): void
    {
        $config = $this->makeConfig([
            'allowSecretBypass'     => true,
            'secretBypassKey'       => 'top-secret',
            'autoIssueBypassCookie' => true,
            'bypassCookieLifetime'  => 7200,
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'      => 'Working',
            'cookie_name'  => 'mm_pass',
            'cookie_value' => 'abcdef0123456789',
            'allowed_ips'  => [],
        ])->toArray());

        $service = new MaintenanceService($config);
        $result  = $service->check($this->mockRequest(get: ['maintenance_secret' => 'top-secret']));

        $this->assertTrue($result->allowed);
        $this->assertSame('bypassed_config_secret', $result->reason);
        $this->assertNotNull($result->setCookie);
        $this->assertSame('mm_pass', $result->setCookie['name']);
        $this->assertSame('abcdef0123456789', $result->setCookie['value']);
        $this->assertSame(7200, $result->setCookie['lifetime']);
    }

    public function testAutoCookieDisabledLeavesNoCookie(): void
    {
        $config = $this->makeConfig([
            'allowSecretBypass'     => true,
            'secretBypassKey'       => 'top-secret',
            'autoIssueBypassCookie' => false,
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'      => 'Working',
            'cookie_name'  => 'mm_pass',
            'cookie_value' => 'abcdef',
            'allowed_ips'  => [],
        ])->toArray());

        $result = (new MaintenanceService($config))
            ->check($this->mockRequest(get: ['maintenance_secret' => 'top-secret']));

        $this->assertTrue($result->allowed);
        $this->assertNull($result->setCookie);
    }

    public function testIpBypassDoesNotIssueAutoCookie(): void
    {
        // Auto-cookie only fires for secret bypass, not for IP allow-list,
        // because IP-based maintainers don't need a cookie to keep working.
        $config = $this->makeConfig(['autoIssueBypassCookie' => true]);
        Factories::injectMock('config', 'Maintenance', $config);

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'      => 'Working',
            'cookie_name'  => 'mm_pass',
            'cookie_value' => 'abcdef',
            'allowed_ips'  => ['10.0.0.0/8'],
        ])->toArray());

        $result = (new MaintenanceService($config))->check($this->mockRequest(ip: '10.5.5.5'));

        $this->assertTrue($result->allowed);
        $this->assertSame('bypassed_ip', $result->reason);
        $this->assertNull($result->setCookie);
    }

    // ========== S3.5 TEMPLATES PER ENVIRONMENT ==========

    public function testTemplateByEnvUsedWhenSet(): void
    {
        $config = $this->makeConfig([
            'templateByEnv'  => [ENVIRONMENT => 'errors/html/special_503'],
            'customTemplate' => 'errors/html/global_503',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        $tpl = (new MaintenanceService($config))->resolveTemplate(
            MaintenanceData::fromArray(['message' => 'x']),
        );
        $this->assertSame('errors/html/special_503', $tpl);
    }

    public function testWindowRenderTemplateOverridesEverything(): void
    {
        $config = $this->makeConfig([
            'templateByEnv'  => [ENVIRONMENT => 'errors/html/env_503'],
            'customTemplate' => 'errors/html/global_503',
        ]);
        Factories::injectMock('config', 'Maintenance', $config);

        $tpl = (new MaintenanceService($config))->resolveTemplate(
            MaintenanceData::fromArray(['message' => 'x', 'render_template' => 'errors/html/window_503']),
        );
        $this->assertSame('errors/html/window_503', $tpl);
    }

    public function testCustomTemplateUsedWhenNoEnvOverride(): void
    {
        $config = $this->makeConfig(['customTemplate' => 'errors/html/global_503']);
        Factories::injectMock('config', 'Maintenance', $config);

        $tpl = (new MaintenanceService($config))->resolveTemplate(
            MaintenanceData::fromArray(['message' => 'x']),
        );
        $this->assertSame('errors/html/global_503', $tpl);
    }

    // ========== S3.11 / S3.12 RENDER + REDIRECT VIA mm:down ==========

    public function testDownAcceptsRenderAndRedirectFlags(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Sprint 3" -ip "10.0.0.1" -render "errors/html/branded" -redirect "https://status.example.com"');

        $data = (new FileStorage($config))->getData();
        $this->assertNotNull($data);
        $this->assertSame('errors/html/branded', $data->render_template);
        $this->assertSame('https://status.example.com', $data->redirect_url);
    }

    public function testDownAcceptsScheduledStartAndEnd(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Scheduled" -ip "10.0.0.1" -start "+10 minutes" -end "+1 hour"');

        $data = (new FileStorage($config))->getData();
        $this->assertNotNull($data);
        $this->assertNotNull($data->scheduled_start);
        $this->assertNotNull($data->scheduled_end);
        $this->assertGreaterThan($data->scheduled_start, $data->scheduled_end);
    }

    public function testDownRejectsEndBeforeStart(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        command('mm:down -message "Bad window" -ip "10.0.0.1" -start "+1 hour" -end "+10 minutes"');

        // The command should bail before saving.
        $this->assertFalse((new FileStorage($config))->isActive());
    }

    // ========== S3 — REGRESSION on the controller redirect path ==========

    public function testControllerThrowsServiceUnavailableForBlockedHtmlRequest(): void
    {
        $config = $this->makeConfig();
        Factories::injectMock('config', 'Maintenance', $config);

        (new MaintenanceStorage($config))->save(MaintenanceData::fromArray([
            'message'     => 'HTML denied',
            'allowed_ips' => [],
        ])->toArray());

        $this->mockRequest(path: '/');

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('HTML denied');
        MaintenanceController::check();
    }
}
