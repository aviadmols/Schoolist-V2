<div id="popup-whatsapp" class="sb-popup" data-popup>
  <div class="sb-popup-card">
    <div class="sb-modal-title">Group WhatsApp</div>
    <div class="sb-modal-body">
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
    </div>
    <div class="sb-modal-actions">
      <button type="button" class="sb-button is-ghost" data-popup-close>סגור</button>
      <button type="button" class="sb-button" data-popup-close>סיום</button>
    </div>
  </div>
</div>
