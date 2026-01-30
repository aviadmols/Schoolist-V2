<template>
  <div class="page-auth">
    <div class="page-auth__bg" aria-hidden="true" />
    <div class="page-auth__container">
      <UiCard class="page-auth__card">
        <div class="card__body">
          <slot />
        </div>
      </UiCard>

          <footer class="page-auth__footer">
      <div class="page-auth__logo">schoolist</div>
      <p class="page-auth__copyright">
        © {{ new Date().getFullYear() }} כל הזכויות שמורות סקוליסט בע"מ
        <span class="page-auth__links">
          הצהרת נגישות | תקנון אתר | מדיניות פרטיות
        </span>
      </p>
    </footer>
    </div>

  </div>
</template>

<script setup>
import UiCard from '../components/ui/UiCard.vue';

/**
 * Auth layout for public authentication pages.
 */
</script>

<style scoped>
.page-auth {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 16px 32px;
  position: relative;
  overflow: hidden;
}

.page-auth__bg {
  position: absolute;
  inset: 0;
  background-image: url('https://app.schoolist.co.il/storage/media/assets/sO5gHke0mVnuWc1HaWtJUIU55gYjr2nhX5bIjLp5.svg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  z-index: 0;
}

.page-auth__container {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  flex-shrink: 0;
}

.page-auth__card {
  background: #fff;
  border-radius: 24px;
  box-shadow: 0 16px 48px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.page-auth__card :deep(.card) {
  background: transparent;
  border-radius: inherit;
}

.page-auth__card :deep(.card__body) {
  padding: 32px 28px;
}

.page-auth__footer {
  position: relative;
  z-index: 1;
  margin-top: auto;
  padding-top: 40px;
  text-align: center;
}

.page-auth__logo {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.02em;
  margin-bottom: 8px;
}

.page-auth__copyright {
  font-size: 0.75rem;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}

.page-auth__links {
  display: block;
  margin-top: 4px;
}
</style>
