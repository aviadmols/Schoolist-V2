<template>
  <div class="field">
    <label v-if="label" class="field__label">
      {{ label }}
    </label>
    <slot />
    <div v-if="help" class="text-muted">
      {{ help }}
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  label: { type: String, default: '' },
  help: { type: String, default: '' },
});
void props;
</script>

