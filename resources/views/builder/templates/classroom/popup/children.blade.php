<div id="popup-children" class="sb-popup" data-popup>
  <div class="sb-popup-card">
    <div class="sb-modal-title">Children</div>
    <div class="sb-modal-body">
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
    </div>
    <div class="sb-modal-actions">
      <button type="button" class="sb-button is-ghost" data-popup-close>סגור</button>
      <button type="button" class="sb-button" data-popup-close>סיום</button>
    </div>
  </div>
</div>
