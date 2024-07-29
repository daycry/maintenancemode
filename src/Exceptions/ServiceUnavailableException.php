<?php

namespace Daycry\Maintenance\Exceptions;

use Daycry\Exceptions\Exceptions\RuntimeException;
use Daycry\Exceptions\Interfaces\BaseExceptionInterface;

class ServiceUnavailableException extends RuntimeException implements BaseExceptionInterface,\CodeIgniter\Exceptions\HTTPExceptionInterface
{
    /**
     * Error code
     *
     * @var int
     */
    protected $code = 503;

    public static function forServerDow(?string $message = null)
    {
        return new static($message ?? false);
    }
}
