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

            $template = $this->createOrUpdateDefaultPopupTemplate($fullKey, $html, $name);
        }
    }

    /**
     * Create or update a default popup template with auto-publish.
     */
    private function createOrUpdateDefaultPopupTemplate(
        string $key,
        string $defaultHtml,
        string $name
    ): BuilderTemplate {
        $parts = $this->splitTemplateParts($defaultHtml);

        $template = BuilderTemplate::query()->firstOrCreate(
            ['scope' => config('builder.scope'), 'key' => $key],
            [
                'name' => $name,
                'type' => self::POPUP_TYPE,
                'draft_html' => $parts['html'],
                'draft_css' => $parts['css'],
                'draft_js' => $parts['js'],
                'published_html' => $parts['html'], // Auto-publish
                'published_css' => $parts['css'],
                'published_js' => $parts['js'],
                'is_override_enabled' => true, // Enable override so popups are shown
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]
        );

        // Always update popups to ensure they have the latest content
        // Check if content needs updating (contains placeholder or missing dynamic content)
        $publishedHtml = $template->published_html ?? '';
        $hasPlaceholder = str_contains($publishedHtml, 'Add your content here')
            || str_contains($publishedHtml, 'portal.example')
            || str_contains($publishedHtml, 'newsletter.example')
            || str_contains($publishedHtml, 'Class portal')
            || str_contains($publishedHtml, 'Weekly newsletter')
            || str_contains($publishedHtml, 'Helpful resources for students')
            || str_contains($publishedHtml, 'Math worksheet')
            || str_contains($publishedHtml, 'Reading pages');
        
        // Check if popup should have dynamic content but doesn't
        $shouldHaveDynamicContent = in_array($key, [
            $this->resolvePopupKey('whatsapp'),
            $this->resolvePopupKey('important-links'),
            $this->resolvePopupKey('holidays'),
            $this->resolvePopupKey('children'),
            $this->resolvePopupKey('contacts'),
            $this->resolvePopupKey('links'),
        ]);
        
        // Force update if template was manually edited but should have default content
        $needsUpdate = $this->shouldSeedTemplate($template) 
            || $this->shouldReplaceTemplateDraft($template, $key) 
            || !$template->published_html
            || $hasPlaceholder
            || ($shouldHaveDynamicContent && !str_contains($publishedHtml, '$page['))
            || ($shouldHaveDynamicContent && !str_contains($publishedHtml, '@if'));

        if ($needsUpdate && $defaultHtml) {
            $template->update([
                'draft_html' => $parts['html'],
                'draft_css' => $parts['css'],
                'draft_js' => $parts['js'],
                'published_html' => $parts['html'], // Auto-publish
                'published_css' => $parts['css'],
                'published_js' => $parts['js'],
                'is_override_enabled' => true, // Enable override
                'updated_by' => auth()->id() ?? 1,
            ]);
        }

        return $template;
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
    public function getDefaultClassroomPageHtml(): string
    {
        return <<<'HTML'
<style>
  .sb-popup-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); opacity: 0; pointer-events: none; transition: opacity 200ms ease; z-index: 40; }
  .sb-popup-backdrop.is-open { opacity: 1; pointer-events: auto; }
  .sb-popup { position: fixed; inset: 0; display: flex; align-items: flex-end; justify-content: center; padding: 16px; opacity: 0; pointer-events: none; transition: opacity 200ms ease; z-index: 50; }
  .sb-popup.is-open { opacity: 1; pointer-events: auto; }
  .sb-popup-card { width: 100%; max-width: 420px; background: #ffffff; border-radius: 24px 24px 0 0; padding: 20px; transform: translateY(40px); transition: transform 240ms ease; }
  .sb-popup.is-open .sb-popup-card { transform: translateY(0); }
  .logo-image { height: 20px; width: auto; display: block; }

  /* Quick Add Button */
  .fixed-add-btn {
    position: fixed;
    bottom: 24px;
    left: 24px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--blue-primary);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 30;
    transition: transform 0.2s;
  }
  .fixed-add-btn:active { transform: scale(0.9); }

  /* Quick Add Card */
  .quick-add-card {
    border-radius: 24px !important;
    padding: 0 !important;
    overflow: hidden;
    max-width: 450px !important;
  }
  .quick-add-header {
    padding: 20px;
    position: relative;
    border-bottom: 1px solid #f1f5f9;
  }
  .quick-add-header .close-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    background: none;
    border: none;
    font-size: 24px;
    color: #94a3b8;
    cursor: pointer;
  }
  .quick-add-title {
    font-size: 20px;
    font-weight: 800;
    text-align: center;
    color: #0f172a;
  }
  .quick-add-title .subtitle {
    font-weight: 400;
    color: #64748b;
    font-size: 16px;
  }
  .quick-add-body { padding: 20px; }
  .input-container {
    background: #f8fafc;
    border-radius: 16px;
    padding: 16px;
    min-height: 200px;
    display: flex;
    flex-direction: column;
  }
  #quick-add-text {
    width: 100%;
    border: none;
    background: transparent;
    resize: none;
    font-family: inherit;
    font-size: 16px;
    color: #1e293b;
    flex: 1;
    min-height: 150px;
    outline: none;
  }
  .file-preview-area {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
  }
  .file-info {
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 14px;
    color: #64748b;
  }
  .remove-file-btn {
    margin-right: auto;
    background: none;
    border: none;
    color: #ef4444;
    font-size: 18px;
    cursor: pointer;
  }
  .quick-add-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
  }
  .add-file-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
  }
  .visibility-option {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
  }
  .toggle-switch input { opacity: 0; width: 0; height: 0; }
  .slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .4s;
    border-radius: 24px;
  }
  .slider:before {
    position: absolute;
    content: "";
    height: 18px; width: 18px;
    left: 3px; bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
  }
  input:checked + .slider { background-color: var(--blue-primary); }
  input:checked + .slider:before { transform: translateX(20px); }
  .toggle-label { font-size: 14px; font-weight: 600; color: #475569; }
  .private-only-note { font-size: 13px; color: #94a3b8; font-style: italic; }
  .quick-add-footer { padding: 0 20px 20px 20px; }
  .submit-btn {
    width: 100%;
    background: var(--blue-primary);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }
  .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<div class="mobile-wrapper" dir="rtl">
  <header class="header">
    <div class="header-info">
      <div class="class-badge">{{ $page['classroom']['grade_level'] ?? '' }}{{ $page['classroom']['grade_number'] ?? '' }}</div>
      <div class="school-text">
        <span class="school-year">{{ $page['school_year'] ?? '' }}</span>
        <span class="school-name">{{ $page['classroom']['school_name'] ?? ($page['classroom']['name'] ?? '') }}</span>
      </div>
    </div>
    <div class="header-actions">
      @if (!empty($page['admin_edit_url']))
        <a href="{{ $page['admin_edit_url'] }}" class="icon-btn" aria-label="Edit classroom">
          <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
        </a>
      @else
        <button type="button" class="icon-btn" data-popup-target="popup-contacts" aria-label="Contacts">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </button>
      @endif
    </div>
  </header>

  <div class="day-tabs-container">
    @foreach (($page['day_labels'] ?? ['א','ב','ג','ד','ה','ו','ש']) as $dayIndex => $dayLabel)
      <button type="button" class="day-tab {{ (int) ($page['selected_day'] ?? 0) === $dayIndex ? 'active' : '' }}" data-day="{{ $dayIndex }}">
        {{ $dayLabel }}
      </button>
    @endforeach
  </div>

  <div class="card card-stacked-top">
    <div class="card-header">
      <div class="card-title">
        יום <span id="selected-day-name">{{ ($page['day_names'] ?? ['ראשון','שני','שלישי','רביעי','חמישי','שישי','שבת'])[(int) ($page['selected_day'] ?? 0)] ?? '' }}</span>
        <span class="card-title-light">{{ $page['greeting'] ?? 'בוקר טוב' }}!</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>

    @if (!empty($page['upcoming_birthdays']))
      <div style="padding: 12px 20px; background: #fff3cd; border-bottom: 1px solid #ffeaa7; margin-bottom: 0;">
        <div style="font-weight: bold; margin-bottom: 4px;">🎂 ימי הולדת קרובים:</div>
        @foreach ($page['upcoming_birthdays'] as $birthday)
          <div style="font-size: 0.9em; color: #666;">
            {{ $birthday['name'] ?? '' }} - {{ $birthday['date'] ?? '' }}
            @if (!empty($birthday['days_until']) && $birthday['days_until'] == 0)
              <span style="color: var(--blue-primary);">היום!</span>
            @elseif (!empty($birthday['days_until']))
              <span style="color: #666;">(בעוד {{ $birthday['days_until'] }} ימים)</span>
            @endif
          </div>
        @endforeach
      </div>
    @endif

    <div id="schedule-content" class="schedule-list">
      @php
        $dayIndex = (int) ($page['selected_day'] ?? 0);
        $dayEntries = $page['timetable'][$dayIndex] ?? [];
      @endphp
      @if (!empty($dayEntries))
        @foreach ($dayEntries as $entry)
          <div class="schedule-row">
            <span class="schedule-subject">{{ $entry['subject'] ?? '' }}</span>
            <span class="schedule-time">{{ $entry['start_time'] ?? '' }}-{{ $entry['end_time'] ?? '' }}</span>
          </div>
        @endforeach
      @else
        <div class="schedule-row">
          <span class="schedule-subject">---</span>
          <span class="schedule-time">08:00-09:00</span>
        </div>
      @endif
    </div>
  </div>

  <div class="card" style="display:flex; justify-content:space-between; align-items:center; padding:12px 20px;">
    <div style="flex: 1;">
      <div class="weather-text">{{ $page['weather']['text'] ?? ($page['weather_text'] ?? '16-20° - מזג אוויר נוח.') }}</div>
      @if (!empty($page['weather']['recommendation']))
        <div style="font-size: 0.85em; color: #666; margin-top: 4px;">{{ $page['weather']['recommendation'] }}</div>
      @endif
    </div>
    <span style="font-size:24px;">{{ $page['weather']['icon'] ?? '☀️' }}</span>
  </div>

  <div class="card" style="padding-bottom: 70px;">
    <div class="card-header">
      <div class="card-title">הודעות</div>
    </div>
    <div class="notices-list">
      @if (!empty($page['announcements']))
        @foreach ($page['announcements'] as $announcement)
          <div
            class="notice-row {{ !empty($announcement['is_done']) ? 'notice-done' : '' }}"
            data-item-popup="popup-content"
            data-item-type="{{ $announcement['type'] ?? 'message' }}"
            data-item-title="{{ $announcement['title'] ?? '' }}"
            data-item-content="{{ $announcement['content'] ?? '' }}"
            data-item-date="{{ $announcement['date'] ?? '' }}"
            data-item-time="{{ $announcement['time'] ?? '' }}"
            data-item-location="{{ $announcement['location'] ?? '' }}"
            data-announcement-id="{{ $announcement['id'] ?? '' }}"
            data-is-done="{{ !empty($announcement['is_done']) ? '1' : '0' }}"
          >
            <span class="notice-check" style="color: var(--blue-primary); cursor: pointer;">✓</span>
            <span class="notice-text">{{ $announcement['title'] ?? ($announcement['content'] ?? '') }}</span>
            @if (!empty($announcement['created_by']))
              <span style="font-size: 0.8em; color: #999; margin-right: 8px;">פורסם על ידי: {{ $announcement['created_by'] }}</span>
            @endif
          </div>
        @endforeach
      @else
        <div class="notice-row">
          <span>✓</span>
          <span>אין הודעות כרגע</span>
        </div>
      @endif
    </div>
    <div class="fab-btn" data-popup-target="popup-quick-add">+</div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">אירועים</div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>
    <div class="section-divider"></div>

    <div class="events-container">
      @if (!empty($page['events']))
        @foreach ($page['events'] as $event)
          <div
            class="event-row"
            data-item-popup="popup-content"
            data-item-type="{{ $event['type'] ?? 'event' }}"
            data-item-title="{{ $event['title'] ?? '' }}"
            data-item-content="{{ $event['content'] ?? '' }}"
            data-item-date="{{ $event['date'] ?? '' }}"
            data-item-time="{{ $event['time'] ?? '' }}"
            data-item-location="{{ $event['location'] ?? '' }}"
            data-event-id="{{ $event['id'] ?? '' }}"
          >
            <img src="https://cdn-icons-png.flaticon.com/512/2948/2948088.png" class="calendar-icon" alt="">
            <div class="event-content">
              <div class="event-main-text">{{ $event['title'] ?? '' }}</div>
              <div class="event-sub-text">
                @if (!empty($event['date'])) <span>{{ $event['date'] }}</span> @endif
                @if (!empty($event['time'])) <span>{{ $event['time'] }}</span> @endif
                @if (!empty($event['location'])) <span>{{ $event['location'] }}</span> @endif
              </div>
              @if (!empty($event['created_by']))
                <div style="font-size: 0.8em; color: #999; margin-top: 4px;">פורסם על ידי: {{ $event['created_by'] }}</div>
              @endif
            </div>
            @if (!empty($event['date']) || !empty($event['time']))
              <button type="button" class="add-to-calendar-btn" data-event-date="{{ $event['date'] ?? '' }}" data-event-time="{{ $event['time'] ?? '' }}" data-event-title="{{ $event['title'] ?? '' }}" data-event-location="{{ $event['location'] ?? '' }}" title="הוסף ליומן">📅</button>
            @endif
          </div>
        @endforeach
      @else
        <div class="event-row">
          <div class="event-content">
            <div class="event-main-text">אין אירועים להצגה</div>
          </div>
        </div>
      @endif
    </div>
  </div>

  <h3 style="margin: 25px 0 15px 0; font-size: 18px;">כל מה שצריך לדעת</h3>
  <div id="draggable-list" class="links-list">
    <div class="link-card" draggable="true" data-popup-target="popup-whatsapp" role="button" tabindex="0">
      <div class="link-right-group">
        <div class="drag-handle"></div>
        <div class="icon-circle bg-green"><img src="https://app.schoolist.co.il/storage/media/assets/uRYt0BSSZGTEvK6pPgj70m0U9lOakfzqjPoGZsA4.svg" class="custom-icon" alt=""></div>
        <span class="link-text">קבוצות וואטסאפ ועדכונים</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>

    <div class="link-card" draggable="true" data-popup-target="popup-holidays" role="button" tabindex="0">
      <div class="link-right-group">
        <div class="drag-handle"></div>
        <div class="icon-circle bg-pink"><img src="https://app.schoolist.co.il/storage/media/assets/npc55lAUitw24XStbopGZX9OgCnSO2W2HrcSEI2A.svg" class="custom-icon" alt=""></div>
        <span class="link-text">חופשות חגים וימים מיוחדים</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>

    <div class="link-card" draggable="true" data-popup-target="popup-important-links" role="button" tabindex="0">
      <div class="link-right-group">
        <div class="drag-handle"></div>
        <div class="icon-circle bg-purple"><img src="https://app.schoolist.co.il/storage/media/assets/NWIo9BORQYrwyiXEdmeN639lokgd6df0exjk9oNn.svg" class="custom-icon" alt=""></div>
        <span class="link-text">קישורים שימושיים</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>

    <div class="link-card" draggable="true" data-popup-target="popup-children" role="button" tabindex="0">
      <div class="link-right-group">
        <div class="drag-handle"></div>
        <div class="icon-circle bg-blue"><img src="https://app.schoolist.co.il/storage/media/assets/d1OZkkIqDyYX33MjhZ8eW6B70M8Hioq1KHO4x8jj.svg" class="custom-icon" alt=""></div>
        <span class="link-text">דף קשר</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>

    <div class="link-card" draggable="true" data-popup-target="popup-contacts" role="button" tabindex="0">
      <div class="link-right-group">
        <div class="drag-handle"></div>
        <div class="icon-circle bg-yellow"><img src="https://app.schoolist.co.il/storage/media/assets/Umbqws83v9sROBSfAMacwsCkfBfd2RSYuJyPg7Ux.svg" class="custom-icon" alt=""></div>
        <span class="link-text">אנשי קשר חשובים</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>

    <div class="link-card" draggable="true" data-popup-target="popup-food" role="button" tabindex="0">
      <div class="link-right-group">
        <div class="drag-handle"></div>
        <div class="icon-circle bg-orange"><img src="https://app.schoolist.co.il/storage/media/assets/m3jNqr4phCSiGqhRbvTrMw2W5slndTivo4KcilD5.svg" class="custom-icon" alt=""></div>
        <span class="link-text">מה אוכלים מחר?</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>
  </div>

  @if (!empty($page['current_user']))
    <div class="card" style="margin-top: 20px;">
      <div class="card-header">
        <div class="card-title">פרטי המשתמש</div>
      </div>
      <div style="padding: 12px 20px;">
        <div style="font-weight: bold;">{{ $page['current_user']['name'] ?? '' }}</div>
        @if (!empty($page['current_user']['phone']))
          <div style="font-size: 0.9em; color: #666; margin-top: 4px;">{{ $page['current_user']['phone'] }}</div>
        @endif
      </div>
    </div>
  @endif

  @if (!empty($page['classroom_admins']))
    <div class="card" style="margin-top: 20px;">
      <div class="card-header">
        <div class="card-title">מנהלי הכיתה</div>
      </div>
      <div style="padding: 12px 20px;">
        @foreach ($page['classroom_admins'] as $admin)
          <div style="margin-bottom: 8px;">
            @if (!empty($admin['phone']))
              <a href="https://wa.me/{{ str_replace(['+', '-', ' ', '(', ')'], '', $admin['phone']) }}" target="_blank" style="font-weight: bold; text-decoration: none; color: inherit;">
                {{ $admin['name'] ?? '' }}
              </a>
            @else
              <span style="font-weight: bold;">{{ $admin['name'] ?? '' }}</span>
            @endif
            @if (!empty($admin['phone']))
              <span style="font-size: 0.9em; color: #666; margin-right: 8px;">{{ $admin['phone'] }}</span>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <footer class="footer">
    <div class="logo-text">schoolist</div>
    <div class="share-btn" data-popup-target="popup-links">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
      שיתוף הדף
    </div>
  </footer>

  <button id="ai-quick-add-trigger" class="fixed-add-btn" title="הוספה מהירה">
    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
  </button>
</div>

<div class="sb-popup-backdrop" data-popup-backdrop></div>

<div id="popup-quick-add" class="sb-popup" data-popup>
  <div class="sb-popup-card quick-add-card">
    <div class="quick-add-header">
      <button class="close-btn" data-popup-close>&times;</button>
      <div class="quick-add-title">הוספה מהירה <span class="subtitle">קדימה!</span></div>
    </div>
    <div class="quick-add-body">
      <div class="input-container">
        <textarea id="quick-add-text" placeholder="יש שיעורים באנגלית אני מצרפת קובץ של המילים להכתבה..."></textarea>
        <div id="quick-add-file-preview" class="file-preview-area" style="display: none;">
          <div class="file-info">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
            <span id="file-name"></span>
            <button id="remove-file" class="remove-file-btn">&times;</button>
          </div>
        </div>
      </div>
      
      <div class="quick-add-actions">
        <label class="add-file-btn">
          <input type="file" id="quick-add-file" accept="image/*" hidden>
          <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
          הוסף קובץ
        </label>

        @if($page['can_manage'] || ($page['classroom']['allow_member_posting'] ?? false))
          <div class="visibility-option">
            <label class="toggle-switch">
              <input type="checkbox" id="quick-add-is-public" checked>
              <span class="slider"></span>
            </label>
            <span class="toggle-label">פרסם לכולם</span>
          </div>
        @else
          <div class="private-only-note">הפרסום יהיה גלוי רק לך</div>
        @endif
      </div>
    </div>
    <div class="quick-add-footer">
      <button id="quick-add-submit" class="submit-btn">
        המשך
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" style="margin-right: 8px; transform: rotate(180deg);"><polyline points="15 18 9 12 15 6"></polyline></svg>
      </button>
    </div>
  </div>
</div>

<div id="popup-ai-confirm" class="sb-popup" data-popup>
  <div class="sb-popup-card">
    <div class="sb-modal-title">אישור פרטים</div>
    <div id="ai-suggestion-content" class="sb-modal-body">
      <!-- Content populated by JS -->
    </div>
    <div class="sb-modal-actions">
      <button type="button" class="sb-button is-ghost" data-popup-close>ביטול</button>
      <button type="button" id="ai-confirm-save" class="sb-button">אשר ושמור</button>
    </div>
  </div>
</div>

[[popup:invite]]
[[popup:homework]]
[[popup:links]]
[[popup:whatsapp]]
[[popup:important-links]]
[[popup:holidays]]
[[popup:children]]
[[popup:content]]
[[popup:contacts]]
[[popup:food]]
[[popup:schedule]]

<script>
  (function () {
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initClassroomPage);
    } else {
      initClassroomPage();
    }

    function initClassroomPage() {
    // Wait for DOM to be ready and popups to be rendered
    if (document.readyState === 'complete') {
      setupClassroomPageFeatures();
    } else {
      window.addEventListener('load', () => {
        setTimeout(setupClassroomPageFeatures, 200);
      });
    }
    }

    function setupClassroomPageFeatures() {
      try {
        // Quick Add Logic
        const quickAddTrigger = document.getElementById('ai-quick-add-trigger');
        // ... rest of the code ...
      } catch (err) {
        console.error('Error in setupClassroomPageFeatures:', err);
      }
    }

    const dayNames = Array.from(document.querySelectorAll('.day-tab'))
      .map((tab) => (tab && tab.textContent) ? tab.textContent.trim() : '');
    const timetable = {!! json_encode($page['timetable'] ?? []) !!};
    const selectedDayNameEl = document.getElementById('selected-day-name');
    const scheduleEl = document.getElementById('schedule-content');

    const buildScheduleHtml = (entries) => {
      if (!Array.isArray(entries) || entries.length === 0) {
        return '<div class="schedule-row"><span class="schedule-subject">---</span><span class="schedule-time">08:00-09:00</span></div>';
      }

      return entries.map((entry) => {
        const subject = entry?.subject || '';
        const startTime = entry?.start_time || '';
        const endTime = entry?.end_time || '';
        const time = startTime || endTime ? `${startTime}-${endTime}` : '';
        return `<div class="schedule-row"><span class="schedule-subject">${subject}</span><span class="schedule-time">${time}</span></div>`;
      }).join('');
    };

    const renderSchedule = (dayIndex) => {
      if (!scheduleEl) return;
      const name = dayNames[dayIndex] || '';
      if (selectedDayNameEl) {
        selectedDayNameEl.textContent = name;
      }
      scheduleEl.innerHTML = buildScheduleHtml(timetable[dayIndex] || []);
    };

    document.querySelectorAll('.day-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.day-tab').forEach((item) => item.classList.remove('active'));
        tab.classList.add('active');
        const dayIndex = parseInt(tab.getAttribute('data-day') || '0', 10);
        renderSchedule(dayIndex);
      });
    });

    const initialTab = document.querySelector('.day-tab.active');
    if (initialTab) {
      const dayIndex = parseInt(initialTab.getAttribute('data-day') || '0', 10);
      renderSchedule(dayIndex);
    }

    const list = document.getElementById('draggable-list');
    let draggingItem = null;

    const getDragAfterElement = (container, y) => {
      const draggableElements = [...container.querySelectorAll('.link-card:not([style*="opacity: 0.5"])')];
      return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          return { offset: offset, element: child };
        }
        return closest;
      }, { offset: Number.NEGATIVE_INFINITY }).element;
    };

    if (list) {
      list.addEventListener('dragstart', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        draggingItem = target;
        target.style.opacity = '0.5';
      });

      list.addEventListener('dragend', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        target.style.opacity = '1';
        draggingItem = null;
      });

      list.addEventListener('dragover', (event) => {
        event.preventDefault();
        if (!draggingItem) return;
        const afterElement = getDragAfterElement(list, event.clientY);
        if (!afterElement) {
          list.appendChild(draggingItem);
        } else {
          list.insertBefore(draggingItem, afterElement);
        }
      });
    }

    // Safely get popup content elements
    const contentPopupTitle = document.getElementById('popup-content-title');
    const contentPopupType = document.getElementById('popup-content-type');
    const contentPopupBody = document.getElementById('popup-content-body');
    const contentPopupDate = document.getElementById('popup-content-date');
    const contentPopupTime = document.getElementById('popup-content-time');
    const contentPopupLocation = document.getElementById('popup-content-location');
    
    // Log if popup elements are missing (for debugging)
    if (!contentPopupTitle || !contentPopupType || !contentPopupBody) {
      console.warn('Some popup content elements are missing. Popup may not work correctly.');
    }
    const typeLabels = {
      message: 'הודעה',
      event: 'אירוע',
      homework: 'שיעורי בית',
    };

    const setContentPopup = (dataset) => {
      if (!dataset) return;
      try {
        const type = dataset.itemType || 'message';
        if (contentPopupType) {
          contentPopupType.textContent = typeLabels[type] || type;
        }
        if (contentPopupTitle) {
          contentPopupTitle.textContent = dataset.itemTitle || '';
        }
        if (contentPopupBody) {
          contentPopupBody.textContent = dataset.itemContent || '';
        }
        if (contentPopupDate) {
          contentPopupDate.textContent = dataset.itemDate || '';
        }
        if (contentPopupTime) {
          contentPopupTime.textContent = dataset.itemTime || '';
        }
        if (contentPopupLocation) {
          contentPopupLocation.textContent = dataset.itemLocation || '';
        }
      } catch (err) {
        // Silently fail
      }
    };

    const backdrop = document.querySelector('[data-popup-backdrop]');
    const popups = document.querySelectorAll('[data-popup]');
    
    // Log popup count for debugging
    if (popups.length === 0) {
      console.warn('No popups found in DOM. Popups may not be rendered correctly.');
    } else {
      console.log(`Found ${popups.length} popups in DOM.`);
    }

    const closePopups = () => {
      popups.forEach((popup) => popup.classList.remove('is-open'));
      backdrop?.classList.remove('is-open');
    };

    const openPopup = (popupId) => {
      try {
        if (!popupId) {
          console.warn('openPopup: popupId is empty');
          return;
        }
        const target = document.getElementById(popupId);
        if (!target) {
          console.warn('Popup not found:', popupId, 'Available popups:', Array.from(document.querySelectorAll('[data-popup]')).map(p => p.id));
          return;
        }
        if (popups && popups.length > 0) {
          popups.forEach((popup) => {
            if (popup) popup.classList.remove('is-open');
          });
        }
        target.classList.add('is-open');
        if (backdrop) {
          backdrop.classList.add('is-open');
        }
      } catch (err) {
        console.error('Error opening popup:', err, 'popupId:', popupId);
      }
    };

    // Setup popup triggers with error handling
    try {
      const itemPopupTriggers = document.querySelectorAll('[data-item-popup]');
      if (itemPopupTriggers && itemPopupTriggers.length > 0) {
        itemPopupTriggers.forEach((trigger) => {
          if (!trigger || typeof trigger.addEventListener !== 'function') return;
          try {
            trigger.addEventListener('click', (event) => {
              try {
                event.preventDefault();
                event.stopPropagation();
                if (!trigger) return;
                // Safe dataset access
                let dataset = {};
                try {
                  if (trigger && trigger.dataset) {
                    dataset = trigger.dataset;
                  }
                } catch (e) {
                  // Ignore
                }
                const targetId = trigger.getAttribute('data-item-popup');
                if (!targetId) return;
                setContentPopup(dataset);
                openPopup(targetId);
              } catch (err) {
                // Ignore
              }
            });
          } catch (err) {
            console.error('Error adding event listener to trigger:', err);
          }
        });
      }
    } catch (err) {
      console.error('Error setting up item popup triggers:', err);
    }

    try {
      const popupTargetTriggers = document.querySelectorAll('[data-popup-target]');
      if (popupTargetTriggers && popupTargetTriggers.length > 0) {
        popupTargetTriggers.forEach((trigger) => {
          if (!trigger || typeof trigger.addEventListener !== 'function') return;
          try {
            trigger.addEventListener('click', (event) => {
              try {
                event.preventDefault();
                event.stopPropagation();
                if (!trigger) return;
                const target = trigger.getAttribute('data-popup-target');
                if (target) {
                  openPopup(target);
                }
              } catch (err) {
                console.error('Error handling popup target click:', err);
              }
            });
          } catch (err) {
            console.error('Error adding event listener to popup target:', err);
          }
        });
      }
    } catch (err) {
      console.error('Error setting up popup target triggers:', err);
    }

    try {
      const closeButtons = document.querySelectorAll('[data-popup-close]');
      if (closeButtons && closeButtons.length > 0) {
        closeButtons.forEach((button) => {
          if (!button || typeof button.addEventListener !== 'function') return;
          button.addEventListener('click', (event) => {
            event.preventDefault();
            closePopups();
          });
        });
      }
    } catch (err) {
      console.error('Error setting up close buttons:', err);
    }

    if (backdrop && typeof backdrop.addEventListener === 'function') {
      backdrop.addEventListener('click', closePopups);
    }

    // Handle child contacts toggle
    document.querySelectorAll('.child-name').forEach((nameEl) => {
      if (!nameEl) return;
      nameEl.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!nameEl) return;
        const childRow = nameEl.closest('.child-row');
        if (!childRow) return;
        const childId = childRow.getAttribute('data-child-id');
        if (!childId) return;
        const contactsEl = document.querySelector(`.child-contacts[data-child-id="${childId}"]`);
        if (contactsEl) {
          contactsEl.style.display = contactsEl.style.display === 'none' ? 'block' : 'none';
        }
      });
    });

    // Handle announcement toggle
    document.querySelectorAll('.notice-check').forEach((checkEl) => {
      if (!checkEl) return;
      checkEl.addEventListener('click', async (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!checkEl) return;
        const noticeRow = checkEl.closest('.notice-row');
        if (!noticeRow) return;
        const announcementId = noticeRow.getAttribute('data-announcement-id');
        if (!announcementId) return;

        const isDone = noticeRow.classList.contains('notice-done');
        
        try {
          const response = await fetch(`/announcements/${announcementId}/done`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin',
          });

          if (response.ok) {
            if (!isDone) {
              noticeRow.classList.add('notice-done');
              noticeRow.setAttribute('data-is-done', '1');
              // Confetti effect
              createConfetti();
            } else {
              noticeRow.classList.remove('notice-done');
              noticeRow.setAttribute('data-is-done', '0');
            }
          }
        } catch (error) {
          console.error('Failed to toggle announcement:', error);
        }
      });
    });

    // Handle add to calendar
    document.querySelectorAll('.add-to-calendar-btn').forEach((btn) => {
      if (!btn) return;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!btn) return;
        const date = btn.getAttribute('data-event-date') || '';
        const time = btn.getAttribute('data-event-time') || '';
        const title = btn.getAttribute('data-event-title') || '';
        const location = btn.getAttribute('data-event-location') || '';

        if (!date) return;

        const [day, month, year] = date.split('.');
        const [hours, minutes] = time ? time.split(':') : ['12', '00'];
        const startDate = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), parseInt(hours), parseInt(minutes));
        const endDate = new Date(startDate.getTime() + 60 * 60 * 1000);

        const formatICSDate = (date) => {
          return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        };

        const icsContent = [
          'BEGIN:VCALENDAR',
          'VERSION:2.0',
          'PRODID:-//Schoolist//Classroom Events//EN',
          'BEGIN:VEVENT',
          `DTSTART:${formatICSDate(startDate)}`,
          `DTEND:${formatICSDate(endDate)}`,
          `SUMMARY:${title}`,
          location ? `LOCATION:${location}` : '',
          'END:VEVENT',
          'END:VCALENDAR',
        ].filter(Boolean).join('\r\n');

        const blob = new Blob([icsContent], { type: 'text/calendar' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${title.replace(/[^a-z0-9]/gi, '_')}.ics`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      });
    });

    // Confetti effect function
    function createConfetti() {
      const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#6c5ce7'];
      const confettiCount = 50;
      
      for (let i = 0; i < confettiCount; i++) {
        const confetti = document.createElement('div');
        confetti.style.position = 'fixed';
        confetti.style.width = '8px';
        confetti.style.height = '8px';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.top = '-10px';
        confetti.style.borderRadius = '50%';
        confetti.style.pointerEvents = 'none';
        confetti.style.zIndex = '9999';
        confetti.style.opacity = '0.9';
        
        document.body.appendChild(confetti);
        
        const animation = confetti.animate([
          { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
          { transform: `translateY(${window.innerHeight + 100}px) rotate(720deg)`, opacity: 0 }
        ], {
          duration: 2000 + Math.random() * 1000,
          easing: 'cubic-bezier(0.5, 0, 0.5, 1)',
        });
        
        animation.onfinish = () => confetti.remove();
      }
    }
    } // End of setupClassroomPageFeatures
  })();
</script>
<style>
  .notice-done .notice-text {
    text-decoration: line-through;
    opacity: 0.6;
  }
  .notice-done .notice-check {
    color: #999 !important;
  }
  .add-to-calendar-btn {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    padding: 4px 8px;
    opacity: 0.7;
    transition: opacity 0.2s;
  }
  .add-to-calendar-btn:hover {
    opacity: 1;
  }
  .child-contacts {
    animation: slideDown 0.3s ease-out;
  }
  @keyframes slideDown {
    from {
      opacity: 0;
      max-height: 0;
    }
    to {
      opacity: 1;
      max-height: 500px;
    }
  }
</style>
HTML;
    }

    /**
     * Build the default popup HTML.
     */
    public function getDefaultPopupHtml(string $title, string $key): string
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
    public function getPopupBodyHtml(string $key): string
    {
        $shortKey = Str::after($key, (string) config('builder.popup_prefix'));

        return match ($shortKey) {
            'invite' => $this->getInvitePopupBodyHtml(),
            'homework' => $this->getHomeworkPopupBodyHtml(),
            'links' => $this->getLinksPopupBodyHtml(),
            'whatsapp' => $this->getWhatsAppPopupBodyHtml(),
            'important-links' => $this->getImportantLinksPopupBodyHtml(),
            'holidays' => $this->getHolidaysPopupBodyHtml(),
            'children' => $this->getChildrenPopupBodyHtml(),
            'content' => $this->getContentPopupBodyHtml(),
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
<p>קישורים שימושיים לשיתוף.</p>
<div class="sb-list">
  <div class="sb-row"><span>קישור לכיתה</span><span>{{ $page['share_link'] ?? '' }}</span></div>
</div>
HTML;
    }

    /**
     * Build WhatsApp links popup body HTML.
     */
    private function getWhatsAppPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>קישורי קבוצות וואטסאפ.</p>
<div class="sb-list">
  @if (!empty($page['links']))
    @foreach ($page['links'] as $link)
      @if (($link['category'] ?? '') === 'group_whatsapp')
        <div class="sb-row">
          <span>{{ $link['title'] ?? '' }}</span>
          @if (!empty($link['link_url']))
            <a href="{{ $link['link_url'] }}" target="_blank" rel="noopener">פתח</a>
          @else
            <span>-</span>
          @endif
        </div>
      @endif
    @endforeach
  @else
    <div class="sb-row"><span>אין קישורים זמינים</span><span></span></div>
  @endif
</div>
HTML;
    }

    /**
     * Build important links popup body HTML.
     */
    private function getImportantLinksPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>Links & Materials.</p>
<div class="sb-list">
  @if (!empty($page['links']))
    @foreach ($page['links'] as $link)
      @if (($link['category'] ?? '') === 'important_links')
        <div class="sb-row">
          <span>{{ $link['title'] ?? '' }}</span>
          @if (!empty($link['link_url']))
            <a href="{{ $link['link_url'] }}" target="_blank" rel="noopener">פתח</a>
          @else
            <span>-</span>
          @endif
        </div>
      @endif
    @endforeach
  @else
    <div class="sb-row"><span>אין קישורים זמינים</span><span></span></div>
  @endif
</div>
HTML;
    }

    /**
     * Build holidays popup body HTML.
     */
    private function getHolidaysPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>חופשות וחגים קרובים.</p>
<div class="sb-list">
  @if (!empty($page['holidays']))
    @foreach ($page['holidays'] as $holiday)
      <div class="sb-row">
        <span>
          {{ $holiday['name'] ?? '' }}
          @if (!empty($holiday['has_kitan']) && $holiday['has_kitan'])
            <span style="color: var(--blue-primary); margin-right: 4px;">🎒</span>
          @endif
        </span>
        <span>
          @if (!empty($holiday['start_date']))
            {{ $holiday['start_date'] }}
          @endif
          @if (!empty($holiday['end_date']) && ($holiday['end_date'] ?? '') !== ($holiday['start_date'] ?? ''))
            - {{ $holiday['end_date'] }}
          @endif
        </span>
      </div>
    @endforeach
  @else
    <div class="sb-row"><span>אין חופשות להצגה</span><span></span></div>
  @endif
</div>
HTML;
    }

    /**
     * Build children popup body HTML.
     */
    private function getChildrenPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>רשימת הילדים בכיתה.</p>
<div class="sb-list" id="children-list">
  @if (!empty($page['children']))
    @foreach ($page['children'] as $child)
      <div class="sb-row child-row" data-child-id="{{ $child['id'] ?? '' }}">
        <span class="child-name" style="cursor: pointer; font-weight: bold;">{{ $child['name'] ?? '' }}</span>
        <span>{{ $child['birth_date'] ?? '' }}</span>
      </div>
      <div class="child-contacts" data-child-id="{{ $child['id'] ?? '' }}" style="display: none; padding-right: 20px; margin-top: 8px;">
        @if (!empty($child['contacts']))
          @foreach ($child['contacts'] as $contact)
            <div class="sb-row" style="font-size: 0.9em; color: #666;">
              <span>{{ $contact['name'] ?? '' }} ({{ $contact['relation'] ?? '' }})</span>
              <span>
                @if (!empty($contact['phone']))
                  <a href="tel:{{ $contact['phone'] }}" style="margin-left: 8px;">📞</a>
                  <a href="https://wa.me/{{ str_replace(['+', '-', ' ', '(', ')'], '', $contact['phone']) }}" target="_blank" style="margin-left: 4px;">💬</a>
                  <a href="tel:{{ $contact['phone'] }}?add" style="margin-left: 4px;">➕</a>
                @endif
              </span>
            </div>
          @endforeach
        @endif
      </div>
    @endforeach
  @else
    <div class="sb-row"><span>אין ילדים להצגה</span><span></span></div>
  @endif
</div>
HTML;
    }

    /**
     * Build content popup body HTML.
     */
    private function getContentPopupBodyHtml(): string
    {
        return <<<'HTML'
<div class="sb-list">
  <div class="sb-row">
    <span id="popup-content-type"></span>
    <span id="popup-content-date"></span>
  </div>
  <div class="sb-row">
    <strong id="popup-content-title"></strong>
    <span id="popup-content-time"></span>
  </div>
  <div class="sb-row">
    <span id="popup-content-location"></span>
  </div>
</div>
<div id="popup-content-body" style="margin-top: 12px;"></div>
HTML;
    }

    /**
     * Build contacts popup body HTML.
     */
    private function getContactsPopupBodyHtml(): string
    {
        return <<<'HTML'
<p>אנשי קשר חשובים.</p>
<div class="sb-list">
  @if (!empty($page['important_contacts']))
    @foreach ($page['important_contacts'] as $contact)
      <div class="sb-row">
        <span>{{ $contact['name'] ?? '' }} @if (!empty($contact['role']))<span style="color: #666; font-size: 0.9em;">({{ $contact['role'] }})</span>@endif</span>
        <span>
          @if (!empty($contact['phone']))
            <a href="tel:{{ $contact['phone'] }}" style="margin-left: 8px;">{{ $contact['phone'] }}</a>
            <a href="https://wa.me/{{ str_replace(['+', '-', ' ', '(', ')'], '', $contact['phone']) }}" target="_blank" style="margin-left: 4px;">💬</a>
            <a href="tel:{{ $contact['phone'] }}?add" style="margin-left: 4px;">➕</a>
          @elseif (!empty($contact['email']))
            <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
          @endif
        </span>
      </div>
    @endforeach
  @else
    <div class="sb-row"><span>אין אנשי קשר להצגה</span><span></span></div>
  @endif
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
    public function splitTemplateParts(string $html): array
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
