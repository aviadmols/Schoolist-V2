<template>
  <div class="stack">
    <div class="stack">
      <h1 class="text-title">Claim Link</h1>
      <p class="text-muted">Select a classroom to attach this link to.</p>
    </div>

    <div v-if="classrooms.length === 0" class="card card__body">
      <p>You don't have any classrooms where you are an owner or admin.</p>
      <UiButton href="/classrooms/create" variant="primary">Create a Classroom First</UiButton>
    </div>

    <form v-else class="stack" @submit.prevent="submit">
      <UiField label="Select Classroom" :help="form.errors.classroom_id">
        <UiSelect 
          v-model="form.classroom_id" 
          :options="classroomOptions"
          placeholder="Choose classroom..."
        />
      </UiField>

      <div class="flex gap-4">
        <UiButton type="submit" variant="primary" :disabled="form.processing">
          Claim Link
        </UiButton>
        <UiButton href="/classrooms" variant="ghost">Cancel</UiButton>
      </div>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiField from '../../components/ui/UiField.vue';
import UiSelect from '../../components/ui/UiSelect.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  token: String,
  classrooms: Array,
});

const form = useForm({
  classroom_id: '',
});

/**
 * Convert classrooms to select options.
 *
 * @returns {Array}
 */
const classroomOptions = computed(() => 
  props.classrooms.map(c => ({ value: c.id, label: c.name }))
);

/**
 * Submit the link claim form.
 *
 * @returns {void}
 */
function submit() {
  form.post('/classrooms/claim');
}
</script>
