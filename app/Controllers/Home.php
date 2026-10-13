<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    /**
     * API root status and application metadata.
     */
    public function index(): ResponseInterface
    {
        return $this->respond([
            'name'        => 'MindMantra Live API',
            'version'     => '1.0.0',
            'environment' => ENVIRONMENT,
            'timestamp'   => date('c'),
        ], 200);
    }

    /**
     * Lightweight health check / ping endpoint.
     */
    public function ping(): ResponseInterface
    {
        return $this->respond([
            'message'   => 'pong',
            'timestamp' => microtime(true),
        ], 200);
    }

    /**
     * Standard JSON 404 response for unknown endpoints.
     */
    public function notFound(): ResponseInterface
    {
        return $this->respond([
            'error' => 'Endpoint not found',
        ], 404);
    }
}
