<style>
  .sb-qlink-digit-wrap { display: flex; flex-direction: column; gap: 0.5rem; }
  .sb-qlink-digit-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  .sb-qlink-digit { width: 2.25rem; height: 2.5rem; text-align: center; font-size: 1.125rem; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; }
  .sb-qlink-digit:focus { outline: none; border-color: #2563eb; }
  .sb-qlink-input { display: block; width: 100%; max-width: 14rem; padding: 0.5rem 0.75rem; font-size: 1.125rem; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; }
  .sb-qlink-input:focus { outline: none; border-color: #2563eb; }
  .sb-qlink-error { color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem; }
  .sb-qlink-back { background: none; border: none; color: #64748b; cursor: pointer; font-size: 0.875rem; margin-top: 0.5rem; }
  .sb-qlink-divider { height: 1px; background: #e2e8f0; margin: 1.25rem 0; }
  .sb-qlink-links { font-size: 0.875rem; color: #64748b; }
  .sb-qlink-links a { color: #2563eb; text-decoration: none; }
</style>
<div class="sb-qlink-page">
  <div class="sb-qlink-card" data-qlink-token="{{ $page['token'] ?? '' }}">
    <h1 class="sb-qlink-title">בואו נתחבר</h1>
    <p class="sb-qlink-subtitle">כניסה מהירה דרך הקישור — הזינו טלפון וקבלו קוד.</p>
    <form id="sb-qlink-form" class="sb-qlink-stack" data-qlink-form>
      <input type="hidden" id="sb-qlink-step" value="phone">

      <div id="sb-qlink-step-phone" class="sb-qlink-step">
        <label class="sb-qlink-field">
          טלפון
          <input type="text" name="phone" id="sb-qlink-phone" class="sb-qlink-input" inputmode="numeric" autocomplete="tel" maxlength="10" placeholder="0501234567" value="" dir="ltr">
        </label>
        <p id="sb-qlink-error" class="sb-qlink-error" style="display: none;"></p>
        <button type="submit" class="sb-qlink-button" id="sb-qlink-submit">שלח קוד אימות</button>
      </div>

      <div id="sb-qlink-step-code" class="sb-qlink-step" style="display: none;">
        <label class="sb-qlink-field">
          קוד אימות
          <div class="sb-qlink-digit-wrap" dir="ltr">
            <div class="sb-qlink-digit-row" id="sb-qlink-code-container">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="0" inputmode="numeric" autocomplete="one-time-code">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="1" inputmode="numeric">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="2" inputmode="numeric">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-qlink-code-idx="3" inputmode="numeric">
            </div>
            <input type="hidden" name="code" id="sb-qlink-code" value="">
          </div>
        </label>
        <p id="sb-qlink-error-code" class="sb-qlink-error" style="display: none;"></p>
        <button type="submit" class="sb-qlink-button" id="sb-qlink-verify">אימות קוד</button>
        <button type="button" class="sb-qlink-back" id="sb-qlink-back">← חזרה</button>
      </div>

      <div id="sb-qlink-step-register" class="sb-qlink-step" style="display: none;">
        <p class="sb-qlink-subtitle" style="margin-bottom: 1rem;">השלימו פרטים והזינו קוד כיתה — הקישור הזה יפתח תמיד את הכיתה שאליה הצטרפתם.</p>
        <label class="sb-qlink-field">
          שם פרטי
          <input type="text" name="first_name" id="sb-qlink-first" class="sb-qlink-input" required>
        </label>
        <label class="sb-qlink-field">
          שם משפחה
          <input type="text" name="last_name" id="sb-qlink-last" class="sb-qlink-input" required>
        </label>
        <label class="sb-qlink-field">
          אימייל (לא חובה)
          <input type="email" name="email" id="sb-qlink-email" class="sb-qlink-input">
        </label>
        <label class="sb-qlink-field">
          קוד כיתה (4 ספרות) — אם יש לכם, ההקישור יפתח את הכיתה הזו
          <div class="sb-qlink-digit-wrap" dir="ltr">
            <div class="sb-qlink-digit-row" id="sb-qlink-join-container">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-join-idx="0" inputmode="numeric">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-join-idx="1" inputmode="numeric">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-join-idx="2" inputmode="numeric">
              <input type="text" maxlength="1" class="sb-qlink-digit" data-join-idx="3" inputmode="numeric">
            </div>
            <input type="hidden" name="join_code" id="sb-qlink-join-code" value="">
          </div>
        </label>
        <p id="sb-qlink-error-reg" class="sb-qlink-error" style="display: none;"></p>
        <button type="submit" class="sb-qlink-button" id="sb-qlink-register">המשך</button>
      </div>
    </form>

    <div class="sb-qlink-divider"></div>
    <div class="sb-qlink-links">
      <span>כבר יש לכם חשבון?</span>
      <a href="{{ url('/login') }}">התחברות רגילה</a>
    </div>
  </div>
</div>
<script>
(function () {
  var card = document.querySelector('[data-qlink-token]');
  var token = (card && card.getAttribute('data-qlink-token')) || '';
  var form = document.getElementById('sb-qlink-form');
  var stepEl = document.getElementById('sb-qlink-step');
  var stepPhone = document.getElementById('sb-qlink-step-phone');
  var stepCode = document.getElementById('sb-qlink-step-code');
  var stepRegister = document.getElementById('sb-qlink-step-register');
  var phoneInput = document.getElementById('sb-qlink-phone');
  var codeHidden = document.getElementById('sb-qlink-code');
  var joinHidden = document.getElementById('sb-qlink-join-code');
  var errorEl = document.getElementById('sb-qlink-error');
  var errorCodeEl = document.getElementById('sb-qlink-error-code');
  var errorRegEl = document.getElementById('sb-qlink-error-reg');
  var submitBtn = document.getElementById('sb-qlink-submit');
  var verifyBtn = document.getElementById('sb-qlink-verify');
  var backBtn = document.getElementById('sb-qlink-back');
  var registerBtn = document.getElementById('sb-qlink-register');

  function csrf() {
    var m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
  }

  function showError(el, msg) {
    if (el) { el.textContent = msg || ''; el.style.display = msg ? 'block' : 'none'; }
  }

  function setStep(step) {
    if (stepEl) stepEl.value = step;
    if (stepPhone) stepPhone.style.display = step === 'phone' ? 'block' : 'none';
    if (stepCode) stepCode.style.display = step === 'code' ? 'block' : 'none';
    if (stepRegister) stepRegister.style.display = step === 'register' ? 'block' : 'none';
    showError(errorEl, ''); showError(errorCodeEl, ''); showError(errorRegEl, '');
  }

  if (backBtn) backBtn.addEventListener('click', function () { setStep('phone'); });

  function syncCode() {
    if (!codeHidden) return;
    var container = document.getElementById('sb-qlink-code-container');
    if (!container) return;
    var inputs = container.querySelectorAll('.sb-qlink-digit[data-qlink-code-idx]');
    var arr = [];
    for (var i = 0; i < inputs.length; i++) arr.push((inputs[i].value || '').replace(/\D/g, '').slice(0, 1));
    codeHidden.value = arr.join('');
  }

  function syncJoin() {
    if (!joinHidden) return;
    var container = document.getElementById('sb-qlink-join-container');
    if (!container) return;
    var inputs = container.querySelectorAll('.sb-qlink-digit[data-join-idx]');
    var arr = [];
    for (var i = 0; i < inputs.length; i++) arr.push((inputs[i].value || '').replace(/\D/g, '').slice(0, 1));
    joinHidden.value = arr.join('');
  }

  function bindDigits(container, attr, sync) {
    if (!container) return;
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

  var codeContainer = document.getElementById('sb-qlink-code-container');
  var joinContainer = document.getElementById('sb-qlink-join-container');
  if (codeContainer) bindDigits(codeContainer, 'data-qlink-code-idx', syncCode);
  if (joinContainer) bindDigits(joinContainer, 'data-join-idx', syncJoin);

  if (phoneInput) {
    phoneInput.addEventListener('input', function () {
      this.value = (this.value || '').replace(/\D/g, '').slice(0, 10);
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      syncCode();
      syncJoin();
      var step = stepEl ? stepEl.value : 'phone';
      var phone = (phoneInput && phoneInput.value) ? phoneInput.value.replace(/\D/g, '').slice(0, 10) : '';

      if (step === 'phone') {
        showError(errorEl, '');
        if (phone.length !== 10) {
          showError(errorEl, 'נא להזין מספר טלפון בן 10 ספרות.');
          return;
        }
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'שולח...'; }
        fetch('{{ route("qlink.request") }}', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          body: JSON.stringify({ phone: phone, qlink_token: token })
        })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'שלח קוד אימות'; }
            if (res.ok) {
              stepEl.value = 'code';
              setStep('code');
              var firstCode = stepCode && stepCode.querySelector('.sb-qlink-digit[data-qlink-code-idx="0"]');
              if (firstCode) firstCode.focus();
            } else {
              var msg = (res.data && res.data.errors && res.data.errors.phone) ? (Array.isArray(res.data.errors.phone) ? res.data.errors.phone[0] : res.data.errors.phone) : (res.data && res.data.message) || 'שגיאה בשליחת הקוד.';
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
        var code = (codeHidden && codeHidden.value) ? codeHidden.value.replace(/\D/g, '').slice(0, 4) : '';
        showError(errorCodeEl, '');
        if (code.length !== 4) {
          showError(errorCodeEl, 'נא להזין קוד בן 4 ספרות.');
          return;
        }
        if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'מאמת...'; }
        fetch('{{ route("qlink.verify") }}', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          body: JSON.stringify({ phone: phone, code: code, qlink_token: token })
        })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
            if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'אימות קוד'; }
            if (res.ok && res.data && res.data.redirect_url) {
              window.location.href = res.data.redirect_url;
              return;
            }
            if (res.ok && res.data && res.data.requires_registration) {
              stepEl.value = 'register';
              setStep('register');
              var firstInput = document.getElementById('sb-qlink-first');
              if (firstInput) firstInput.focus();
              return;
            }
            showError(errorCodeEl, (res.data && res.data.message) || 'הקוד שגוי או שפג תוקפו.');
          })
          .catch(function () {
            if (verifyBtn) { verifyBtn.disabled = false; verifyBtn.textContent = 'אימות קוד'; }
            showError(errorCodeEl, 'שגיאה בתקשורת. נסו שוב.');
          });
        return;
      }

      if (step === 'register') {
        var firstName = (document.getElementById('sb-qlink-first') && document.getElementById('sb-qlink-first').value) ? document.getElementById('sb-qlink-first').value.trim() : '';
        var lastName = (document.getElementById('sb-qlink-last') && document.getElementById('sb-qlink-last').value) ? document.getElementById('sb-qlink-last').value.trim() : '';
        var emailVal = (document.getElementById('sb-qlink-email') && document.getElementById('sb-qlink-email').value) ? document.getElementById('sb-qlink-email').value.trim() : '';
        var joinCodeVal = (joinHidden && joinHidden.value) ? joinHidden.value.replace(/\D/g, '').slice(0, 4) : '';
        showError(errorRegEl, '');
        if (!firstName || !lastName) {
          showError(errorRegEl, 'נא למלא שם פרטי ומשפחה.');
          return;
        }
        if (registerBtn) { registerBtn.disabled = true; registerBtn.textContent = 'שומר...'; }
        fetch('{{ route("qlink.register") }}', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          body: JSON.stringify({
            phone: phone,
            first_name: firstName,
            last_name: lastName,
            email: emailVal || null,
            join_code: joinCodeVal || null,
            qlink_token: token
          })
        })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
            if (registerBtn) { registerBtn.disabled = false; registerBtn.textContent = 'המשך'; }
            if (res.ok && res.data && res.data.redirect_url) {
              window.location.href = res.data.redirect_url;
            } else {
              showError(errorRegEl, (res.data && res.data.message) || (res.data && res.data.errors && res.data.errors.join_code && res.data.errors.join_code[0]) || 'שגיאה בשמירה.');
            }
          })
          .catch(function () {
            if (registerBtn) { registerBtn.disabled = false; registerBtn.textContent = 'המשך'; }
            showError(errorRegEl, 'שגיאה בתקשורת. נסו שוב.');
          });
      }
    });
  }
})();
</script>
