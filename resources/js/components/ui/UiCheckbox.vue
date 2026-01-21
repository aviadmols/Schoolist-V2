<template>
  <label class="checkbox">
    <input
      class="checkbox__box"
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      @change="onChange"
    />
    <span>
      <slot />
    </span>
  </label>
</template>

<script setup>
const emit = defineEmits(['update:modelValue']);

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

/**
 * Emit v-model updates for the checkbox.
 *
 * @param {Event} event
 * @returns {void}
 */
function onChange(event) {
  const target = event.target;
  emit('update:modelValue', Boolean(target && target.checked));
}
</script>

