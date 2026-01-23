<?php

namespace App\Services\Builder;

use App\Models\BuilderTemplate;
use App\Models\BuilderTemplateVersion;
use Illuminate\Support\Str;

class TemplateManager
{
    /** @var string */
    private const POPUP_TYPE = BuilderTemplate::TYPE_SECTION;

    /** @var string */
    private const SCREEN_TYPE = BuilderTemplate::TYPE_SCREEN;

    /**
     * Validate template HTML for safety.
     */
    public function assertTemplateIsSafe(string $html, ?string $css = null, ?string $js = null): void
    {
        /** @var TemplateRenderer $renderer */
        $renderer = app(TemplateRenderer::class);

        if (!$renderer->isTemplateSafe($html, $css, $js)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'draft_html' => 'Unsafe PHP execution is not allowed.',
            ]);
        }
    }

    /**
     * Ensure required global templates exist.
     */
    public function ensureDefaultTemplates(): void
    {
        $defaultKeys = (array) config('builder.allowed_keys', []);

        foreach ($defaultKeys as $key) {
            $this->createOrUpdateDefaultTemplate($key, $this->getDefaultHtmlForKey($key), self::SCREEN_TYPE);
        }

        $this->ensureDefaultPopups();
    }

    /**
     * Ensure default popup templates exist.
     */
    private function ensureDefaultPopups(): void
    {
        $popups = (array) config('builder.default_popups', []);

        foreach ($popups as $popup) {
            $popupKey = (string) ($popup['key'] ?? '');
            $title = (string) ($popup['title'] ?? '');

            if (!$popupKey) {
                continue;
            }

            $fullKey = $this->resolvePopupKey($popupKey);
            $name = $title ?: Str::title(str_replace('-', ' ', $popupKey));
            $html = $this->getDefaultPopupHtml($name, $fullKey);

            $this->createOrUpdateDefaultTemplate($fullKey, $html, self::POPUP_TYPE, $name);
        }
    }

    /**
     * Create a popup template under the configured prefix.
     */
    public function createPopupTemplate(string $name): BuilderTemplate
    {
        $prefix = (string) config('builder.popup_prefix');
        $key = $prefix.Str::slug($name);

        return $this->createOrUpdateDefaultTemplate(
            $key,
            $this->getDefaultPopupHtml($name, $key),
            self::POPUP_TYPE,
            $name
        );
    }

    /**
     * Publish the draft HTML and record a version.
     */
    public function publishTemplate(BuilderTemplate $template): BuilderTemplate
    {
        $draftHtml = (string) ($template->draft_html ?? '');
        $draftCss = $template->draft_css;
        $draftJs = $template->draft_js;

        $template->update([
            'published_html' => $draftHtml,
            'published_css' => $draftCss,
            'published_js' => $draftJs,
            'updated_by' => auth()->id(),
        ]);

        BuilderTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_type' => BuilderTemplateVersion::VERSION_PUBLISHED,
            'html' => $draftHtml,
            'css' => $draftCss,
            'js' => $draftJs,
            'created_by' => auth()->id(),
        ]);

        return $template;
    }

    /**
     * Revert draft HTML to a previous version.
     */
    public function revertTemplateToVersion(
        BuilderTemplate $template,
        BuilderTemplateVersion $version,
        bool $publishAfterRevert
    ): BuilderTemplate {
        $template->update([
            'draft_html' => $version->html,
            'draft_css' => $version->css,
            'draft_js' => $version->js,
            'updated_by' => auth()->id(),
        ]);

        BuilderTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version_type' => BuilderTemplateVersion::VERSION_DRAFT,
            'html' => $version->html,
            'css' => $version->css,
            'js' => $version->js,
            'created_by' => auth()->id(),
        ]);

        if ($publishAfterRevert) {
            return $this->publishTemplate($template);
        }

        return $template;
    }

    /**
     * Create or update a default template.
     */
    private function createOrUpdateDefaultTemplate(
        string $key,
        string $defaultHtml,
        string $type,
        ?string $nameOverride = null
    ): BuilderTemplate {
        $name = $nameOverride ?: Str::title(str_replace('.', ' ', $key));
        $parts = $this->splitTemplateParts($defaultHtml);

        $template = BuilderTemplate::query()->firstOrCreate(
            ['scope' => config('builder.scope'), 'key' => $key],
            [
                'name' => $name,
                'type' => $type,
                'draft_html' => $parts['html'],
                'draft_css' => $parts['css'],
                'draft_js' => $parts['js'],
                'published_html' => null,
                'published_css' => null,
                'published_js' => null,
                'is_override_enabled' => false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        if (($this->shouldSeedTemplate($template) || $this->shouldReplaceTemplateDraft($template, $key)) && $defaultHtml) {
            $template->update([
                'draft_html' => $parts['html'],
                'draft_css' => $parts['css'],
                'draft_js' => $parts['js'],
                'updated_by' => auth()->id(),
            ]);
        }

        return $template;
    }

    /**
     * Build default HTML for a known key.
     */
    private function getDefaultHtmlForKey(string $key): string
    {
        if ($key === 'classroom.page') {
            return $this->getDefaultClassroomPageHtml();
        }

        if ($key === 'auth.login') {
            return $this->getDefaultLoginPageHtml();
        }

        if ($key === 'auth.qlink') {
            return $this->getDefaultQlinkPageHtml();
        }

        return '';
    }

    /**
     * Build the default classroom page HTML.
     */
    private function getDefaultClassroomPageHtml(): string
    {
        return <<<'HTML'
<style>
  .sb-popup-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); opacity: 0; pointer-events: none; transition: opacity 200ms ease; z-index: 40; }
  .sb-popup-backdrop.is-open { opacity: 1; pointer-events: auto; }
  .sb-popup { position: fixed; inset: 0; display: flex; align-items: flex-end; justify-content: center; padding: 16px; opacity: 0; pointer-events: none; transition: opacity 200ms ease; z-index: 50; }
  .sb-popup.is-open { opacity: 1; pointer-events: auto; }
  .sb-popup-card { width: 100%; max-width: 420px; background: #ffffff; border-radius: 24px 24px 0 0; padding: 20px; transform: translateY(40px); transition: transform 240ms ease; }
  .sb-popup.is-open .sb-popup-card { transform: translateY(0); }
</style>

<div class="mobile-wrapper">
  <header class="header">
    <div class="header-actions">
      <button type="button" class="icon-btn" data-popup-target="popup-contacts">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
      </button>
      <button type="button" class="icon-btn" data-popup-target="popup-invite">
        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
      </button>
    </div>

    <div class="header-info">
      <div class="school-text">
        <span class="school-year">{{ $page['school_year'] ?? '' }}</span>
        <span class="school-name">{{ $page['classroom']['school_name'] ?? ($page['classroom']['name'] ?? '') }}</span>
      </div>
      <div class="class-badge">{{ $page['classroom']['grade_level'] ?? '' }}{{ $page['classroom']['grade_number'] ?? '' }}</div>
    </div>
  </header>

  <div class="day-tabs-container">
    @foreach (($page['day_labels'] ?? ['א','ב','ג','ד','ה','ו','ש']) as $dayIndex => $dayLabel)
      <button
        type="button"
        class="day-tab {{ (int) ($page['selected_day'] ?? 0) === $dayIndex ? 'active' : '' }}"
        data-popup-target="popup-schedule"
      >
        {{ $dayLabel }}
      </button>
    @endforeach
  </div>

  <div class="card card-stacked-top">
    <div class="card-header">
      <svg class="icon-edit-small" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
      <div class="card-title">יום {{ ($page['day_labels'] ?? ['א','ב','ג','ד','ה','ו','ש'])[(int) ($page['selected_day'] ?? 0)] ?? '' }} <span class="card-title-light">בוקר טוב!</span></div>
    </div>

    <div class="schedule-list">
      @php
        $dayIndex = (int) ($page['selected_day'] ?? 0);
        $dayEntries = $page['timetable'][$dayIndex] ?? [];
      @endphp
      @if (!empty($dayEntries))
        @foreach ($dayEntries as $entry)
          <div class="schedule-row">
            <span class="schedule-time">{{ $entry['start_time'] ?? '' }}-{{ $entry['end_time'] ?? '' }}</span>
            <span class="schedule-subject">{{ $entry['subject'] ?? '' }}</span>
          </div>
        @endforeach
      @else
        <div class="schedule-row">
          <span class="schedule-time">08:00-09:00</span>
          <span class="schedule-subject">---</span>
        </div>
      @endif
    </div>
  </div>

  <div class="card" style="padding: 10px 20px; display: flex; align-items: center; justify-content: space-between;">
    <div class="weather-text">
      {{ $page['weather_text'] ?? '16-20° - מזג אוויר נוח.' }}
    </div>
    <svg class="weather-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
  </div>

  <div class="card" style="padding-bottom: 70px;">
    <div class="card-header">
      <svg class="icon-edit-small" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
      <div class="card-title">הודעות</div>
    </div>

    <div class="notices-list">
      @if (!empty($page['announcements']))
        @foreach ($page['announcements'] as $announcement)
          <div class="notice-row">
            <svg class="check-icon {{ !empty($announcement['is_done']) ? 'check-blue' : 'check-black' }}" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span class="notice-content">{{ $announcement['title'] ?? ($announcement['content'] ?? '') }}</span>
          </div>
        @endforeach
      @else
        <div class="notice-row">
          <div style="width:20px;"></div>
          <span class="notice-content">אין הודעות כרגע</span>
        </div>
      @endif
    </div>

    <div class="fab-btn" data-popup-target="popup-homework">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <svg class="icon-edit-small" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
      <div class="card-title">אירועים</div>
    </div>

    <div class="section-label">היום</div>
    @if (!empty($page['events_today']))
      @foreach ($page['events_today'] as $event)
        <div class="event-item">
          <div class="event-indicator-dot dot-blue"></div>
          <div class="event-details">
            <div class="event-title">{{ $event['title'] ?? '' }}</div>
            <div class="event-meta">
              <span>{{ $event['date'] ?? '' }}</span>
              @if (!empty($event['time']))
                <span>{{ $event['time'] }}</span>
              @endif
              @if (!empty($event['location']))
                <span>{{ $event['location'] }}</span>
              @endif
              <svg class="event-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
          </div>
        </div>
      @endforeach
    @else
      <div class="event-item">
        <div class="event-details">
          <div class="event-title">אין אירועים להיום</div>
        </div>
      </div>
    @endif

    <div class="section-label">השבוע</div>
    @if (!empty($page['events_week']))
      @foreach ($page['events_week'] as $event)
        <div class="event-item">
          <div class="event-indicator-dot dot-purple"></div>
          <div class="event-details">
            <div class="event-title">{{ $event['title'] ?? '' }}</div>
            <div class="event-meta">
              <span>{{ $event['date'] ?? '' }}</span>
              @if (!empty($event['time']))
                <span>{{ $event['time'] }}</span>
              @endif
              @if (!empty($event['location']))
                <span>{{ $event['location'] }}</span>
              @endif
              <svg class="event-icon-small" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
          </div>
        </div>
      @endforeach
    @else
      <div class="event-item">
        <div class="event-details">
          <div class="event-title">אין אירועים לשבוע הקרוב</div>
        </div>
      </div>
    @endif
  </div>

  <h3 class="section-heading-external">כל מה שצריך לדעת</h3>

  <div class="links-list">
    @if (!empty($page['links']))
      @foreach ($page['links'] as $link)
        <a class="link-card" href="{{ $link['url'] ?? '#' }}" target="_blank" rel="noopener">
          <svg class="icon-edit-small" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
          <div class="link-right-group">
            <span class="link-text">{{ $link['title'] ?? '' }}</span>
            <div class="icon-circle bg-blue">
              <svg class="colored-icon" viewBox="0 0 24 24" fill="none" stroke="#1565C0"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v8"></path><path d="M8 12h8"></path></svg>
            </div>
            <div class="drag-handle">☰</div>
          </div>
        </a>
      @endforeach
    @else
      <div class="link-card">
        <div class="link-right-group">
          <span class="link-text">אין קישורים זמינים</span>
        </div>
      </div>
    @endif
  </div>

  <footer class="footer">
    <div class="share-btn" data-popup-target="popup-links">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
      שיתוף הדף
    </div>
    <div class="logo-text">schoolist</div>
  </footer>
</div>

<div class="sb-popup-backdrop" data-popup-backdrop></div>

[[popup:invite]]
[[popup:homework]]
[[popup:links]]
[[popup:contacts]]
[[popup:food]]
[[popup:schedule]]

<script>
  (function () {
    const backdrop = document.querySelector('[data-popup-backdrop]');
    const popups = document.querySelectorAll('[data-popup]');

    const closePopups = () => {
      popups.forEach((popup) => popup.classList.remove('is-open'));
      backdrop?.classList.remove('is-open');
    };

    const openPopup = (popupId) => {
      const target = document.getElementById(popupId);
      if (!target) return;
      popups.forEach((popup) => popup.classList.remove('is-open'));
      target.classList.add('is-open');
      backdrop?.classList.add('is-open');
    };

    document.querySelectorAll('[data-popup-target]').forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        const target = trigger.getAttribute('data-popup-target');
        if (target) {
          openPopup(target);
        }
      });
    });

    document.querySelectorAll('[data-popup-close]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        closePopups();
      });
    });

    backdrop?.addEventListener('click', closePopups);
  })();
