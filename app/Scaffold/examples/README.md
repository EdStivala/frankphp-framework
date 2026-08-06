# FrankPHP example recipes

Each subfolder here is a self-contained, working "recipe" — copy the whole
folder into your app's `app/` directory, wire up its route(s)/container
binding(s) as noted in its own README, and adapt or delete it. None of
these are framework-owned; they exist to show a working pattern, not to
be permanent app features.

| Recipe | Demonstrates |
|---|---|
| [`organization/`](organization/README.md) | A tenant-scoped API resource controller using `Core\BaseApiController` and the Syncfusion DataManager paging/sort/filter pattern. |
| [`media/`](media/README.md) | Range-request media streaming (video seeking) via a plain PHP helper and controller. |
| [`form-presenter/`](form-presenter/README.md) | The `Core\BasePresenter` pattern — a presenter that renders form fields from a model's properties. |
| [`video-library/`](video-library/README.md) | Bootstrap modal partials for a video bookmark/library UI, plus starter CSS for a browsing grid. |
