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
            'status'      => 200,
            'success'     => true,
            'name'        => 'MindMantra Live API',
            'version'     => '1.0.0',
            'environment' => ENVIRONMENT,
            'timestamp'   => date('c'),
        ]);
    }

    /**
     * Lightweight health check / ping endpoint.
     */
    public function ping(): ResponseInterface
    {
        return $this->respond([
            'status'    => 200,
            'success'   => true,
            'message'   => 'pong',
            'timestamp' => microtime(true),
        ]);
    }
}
