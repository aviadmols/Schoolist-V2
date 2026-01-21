<template>
  <div class="stack">
    <div class="stack">
      <h1 class="text-title">Select Classroom</h1>
      <p class="text-muted">Choose a classroom to continue.</p>
    </div>

    <div class="stack">
      <div v-for="classroom in classrooms" :key="classroom.id" class="card">
        <div class="card__body flex items-center justify-between">
          <div>
            <div class="text-body font-bold">{{ classroom.name }}</div>
            <div class="text-muted small">Role: {{ classroom.pivot.role }}</div>
          </div>
          <div class="flex gap-2">
            <UiButton 
              v-if="classroom.id !== current_id"
              @click="switchClassroom(classroom.id)"
              variant="ghost"
            >
              Switch
            </UiButton>
            <span v-else class="text-muted">Active</span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex gap-4">
      <UiButton href="/classrooms/create" variant="primary">Create New</UiButton>
      <UiButton href="/classrooms/join" variant="ghost">Join with Code</UiButton>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import UiButton from '../../components/ui/UiButton.vue';
import UiCard from '../../components/ui/UiCard.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  classrooms: Array,
  current_id: Number,
});

/**
 * Switch to a different classroom.
 *
 * @param {number} id
 * @returns {void}
 */
function switchClassroom(id) {
  router.post(`/classrooms/${id}/switch`);
}
</script>
