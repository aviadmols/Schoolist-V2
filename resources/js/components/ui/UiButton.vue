<template>
  <component
    :is="tagName"
    class="btn"
    :class="computedClass"
    :type="buttonType"
    :href="computedHref"
    :disabled="computedDisabledAttribute"
    :aria-disabled="isDisabled ? 'true' : undefined"
  >
    <slot />
  </component>
</template>

<script setup>
import { computed } from 'vue';

const BUTTON_TAG = 'button';
const LINK_TAG = 'a';

const props = defineProps({
  variant: { type: String, default: 'primary' },
  href: { type: String, default: '' },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
});

/**
 * Determine which tag to render.
 *
 * @returns {string}
 */
function computeTagName() {
  return props.href ? LINK_TAG : BUTTON_TAG;
}

const tagName = computed(computeTagName);

/**
 * Compute the CSS class for the button.
 *
 * @returns {string}
 */
function computeButtonClass() {
  return `btn--${props.variant}`;
}

const computedClass = computed(computeButtonClass);

/**
 * Compute the button type (only valid for <button>).
 *
 * @returns {string|undefined}
 */
function computeButtonType() {
  return props.href ? undefined : props.type;
}

const buttonType = computed(computeButtonType);

/**
 * Compute disabled state.
 *
 * @returns {boolean}
 */
function computeIsDisabled() {
  return Boolean(props.disabled);
}

const isDisabled = computed(computeIsDisabled);

/**
 * Compute href attribute (only valid for <a>).
 *
 * @returns {string|undefined}
 */
function computeHref() {
  return props.href ? props.href : undefined;
}

const computedHref = computed(computeHref);

/**
 * Compute disabled attribute (only valid for <button>).
 *
 * @returns {boolean|undefined}
 */
function computeDisabledAttribute() {
  return props.href ? undefined : Boolean(props.disabled);
}

const computedDisabledAttribute = computed(computeDisabledAttribute);
</script>

