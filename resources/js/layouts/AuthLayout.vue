<template>
  <div class="page-auth">
    <div class="container page-auth__inner">
      <UiCard>
        <div class="card__body">
          <slot />
        </div>
      </UiCard>
    </div>
  </div>
</template>

<script setup>
import UiCard from '../components/ui/UiCard.vue';

/**
 * Auth layout for public authentication pages.
 */
</script>

