<div id="popup-holidays" class="sb-popup" data-popup>
  <div class="sb-popup-card">
    <div class="sb-modal-title">Holidays</div>
    <div class="sb-modal-body">
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
    </div>
    <div class="sb-modal-actions">
      <button type="button" class="sb-button is-ghost" data-popup-close>סגור</button>
      <button type="button" class="sb-button" data-popup-close>סיום</button>
    </div>
  </div>
</div>
