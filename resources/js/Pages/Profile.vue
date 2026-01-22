<template>
  <div class="stack">
    <h1 class="text-title">החשבון שלי</h1>
    <p class="text-muted">שלום, {{ user.name }}</p>
    <p class="text-muted">טלפון: {{ user.phone }}</p>
  </div>
</template>

<script setup>
import AppLayout from '../layouts/AppLayout.vue';

/**
 * Profile page.
 */
defineOptions({ layout: AppLayout });

const props = defineProps({
  user: Object,
});
</script>
