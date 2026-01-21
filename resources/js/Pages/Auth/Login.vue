<template>
  <div class="stack">
    <div>
      <h1 class="text-title">Sign in</h1>
      <p class="text-muted">This page demonstrates the AuthLayout and UI kit.</p>
    </div>

    <form class="stack" @submit.prevent="submitLoginForm">
      <UiField label="Email">
        <UiInput v-model="email" type="email" placeholder="name@example.com" />
      </UiField>

      <UiField label="Password">
        <UiInput v-model="password" type="password" placeholder="••••••••" />
      </UiField>

      <UiCheckbox v-model="remember">Remember me</UiCheckbox>

      <UiButton type="submit" variant="primary" :disabled="isSubmitting">
        Continue
      </UiButton>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiCheckbox from '../../components/ui/UiCheckbox.vue';
import UiField from '../../components/ui/UiField.vue';
import UiInput from '../../components/ui/UiInput.vue';

/**
 * Login page.
 */
defineOptions({ layout: AuthLayout });

const SUBMIT_SIMULATION_DELAY_MS = 250;

const email = ref('');
const password = ref('');
const remember = ref(false);
const isSubmitting = ref(false);

/**
 * Reset the submitting state.
 *
 * @returns {void}
 */
function resetSubmittingState() {
  isSubmitting.value = false;
}

/**
 * Submit the login form (placeholder).
 *
 * @returns {void}
 */
function submitLoginForm() {
  isSubmitting.value = true;

  window.setTimeout(resetSubmittingState, SUBMIT_SIMULATION_DELAY_MS);
}
</script>

