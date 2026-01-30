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
      <div class="weather-text">{{ $page['weather']['text'] ?? '16-20° - מזג אוויר נוח.' }}</div>
      @if (!empty($page['weather']['recommendation']))
        <div style="font-size: 0.85em; color: #666; margin-top: 4px;">{{ $page['weather']['recommendation'] }}</div>
      @endif
    </div>
    @if (!empty($page['weather']['icon']) && (str_starts_with($page['weather']['icon'], 'http') || str_starts_with($page['weather']['icon'], '/')))
      <img src="{{ $page['weather']['icon'] }}" alt="Weather Icon" style="width: 32px; height: 32px; object-fit: contain;" />
    @else
      <span style="font-size:24px;">{{ $page['weather']['icon'] ?? '☀️' }}</span>
    @endif
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

        @if(($page['can_manage'] ?? false) || (($page['classroom']['allow_member_posting'] ?? false)))
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
