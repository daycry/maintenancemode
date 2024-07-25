<?php

namespace Daycry\Maintenance\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Maintenance\Controllers\Maintenance as MaintenanceController;

class Maintenance implements FilterInterface
{
    /**
     * This is implementation of Maintenance Mode class
     *
     * @param \CodeIgniter\HTTP\IncomingRequest|RequestInterface $request
     * @param mixed|null                                         $arguments
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
     * @param \CodeIgniter\HTTP\IncomingRequest|RequestInterface $request
     * @param \CodeIgniter\HTTP\Response|ResponseInterface       $response
     * @param mixed|null                                         $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
