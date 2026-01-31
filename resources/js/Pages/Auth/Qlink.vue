<template>
  <div class="stack">
    <div>
      <h1 class="text-title">בואו נתחבר</h1>
      <p class="text-muted">כניסה מהירה באמצעות קישור.</p>
    </div>

    <form class="stack" @submit.prevent="handleSubmit">
      <div v-if="error" class="text-danger text-sm">{{ error }}</div>

      <UiField v-if="step === 'phone'" label="טלפון">
        <UiInput v-model="phone" type="tel" inputmode="numeric" maxlength="10" placeholder="0501234567" autocomplete="tel" />
      </UiField>

      <UiField v-if="step === 'code'" label="קוד אימות">
        <OtpInput v-model="code" />
      </UiField>

      <div v-if="step === 'register'" class="stack">
        <UiField label="שם פרטי">
          <UiInput v-model="firstName" type="text" />
        </UiField>
        <UiField label="שם משפחה">
          <UiInput v-model="lastName" type="text" />
        </UiField>
        <UiField label="אימייל (לא חובה)">
          <UiInput v-model="email" type="email" />
        </UiField>
        <UiField label="קוד כיתה (לא חובה)">
          <DigitInput v-model="joinCode" :length="4" />
        </UiField>
      </div>

      <UiButton type="submit" variant="primary" :disabled="isSubmitting">
        {{ submitLabel }}
      </UiButton>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiField from '../../components/ui/UiField.vue';
import UiInput from '../../components/ui/UiInput.vue';
import OtpInput from '../../components/ui/OtpInput.vue';
import DigitInput from '../../components/ui/DigitInput.vue';

/**
 * Qlink login page.
 */
defineOptions({ layout: AuthLayout });

const props = defineProps({
  token: String,
  is_valid: Boolean,
});

const step = ref('phone');
const phone = ref('');
const code = ref('');
const firstName = ref('');
const lastName = ref('');
const email = ref('');
const joinCode = ref('');
const error = ref(null);
const isSubmitting = ref(false);

const submitLabel = computed(() => {
  if (step.value === 'phone') return 'שלח קוד אימות';
  if (step.value === 'code') return 'אימות קוד';
  return 'המשך';
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

/**
 * Read a cookie by name.
 *
 * @param {string} name
 * @returns {string|null}
 */
function readCookie(name) {
  const match = document.cookie.match(new RegExp(`(^|;\\s*)${name}=([^;]*)`));
  return match ? decodeURIComponent(match[2]) : null;
}

/**
 * Post JSON to the backend.
 *
 * @param {string} url
 * @param {object} payload
 * @returns {Promise<object>}
 */
async function postJson(url, payload) {
  const xsrfToken = readCookie('XSRF-TOKEN');
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken || '',
      'X-XSRF-TOKEN': xsrfToken || '',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(payload),
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw data;
  }

  return data;
}

/**
 * Attempt auto-login using local token.
 *
 * @returns {Promise<void>}
 */
async function tryAutoLogin() {
  const token = window.localStorage.getItem('schoolist_qtoken');
  if (!token) return;

  try {
    const data = await postJson('/qlink/auto', { auth_token: token });
    if (data.redirect_url) {
      window.location.href = data.redirect_url;
    }
  } catch (_) {
    window.localStorage.removeItem('schoolist_qtoken');
  }
}

/**
 * Handle form submit.
 *
 * @returns {Promise<void>}
 */
async function handleSubmit() {
  error.value = null;
  isSubmitting.value = true;

  try {
    if (!props.is_valid) {
      error.value = 'הקישור אינו תקין.';
      return;
    }

    if (step.value === 'phone') {
      await postJson('/qlink/request', {
        phone: phone.value,
        qlink_token: props.token,
      });
      step.value = 'code';
      return;
    }

    if (step.value === 'code') {
      const data = await postJson('/qlink/verify', {
        phone: phone.value,
        code: code.value,
        qlink_token: props.token,
      });
      if (data.requires_registration) {
        step.value = 'register';
        return;
      }
      if (data.auth_token) {
        window.localStorage.setItem('schoolist_qtoken', data.auth_token);
      }
      if (data.redirect_url) {
        window.location.href = data.redirect_url;
      }
      return;
    }

  if (step.value === 'register') {
      const data = await postJson('/qlink/register', {
        phone: phone.value,
        first_name: firstName.value,
        last_name: lastName.value,
        email: email.value || null,
      join_code: joinCode.value || null,
        qlink_token: props.token,
      });
      if (data.auth_token) {
        window.localStorage.setItem('schoolist_qtoken', data.auth_token);
      }
      if (data.redirect_url) {
        window.location.href = data.redirect_url;
      }
      return;
  }
  } catch (err) {
    error.value = err?.message || 'שגיאה לא צפויה.';
  } finally {
    isSubmitting.value = false;
  }
}

onMounted(() => {
  tryAutoLogin();
});
</script>
