<?php
namespace Daycry\Maintenance\Exceptions;

class ServiceUnavailableException extends \RuntimeException implements ExceptionInterface
{
	/**
	 * Error code
	 *
	 * @var integer
	 */
	protected $code = 503;

	public static function forServerDow( string $message = null )
	{
		return new static( $message ?? false );
	}
}