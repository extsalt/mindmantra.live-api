<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Health and information endpoints
$routes->get('/', 'Home::index');
$routes->get('health', 'Home::index');
$routes->get('ping', 'Home::ping');

// API Version 1 Routes
$routes->group('api/v1', static function ($routes) {
    $routes->get('/', 'Home::index');
    $routes->get('health', 'Home::index');
    $routes->get('ping', 'Home::ping');

    // Auth Endpoints
    $routes->group('auth', static function ($routes) {
        $routes->post('login', 'Api\V1\AuthController::login');
    });
});

// Convenient / backward-compatible aliases
$routes->post('api/auth/login', 'Api\V1\AuthController::login');
$routes->post('auth/login', 'Api\V1\AuthController::login');
$routes->post('login', 'Api\V1\AuthController::login');

// JSON 404 Override for unknown endpoints
$routes->set404Override(static function () {
    return response()->setStatusCode(404)->setJSON([
        'status'   => 404,
        'success'  => false,
        'error'    => 404,
        'messages' => [
            'error' => 'Endpoint not found',
        ],
    ]);
});
