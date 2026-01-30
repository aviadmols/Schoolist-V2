<div id="popup-contacts" class="sb-popup" data-popup>
  <div class="sb-popup-card">
    <div class="sb-modal-title">Important Contacts</div>
    <div class="sb-modal-body">
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
    </div>
    <div class="sb-modal-actions">
      <button type="button" class="sb-button is-ghost" data-popup-close>סגור</button>
      <button type="button" class="sb-button" data-popup-close>סיום</button>
    </div>
  </div>
</div>
