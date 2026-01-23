<template>
  <div class="stack">
    <div>
      <h1 class="text-title">בואו נתחבר</h1>
      <p class="text-muted">הזינו מספר טלפון וקבלו קוד אימות.</p>
    </div>

    <form class="stack" @submit.prevent="handleSubmit">
      <div v-if="error" class="text-danger text-sm">{{ error }}</div>

      <UiField label="טלפון">
        <UiInput v-model="phone" type="tel" placeholder="0500000000" />
      </UiField>

      <UiField v-if="step === 'code'" label="קוד אימות">
        <UiInput v-model="code" type="text" placeholder="123456" />
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
      </div>

      <UiButton type="submit" variant="primary" :disabled="isSubmitting">
        {{ submitLabel }}
      </UiButton>
    </form>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiField from '../../components/ui/UiField.vue';
import UiInput from '../../components/ui/UiInput.vue';

/**
 * OTP login page.
 */
defineOptions({ layout: AuthLayout });

const step = ref('phone');
const phone = ref('');
const code = ref('');
const firstName = ref('');
const lastName = ref('');
const email = ref('');
const error = ref(null);
const isSubmitting = ref(false);
const isOtpListening = ref(false);
const otpAbortController = ref(null);

const submitLabel = computed(() => {
  if (step.value === 'phone') return 'שלח קוד אימות';
  if (step.value === 'code') return 'אימות קוד';
  return 'המשך';
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

/**
 * Post JSON to the backend.
 *
 * @param {string} url
 * @param {object} payload
 * @returns {Promise<object>}
 */
async function postJson(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken || '',
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
 * Check if the browser supports Web OTP.
 *
 * @returns {boolean}
 */
function supportsWebOtp() {
  return typeof window !== 'undefined' && 'OTPCredential' in window && window.isSecureContext;
}

/**
 * Start listening for an SMS OTP code.
 *
 * @returns {Promise<void>}
 */
async function startOtpListener() {
  if (!supportsWebOtp() || isOtpListening.value || step.value !== 'code') return;

  isOtpListening.value = true;
  const controller = new AbortController();
  otpAbortController.value = controller;

  try {
    const otp = await navigator.credentials.get({
      otp: { transport: ['sms'] },
      signal: controller.signal,
    });

    if (otp?.code) {
      code.value = otp.code;
      handleSubmit();
    }
  } catch (_) {
    // Ignore errors or aborts.
  } finally {
    isOtpListening.value = false;
    otpAbortController.value = null;
  }
}

/**
 * Stop listening for OTP codes.
 *
 * @returns {void}
 */
function stopOtpListener() {
  if (otpAbortController.value) {
    otpAbortController.value.abort();
    otpAbortController.value = null;
  }
  isOtpListening.value = false;
}

/**
 * Handle the submit flow.
 *
 * @returns {void}
 */
function handleSubmit() {
  error.value = null;
  isSubmitting.value = true;

  if (step.value === 'phone') {
    postJson('/auth/otp/request', { phone: phone.value })
      .then(() => { step.value = 'code'; })
      .catch(() => { error.value = 'שליחת הקוד נכשלה.'; })
      .finally(() => { isSubmitting.value = false; });
    return;
  }

  if (step.value === 'code') {
    postJson('/auth/otp/verify', { phone: phone.value, code: code.value })
      .then((data) => {
        if (data.requires_registration) {
          step.value = 'register';
          return;
        }
        if (data.redirect) {
          window.location.href = data.redirect;
        }
      })
      .catch((err) => {
        error.value = err?.code?.[0] || err?.message || 'קוד האימות שגוי.';
      })
      .finally(() => { isSubmitting.value = false; });
    return;
  }

  postJson('/auth/register', {
    phone: phone.value,
    first_name: firstName.value,
    last_name: lastName.value,
    email: email.value || null,
  })
    .then((data) => {
      if (data.redirect) {
        window.location.href = data.redirect;
      }
    })
    .catch(() => { error.value = 'הרישום נכשל.'; })
    .finally(() => { isSubmitting.value = false; });
}

watch(step, (next) => {
  if (next === 'code') {
    startOtpListener();
    return;
  }

  stopOtpListener();
});
</script>
