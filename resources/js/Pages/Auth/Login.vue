<template>
  <div class="stack">
    <div>
      <h1 class="text-title">בואו נתחבר</h1>
      <p class="text-muted">כניסה עם אימייל וסיסמה.</p>
    </div>

    <form class="stack" @submit.prevent="submitLoginForm">
      <div v-if="error" class="text-danger text-sm">{{ error }}</div>

      <UiField label="אימייל">
        <UiInput v-model="email" type="email" placeholder="name@example.com" />
      </UiField>

      <UiField label="סיסמה">
        <UiInput v-model="password" type="password" placeholder="••••••••" />
      </UiField>

      <UiCheckbox v-model="remember">זכור אותי</UiCheckbox>

      <UiButton type="submit" variant="primary" :disabled="isSubmitting">
        התחברות
      </UiButton>

      <UiButton variant="ghost" href="/auth/code" class="text-sm">
        התחבר עם קוד
      </UiButton>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiCheckbox from '../../components/ui/UiCheckbox.vue';
import UiField from '../../components/ui/UiField.vue';
import UiInput from '../../components/ui/UiInput.vue';

/**
 * Login page.
 */
defineOptions({ layout: AuthLayout });

const email = ref('');
const password = ref('');
const remember = ref(false);
const isSubmitting = ref(false);
const error = ref(null);

/**
 * Submit the login form.
 *
 * @returns {void}
 */
function submitLoginForm() {
  isSubmitting.value = true;
  error.value = null;

  router.post('/login', {
    email: email.value,
    password: password.value,
    remember: remember.value,
  }, {
    onFinish: () => {
      isSubmitting.value = false;
    },
    onError: (errors) => {
      error.value = errors.email || 'Login failed. Please check your credentials.';
    }
  });
}
</script>

