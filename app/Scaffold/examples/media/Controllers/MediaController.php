<?php

declare(strict_types=1);

/**
 * EXAMPLE CONTROLLER — one part of the scaffold/examples/media/ recipe,
 * demonstrating range-request media streaming via the Helpers/media.php
 * helper functions in this same folder — see this folder's README.md.
 * Safe to delete the whole media/ folder if you don't need this example.
 */

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Request;

class MediaController extends BaseController
{
    private const ALLOWED_TYPES = ['thumbnails', 'videos'];

    /**
     * Streams a bare filename from storage/media/exercise/{type}/, with
     * HTTP Range support via the framework's serve_media_file() helper.
     * $type is checked against an allowlist and $filename is reduced to
     * its basename before touching the filesystem — the two safeguards
     * against path traversal for an endpoint that takes both from the URL.
     */
    public function serve(Request $request, array $params): void
    {
        $type     = $params['type'] ?? '';
        $filename = basename($params['filename'] ?? '');

        if (!in_array($type, self::ALLOWED_TYPES, true) || $filename === '') {
            http_response_code(404);
            exit('File not found.');
        }

        $absolutePath = APP_BASE_DIR . '/storage/media/exercise/' . $type . '/' . $filename;

        serve_media_file($absolutePath);
    }
}
