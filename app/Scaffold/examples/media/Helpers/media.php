<?php

/**
* Helpers/media.php
*
* EXAMPLE HELPERS — one part of the scaffold/examples/media/ recipe, for
* exercise thumbnail/video URLs and range-request media streaming — see
* this folder's README.md. Not a framework-owned file — safe to delete
* the whole media/ folder if you don't need this example.
*
* Loaded globally in bootstrap.php so functions are available everywhere.
*/

/**
* exerciseThumbnailUrl()
* Returns the proxy URL for a thumbnail image.
* The database stores bare filenames; this produces the routed URL.
*/
function exerciseThumbnailUrl(string $filename): string
{
	return '/media/exercise/thumbnails/' . urlencode(basename($filename));
}

/**
* exerciseVideoUrl()
* Returns the proxy URL for a video file.
*/
function exerciseVideoUrl(string $filename): string
{
	return '/media/exercise/videos/' . urlencode(basename($filename));
}

/**
* serve_media_file()
*
* Streams a file from an absolute path with full HTTP Range request support.
* Required for video so browsers can seek and start playback before the full
* file has downloaded.
*
* Called by MediaController@serve for video files.
*/
function serve_media_file(string $absolute_path): void
{
	// 1. Sanity checks
	if (!file_exists($absolute_path) || !is_readable($absolute_path)) {
		http_response_code(404);
		exit('File not found.');
	}

	$file_size = filesize($absolute_path);
	$mime      = mime_content_type($absolute_path) ?: 'application/octet-stream';

	// 2. Always advertise range support
	header('Accept-Ranges: bytes');
	header('Content-Type: ' . $mime);
	header('Cache-Control: public, max-age=86400');

	$start = 0;
	$end   = $file_size - 1;

	if (isset($_SERVER['HTTP_RANGE'])) {
		// e.g. bytes=0-1  or  bytes=1024-
		if (!preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
			http_response_code(416); // Range Not Satisfiable
			header('Content-Range: bytes */' . $file_size);
			exit();
		}

		$range_start = $matches[1] === '' ? null : (int) $matches[1];
		$range_end   = $matches[2] === '' ? null : (int) $matches[2];

		if ($range_start !== null) {
			$start = $range_start;
			$end   = $range_end ?? $file_size - 1;
		} else {
			// Suffix range: bytes=-500 means last 500 bytes
			$start = $file_size - $range_end;
			$end   = $file_size - 1;
		}

		// Validate range
		if ($start > $end || $end >= $file_size) {
			http_response_code(416);
			header('Content-Range: bytes */' . $file_size);
			exit();
		}

		http_response_code(206); // Partial Content
		header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $file_size));
	} else {
		http_response_code(200);
	}

	$length = $end - $start + 1;
	header('Content-Length: ' . $length);

	// 3. Stream the requested byte range in chunks
	$fp        = fopen($absolute_path, 'rb');
	fseek($fp, $start);

	$buffer    = 8192; // 8 KB chunks
	$remaining = $length;

	while (!feof($fp) && $remaining > 0) {
		$read = min($buffer, $remaining);
		echo fread($fp, $read);
		$remaining -= $read;
		flush();
	}

	fclose($fp);
	exit();
}