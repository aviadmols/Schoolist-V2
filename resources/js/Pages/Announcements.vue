<template>
  <div class="stack">
    <div class="flex items-center justify-between">
      <h1 class="text-title">Announcements</h1>
      <UiButton @click="showCreateModal = true" variant="primary">Add New</UiButton>
    </div>

    <div v-if="announcements.length === 0" class="card card__body">
      <p class="text-muted">No active announcements for now.</p>
    </div>

    <div v-else class="stack">
      <div v-for="item in announcements" :key="item.id" class="card">
        <div class="card__body flex items-start gap-4">
          <UiCheckbox 
            :model-value="item.is_done" 
            @update:model-value="toggleDone(item)" 
          />
          <div :class="{ 'line-through opacity-60': item.is_done }">
            <div class="flex items-center gap-2">
              <span class="text-xs uppercase font-bold text-muted">{{ item.type }}</span>
              <h3 class="text-body font-bold">{{ item.title }}</h3>
            </div>
            <p v-if="item.content" class="text-muted small mt-1">{{ item.content }}</p>
          </div>
        </div>
      </div>
    </div>

    <UiModal v-model="showCreateModal" title="New Announcement">
      <form class="stack" @submit.prevent="submit">
        <UiField label="Type">
          <UiSelect v-model="form.type" :options="typeOptions" />
        </UiField>

        <UiField label="Title" :help="form.errors.title">
          <UiInput v-model="form.title" placeholder="Homework: Math p.42" />
        </UiField>

        <UiField label="Content">
          <UiTextarea v-model="form.content" placeholder="Additional details..." />
        </UiField>

        <UiField label="Date (Optional)">
          <UiInput v-model="form.occurs_on_date" type="date" />
        </UiField>

        <template #footer>
          <UiButton type="submit" variant="primary" :disabled="form.processing">Create</UiButton>
          <UiButton @click="showCreateModal = false" variant="ghost">Cancel</UiButton>
        </template>
      </form>
    </UiModal>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import UiButton from '../components/ui/UiButton.vue';
import UiCheckbox from '../components/ui/UiCheckbox.vue';
import UiModal from '../components/ui/UiModal.vue';
import UiField from '../components/ui/UiField.vue';
import UiInput from '../components/ui/UiInput.vue';
import UiTextarea from '../components/ui/UiTextarea.vue';
import UiSelect from '../components/ui/UiSelect.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  announcements: Array,
});

const showCreateModal = ref(false);

const typeOptions = [
  { value: 'message', label: 'Message' },
  { value: 'homework', label: 'Homework' },
  { value: 'event', label: 'Event' },
];

const form = useForm({
  type: 'message',
  title: '',
  content: '',
  occurs_on_date: '',
});

/**
 * Submit the new announcement form.
 *
 * @returns {void}
 */
function submit() {
  form.post('/announcements', {
    onSuccess: () => {
      showCreateModal.value = false;
      form.reset();
    },
  });
}

/**
 * Toggle the done state of an announcement.
 *
 * @param {Object} item
 * @returns {void}
 */
function toggleDone(item) {
  router.post(`/announcements/${item.id}/done`);
}
</script>

<style scoped>
.line-through {
  text-decoration: line-through;
}
</style>