</script>
HTML;
    }

    /**
     * Build the default popup HTML.
     */
    private function getDefaultPopupHtml(string $title, string $key): string
    {
        $id = $this->getPopupIdFromKey($key);
        $body = $this->getPopupBodyHtml($key);

        return <<<HTML
<div id="{$id}" class="sb-popup" data-popup>
  <div class="sb-popup-card">
    <div class="sb-modal-title">{$title}</div>
    <div class="sb-modal-body">
      {$body}
    </div>
    <div class="sb-modal-actions">
      <button type="button" class="sb-button is-ghost" data-popup-close>סגור</button>
      <button type="button" class="sb-button" data-popup-close>סיום</button>
    </div>
  </div>
</div>
HTML;
    }

    /**
     * Resolve a popup key from a short key.
     */
    private function resolvePopupKey(string $key): string
    {
        $prefix = (string) config('builder.popup_prefix');

        if (Str::startsWith($key, $prefix)) {
            return $key;
        }

        return $prefix.$key;
    }

    /**
     * Build popup DOM id from a template key.
     */
    private function getPopupIdFromKey(string $key): string
    {
        $prefix = (string) config('builder.popup_prefix');
        $shortKey = Str::after($key, $prefix);

        return 'popup-'.Str::slug($shortKey);
    }

    /**
     * Determine if a template should be seeded with defaults.
     */
    private function shouldSeedTemplate(BuilderTemplate $template): bool
    {
        return !$template->draft_html && !$template->draft_css && !$template->draft_js;
    }

    /**
     * Determine if a template should be refreshed with new defaults.
     */
    private function shouldReplaceTemplateDraft(BuilderTemplate $template, string $key): bool
    {
        $draftHtml = (string) ($template->draft_html ?? '');

        if (str_contains($draftHtml, 'This is a sample popup template')) {
            return true;
        }

        if ($key === 'classroom.page' && str_contains($draftHtml, 'sb-page')) {
            return true;
        }

        if ($key === 'auth.login' && $this->shouldSeedTemplate($template)) {
            return true;
        }

        return false;
    }

    /**
     * Build the default login HTML.
     */
    private function getDefaultLoginPageHtml(): string
    {
        return <<<'HTML'
<style>
  .sb-login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; background: #f8fafc; font-family: "Inter", Arial, sans-serif; }
  .sb-login-card { width: 100%; max-width: 420px; background: #ffffff; border-radius: 20px; padding: 28px; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12); }
  .sb-login-title { font-size: 20px; font-weight: 700; margin: 0 0 6px; color: #0f172a; }
  .sb-login-subtitle { font-size: 13px; color: #64748b; margin-bottom: 18px; }
  .sb-login-stack { display: grid; gap: 12px; }
  .sb-login-field { display: grid; gap: 6px; font-size: 12px; color: #475569; }
  .sb-login-input { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 14px; }
  .sb-login-button { width: 100%; border: none; border-radius: 12px; background: #2563eb; color: #ffffff; padding: 12px; font-weight: 600; cursor: pointer; }
  .sb-login-links { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-top: 10px; }
  .sb-login-links a { color: inherit; text-decoration: none; }
</style>
<div class="sb-login-page">
  <div class="sb-login-card">
    <h1 class="sb-login-title">Welcome back</h1>
    <p class="sb-login-subtitle">Sign in to manage your classroom updates.</p>
    <form method="post" action="/login" class="sb-login-stack">
      @csrf
      <label class="sb-login-field">
        Phone
        <input type="text" name="phone" class="sb-login-input" placeholder="0500000000" autocomplete="tel">
      </label>
      <label class="sb-login-field">
        Password
        <input type="password" name="password" class="sb-login-input" placeholder="••••••••" autocomplete="current-password">
      </label>
      <button type="submit" class="sb-login-button">Sign in</button>
    </form>
    <div class="sb-login-links">
      <a href="/auth/code">Use one-time code</a>
      <a href="/">Back to site</a>
    </div>
  </div>
</div>
HTML;
    }

    /**
     * Build the default qlink HTML.
     */
    private function getDefaultQlinkPageHtml(): string
    {
        return <<<'HTML'
<style>
  .sb-qlink-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 16px; background: #f8fafc; font-family: "Inter", Arial, sans-serif; }
  .sb-qlink-card { width: 100%; max-width: 420px; background: #ffffff; border-radius: 20px; padding: 28px; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12); }
  .sb-qlink-title { font-size: 20px; font-weight: 700; margin: 0 0 6px; color: #0f172a; }
  .sb-qlink-subtitle { font-size: 13px; color: #64748b; margin-bottom: 18px; }
  .sb-qlink-stack { display: grid; gap: 12px; }
  .sb-qlink-field { display: grid; gap: 6px; font-size: 12px; color: #475569; }
  .sb-qlink-input { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 14px; }
  .sb-qlink-button { width: 100%; border: none; border-radius: 12px; background: #2563eb; color: #ffffff; padding: 12px; font-weight: 600; cursor: pointer; }
  .sb-qlink-note { font-size: 12px; color: #64748b; }
  .sb-qlink-error { color: #b91c1c; font-size: 12px; }
</style>
<div class="sb-qlink-page">
  <div class="sb-qlink-card" data-qlink-token="{{ $page['token'] ?? '' }}">
    <h1 class="sb-qlink-title">Enter your phone</h1>
    <p class="sb-qlink-subtitle">We will send a one-time code to continue.</p>
    <form class="sb-qlink-stack" data-qlink-form>
      <div class="sb-qlink-error" data-qlink-error style="display: none;"></div>
      <label class="sb-qlink-field">
        Phone
        <input type="text" name="phone" class="sb-qlink-input" placeholder="0500000000" autocomplete="tel">
      </label>
      <label class="sb-qlink-field" data-qlink-code-field style="display: none;">
        Code
        <input type="text" name="code" class="sb-qlink-input" placeholder="123456" autocomplete="one-time-code">
      </label>
      <button type="submit" class="sb-qlink-button">Send code</button>
      <div class="sb-qlink-note">By continuing you agree to receive an SMS for verification.</div>
    </form>
  </div>
</div>
<script>
  (function () {
    const root = document.querySelector('[data-qlink-form]');
    if (!root) return;

    const wrapper = document.querySelector('[data-qlink-token]');
    const token = wrapper?.getAttribute('data-qlink-token') || '';
    const errorEl = document.querySelector('[data-qlink-error]');
    const codeField = document.querySelector('[data-qlink-code-field]');
    const submitButton = root.querySelector('button[type="submit"]');
    let step = 'phone';

    const getCsrfToken = () => {
      const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
      return match ? decodeURIComponent(match[1]) : '';
    };

    const requestJson = async (url, payload) => {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw data;
      }

      return data;
    };

    root.addEventListener('submit', async (event) => {
      event.preventDefault();
      errorEl.style.display = 'none';

      const phone = root.querySelector('input[name="phone"]').value;
      const code = root.querySelector('input[name="code"]').value;

      try {
        if (step === 'phone') {
          await requestJson('/qlink/request', { phone, qlink_token: token });
          step = 'code';
          codeField.style.display = 'grid';
          submitButton.textContent = 'Verify code';
          return;
        }

        const data = await requestJson('/qlink/verify', { phone, code, qlink_token: token });
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
        }
      } catch (err) {
        const message = err?.message || err?.phone?.[0] || err?.code?.[0] || 'Request failed.';
        errorEl.textContent = message;
        errorEl.style.display = 'block';
      }
    });
  })();
</script>
HTML;
    }

    /**
     * Build popup body HTML by key.
     */
    private function getPopupBodyHtml(string $key): string
    {
        $shortKey = Str::after($key, (string) config('builder.popup_prefix'));

        return match ($shortKey) {
            'invite' => $this->getInvitePopupBodyHtml(),
            'homework' => $this->getHomeworkPopupBodyHtml(),
            'links' => $this->getLinksPopupBodyHtml(),
            'contacts' => $this->getContactsPopupBodyHtml(),
            'food' => $this->getFoodPopupBodyHtml(),
            'schedule' => $this->getSchedulePopupBodyHtml(),
            default => '<p>Add your content here.</p>',
        };
    }

    /**
     * Build invite popup body HTML.
     */
    private function getInvitePopupBodyHtml(): string
    {
        return <<<'HTML'
<p>Share this invite link with parents to join the classroom.</p>
<div class="sb-list">
  <div class="sb-row"><span>Invite Link</span><span>classroom.link/ABCD</span></div>
  <div class="sb-row"><span>Expires</span><span>30 days</span></div>
</div>
HTML;
    }

    /**
     * Build homework popup body HTML.
     */
    private function getHomeworkPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>Weekly assignments and reminders.</p>
<div class="sb-list">
  <div class="sb-row"><span>Math worksheet</span><span>Due Tue</span></div>
  <div class="sb-row"><span>Reading pages 20-30</span><span>Due Wed</span></div>
</div>
HTML;
    }

    /**
     * Build links popup body HTML.
     */
    private function getLinksPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>Helpful resources for students and families.</p>
<div class="sb-list">
  <div class="sb-row"><span>Class portal</span><span>portal.example</span></div>
  <div class="sb-row"><span>Weekly newsletter</span><span>newsletter.example</span></div>
</div>
HTML;
    }

    /**
     * Build contacts popup body HTML.
     */
    private function getContactsPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>Important contacts for the classroom.</p>
<div class="sb-list">
  <div class="sb-row"><span>Teacher</span><span>teacher@schoolist.co.il</span></div>
  <div class="sb-row"><span>School office</span><span>03-0000000</span></div>
</div>
HTML;
    }

    /**
     * Build food popup body HTML.
     */
    private function getFoodPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>This week's menu highlights.</p>
<div class="sb-list">
  <div class="sb-row"><span>Monday</span><span>Pasta</span></div>
  <div class="sb-row"><span>Tuesday</span><span>Chicken salad</span></div>
</div>
HTML;
    }

    /**
     * Build schedule popup body HTML.
     */
    private function getSchedulePopupBodyHtml(): string
    {
        return <<<'HTML'
<p>Upcoming schedule changes.</p>
<div class="sb-list">
  <div class="sb-row"><span>Friday</span><span>Short day</span></div>
  <div class="sb-row"><span>Next week</span><span>Field trip</span></div>
</div>
HTML;
    }

    /**
     * Split HTML into HTML/CSS/JS parts.
     *
     * @return array{html: string, css: string|null, js: string|null}
     */
    private function splitTemplateParts(string $html): array
    {
        $css = null;
        $js = null;

        preg_match_all('/<style\\b[^>]*>(.*?)<\\/style>/is', $html, $cssMatches);
        if (!empty($cssMatches[1])) {
            $css = trim(implode("\n\n", $cssMatches[1]));
            $html = preg_replace('/<style\\b[^>]*>.*?<\\/style>/is', '', $html) ?? $html;
        }

        preg_match_all('/<script\\b[^>]*>(.*?)<\\/script>/is', $html, $jsMatches);
        if (!empty($jsMatches[1])) {
            $js = trim(implode("\n\n", $jsMatches[1]));
            $html = preg_replace('/<script\\b[^>]*>.*?<\\/script>/is', '', $html) ?? $html;
        }

        return [
            'html' => trim($html),
            'css' => $css ?: null,
            'js' => $js ?: null,
        ];
    }
}
