<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Core;

class Response
{
    public static function json($data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function view(string $viewPath, array $params = [])
    {
        extract($params, EXTR_SKIP);
        require $viewPath;
    }

    public static function redirect(string $url)
    {
        header('Location: ' . $url);
        exit;
    }
}
