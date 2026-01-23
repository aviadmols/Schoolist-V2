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
  .sb-page { background: #0b0b0b; padding: 32px 0 64px; font-family: "Inter", Arial, sans-serif; }
  .sb-container { max-width: 420px; margin: 0 auto; padding: 0 16px; }
  .sb-stack { display: grid; gap: 16px; }
  .sb-card { background: #ffffff; border-radius: 20px; padding: 18px; box-shadow: 0 14px 40px rgba(0,0,0,0.25); }
  .sb-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  .sb-header-left { display: flex; gap: 10px; align-items: center; }
  .sb-badge { width: 46px; height: 46px; border-radius: 999px; background: #ffedd5; display:flex; align-items:center; justify-content:center; font-weight:700; color:#ea580c; }
  .sb-title { font-size: 14px; font-weight: 700; color: #111827; margin: 0; }
  .sb-subtitle { font-size: 12px; color: #6b7280; }
  .sb-actions { display: flex; gap: 10px; color: #9ca3af; }
  .sb-action { width: 28px; height: 28px; border-radius: 8px; background: #f3f4f6; display:flex; align-items:center; justify-content:center; font-size: 12px; }
  .sb-tabs { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
  .sb-tab { padding: 6px 10px; border-radius: 999px; font-size: 12px; background: #f3f4f6; color:#111827; text-decoration: none; }
  .sb-tab.is-active { background: #e5f0ff; color:#1d4ed8; font-weight: 600; }
  .sb-section-title { font-size: 14px; font-weight: 700; margin: 10px 0 8px; color:#111827; }
  .sb-list { display: grid; gap: 10px; font-size: 13px; color:#111827; }
  .sb-row { display:flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #f3f4f6; padding-bottom: 8px; }
  .sb-note { background: #eff6ff; border-radius: 12px; padding: 10px 12px; display:flex; justify-content: space-between; align-items: center; font-size: 12px; }
  .sb-links-title { text-align: center; font-size: 14px; font-weight: 700; margin: 8px 0 4px; }
  .sb-links { display: grid; gap: 10px; }
  .sb-link-card { background: #ffffff; border-radius: 16px; padding: 10px 12px; display:flex; align-items:center; justify-content: space-between; box-shadow: 0 10px 28px rgba(0,0,0,0.2); text-decoration: none; color: inherit; }
  .sb-link-label { display:flex; align-items:center; gap: 10px; font-size: 13px; }
  .sb-icon { width: 34px; height: 34px; border-radius: 12px; background: #eef2ff; display:flex; align-items:center; justify-content:center; font-weight:700; color:#1e3a8a; }
  .sb-link-action { font-size: 18px; color: #9ca3af; }
  .sb-footer { margin-top: 20px; text-align:center; color:#6b7280; font-size: 12px; }
  .sb-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(0,0,0,0.6); z-index: 1000; }
  .sb-modal:target { display: flex; }
  .sb-modal-card { background: #ffffff; border-radius: 16px; padding: 18px; width: 100%; max-width: 360px; box-shadow: 0 12px 28px rgba(0,0,0,0.3); }
  .sb-modal-title { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
  .sb-modal-body { font-size: 13px; color:#374151; margin-bottom: 16px; }
  .sb-modal-actions { display:flex; justify-content:flex-end; gap: 8px; }
  .sb-button { display:inline-block; padding: 8px 12px; border-radius: 10px; background: #2563eb; color:#fff; font-size: 12px; text-decoration: none; }
  .sb-button.is-ghost { background: #e5e7eb; color:#111827; }
  .sb-link { text-decoration: none; color: inherit; }
</style>
<div class="sb-page">
  <div class="sb-container sb-stack">
    <div class="sb-card">
      <div class="sb-header">
        <div class="sb-header-left">
          <div class="sb-badge">
            {{ $page['classroom']['grade_level'] ?? '' }}'{{ $page['classroom']['grade_number'] ?? '' }}
          </div>
          <div>
            <h2 class="sb-title">{{ $page['classroom']['school_name'] ?? ($page['classroom']['name'] ?? 'Classroom') }}</h2>
            <div class="sb-subtitle">2025-2026</div>
          </div>
        </div>
        <div class="sb-actions">
          <a href="#popup-contacts" class="sb-action">U</a>
          <a href="#popup-invite" class="sb-action">E</a>
        </div>
      </div>

      <div class="sb-tabs">
        @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as \$dayIndex => \$dayLabel)
          <a class="sb-tab {{ (int) (\$page['selected_day'] ?? 0) === \$dayIndex ? 'is-active' : '' }}" href="#popup-schedule">
            {{ \$dayLabel }}
          </a>
        @endforeach
      </div>

      <div class="sb-section-title">
        Day {{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][(int) (\$page['selected_day'] ?? 0)] }} <span class="sb-subtitle">Good morning!</span>
      </div>
      <div class="sb-list">
        @if (!empty(\$page['timetable']) && !empty(\$page['timetable'][\$page['selected_day'] ?? 0]))
          @foreach (\$page['timetable'][\$page['selected_day'] ?? 0] as \$entry)
            <div class="sb-row">
              <span>{{ \$entry['start_time'] ?? '' }}-{{ \$entry['end_time'] ?? '' }}</span>
              <span>{{ \$entry['subject'] ?? '' }}</span>
            </div>
          @endforeach
        @else
          <div class="sb-row"><span>08:00-09:00</span><span>Math</span></div>
          <div class="sb-row"><span>09:00-10:00</span><span>Literature</span></div>
          <div class="sb-row"><span>10:20-11:00</span><span>Arts</span></div>
        @endif
      </div>

      <div class="sb-note">
        <span>16-20 C, light breeze</span>
        <span>Sunny</span>
      </div>

      <div class="sb-section-title">Announcements</div>
      @if (!empty(\$page['announcements']))
        <div class="sb-list">
          @foreach (\$page['announcements'] as \$announcement)
            <div class="sb-row">
              <span>{{ \$announcement['title'] ?? '' }}</span>
              <span>{{ \$announcement['is_done'] ? 'Done' : 'Open' }}</span>
            </div>
          @endforeach
        </div>
      @else
        <div class="sb-list">
          <div class="sb-row"><span>No active announcements</span><span></span></div>
        </div>
      @endif
    </div>

    <div class="sb-card">
      <div class="sb-links-title">Everything you need</div>
      <div class="sb-links">
        <a class="sb-link-card" href="#popup-invite">
          <div class="sb-link-label"><span class="sb-icon">I</span>Invite Parents</div>
          <span class="sb-link-action">+</span>
        </a>
        <a class="sb-link-card" href="#popup-homework">
          <div class="sb-link-label"><span class="sb-icon">H</span>Homework</div>
          <span class="sb-link-action">+</span>
        </a>
        <a class="sb-link-card" href="#popup-links">
          <div class="sb-link-label"><span class="sb-icon">L</span>Useful Links</div>
          <span class="sb-link-action">+</span>
        </a>
        <a class="sb-link-card" href="#popup-contacts">
          <div class="sb-link-label"><span class="sb-icon">C</span>Important Contacts</div>
          <span class="sb-link-action">+</span>
        </a>
        <a class="sb-link-card" href="#popup-food">
          <div class="sb-link-label"><span class="sb-icon">F</span>What We Eat</div>
          <span class="sb-link-action">+</span>
        </a>
        <a class="sb-link-card" href="#popup-schedule">
          <div class="sb-link-label"><span class="sb-icon">S</span>Weekly Schedule</div>
          <span class="sb-link-action">+</span>
        </a>
      </div>
    </div>

    <div class="sb-footer">schoolist</div>
  </div>
</div>

[[popup:invite]]
[[popup:homework]]
[[popup:links]]
[[popup:contacts]]
[[popup:food]]
[[popup:schedule]]
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
<div id="{$id}" class="sb-modal">
  <div class="sb-modal-card">
    <div class="sb-modal-title">{$title}</div>
    <div class="sb-modal-body">
      {$body}
    </div>
    <div class="sb-modal-actions">
      <a href="#" class="sb-button is-ghost">Close</a>
      <a href="#" class="sb-button">Done</a>
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
