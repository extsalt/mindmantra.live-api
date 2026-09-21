<?php

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/vendor/autoload.php';

$app = Flight::app();
$app->set('flight.base_url', '/');
$app->set('flight.case_sensitive', false);
$app->set('flight.log_errors', true);
$app->set('flight.handle_errors', false);

require __DIR__ . '/routes.php';
$app->start();
