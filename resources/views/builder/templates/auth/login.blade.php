<style>
  .sb-digit-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
  .sb-digit-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  .sb-digit { width: 2.25rem; height: 2.5rem; text-align: center; font-size: 1.125rem; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; }
  .sb-digit:focus { outline: none; border-color: #2563eb; }
  .sb-digit-sep { padding-bottom: 0.25rem; font-weight: 600; color: #64748b; }
  .sb-login-input { display: block; width: 100%; max-width: 14rem; padding: 0.5rem 0.75rem; font-size: 1.125rem; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; }
  .sb-login-input:focus { outline: none; border-color: #2563eb; }
</style>
<div class="sb-login-page">
  <div class="sb-login-card">
    <h1 class="sb-login-title">בואו נתחבר</h1>
    <p class="sb-login-subtitle">אחר כך תוכלו לצרף כיתה מהפרופיל. הקישור לא ייקשר לכיתה.</p>
    <form id="sb-login-form" class="sb-login-stack" method="post" action="">
      @csrf
      <input type="hidden" name="_step" id="sb-login-step" value="phone">

      <div id="sb-step-phone" class="sb-login-step">
        <label class="sb-login-field">
          טלפון
          <input type="text" name="phone" id="sb-login-phone" class="sb-login-input" inputmode="numeric" autocomplete="tel" maxlength="10" placeholder="0501234567" value="" dir="ltr">
        </label>
        <p id="sb-login-error" class="sb-login-error" style="display: none;"></p>
        <button type="submit" class="sb-login-button" id="sb-login-submit">שלח קוד אימות</button>
      </div>

      <div id="sb-step-code" class="sb-login-step" style="display: none;">
        <label class="sb-login-field">
          קוד אימות
          <div class="sb-digit-wrap" dir="ltr">
            <div class="sb-digit-row" id="sb-code-container">
              <input type="text" maxlength="1" class="sb-digit" data-code-idx="0" inputmode="numeric" autocomplete="one-time-code">
              <input type="text" maxlength="1" class="sb-digit" data-code-idx="1" inputmode="numeric">
              <input type="text" maxlength="1" class="sb-digit" data-code-idx="2" inputmode="numeric">
              <input type="text" maxlength="1" class="sb-digit" data-code-idx="3" inputmode="numeric">
            </div>
            <input type="hidden" name="code" id="sb-login-code" value="">
          </div>
        </label>
        <p id="sb-login-error-code" class="sb-login-error" style="display: none;"></p>
        <button type="submit" class="sb-login-button" id="sb-login-verify">אימות קוד</button>
        <button type="button" class="sb-login-back" id="sb-login-back">← חזרה</button>
      </div>
    </form>

    <div class="sb-login-divider"></div>
    <div class="sb-login-links">
      <span>עדיין לא הצטרפתם?</span>
      <a href="{{ route('auth.code') }}">מוזמנים כאן</a>
    </div>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('sb-login-form');
  var stepPhone = document.getElementById('sb-step-phone');
  var stepCode = document.getElementById('sb-step-code');
  var phoneInput = document.getElementById('sb-login-phone');
  var codeInput = document.getElementById('sb-login-code');
  var errorEl = document.getElementById('sb-login-error');
  var errorCodeEl = document.getElementById('sb-login-error-code');
  var submitBtn = document.getElementById('sb-login-submit');
  var verifyBtn = document.getElementById('sb-login-verify');
  var backBtn = document.getElementById('sb-login-back');

  function csrf() {
    var m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
  }

  function showError(el, msg) {
    if (el) { el.textContent = msg || ''; el.style.display = msg ? 'block' : 'none'; }
  }

  function setStep(step) {
    if (step === 'phone') {
      stepPhone.style.display = 'block';
      stepCode.style.display = 'none';
      showError(errorEl, '');
      showError(errorCodeEl, '');
    } else {
      stepPhone.style.display = 'none';
      stepCode.style.display = 'block';
      showError(errorEl, '');
      showError(errorCodeEl, '');
    }
  }

  if (backBtn) {
    backBtn.addEventListener('click', function () {
      setStep('phone');
    });
  }

  function syncCodeHidden() {
    if (!codeInput) return;
    var digits = stepCode.querySelectorAll('.sb-digit[data-code-idx]');
    var arr = [];
    for (var i = 0; i < digits.length; i++) { arr.push((digits[i].value || '').replace(/\D/g, '').slice(0, 1)); }
    codeInput.value = arr.join('');
  }

  function bindDigitInputs(container, attr, hiddenSync) {
    var inputs = container.querySelectorAll('.sb-digit[' + attr + ']');
    for (var i = 0; i < inputs.length; i++) {
      (function (idx, inp) {
        inp.addEventListener('input', function () {
          var v = (inp.value || '').replace(/\D/g, '').slice(0, 1);
          inp.value = v;
          hiddenSync();
          if (v && inputs[idx + 1]) inputs[idx + 1].focus();
        });
        inp.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !inp.value && inputs[idx - 1]) inputs[idx - 1].focus();
        });
        inp.addEventListener('paste', function (e) {
          e.preventDefault();
          var text = (e.clipboardData || window.clipboardData).getData('text') || '';
          var nums = text.replace(/\D/g, '').slice(0, inputs.length - idx);
          for (var j = 0; j < nums.length; j++) {
            var k = idx + j;
            if (inputs[k]) { inputs[k].value = nums[j]; }
          }
          hiddenSync();
          var nextIdx = Math.min(idx + nums.length, inputs.length) - 1;
          if (inputs[nextIdx]) inputs[nextIdx].focus();
        });
      })(i, inputs[i]);
    }
  }

  var codeContainer = document.getElementById('sb-code-container');
  if (codeContainer) bindDigitInputs(codeContainer, 'data-code-idx', syncCodeHidden);

  if (phoneInput) {
    phoneInput.addEventListener('input', function () {
      this.value = (this.value || '').replace(/\D/g, '').slice(0, 10);
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      syncCodeHidden();
      var step = document.getElementById('sb-login-step').value;
      var phone = (phoneInput && phoneInput.value) ? phoneInput.value.replace(/\D/g, '').slice(0, 10) : '';

      if (step === 'phone') {
        showError(errorEl, '');
        if (phone.length !== 10) {
          showError(errorEl, 'נא להזין מספר טלפון בן 10 ספרות.');
          return;
        }
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'שולח...'; }
        fetch('{{ route("auth.otp.request") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ phone: phone })
        })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'שלח קוד אימות'; }
            if (res.ok) {
              document.getElementById('sb-login-step').value = 'code';
              setStep('code');
              var firstCode = stepCode.querySelector('.sb-digit[data-code-idx="0"]');
              if (firstCode) firstCode.focus();
            } else {
              var msg = (res.data && res.data.errors && res.data.errors.phone) ? (Array.isArray(res.data.errors.phone) ? res.data.errors.phone[0] : res.data.errors.phone) : (res.data && res.data.message) ? (Array.isArray(res.data.message) ? res.data.message[0] : res.data.message) : 'שגיאה בשליחת הקוד.';
              showError(errorEl, msg);
            }
          })
          .catch(function () {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'שלח קוד אימות'; }
            showError(errorEl, 'שגיאה בתקשורת. נסו שוב.');
          });
        return;
      }

      if (step === 'code') {
        var code = (codeInput && codeInput.value) ? codeInput.value.replace(/\D/g, '').slice(0, 4) : '';
        showError(errorCodeEl, '');
        if (code.length !== 4) {
          showError(errorCodeEl, 'נא להזין קוד בן 4 ספרות.');
          return;
        }
        if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'מאמת...'; }
        fetch('{{ route("auth.otp.verify") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ phone: phone, code: code })
        })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
            if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'אימות קוד'; }
            if (res.ok && res.data && res.data.redirect) {
              window.location.href = res.data.redirect;
              return;
            }
            if (res.ok && res.data && res.data.requires_registration) {
              window.location.href = '{{ route("auth.code") }}?phone=' + encodeURIComponent(phone);
              return;
            }
            showError(errorCodeEl, (res.data && res.data.errors && res.data.errors.code) ? (Array.isArray(res.data.errors.code) ? res.data.errors.code[0] : res.data.errors.code) : (res.data && res.data.message) || 'הקוד שגוי או שפג תוקפו.');
          })
          .catch(function () {
            if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'אימות קוד'; }
            showError(errorCodeEl, 'שגיאה בתקשורת. נסו שוב.');
          });
      }
    });
  }
})();
</script>
