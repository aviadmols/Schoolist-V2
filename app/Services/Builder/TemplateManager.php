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
  .logo-image { height: 20px; width: auto; display: block; }
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
        <span class="card-title-light">בוקר טוב!</span>
      </div>
      <img src="https://app.schoolist.co.il/storage/media/assets/u4GUGAJ888XuMp1EI4roXPiQ996DzG95qiohqyID.svg" class="icon-edit-small" alt="">
    </div>

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
    <span class="weather-text">{{ $page['weather_text'] ?? '16-20° - מזג אוויר נוח.' }}</span>
    <span style="font-size:24px;">☀️</span>
  </div>

  <div class="card" style="padding-bottom: 70px;">
    <div class="card-header">
      <div class="card-title">הודעות</div>
    </div>
    <div class="notices-list">
      @if (!empty($page['announcements']))
        @foreach ($page['announcements'] as $announcement)
          <div
            class="notice-row"
            data-item-popup="popup-content"
            data-item-type="{{ $announcement['type'] ?? 'message' }}"
            data-item-title="{{ $announcement['title'] ?? '' }}"
            data-item-content="{{ $announcement['content'] ?? '' }}"
            data-item-date="{{ $announcement['date'] ?? '' }}"
            data-item-time="{{ $announcement['time'] ?? '' }}"
            data-item-location="{{ $announcement['location'] ?? '' }}"
          >
            <span style="color: var(--blue-primary);">✓</span>
            <span>{{ $announcement['title'] ?? ($announcement['content'] ?? '') }}</span>
          </div>
        @endforeach
      @else
        <div class="notice-row">
          <span>✓</span>
          <span>אין הודעות כרגע</span>
        </div>
      @endif
    </div>
    <div class="fab-btn" data-popup-target="popup-homework">+</div>
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
          >
            <img src="https://cdn-icons-png.flaticon.com/512/2948/2948088.png" class="calendar-icon" alt="">
            <div class="event-content">
              <div class="event-main-text">{{ $event['title'] ?? '' }}</div>
              <div class="event-sub-text">
                @if (!empty($event['date'])) <span>{{ $event['date'] }}</span> @endif
                @if (!empty($event['time'])) <span>{{ $event['time'] }}</span> @endif
                @if (!empty($event['location'])) <span>{{ $event['location'] }}</span> @endif
              </div>
            </div>
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

  <footer class="footer">
    <div class="logo-text">schoolist</div>
    <div class="share-btn" data-popup-target="popup-links">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
      שיתוף הדף
    </div>
  </footer>
</div>

<div class="sb-popup-backdrop" data-popup-backdrop></div>

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
    const dayNames = Array.from(document.querySelectorAll('.day-tab'))
      .map((tab) => (tab.textContent || '').trim());
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

    const contentPopupTitle = document.getElementById('popup-content-title');
    const contentPopupType = document.getElementById('popup-content-type');
    const contentPopupBody = document.getElementById('popup-content-body');
    const contentPopupDate = document.getElementById('popup-content-date');
    const contentPopupTime = document.getElementById('popup-content-time');
    const contentPopupLocation = document.getElementById('popup-content-location');
    const typeLabels = {
      message: 'הודעה',
      event: 'אירוע',
      homework: 'שיעורי בית',
    };

    const setContentPopup = (dataset) => {
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
    };

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

    document.querySelectorAll('[data-item-popup]').forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        const targetId = trigger.getAttribute('data-item-popup');
        if (!targetId) return;
        setContentPopup(trigger.dataset);
        openPopup(targetId);
      });
    });

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
        <span>{{ $holiday['name'] ?? '' }}</span>
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
<div class="sb-list">
  @if (!empty($page['children']))
    @foreach ($page['children'] as $child)
      <div class="sb-row">
        <span>{{ $child['name'] ?? '' }}</span>
        <span>{{ $child['birth_date'] ?? '' }}</span>
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
        <span>{{ $contact['name'] ?? '' }}</span>
        <span>{{ $contact['phone'] ?? ($contact['email'] ?? '') }}</span>
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
