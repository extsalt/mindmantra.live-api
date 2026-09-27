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

    // Future resource routes can be declared here, e.g.:
    // $routes->resource('users', ['controller' => 'UserController']);
});

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
