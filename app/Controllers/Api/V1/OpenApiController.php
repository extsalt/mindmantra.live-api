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
        $decoded     = json_decode((string) $specContent, true);

        return $this->respond($decoded ?: [], 200);
    }
}
