<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
*/

namespace Frank\Core;

class BaseController
{
    /**
     * Resolves $name against APP_VIEWS_DIR — always the app's own copied
     * Views/, regardless of whether the calling controller is
     * framework-owned (Frank\Controllers\*) or app-owned (App\Controllers\*).
     * Views are seeded into app/Views/ once, at install time, from the
     * framework's own default Views/ — they are never read live from the
     * framework/submodule at request time. This is what makes "nothing in
     * app/ ever gets updated" true: once installed, a project's views are
     * entirely its own, permanently disconnected from future framework
     * upgrades.
     */
    protected function view(string $name, array $params = [])
    {
        $viewPath = APP_VIEWS_DIR . '/' . $name . '.php';
        if (!file_exists($viewPath)) throw new \RuntimeException("View not found: $viewPath");
        return Response::view($viewPath, $params);
    }
}
