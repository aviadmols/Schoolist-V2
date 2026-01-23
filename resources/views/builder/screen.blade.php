<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    @vite(['resources/js/app.js'])
    @if ($themeCssUrl = builder_theme_css_url())
      <link rel="stylesheet" href="{{ $themeCssUrl }}">
    @endif
    @if (!empty($css))
      <style>
        {!! $css !!}
      </style>
    @endif
  </head>
  <body>
    {!! $html !!}
    @if (!empty($js))
      <script>
        {!! $js !!}
      </script>
    @endif
  </body>
</html>
