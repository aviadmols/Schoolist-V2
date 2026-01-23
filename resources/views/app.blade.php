@php
  $isDashboard = request()->is('dashboard*');
  $htmlLang = $isDashboard ? str_replace('_', '-', app()->getLocale()) : 'he';
  $htmlDir = $isDashboard ? 'ltr' : 'rtl';
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}" dir="{{ $htmlDir }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    @vite(['resources/js/app.js'])
    @inertiaHead
    @if ($themeCssUrl = builder_theme_css_url())
      <link rel="stylesheet" href="{{ $themeCssUrl }}">
    @endif
  </head>
  <body>
    @inertia
  </body>
</html>

