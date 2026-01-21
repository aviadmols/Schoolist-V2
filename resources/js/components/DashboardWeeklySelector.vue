<template>
  <div class="flex justify-between p-4 bg-white rounded-xl shadow-sm overflow-x-auto">
    <button
      v-for="day in days"
      :key="day.value"
      @click="$emit('update:modelValue', day.value)"
      class="flex flex-col items-center justify-center w-10 h-10 rounded-full transition-colors"
      :class="modelValue === day.value ? 'bg-blue-100 text-blue-600 font-bold' : 'text-gray-500'"
    >
      <span class="text-xs">{{ day.label }}</span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  modelValue: Number,
});

defineEmits(['update:modelValue']);

const days = [
  { value: 0, label: 'א' },
  { value: 1, label: 'ב' },
  { value: 2, label: 'ג' },
  { value: 3, label: 'ד' },
  { value: 4, label: 'ה' },
  { value: 5, label: 'ו' },
];
</script>

<style scoped>
/* Inline layout helpers since tokens.css/ui.css focus on components */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.flex-col { flex-direction: column; }
.gap-2 { gap: 0.5rem; }
.rounded-xl { border-radius: 0.75rem; }
.rounded-full { border-radius: 9999px; }
.p-4 { padding: 1rem; }
.bg-white { background: #fff; }
.bg-blue-100 { background: #e0f2fe; }
.text-blue-600 { color: #2563eb; }
.text-gray-500 { color: #6b7280; }
.font-bold { font-weight: 700; }
.text-xs { font-size: 0.75rem; }
.w-10 { width: 2.5rem; }
.h-10 { height: 2.5rem; }
</style>
