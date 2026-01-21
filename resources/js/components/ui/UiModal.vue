<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="modelValue"
        class="modal__backdrop"
        role="dialog"
        aria-modal="true"
        @click.self="closeModal"
      >
        <div class="modal__panel">
          <div v-if="title" class="modal__header">
            <h3 class="modal__title">{{ title }}</h3>
            <UiButton variant="ghost" type="button" @click="closeModal">
              Close
            </UiButton>
          </div>
          <div class="modal__body">
            <slot />
          </div>
          <div v-if="$slots.footer" class="modal__footer">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, watch } from 'vue';
import UiButton from './UiButton.vue';

const emit = defineEmits(['update:modelValue']);

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
});

/**
 * Close the modal and emit v-model update.
 *
 * @returns {void}
 */
function closeModal() {
  emit('update:modelValue', false);
}

/**
 * Close the modal on Escape key press.
 *
 * @param {KeyboardEvent} event
 * @returns {void}
 */
function closeModalOnEscape(event) {
  if (event.key !== 'Escape') {
    return;
  }

  closeModal();
}

/**
 * Toggle Escape listener based on open state.
 *
 * @param {boolean} isOpen
 * @returns {void}
 */
function toggleEscapeListener(isOpen) {
  if (isOpen) {
    window.addEventListener('keydown', closeModalOnEscape);
    return;
  }

  window.removeEventListener('keydown', closeModalOnEscape);
}

/**
 * React to v-model open state changes.
 *
 * @param {boolean} isOpen
 * @returns {void}
 */
function onModelValueChanged(isOpen) {
  toggleEscapeListener(Boolean(isOpen));
}

watch(
  () => props.modelValue,
  onModelValueChanged,
  { immediate: true }
);

/**
 * Cleanup modal listeners on component unmount.
 *
 * @returns {void}
 */
function cleanupModalListeners() {
  toggleEscapeListener(false);
}

onBeforeUnmount(cleanupModalListeners);
</script>

