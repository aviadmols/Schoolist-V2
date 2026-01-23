<!DOCTYPE html>
<html lang="he" dir="rtl">
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
    <style>
      .sb-loading-screen {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        z-index: 9999;
        transition: opacity 200ms ease;
      }

      .sb-loading-screen.is-hidden {
        opacity: 0;
        pointer-events: none;
      }

      .sb-loading-spinner {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        border: 3px solid rgba(0, 0, 0, 0.08);
        border-top-color: rgba(0, 0, 0, 0.4);
        animation: sb-spin 0.9s linear infinite;
      }

      @keyframes sb-spin {
        to { transform: rotate(360deg); }
      }
    </style>
  </head>
  <body>
    <div class="sb-loading-screen" data-loading-screen>
      <div class="sb-loading-spinner" aria-hidden="true"></div>
    </div>
    {!! $html !!}
    @if (!empty($js))
      <script>
        {!! $js !!}
      </script>
    @endif
    <script>
      window.addEventListener('load', function () {
        var screen = document.querySelector('[data-loading-screen]');
        if (!screen) return;
        screen.classList.add('is-hidden');
        setTimeout(function () { screen.remove(); }, 250);
      });
    </script>
  </body>
</html>
