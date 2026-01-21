<template>
  <div class="stack">
    <div class="flex items-center justify-between">
      <h1 class="text-title">Holidays & Days Off</h1>
      <UiButton @click="openCreateModal" variant="primary">Add Holiday</UiButton>
    </div>

    <div v-if="holidays.length === 0" class="card card__body">
      <p class="text-muted text-center">No upcoming holidays scheduled.</p>
    </div>

    <div v-else class="stack">
      <div v-for="holiday in holidays" :key="holiday.id" class="card">
        <div class="card__body flex items-center justify-between">
          <div>
            <h3 class="text-body font-bold m-0">{{ holiday.name }}</h3>
            <p class="text-muted small m-0">
              {{ formatDate(holiday.start_date) }} 
              <span v-if="holiday.start_date !== holiday.end_date"> - {{ formatDate(holiday.end_date) }}</span>
            </p>
            <p v-if="holiday.description" class="text-muted small mt-1">{{ holiday.description }}</p>
          </div>
          <div class="flex gap-2">
            <UiButton @click="openEditModal(holiday)" variant="ghost" size="sm">Edit</UiButton>
            <UiButton @click="deleteHoliday(holiday)" variant="ghost" size="sm" class="text-danger">Delete</UiButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <UiModal v-model="showModal" :title="isEditing ? 'Edit Holiday' : 'Add Holiday'">
      <form class="stack" @submit.prevent="submit">
        <UiField label="Holiday Name" :help="form.errors.name">
          <UiInput v-model="form.name" placeholder="e.g. Passover Break" />
        </UiField>

        <div class="flex gap-4">
          <UiField label="Start Date" :help="form.errors.start_date" class="flex-1">
            <UiInput v-model="form.start_date" type="date" />
          </UiField>
          <UiField label="End Date" :help="form.errors.end_date" class="flex-1">
            <UiInput v-model="form.end_date" type="date" />
          </UiField>
        </div>

        <UiField label="Description (Optional)" :help="form.errors.description">
          <UiInput v-model="form.description" placeholder="Additional info..." />
        </UiField>

        <div class="flex gap-3 justify-end mt-4">
          <UiButton @click="showModal = false" variant="ghost" type="button">Cancel</UiButton>
          <UiButton type="submit" variant="primary" :disabled="form.processing">
            {{ isEditing ? 'Update' : 'Create' }}
          </UiButton>
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
import UiModal from '../components/ui/UiModal.vue';
import UiField from '../components/ui/UiField.vue';
import UiInput from '../components/ui/UiInput.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  holidays: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
  name: '',
  start_date: '',
  end_date: '',
  description: '',
});

/**
 * Open modal for new holiday.
 */
function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  form.reset();
  showModal.value = true;
}

/**
 * Open modal to edit existing holiday.
 */
function openEditModal(holiday) {
  isEditing.value = true;
  editingId.value = holiday.id;
  form.name = holiday.name;
  form.start_date = holiday.start_date;
  form.end_date = holiday.end_date;
  form.description = holiday.description || '';
  showModal.value = true;
}

/**
 * Submit form (create or update).
 */
function submit() {
  if (isEditing.value) {
    form.put(`/holidays/${editingId.value}`, {
      onSuccess: () => {
        showModal.value = false;
      },
    });
  } else {
    form.post('/holidays', {
      onSuccess: () => {
        showModal.value = false;
        form.reset();
      },
    });
  }
}

/**
 * Delete a holiday.
 */
function deleteHoliday(holiday) {
  if (confirm('Are you sure you want to remove this holiday?')) {
    router.delete(`/holidays/${holiday.id}`);
  }
}

/**
 * Basic date formatter.
 */
function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-GB');
}
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.gap-4 { gap: 1rem; }
.flex-1 { flex: 1; }
.m-0 { margin: 0; }
.mt-1 { margin-top: 0.25rem; }
.text-danger { color: #dc2626; }
</style>
