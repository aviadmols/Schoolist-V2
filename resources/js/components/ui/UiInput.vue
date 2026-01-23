<template>
  <input
    class="input"
    :type="type"
    :value="modelValue"
    :placeholder="placeholder"
    :disabled="disabled"
    :autocomplete="autocomplete"
    :inputmode="inputmode"
    :maxlength="maxlength"
    @input="onInput"
  />
</template>

<script setup>
const emit = defineEmits(['update:modelValue']);

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  autocomplete: { type: String, default: '' },
  inputmode: { type: String, default: '' },
  maxlength: { type: [String, Number], default: undefined },
});

/**
 * Emit v-model updates for the input.
 *
 * @param {Event} event
 * @returns {void}
 */
function onInput(event) {
  const target = event.target;
  emit('update:modelValue', target ? target.value : '');
}
</script>

