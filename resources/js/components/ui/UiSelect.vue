<template>
  <select
    class="select"
    :value="modelValue"
    :disabled="disabled"
    @change="onChange"
  >
    <option v-if="placeholder" value="" disabled>
      {{ placeholder }}
    </option>
    <option v-for="option in options" :key="option.value" :value="option.value">
      {{ option.label }}
    </option>
  </select>
</template>

<script setup>
const emit = defineEmits(['update:modelValue']);

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

/**
 * Emit v-model updates for the select.
 *
 * @param {Event} event
 * @returns {void}
 */
function onChange(event) {
  const target = event.target;
  emit('update:modelValue', target ? target.value : '');
}
</script>
