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
        $routes->post('signup', 'Api\V1\AuthController::signup');
        $routes->post('logout', 'Api\V1\AuthController::logout');
    });

    // Course Endpoints
    $routes->get('courses', 'Api\V1\CourseController::index');
    $routes->get('courses/(:segment)/mcqs', 'Api\V1\CourseController::mcqs/$1');
    $routes->get('courses/(:segment)', 'Api\V1\CourseController::show/$1');

    // Notification & Push Notification Endpoints
    $routes->group('notifications', static function ($routes) {
        // In-app notifications
        $routes->get('/', 'Api\V1\NotificationController::index');
        $routes->get('unread-count', 'Api\V1\NotificationController::unreadCount');
        $routes->get('(:num)', 'Api\V1\NotificationController::show/$1');
        $routes->post('(:num)/read', 'Api\V1\NotificationController::markAsRead/$1');
        $routes->put('(:num)/read', 'Api\V1\NotificationController::markAsRead/$1');
        $routes->post('read-all', 'Api\V1\NotificationController::markAllAsRead');
        $routes->put('read-all', 'Api\V1\NotificationController::markAllAsRead');
        $routes->delete('(:num)', 'Api\V1\NotificationController::delete/$1');

        // Device token management for push notifications
        $routes->post('devices/register', 'Api\V1\NotificationController::registerDevice');
        $routes->post('devices/unregister', 'Api\V1\NotificationController::unregisterDevice');

        // Push dispatching endpoints
        $routes->post('send', 'Api\V1\NotificationController::send');
        $routes->post('broadcast', 'Api\V1\NotificationController::broadcast');
        $routes->post('test-push', 'Api\V1\NotificationController::testPush');
    });

    // OpenAPI Specification Endpoint
    $routes->get('openapi.json', 'Api\V1\OpenApiController::index');
});


// Convenient / backward-compatible aliases
$routes->post('api/auth/login', 'Api\V1\AuthController::login');
$routes->post('auth/login', 'Api\V1\AuthController::login');
$routes->post('login', 'Api\V1\AuthController::login');
$routes->post('api/auth/signup', 'Api\V1\AuthController::signup');
$routes->post('auth/signup', 'Api\V1\AuthController::signup');
$routes->post('signup', 'Api\V1\AuthController::signup');
$routes->post('api/auth/logout', 'Api\V1\AuthController::logout');
$routes->post('auth/logout', 'Api\V1\AuthController::logout');
$routes->post('logout', 'Api\V1\AuthController::logout');
$routes->get('courses', 'Api\V1\CourseController::index');
$routes->get('courses/(:segment)/mcqs', 'Api\V1\CourseController::mcqs/$1');
$routes->get('courses/(:segment)', 'Api\V1\CourseController::show/$1');
$routes->get('openapi.json', 'Api\V1\OpenApiController::index');

// JSON 404 Override for unknown endpoints
$routes->set404Override('App\Controllers\Home::notFound');
