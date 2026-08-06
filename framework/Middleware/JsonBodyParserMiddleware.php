<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
**/

namespace Frank\Middleware;

use Frank\Core\MiddlewareInterface;
use Frank\Core\Request;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next)
    {
        $raw = $request->rawBody;
        if (!empty($raw)) {
            $data = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->bodyParams = $data;
            }
        }
        // also populate from $_POST for form submissions
        if ($request->bodyParams === null) {
            $request->bodyParams = $_POST;
        }
        return $next($request);
    }
}
