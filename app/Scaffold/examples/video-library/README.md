# Recipe: video bookmark/library modals

Bootstrap modal markup for adding/editing/deleting a bookmarked video or
a library video, plus a YouTube-embed player modal and starter CSS for a
browsing grid.

## Files

```
Views/modals/videos/modal-add-bookmark.php
Views/modals/videos/modal-edit-bookmark.php
Views/modals/videos/modal-delete-bookmark.php
Views/modals/videos/modal-add-library-video.php
Views/modals/videos/modal-edit-library-video.php
Views/modals/videos/modal-delete-library-video.php
Views/modals/videos/modal-video-player.php
public/assets/css/app-exercises.css   (styles the player modal)
public/assets/css/app-workouts.css    (styles a browsing grid — see below)
```

## To adopt

1. Copy `Views/` and `public/` into your app.
2. Include the CSS via the framework layout's `headExtra` slot, from
   whichever controller renders the page that uses these modals:

   ```php
   return $this->view('videos/library', [
       // ...
       'headExtra' => ['/assets/css/app-exercises.css', '/assets/css/app-workouts.css'],
   ]);
   ```

3. `include` the modal partials from that same page, and wire up the
   "Add"/"Edit"/"Delete" buttons and the video player's JS to open them.

## Important — this is markup only

Unlike the other three recipes, **no controller, model, database table,
or route ships with this one**. The modals assume forms named
`youtube_url`, `title`, `genre`, etc. — you'll need to build the backing
model/controller/table yourself to actually save what they submit, and a
browsing page to host `app-workouts.css`'s `.workout-grid`/`.workout-card`
classes. Treat this recipe as UI scaffolding, not a complete feature.
