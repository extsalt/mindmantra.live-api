<?php

use App\Controller\HomeController;

$app->route('GET /', [HomeController::class, 'index']);
$app->route('GET /health', [HomeController::class, 'health']);
