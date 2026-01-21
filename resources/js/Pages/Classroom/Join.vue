<template>
  <div class="stack">
    <div class="stack">
      <h1 class="text-title">Join Classroom</h1>
      <p class="text-muted">Enter the 10-digit join code provided by your teacher.</p>
    </div>

    <form class="stack" @submit.prevent="submit">
      <UiField label="Join Code" :help="form.errors.join_code">
        <UiInput v-model="form.join_code" placeholder="10-digit code" maxlength="10" />
      </UiField>

      <div class="flex gap-4">
        <UiButton type="submit" variant="primary" :disabled="form.processing">
          Join
        </UiButton>
        <UiButton href="/classrooms" variant="ghost">Cancel</UiButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiField from '../../components/ui/UiField.vue';
import UiInput from '../../components/ui/UiInput.vue';

defineOptions({ layout: AppLayout });

const form = useForm({
  join_code: '',
});

/**
 * Submit the join classroom form.
 *
 * @returns {void}
 */
function submit() {
  form.post('/classrooms/join');
}
</script>
