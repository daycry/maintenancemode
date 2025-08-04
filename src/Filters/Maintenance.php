<?php

namespace Daycry\Maintenance\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Maintenance\Controllers\Maintenance as MaintenanceController;

class Maintenance implements FilterInterface
{
    /**
     * This is implementation of Maintenance Mode class
     *
     * @param IncomingRequest|RequestInterface $request
     * @param mixed|null                       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        return MaintenanceController::check();
    }

    // --------------------------------------------------------------------

    /**
     * We don't have anything to do here.
     *
     * @param IncomingRequest|RequestInterface $request
     * @param Response|ResponseInterface       $response
     * @param mixed|null                       $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
