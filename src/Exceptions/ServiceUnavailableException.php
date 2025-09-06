<?php
namespace Daycry\Maintenance\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use CodeIgniter\Exceptions\RuntimeException;

class ServiceUnavailableException extends RuntimeException implements HTTPExceptionInterface
{
    /**
     * Error code
     *
     * @var int
     */
    protected $code = 503;

    /**
     * Create a new ServiceUnavailableException for server down status
     *
     * @param string|null $message Custom message for the maintenance mode
     *
     * @return static
     */
    public static function forServerDown(?string $message = null)
    {
        return new static($message ?? 'Service temporarily unavailable due to maintenance.');
    }
}
