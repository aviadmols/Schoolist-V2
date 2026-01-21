<template>
  <div class="stack">
    <div class="flex items-center justify-between">
      <h1 class="text-title">My Private Items</h1>
      <UiButton @click="openCreateModal" variant="primary">Add Item</UiButton>
    </div>

    <div v-if="items.length === 0" class="card card__body">
      <p class="text-muted text-center">No private items found. These are only visible to you.</p>
    </div>

    <div v-else class="stack">
      <div v-for="item in items" :key="item.id" class="card">
        <div class="card__body flex items-center justify-between">
          <div class="flex-1">
            <h3 class="text-body font-bold m-0">{{ item.title }}</h3>
            <p v-if="item.occurs_on_date" class="text-muted small m-0">Date: {{ formatDate(item.occurs_on_date) }}</p>
            <p v-if="item.content" class="text-muted small mt-1">{{ item.content }}</p>
          </div>
          <div class="flex gap-2">
            <UiButton @click="openEditModal(item)" variant="ghost" size="sm">Edit</UiButton>
            <UiButton @click="deleteItem(item)" variant="ghost" size="sm" class="text-danger">Delete</UiButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <UiModal v-model="showModal" :title="isEditing ? 'Edit Private Item' : 'New Private Item'">
      <form class="stack" @submit.prevent="submit">
        <UiField label="Title" :help="form.errors.title">
          <UiInput v-model="form.title" placeholder="e.g. Bring money for trip" />
        </UiField>

        <UiField label="Content (Optional)" :help="form.errors.content">
          <UiTextarea v-model="form.content" placeholder="Details only you can see..." />
        </UiField>

        <UiField label="Display Date (Optional)" :help="form.errors.occurs_on_date">
          <UiInput v-model="form.occurs_on_date" type="date" />
          <p class="text-xs text-muted mt-1">If set, will only show from 16:00 the day before.</p>
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
import UiTextarea from '../components/ui/UiTextarea.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  items: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
  title: '',
  content: '',
  occurs_on_date: '',
});

/**
 * Open create modal.
 */
function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  form.reset();
  showModal.value = true;
}

/**
 * Open edit modal.
 */
function openEditModal(item) {
  isEditing.value = true;
  editingId.value = item.id;
  form.title = item.title;
  form.content = item.content || '';
  form.occurs_on_date = item.occurs_on_date ? item.occurs_on_date.split('T')[0] : '';
  showModal.value = true;
}

/**
 * Submit form.
 */
function submit() {
  if (isEditing.value) {
    form.put(`/classrooms/private/${editingId.value}`, {
      onSuccess: () => { showModal.value = false; },
    });
  } else {
    form.post('/classrooms/private', {
      onSuccess: () => {
        showModal.value = false;
        form.reset();
      },
    });
  }
}

/**
 * Delete item.
 */
function deleteItem(item) {
  if (confirm('Are you sure you want to remove this private item?')) {
    router.delete(`/classrooms/private/${item.id}`);
  }
}

/**
 * Format date for display.
 */
function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-GB');
}
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.flex-1 { flex: 1; }
.m-0 { margin: 0; }
.mt-1 { margin-top: 0.25rem; }
.text-danger { color: #dc2626; }
</style>
