<template>
  <textarea
    class="textarea"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    @input="onInput"
  />
</template>

<script setup>
const emit = defineEmits(['update:modelValue']);

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

/**
 * Emit v-model updates for the textarea.
 *
 * @param {Event} event
 * @returns {void}
 */
function onInput(event) {
  const target = event.target;
  emit('update:modelValue', target ? target.value : '');
}
</script>

