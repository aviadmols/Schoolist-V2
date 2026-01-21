<template>
  <div class="card p-0">
    <div class="card__body">
      <div v-if="entries.length === 0" class="text-muted text-center py-8">
        אין מערכת שעות ליום זה
      </div>
      <div v-else class="stack">
        <div v-for="entry in entries" :key="entry.id" class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
          <div class="text-muted text-sm font-medium">
            {{ entry.start_time }}-{{ entry.end_time }}
          </div>
          <div class="text-body font-bold text-right">
            {{ entry.subject }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  entries: Array,
});
</script>

<style scoped>
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.text-right { text-align: right; }
.text-sm { font-size: 0.875rem; }
.font-medium { font-weight: 500; }
.font-bold { font-weight: 700; }
.py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.border-b { border-bottom-width: 1px; }
.border-gray-100 { border-color: #f3f4f6; }
.text-center { text-align: center; }
</style>
