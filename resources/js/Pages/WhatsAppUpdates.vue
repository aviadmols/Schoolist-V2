<template>
  <div class="stack">
    <div class="flex items-center justify-between">
      <h1 class="text-title">WhatsApp & Updates</h1>
      <UiButton @click="openCreateModal" variant="primary">Add Group</UiButton>
    </div>

    <div v-if="updates.length === 0" class="card card__body">
      <p class="text-muted text-center">No WhatsApp groups or updates added yet.</p>
    </div>

    <div v-else class="stack">
      <div v-for="update in updates" :key="update.id" class="card">
        <div class="card__body flex items-center justify-between">
          <div class="flex items-center gap-3">
             <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
               <span class="text-xl">💬</span>
             </div>
             <div>
               <h3 class="text-body font-bold m-0">{{ update.title }}</h3>
               <a :href="update.url" target="_blank" class="text-muted small m-0 hover:underline">Join Group</a>
             </div>
          </div>
          <div class="flex gap-2">
            <UiButton @click="openEditModal(update)" variant="ghost" size="sm">Edit</UiButton>
            <UiButton @click="deleteUpdate(update)" variant="ghost" size="sm" class="text-danger">Delete</UiButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <UiModal v-model="showModal" :title="isEditing ? 'Edit Update Link' : 'Add WhatsApp/Update Link'">
      <form class="stack" @submit.prevent="submit">
        <UiField label="Title" :help="form.errors.title">
          <UiInput v-model="form.title" placeholder="e.g. Parents WhatsApp Group" />
        </UiField>

        <UiField label="URL" :help="form.errors.url">
          <UiInput v-model="form.url" placeholder="https://chat.whatsapp.com/..." />
        </UiField>

        <template #footer>
          <UiButton type="submit" variant="primary" :disabled="form.processing">
            {{ isEditing ? 'Update' : 'Create' }}
          </UiButton>
          <UiButton @click="showModal = false" variant="ghost">Cancel</UiButton>
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
import UiModal from '../components/ui/UiModal.vue';
import UiField from '../components/ui/UiField.vue';
import UiInput from '../components/ui/UiInput.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  updates: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
  title: '',
  url: '',
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
function openEditModal(update) {
  isEditing.value = true;
  editingId.value = update.id;
  form.title = update.title;
  form.url = update.url;
  showModal.value = true;
}

/**
 * Submit form.
 */
function submit() {
  if (isEditing.value) {
    form.put(`/classrooms/whatsapp/${editingId.value}`, {
      onSuccess: () => { showModal.value = false; },
    });
  } else {
    form.post('/classrooms/whatsapp', {
      onSuccess: () => {
        showModal.value = false;
        form.reset();
      },
    });
  }
}

/**
 * Delete link.
 */
function deleteUpdate(update) {
  if (confirm('Are you sure you want to remove this update link?')) {
    router.delete(`/classrooms/whatsapp/${update.id}`);
  }
}
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.w-10 { width: 2.5rem; }
.h-10 { height: 2.5rem; }
.rounded-lg { border-radius: 0.5rem; }
.bg-green-100 { background: #dcfce7; }
.m-0 { margin: 0; }
.text-danger { color: #dc2626; }
.hover\:underline:hover { text-decoration: underline; }
</style>
