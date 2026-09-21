<?php

declare(strict_types=1);

namespace App\Controller;

class HomeController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => 'API ready']);
    }

    public function health(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok']);
    }
}
