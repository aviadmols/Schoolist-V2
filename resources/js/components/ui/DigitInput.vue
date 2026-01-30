<template>
  <div class="digit-input-wrap" :class="{ 'digit-input-wrap--rtl': dir === 'rtl' }" dir="ltr">
    <template v-for="(item, i) in items" :key="i">
      <span v-if="item.type === 'separator'" class="digit-input-sep">-</span>
      <input
        v-else
        :ref="(el) => setRef(el, item.index)"
        v-model="digits[item.index]"
        type="text"
        inputmode="numeric"
        maxlength="1"
        class="digit-input"
        :disabled="disabled"
        :autocomplete="item.index === 0 && autocomplete ? autocomplete : 'off'"
        @paste="onPaste($event, item.index)"
        @input="onInput($event, item.index)"
        @keydown="onKeydown($event, item.index)"
      />
    </template>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, computed } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  /** Number of digits (e.g. 4 for join code, 10 for phone) */
  length: { type: Number, required: true },
  /** Show separator after this index (e.g. 3 for 050-xxxxxxx) */
  separatorAfter: { type: Number, default: null },
  disabled: { type: Boolean, default: false },
  dir: { type: String, default: 'ltr' },
  autocomplete: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const digits = ref([]);
const inputRefs = ref([]);

const items = computed(() => {
  const list = [];
  for (let i = 0; i < props.length; i++) {
    list.push({ type: 'input', index: i });
    if (props.separatorAfter !== null && i === props.separatorAfter) {
      list.push({ type: 'separator' });
    }
  }
  return list;
});

function setRef(el, index) {
  if (el) inputRefs.value[index] = el;
}

function getValue() {
  return digits.value.join('');
}

function updateEmit() {
  emit('update:modelValue', getValue());
}

function onPaste(e, startIndex) {
  const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
  const nums = text.replace(/\D/g, '').slice(0, props.length - startIndex);
  if (!nums) return;
  e.preventDefault();
  nums.split('').forEach((ch, i) => {
    const idx = startIndex + i;
    if (idx < props.length) digits.value[idx] = ch;
  });
  const nextIdx = Math.min(startIndex + nums.length, props.length) - 1;
  nextTick(() => inputRefs.value[nextIdx]?.focus());
  updateEmit();
}

function onInput(e, idx) {
  const v = (e.target.value || '').replace(/\D/g, '').slice(0, 1);
  digits.value[idx] = v;
  updateEmit();
  if (v && inputRefs.value[idx + 1]) {
    nextTick(() => inputRefs.value[idx + 1].focus());
  }
}

function onKeydown(e, idx) {
  if (e.key === 'Backspace' && !digits.value[idx] && inputRefs.value[idx - 1]) {
    inputRefs.value[idx - 1].focus();
  }
}

watch(() => props.modelValue, (val) => {
  const str = (val || '').replace(/\D/g, '').slice(0, props.length);
  const arr = str.split('');
  while (arr.length < props.length) arr.push('');
  digits.value = arr;
}, { immediate: true });

function initDigits() {
  const str = (props.modelValue || '').replace(/\D/g, '').slice(0, props.length);
  const arr = str.split('');
  while (arr.length < props.length) arr.push('');
  digits.value = arr;
}

initDigits();
</script>

<style scoped>
.digit-input-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.digit-input-wrap--rtl {
  flex-direction: row-reverse;
}

.digit-input {
  width: 2.25rem;
  height: 2.5rem;
  text-align: center;
  font-size: 1.125rem;
  font-weight: 600;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  transition: border-color 0.2s;
}

.digit-input:focus {
  outline: none;
  border-color: #2563eb;
}

.digit-input:disabled {
  background: #f1f5f9;
  cursor: not-allowed;
}

.digit-input-sep {
  padding-bottom: 0.25rem;
  font-weight: 600;
  color: #64748b;
  user-select: none;
}
</style>
