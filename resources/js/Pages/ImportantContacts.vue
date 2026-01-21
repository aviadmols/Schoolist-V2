<template>
  <div class="stack">
    <div class="flex items-center justify-between">
      <h1 class="text-title">Important Contacts</h1>
      <UiButton @click="openCreateModal" variant="primary">Add Contact</UiButton>
    </div>

    <div v-if="contacts.length === 0" class="card card__body">
      <p class="text-muted text-center">No important contacts added yet.</p>
    </div>

    <div v-else class="stack">
      <div v-for="contact in contacts" :key="contact.id" class="card">
        <div class="card__body flex items-center justify-between">
          <div class="flex items-center gap-3">
             <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
               <span class="text-xl">📞</span>
             </div>
             <div>
               <h3 class="text-body font-bold m-0">{{ contact.name }}</h3>
               <p class="text-muted small m-0">{{ contact.title }}</p>
               <a :href="`tel:${contact.phone}`" class="text-blue-600 font-medium text-sm">{{ contact.phone }}</a>
             </div>
          </div>
          <div class="flex gap-2">
            <UiButton @click="openEditModal(contact)" variant="ghost" size="sm">Edit</UiButton>
            <UiButton @click="deleteContact(contact)" variant="ghost" size="sm" class="text-danger">Delete</UiButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <UiModal v-model="showModal" :title="isEditing ? 'Edit Contact' : 'Add Important Contact'">
      <form class="stack" @submit.prevent="submit">
        <UiField label="Name" :help="form.errors.name">
          <UiInput v-model="form.name" placeholder="Contact Name" />
        </UiField>

        <UiField label="Title" :help="form.errors.title">
          <UiInput v-model="form.title" placeholder="e.g. Class Teacher / Committee Chair" />
        </UiField>

        <UiField label="Phone" :help="form.errors.phone">
          <UiInput v-model="form.phone" placeholder="050-000-0000" />
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
  contacts: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
  name: '',
  title: '',
  phone: '',
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
function openEditModal(contact) {
  isEditing.value = true;
  editingId.value = contact.id;
  form.name = contact.name;
  form.title = contact.title;
  form.phone = contact.phone;
  showModal.value = true;
}

/**
 * Submit form.
 */
function submit() {
  if (isEditing.value) {
    form.put(`/classrooms/important-contacts/${editingId.value}`, {
      onSuccess: () => { showModal.value = false; },
    });
  } else {
    form.post('/classrooms/important-contacts', {
      onSuccess: () => {
        showModal.value = false;
        form.reset();
      },
    });
  }
}

/**
 * Delete contact.
 */
function deleteContact(contact) {
  if (confirm('Are you sure you want to remove this contact?')) {
    router.delete(`/classrooms/important-contacts/${contact.id}`);
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
.bg-blue-100 { background: #dbeafe; }
.m-0 { margin: 0; }
.text-danger { color: #dc2626; }
.text-blue-600 { color: #2563eb; }
.font-medium { font-weight: 500; }
.font-bold { font-weight: 700; }
</style>
