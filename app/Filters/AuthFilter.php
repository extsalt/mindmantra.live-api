<?php

namespace App\Filters;

use App\Libraries\AuthLibrary;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * AuthFilter ensures incoming requests carry a valid session token.
 */
class AuthFilter implements FilterInterface
{
    /**
     * Inspect request headers/cookies for valid user session token.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     * @return ResponseInterface|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = AuthLibrary::getUserFromRequest($request);

        if ($user === null) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'success' => false,
                    'error'   => 'Unauthorized',
                    'message' => 'A valid authentication token is required to access this resource.',
                ]);
        }

        // Expose user data on request object
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
