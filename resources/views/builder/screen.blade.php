<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    @vite(['resources/js/app.js'])
    @if ($themeCssUrl = builder_theme_css_url())
      <link rel="stylesheet" href="{{ $themeCssUrl }}">
    @endif
  </head>
  <body>
    {!! $html !!}
  </body>
</html>
