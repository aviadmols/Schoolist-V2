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

      const phoneInput = root.querySelector('input[name="phone"]');
      const codeInput = root.querySelector('input[name="code"]');
      const phone = phoneInput ? phoneInput.value.replace(/\D/g, '').slice(0, 10) : '';
      const code = codeInput ? codeInput.value.replace(/\D/g, '').slice(0, 4) : '';

      try {
        if (step === 'phone') {
          await requestJson('/qlink/request', { phone, qlink_token: token });
          step = 'code';
          codeField.style.display = 'grid';
          submitButton.textContent = 'Verify code';
          const firstCode = root.querySelector('.sb-qlink-digit[data-qlink-code-idx="0"]');
          if (firstCode) firstCode.focus();
          return;
        }

        const data = await requestJson('/qlink/verify', { phone, code, qlink_token: token });
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
        }
      } catch (error) {
        const message = error?.message || error?.error || 'Something went wrong. Please try again.';
        errorEl.textContent = message;
        errorEl.style.display = 'block';
      }
    });
  })();
