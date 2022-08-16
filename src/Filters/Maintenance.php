<?php
namespace Daycry\Maintenance\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Maintenance implements FilterInterface
{
		/**
		 * This is implementation of Maintenance Mode class
		 *
		 * @param RequestInterface|\CodeIgniter\HTTP\IncomingRequest $request
		 *
		 * @return mixed
		 */
		public function before( RequestInterface $request, $arguments = null )
		{
			return \Daycry\Maintenance\Controllers\Maintenance::check();
		}

		//--------------------------------------------------------------------

		/**
		 * We don't have anything to do here.
		 *
		 * @param RequestInterface|\CodeIgniter\HTTP\IncomingRequest $request
		 * @param ResponseInterface|\CodeIgniter\HTTP\Response       $response
		 *
		 * @return mixed
		 */
		public function after( RequestInterface $request, ResponseInterface $response, $arguments = null )
		{
		}
}