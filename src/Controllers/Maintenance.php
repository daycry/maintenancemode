<?php

namespace Daycry\Maintenance\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Daycry\Maintenance\DTO\MaintenanceData;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Daycry\Maintenance\Services\CheckResult;
use Daycry\Maintenance\Services\MaintenanceService;

class Maintenance extends Controller
{
    /**
     * Static entry point used by the legacy filter (and by tests).
     *
     * Returns true when the request should be allowed through, or a
     * {@see ResponseInterface} when the package wants to short-circuit with a
     * redirect / JSON 503 response. Throws a
     * {@see ServiceUnavailableException} when maintenance is active and no
     * bypass matched and HTML rendering is requested.
     *
     * @return bool|ResponseInterface
     */
    public static function check()
    {
        // CLI is always allowed except in the testing environment, where we
        // simulate a real HTTP request to exercise the bypass logic.
        if (is_cli() && ENVIRONMENT !== 'testing') {
            return true;
        }

        $service = MaintenanceService::fromCurrentConfig();

        if (! $service->isActive()) {
            return true;
        }

        $request = Services::request();

        // The CLI short-circuit above guarantees we have an HTTP request here.
        if (! $request instanceof IncomingRequest) {
            return true;
        }

        $result = $service->check($request);

        if ($result->allowed) {
            self::applyAutoCookie($result);

            return true;
        }

        $data = $service->getData();

        // Per-window redirect short-circuits everything else.
        if ($data !== null && $data->redirect_url !== '') {
            return Services::response()
                ->redirect($data->redirect_url, 'auto', 302);
        }

        // JSON content negotiation.
        if ($service->shouldRespondJson($request)) {
            return self::buildJsonResponse($service, $data);
        }

        // HTML 503: set the Retry-After header and let the framework render the
        // exception template. resolveTemplate() decides which one.
        Services::response()->setHeader('Retry-After', (string) $service->getRetryAfterSeconds());

        $message = $data === null ? '' : $data->message;

        throw ServiceUnavailableException::forServerDown(
            $message !== '' ? $message : $service->getDefaultMessage(),
        );
    }

    /**
     * Persist the bypass cookie on the current response when the service asked
     * for it (Sprint 3 auto-cookie). The framework will flush it with the
     * outbound response.
     */
    private static function applyAutoCookie(CheckResult $result): void
    {
        if ($result->setCookie === null) {
            return;
        }

        Services::response()->setCookie([
            'name'     => $result->setCookie['name'],
            'value'    => $result->setCookie['value'],
            'expire'   => $result->setCookie['lifetime'],
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => self::isHttps(),
        ]);
    }

    private static function isHttps(): bool
    {
        $request = Services::request();

        if ($request instanceof IncomingRequest) {
            return $request->isSecure();
        }

        return false;
    }

    private static function buildJsonResponse(
        MaintenanceService $service,
        ?MaintenanceData $data,
    ): ResponseInterface {
        $message = $data === null ? '' : $data->message;
        if ($message === '') {
            $message = $service->getDefaultMessage();
        }

        $body = [
            'status'      => 503,
            'error'       => 'Service Unavailable',
            'message'     => $message,
            'retry_after' => $service->getRetryAfterSeconds(),
        ];

        if ($data !== null && $data->estimated_end !== null) {
            $body['estimated_end'] = gmdate('c', $data->estimated_end);
        }

        return Services::response()
            ->setStatusCode(503)
            ->setHeader('Retry-After', (string) $service->getRetryAfterSeconds())
            ->setJSON($body);
    }
}
