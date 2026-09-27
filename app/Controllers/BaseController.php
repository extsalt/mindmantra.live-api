<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a streamlined base class for API controllers,
 * equipped with CodeIgniter's ResponseTrait for standard JSON responses,
 * input parsers, and standardized response helpers.
 */
abstract class BaseController extends Controller
{
    use ResponseTrait;

    /**
     * Default response format for API controllers.
     *
     * @var string
     */
    protected $format = 'json';

    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do not edit this line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc., here.
    }

    /**
     * Retrieve input data regardless of Content-Type (JSON payload, form-data, or query string).
     */
    protected function getRequestInput(?string $key = null, mixed $default = null): mixed
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            if ($key === null) {
                return $json;
            }
            return $json[$key] ?? $default;
        }

        $rawInput = $this->request->getRawInput();
        if (is_array($rawInput) && ! empty($rawInput)) {
            if ($key === null) {
                return $rawInput;
            }
            return $rawInput[$key] ?? $default;
        }

        return $this->request->getVar($key) ?? $default;
    }

    /**
     * Standardized success response helper.
     */
    protected function sendResponse(mixed $data = null, string $message = 'Success', int $status = 200, array $headers = []): ResponseInterface
    {
        $response = [
            'status'  => $status,
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        $res = $this->respond($response, $status);
        foreach ($headers as $header => $value) {
            $res->setHeader($header, $value);
        }

        return $res;
    }

    /**
     * Standardized error response helper.
     */
    protected function sendError(string|array $error, int $status = 400, mixed $data = null): ResponseInterface
    {
        $response = [
            'status'   => $status,
            'success'  => false,
            'messages' => is_array($error) ? $error : ['error' => $error],
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $this->respond($response, $status);
    }
}
