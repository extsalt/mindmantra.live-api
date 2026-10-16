<?php

namespace App\Filters;

use App\Libraries\AuthLibrary;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * OptionalAuthFilter attempts to resolve user session without blocking unauthenticated requests.
 */
class OptionalAuthFilter implements FilterInterface
{
    /**
     * @param RequestInterface $request
     * @param array|null       $arguments
     * @return void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = AuthLibrary::getUserFromRequest($request);
        $request->user = $user;
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
