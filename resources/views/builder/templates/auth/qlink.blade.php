<style>
  .sb-qlink-digit-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
  .sb-qlink-digit-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  .sb-qlink-digit { width: 2.25rem; height: 2.5rem; text-align: center; font-size: 1.125rem; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; }
  .sb-qlink-digit:focus { outline: none; border-color: #2563eb; }
  .sb-qlink-digit-sep { padding-bottom: 0.25rem; font-weight: 600; color: #64748b; }
  .sb-qlink-input { display: block; width: 100%; max-width: 14rem; padding: 0.5rem 0.75rem; font-size: 1.125rem; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; }
  .sb-qlink-input:focus { outline: none; border-color: #2563eb; }
</style>
<div class="sb-qlink-page">
  <div class="sb-qlink-card" data-qlink-token="{{ $page['token'] ?? '' }}">
    <h1 class="sb-qlink-title">Enter your phone</h1>
    <p class="sb-qlink-subtitle">We will send a one-time code to continue.</p>
    <form class="sb-qlink-stack" data-qlink-form>
      <div class="sb-qlink-error" data-qlink-error style="display: none;"></div>
      <label class="sb-qlink-field">
        Phone
        <input type="text" name="phone" class="sb-qlink-input" inputmode="numeric" autocomplete="tel" maxlength="10" placeholder="0501234567" value="" dir="ltr">
      </label>
      <label class="sb-qlink-field" data-qlink-code-field style="display: none;">
        Code
        <div class="sb-qlink-digit-wrap" dir="ltr">
          <div class="sb-qlink-digit-row" id="sb-qlink-code-container">
            <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="0" inputmode="numeric" autocomplete="one-time-code">
            <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="1" inputmode="numeric">
            <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="2" inputmode="numeric">
            <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="3" inputmode="numeric">
          </div>
          <input type="hidden" name="code" value="">
        </div>
      </label>
      <button type="submit" class="sb-qlink-button">Send code</button>
      <div class="sb-qlink-note">By continuing you agree to receive an SMS for verification.</div>
    </form>
  </div>
</div>
<script>
(function () {
  var root = document.querySelector('[data-qlink-form]');
  if (!root) return;
  var phoneInput = root.querySelector('input[name="phone"]');
  var codeContainer = document.getElementById('sb-qlink-code-container');
  var codeHidden = root.querySelector('input[name="code"]');

  if (phoneInput) {
    phoneInput.addEventListener('input', function () {
      this.value = (this.value || '').replace(/\D/g, '').slice(0, 10);
    });
  }

  function syncCode() {
    if (!codeHidden) return;
    var inputs = codeContainer.querySelectorAll('.sb-qlink-digit[data-qlink-code-idx]');
    var arr = [];
    for (var i = 0; i < inputs.length; i++) arr.push((inputs[i].value || '').replace(/\D/g, '').slice(0, 1));
    codeHidden.value = arr.join('');
  }
  function bindDigits(container, attr, sync) {
    var inputs = container.querySelectorAll('.sb-qlink-digit[' + attr + ']');
    for (var i = 0; i < inputs.length; i++) {
      (function (idx, inp) {
        inp.addEventListener('input', function () {
          var v = (inp.value || '').replace(/\D/g, '').slice(0, 1);
          inp.value = v;
          sync();
          if (v && inputs[idx + 1]) inputs[idx + 1].focus();
        });
        inp.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !inp.value && inputs[idx - 1]) inputs[idx - 1].focus();
        });
        inp.addEventListener('paste', function (e) {
          e.preventDefault();
          var text = (e.clipboardData || window.clipboardData).getData('text') || '';
          var nums = text.replace(/\D/g, '').slice(0, inputs.length - idx);
          for (var j = 0; j < nums.length; j++) { if (inputs[idx + j]) inputs[idx + j].value = nums[j]; }
          sync();
          var nextIdx = Math.min(idx + nums.length, inputs.length) - 1;
          if (inputs[nextIdx]) inputs[nextIdx].focus();
        });
      })(i, inputs[i]);
    }
  }
  if (codeContainer) bindDigits(codeContainer, 'data-qlink-code-idx', syncCode);
})();
</script>
