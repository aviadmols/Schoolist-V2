<!DOCTYPE html>
<html lang="he" dir="rtl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Builder screens (classroom etc.) are server-rendered + inline template JS; no Vue/Inertia bundle needed - improves LCP and avoids app.js errors --}}
    <link rel="stylesheet" href="{{ asset('polin.css') }}">
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
      (function () {
        var screen = document.querySelector('[data-loading-screen]');
        if (!screen) return;
        function hideLoader() {
          screen.classList.add('is-hidden');
          setTimeout(function () { screen.remove(); }, 250);
        }
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', function () { setTimeout(hideLoader, 120); });
        } else {
          setTimeout(hideLoader, 50);
        }
      })();
    </script>
  </body>
</html>
