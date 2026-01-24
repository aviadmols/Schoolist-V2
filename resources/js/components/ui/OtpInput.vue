<template>
  <div id="otp" class="otp-container">
    <input
      v-for="(digit, idx) in digits"
      :key="idx"
      :ref="(el) => setInputRef(el, idx)"
      v-model="digits[idx]"
      type="text"
      inputmode="numeric"
      :autocomplete="idx === 0 ? 'one-time-code' : ''"
      maxlength="1"
      class="otp-input"
      @paste="handlePaste($event, idx)"
      @input="handleInput($event, idx)"
      @keydown="handleKeydown($event, idx)"
    />
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const digits = ref(['', '', '', '']);
const inputRefs = ref([]);

/**
 * Store input refs.
 */
function setInputRef(el, idx) {
  if (el) {
    inputRefs.value[idx] = el;
  }
}

/**
 * Emit concatenated code.
 */
function updateModelValue() {
  const code = digits.value.join('');
  emit('update:modelValue', code);
}

/**
 * Handle paste into OTP inputs.
 */
function handlePaste(e, idx) {
  const text = (e.clipboardData || window.clipboardData).getData('text') || '';
  const numericText = text.replace(/\D/g, '').slice(0, 4);
  if (!numericText) return;
  
  e.preventDefault();
  numericText.split('').forEach((d, i) => {
    if (i < 4) {
      digits.value[i] = d;
    }
  });
  
  const nextIdx = Math.min(numericText.length, 4) - 1;
  nextTick(() => {
    inputRefs.value[nextIdx]?.focus();
  });
  
  updateModelValue();
}

/**
 * Handle single digit input.
 */
function handleInput(e, idx) {
  const value = (e.target.value || '').replace(/\D/g, '').slice(0, 1);
  digits.value[idx] = value;
  
  updateModelValue();
  
  if (value && inputRefs.value[idx + 1]) {
    nextTick(() => {
      inputRefs.value[idx + 1]?.focus();
    });
  }
}

/**
 * Handle backspace navigation.
 */
function handleKeydown(e, idx) {
  if (e.key === 'Backspace' && !digits.value[idx] && inputRefs.value[idx - 1]) {
    inputRefs.value[idx - 1].focus();
  }
}

/**
 * Sync model value to inputs.
 */
watch(() => props.modelValue, (newValue) => {
  if (newValue !== digits.value.join('')) {
    const newDigits = newValue.replace(/\D/g, '').slice(0, 4).split('');
    while (newDigits.length < 4) {
      newDigits.push('');
    }
    digits.value = newDigits;
  }
}, { immediate: true });

/**
 * Focus the first input on mount.
 */
function focusFirstInput() {
  nextTick(() => {
    inputRefs.value[0]?.focus();
  });
}

onMounted(() => {
  focusFirstInput();
});
</script>

<style scoped>
.otp-container {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-direction: row-reverse;
}

.otp-input {
  width: 56px;
  height: 56px;
  text-align: center;
  direction: ltr;
  font-size: 24px;
  font-weight: 600;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: #ffffff;
  transition: border-color 200ms ease;
}

.otp-input:focus {
  outline: none;
  border-color: #2563eb;
}

.otp-input:disabled {
  background: #f1f5f9;
  cursor: not-allowed;
}
</style>
