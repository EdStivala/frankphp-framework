# Recipe: form-field presenter

Demonstrates `Core\BasePresenter` (a framework primitive — stays in
`Core/`, not part of this recipe) via a concrete presenter that renders
Syncfusion-flavoured form fields from a model's properties.

## Files

```
Presenters/FormPresenter.php
```

## To adopt

1. Copy `Presenters/` into your app.
2. Instantiate it with any object that has the properties you want to
   render, then call its field methods directly in a view:

   ```php
   $presenter = new \App\Presenters\FormPresenter($someModel);

   echo $presenter->tags('genres', $genreOptions);
   echo $presenter->date('starts_at');
   echo $presenter->currency('price', 'GBP');
   ```

No route or container binding is needed — this is a plain class you
construct where you need it, not a framework service.

## Notes

`BasePresenter::__get()` falls back to reading the wrapped model's own
property when the presenter itself has no matching method — so
`$presenter->name` works even though `FormPresenter` never defines a
`name` property or method.
