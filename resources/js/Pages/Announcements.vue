<template>
  <div class="stack">
    <div class="flex items-center justify-between">
      <h1 class="text-title">Announcements</h1>
      <UiButton @click="openModal" variant="primary">Add New</UiButton>
    </div>

    <div v-if="!announcements || announcements.length === 0" class="card card__body">
      <p class="text-muted text-center">No active announcements for now.</p>
    </div>

    <div v-else class="stack">
      <div v-for="item in announcements" :key="item.id" class="card">
        <div class="card__body flex items-start gap-4">
          <UiCheckbox 
            v-model="item.is_done"
            @update:model-value="toggleDone(item)" 
          />
          <div :class="{ 'done-item': item.is_done }">
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

        <div class="flex gap-3 justify-end mt-4">
          <UiButton @click="closeModal" variant="ghost" type="button">Cancel</UiButton>
          <UiButton type="submit" variant="primary" :disabled="form.processing">Create</UiButton>
        </div>
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
  announcements: {
    type: Array,
    default: () => [],
  },
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
 * Open create modal.
 */
function openModal() {
  showCreateModal.value = true;
}

/**
 * Close create modal.
 */
function closeModal() {
  showCreateModal.value = false;
}

/**
 * Submit the new announcement form.
 */
function submit() {
  form.post('/announcements', {
    onSuccess: () => {
      closeModal();
      form.reset();
    },
  });
}

/**
 * Toggle the done state of an announcement.
 *
 * @param {Object} item
 */
function toggleDone(item) {
  router.post(`/announcements/${item.id}/done`, {}, {
    preserveScroll: true,
  });
}
</script>

<style scoped>
.done-item {
  text-decoration: line-through;
  opacity: 0.6;
}
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-end { justify-content: flex-end; }
.items-center { align-items: center; }
.items-start { align-items: flex-start; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.gap-4 { gap: 1rem; }
.mt-1 { margin-top: 0.25rem; }
.mt-4 { margin-top: 1rem; }
.text-xs { font-size: 0.75rem; }
.uppercase { text-transform: uppercase; }
.font-bold { font-weight: 700; }
</style>
