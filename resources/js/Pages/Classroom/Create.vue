<template>
  <div class="stack">
    <div class="stack">
      <h1 class="text-title">Create Classroom</h1>
      <p class="text-muted">Start a new classroom for your students.</p>
    </div>

    <form class="stack" @submit.prevent="submit">
      <UiField label="Classroom Name" :help="form.errors.name">
        <UiInput v-model="form.name" placeholder="e.g. Grade 4A - Berner School" />
      </UiField>

      <div class="flex gap-4">
        <UiButton type="submit" variant="primary" :disabled="form.processing">
          Create
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
  name: '',
});

/**
 * Submit the create classroom form.
 *
 * @returns {void}
 */
function submit() {
  form.post('/classrooms');
}
</script>
