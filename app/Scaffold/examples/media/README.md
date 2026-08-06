# Recipe: range-request media streaming

Demonstrates HTTP Range support (the mechanism browsers need to seek
within a video, or resume a partial download) via a plain PHP helper —
no framework dependency, works with any file on disk.

## Files

```
Controllers/MediaController.php
Helpers/media.php
storage/media/exercise/thumbnails/   (put your thumbnail files here)
storage/media/exercise/videos/       (put your video files here)
```

## To adopt

1. Copy `Controllers/`, `Helpers/`, and `storage/` into your app.
2. Require the helper file from your app's bootstrap so its functions
   are globally available:

   ```php
   require_once APP_BASE_DIR . '/app/Helpers/media.php';
   ```

3. Register the route (no container binding needed):

   ```php
   $router->add(
       'GET',
       '/media/exercise/{type}/{filename}',
       'App\\Controllers\\MediaController@serve',
       [$auth]
   );
   ```

4. In a view, build URLs with the helper functions rather than
   hand-writing the path:

   ```php
   <img src="<?= exerciseThumbnailUrl($row['thumbnail_filename']) ?>">
   <video src="<?= exerciseVideoUrl($row['video_filename']) ?>" controls></video>
   ```

## Notes

`$type` is checked against an allowlist (`thumbnails`, `videos`) and
`$filename` is reduced to its basename before touching the filesystem —
the two safeguards against path traversal for an endpoint that takes
both from the URL. Extend `MediaController::ALLOWED_TYPES` if you add
more media categories.
