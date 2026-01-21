<template>
  <UiCard>
    <div class="card__body">
      <div class="stack">
        <div>
          <h1 class="text-title">Welcome</h1>
          <p class="text-muted">
            This is a minimal Inertia + Vue 3 bootstrap with a plain CSS UI kit.
          </p>
        </div>
        <div>
          <UiButton href="/login" variant="primary">Go to login</UiButton>
        </div>
      </div>
    </div>
  </UiCard>
</template>

<script setup>
import AppLayout from '../layouts/AppLayout.vue';
import UiButton from '../components/ui/UiButton.vue';
import UiCard from '../components/ui/UiCard.vue';

/**
 * Public landing page.
 */
defineOptions({ layout: AppLayout });
</script>

