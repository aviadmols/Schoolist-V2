<template>
  <div class="stack">
    <div class="flex items-center justify-between">
      <h1 class="text-title">Class Directory</h1>
      <UiButton @click="openCreateModal" variant="primary">Add Child</UiButton>
    </div>

    <div v-if="children.length === 0" class="card card__body">
      <p class="text-muted text-center">No children in the directory yet.</p>
    </div>

    <div v-else class="stack">
      <div v-for="child in children" :key="child.id" class="card">
        <div class="card__body">
          <div class="flex items-start gap-4">
            <!-- Photo Placeholder or Actual Image -->
            <div class="w-16 h-16 bg-gray-100 rounded-full flex-shrink-0 overflow-hidden">
              <img v-if="child.photo" :src="`/storage/${child.photo.path}`" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-2xl">👤</div>
            </div>

            <div class="flex-1">
              <div class="flex items-center justify-between">
                <h3 class="text-body font-bold m-0">{{ child.name }}</h3>
                <div class="flex gap-2">
                  <button @click="openEditModal(child)" class="text-muted text-sm hover:underline">Edit</button>
                  <button @click="deleteChild(child)" class="text-danger text-sm hover:underline">Remove</button>
                </div>
              </div>

              <div class="stack gap-1 mt-3">
                <div v-for="contact in child.contacts" :key="contact.id" class="flex items-center justify-between text-sm py-1 border-b border-gray-50 last:border-0">
                  <div>
                    <span class="font-medium">{{ contact.name }}</span>
                    <span class="text-muted mx-1">({{ contact.relation }})</span>
                  </div>
                  <a :href="`tel:${contact.phone}`" class="text-blue-600 font-medium">{{ contact.phone }}</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <UiModal v-model="showModal" :title="isEditing ? 'Edit Child' : 'Add Child to Directory'">
      <form class="stack" @submit.prevent="submit">
        <UiField label="Child Name" :help="form.errors.name">
          <UiInput v-model="form.name" placeholder="Full name" />
        </UiField>

        <UiField label="Photo (Optional)" :help="form.errors.photo">
          <input type="file" @input="form.photo = $event.target.files[0]" accept="image/*" class="text-sm" />
        </UiField>

        <div class="stack gap-3 border-t pt-4 mt-2">
          <div class="flex items-center justify-between">
            <h4 class="text-sm font-bold m-0">Contacts</h4>
            <UiButton @click="addContact" variant="ghost" size="sm" type="button">+ Add Contact</UiButton>
          </div>

          <div v-for="(contact, index) in form.contacts" :key="index" class="card bg-gray-50 p-3 relative">
             <button @click="removeContact(index)" type="button" class="absolute top-2 right-2 text-danger text-xs" v-if="form.contacts.length > 1">Remove</button>
             <div class="stack gap-2">
                <UiField label="Name" class="flex-1">
                  <UiInput v-model="contact.name" placeholder="Contact Name" />
                </UiField>
                <div class="flex gap-2">
                  <UiField label="Phone" class="flex-1">
                    <UiInput v-model="contact.phone" placeholder="050-000-0000" />
                  </UiField>
                  <UiField label="Relation" class="w-1/3">
                    <UiInput v-model="contact.relation" placeholder="Mother/Father/etc" />
                  </UiField>
                </div>
             </div>
          </div>
        </div>

        <div class="flex gap-3 justify-end mt-4 border-t pt-4">
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
  children: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
  name: '',
  photo: null,
  contacts: [
    { name: '', phone: '', relation: '' }
  ],
});

/**
 * Open create modal.
 */
function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  form.reset();
  form.contacts = [{ name: '', phone: '', relation: '' }];
  showModal.value = true;
}

/**
 * Open edit modal.
 */
function openEditModal(child) {
  isEditing.value = true;
  editingId.value = child.id;
  form.name = child.name;
  form.photo = null;
  form.contacts = child.contacts.map(c => ({
    name: c.name,
    phone: c.phone,
    relation: c.relation
  }));
  showModal.value = true;
}

/**
 * Add a new contact field.
 */
function addContact() {
  form.contacts.push({ name: '', phone: '', relation: '' });
}

/**
 * Remove a contact field.
 */
function removeContact(index) {
  form.contacts.splice(index, 1);
}

/**
 * Submit form.
 */
function submit() {
  if (isEditing.value) {
    // Note: To handle file upload in PUT requests in Laravel/Inertia, 
    // we often use POST with _method=PUT.
    form.post(`/classrooms/directory/${editingId.value}`, {
      onSuccess: () => { showModal.value = false; },
      forceFormData: true,
      data: { ...form.data(), _method: 'PUT' }
    });
  } else {
    form.post('/classrooms/directory', {
      onSuccess: () => {
        showModal.value = false;
        form.reset();
      },
    });
  }
}

/**
 * Delete child.
 */
function deleteChild(child) {
  if (confirm('Are you sure you want to remove this child from the directory?')) {
    router.delete(`/classrooms/directory/${child.id}`);
  }
}
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.items-start { align-items: flex-start; }
.gap-1 { gap: 0.25rem; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.gap-4 { gap: 1rem; }
.flex-1 { flex: 1; }
.flex-shrink-0 { flex-shrink: 0; }
.w-16 { width: 4rem; }
.h-16 { height: 4rem; }
.w-full { width: 100%; }
.h-full { height: 100%; }
.object-cover { object-fit: cover; }
.rounded-full { border-radius: 9999px; }
.bg-gray-100 { background: #f3f4f6; }
.bg-gray-50 { background: #f9fafb; }
.m-0 { margin: 0; }
.mx-1 { margin-left: 0.25rem; margin-right: 0.25rem; }
.mt-3 { margin-top: 0.75rem; }
.mt-2 { margin-top: 0.5rem; }
.pt-4 { padding-top: 1rem; }
.text-xs { font-size: 0.75rem; }
.text-sm { font-size: 0.875rem; }
.text-2xl { font-size: 1.5rem; }
.text-danger { color: #dc2626; }
.text-blue-600 { color: #2563eb; }
.font-medium { font-weight: 500; }
.font-bold { font-weight: 700; }
.hover\:underline:hover { text-decoration: underline; }
.border-b { border-bottom-width: 1px; }
.border-t { border-top-width: 1px; }
.border-gray-50 { border-color: #f9fafb; }
</style>
