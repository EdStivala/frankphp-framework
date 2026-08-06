<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Core;

class Request
{
    public string $method;
    public string $path;
    public array $get;
    public array $post;
    public array $server;
    public array $headers;
    public string $rawBody;

    public array $routeParams = [];
    public ?array $bodyParams = null;
    public $tenant = null;
    public $user = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->headers = function_exists('getallheaders') ? getallheaders() : [];
        $this->rawBody = file_get_contents('php://input');
    }
    
    public function header(string $name, $default = null)
    {	
        return $this->headers[$name] ?? $this->headers[strtolower($name)] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        if ($this->bodyParams !== null && array_key_exists($key, $this->bodyParams)) return $this->bodyParams[$key];
        if (array_key_exists($key, $this->post)) return $this->post[$key];
        if (array_key_exists($key, $this->get)) return $this->get[$key];
        return $default;
    }
}
