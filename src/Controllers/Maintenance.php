<?php

namespace Daycry\Maintenance\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\ExceptionInterface;
use Config\Services;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;
use Daycry\Maintenance\Libraries\IpUtils;
use Daycry\Maintenance\Libraries\MaintenanceStorage;

class Maintenance extends Controller
{
    public static function check()
    {
        // if request is from CLI
        if (is_cli() && ENVIRONMENT !== 'testing') {
            return true;
        }

        $config  = config('Maintenance');
        $storage = new MaintenanceStorage($config);

        // Check if maintenance mode is active
        if (! $storage->isActive()) {
            return true;
        }

        try {
            // Get maintenance data
            $data = $storage->getData();

            if ($data === null) {
                // Invalid data, log error and allow access
                if ($config->enableLogging) {
                    log_message('error', 'Maintenance mode data is invalid or corrupted');
                }

                return true;
            }

            // Check for secret bypass via URL parameter
            if ($config->allowSecretBypass && ! empty($config->secretBypassKey)) {
                $request = Services::request();
                if ($request->getGet('maintenance_secret') === $config->secretBypassKey) {
                    if ($config->enableLogging) {
                        log_message('info', 'Maintenance mode bypassed via secret key from IP: ' . $request->getIPAddress());
                    }

                    return true;
                }
            }

            // Check bypass via secret from maintenance data
            if (isset($data->secret_bypass) && $data->secret_bypass && isset($data->secret_key)) {
                $request = Services::request();
                if ($request->getGet('maintenance_secret') === $data->secret_key) {
                    if ($config->enableLogging) {
                        log_message('info', 'Maintenance mode bypassed via data secret key from IP: ' . $request->getIPAddress());
                    }

                    return true;
                }
            }

            // if request ip was entered in allowed_ips
            // the app should continue running
            $lib      = new IpUtils();
            $clientIp = Services::request()->getIPAddress();

            if (isset($data->allowed_ips) && $lib->checkIp($clientIp, $data->allowed_ips)) {
                if ($config->enableLogging) {
                    log_message('info', 'Maintenance mode bypassed for allowed IP: ' . $clientIp);
                }

                return true;
            }

            // if user's browser has been used the cookie pass
            // the app should continue running
            helper('cookie');
            $cookieName = get_cookie($data->cookie_name ?? '');

            if (! empty($data->cookie_name) && $cookieName === $data->cookie_name) {
                if ($config->enableLogging) {
                    log_message('info', 'Maintenance mode bypassed via cookie for IP: ' . $clientIp);
                }

                // @codeCoverageIgnoreStart
                return true;
                // @codeCoverageIgnoreEnd
            }

            // Log maintenance mode access attempt
            if ($config->enableLogging) {
                log_message('info', 'Maintenance mode blocking access from IP: ' . $clientIp);
            }

            // Set Retry-After header
            $response = Services::response();
            $response->setHeader('Retry-After', (string) $config->retryAfterSeconds);

            throw ServiceUnavailableException::forServerDown($data->message ?? $config->defaultMessage);
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (ExceptionInterface $e) {
            // Log any unexpected errors
            if ($config->enableLogging) {
                log_message('error', 'Unexpected error in maintenance mode check: ' . $e->getMessage());
            }

            // In case of error, allow access to prevent site lockout
            return true;
        }
    }
}
