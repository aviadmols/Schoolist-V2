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
