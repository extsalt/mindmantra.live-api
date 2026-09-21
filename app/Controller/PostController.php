<?php

declare(strict_types=1);

namespace App\Controller;

class PostController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['posts' => []]);
    }
}
