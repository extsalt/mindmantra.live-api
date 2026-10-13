<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * OpenApiController serves the OpenAPI 3.0 specification for LLMs and API consumers.
 */
class OpenApiController extends BaseController
{
    /**
     * Return OpenAPI 3.0 specification in JSON format.
     */
    public function index(): ResponseInterface
    {
        $specPath = FCPATH . 'openapi.json';

        if (! is_file($specPath)) {
            return $this->respond([
                'status'   => 404,
                'success'  => false,
                'message'  => 'OpenAPI specification not found.',
            ], 404);
        }

        $specContent = file_get_contents($specPath);

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setBody((string) $specContent);
    }
}
