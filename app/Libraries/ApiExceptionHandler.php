<?php

namespace App\Libraries;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * ApiExceptionHandler ensures all web/API exceptions and errors
 * are rendered as clean JSON structures, while delegating CLI
 * exceptions to the default handler for spark commands.
 */
class ApiExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        // Retain standard CLI error formatting for spark / terminal commands
        if ($request instanceof CLIRequest) {
            (new ExceptionHandler($this->config))->handle(
                $exception,
                $request,
                $response,
                $statusCode,
                $exitCode,
            );

            return;
        }

        try {
            $response->setStatusCode($statusCode);
        } catch (HTTPException) {
            $statusCode = 500;
            $response->setStatusCode($statusCode);
        }

        if (! headers_sent()) {
            header(
                sprintf(
                    'HTTP/%s %s %s',
                    $request->getProtocolVersion(),
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                ),
                true,
                $statusCode,
            );
        }

        $isDev = (ENVIRONMENT === 'development');

        $errorMessage = $exception->getMessage() ?: ($response->getReasonPhrase() ?: 'An error occurred');

        $payload = [
            'error' => $errorMessage,
        ];

        if ($isDev) {
            $payload['exception'] = [
                'type' => get_class($exception),
                'file' => $exception->getFile() . ':' . $exception->getLine(),
            ];
        }

        $response->setContentType('application/json')
            ->setBody((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->send();

        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }
}
