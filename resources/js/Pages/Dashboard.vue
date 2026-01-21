<template>
  <div class="stack dashboard-page">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 pt-6">
      <div class="flex items-center gap-2">
        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center text-orange-600 font-bold text-lg">
          {{ classroom.grade_level }}'{{ classroom.grade_number }}
        </div>
        <div>
          <h2 class="text-sm font-bold m-0">{{ classroom.school_name || classroom.name }}</h2>
          <span class="text-xs text-muted">2025-2026</span>
        </div>
      </div>
      <div class="flex gap-4">
        <button class="text-muted"><i class="icon-user"></i></button>
        <button class="text-muted"><i class="icon-edit"></i></button>
      </div>
    </div>

    <!-- Weekly Selector -->
    <div class="px-4">
      <DashboardWeeklySelector v-model="currentDay" />
    </div>

    <!-- Timetable -->
    <div class="px-4">
      <div class="flex items-center justify-between mb-2">
        <h3 class="text-body font-bold m-0">
          יום {{ dayNames[currentDay] }} <span class="text-muted font-normal">בוקר טוב!</span>
        </h3>
        <button class="text-muted text-xs"><i class="icon-edit"></i></button>
      </div>
      <TimetableCard :entries="timetable" />
    </div>

    <!-- Weather/Info Box (Placeholder) -->
    <div class="px-4">
      <div class="card bg-blue-50 border-0">
        <div class="card__body flex items-center justify-between p-3">
          <p class="text-xs m-0">16-20° - חולצה ארוכה דקה או חולצה קצרה עם קפוצ'ון מעל.</p>
          <span class="text-2xl">☀️</span>
        </div>
      </div>
    </div>

    <!-- Announcements -->
    <div class="px-4 stack mt-6">
      <div class="flex items-center justify-between mb-2">
        <h3 class="text-body font-bold m-0">הודעות</h3>
        <button class="text-muted text-xs"><i class="icon-edit"></i></button>
      </div>
      
      <div v-if="announcements.length === 0" class="text-muted text-sm py-4 text-center">
        אין הודעות פעילות
      </div>
      <div v-else class="card stack gap-0 p-0 overflow-hidden">
        <div v-for="item in announcements" :key="item.id" class="flex items-start gap-3 p-4 border-b border-gray-50 last:border-0">
          <UiCheckbox :model-value="item.is_done" @update:model-value="toggleAnnouncement(item)" />
          <div :class="{ 'line-through opacity-50': item.is_done }">
            <p class="text-sm font-medium m-0">{{ item.title }}</p>
          </div>
        </div>
      </div>

      <!-- Quick Add Button -->
      <button @click="showQuickAdd = true" class="w-14 h-14 bg-blue-600 text-white rounded-full flex items-center justify-center shadow-lg fixed bottom-6 left-6 z-10">
        <span class="text-3xl">+</span>
      </button>
    </div>

    <!-- Footer Links (Placeholder/Example) -->
    <div class="px-4 pt-4 stack gap-4 border-t border-gray-100 mt-4 pb-8">
       <h3 class="text-body font-bold m-0 text-center">כל מה שצריך לדעת</h3>
       <div class="stack gap-3">
         <div v-for="i in 5" :key="i" class="flex items-center justify-between py-1">
           <div class="flex items-center gap-3">
             <button class="text-muted"><i class="icon-edit"></i></button>
             <span class="text-sm">מידע וקישורים {{ i }}</span>
           </div>
           <div class="flex items-center gap-2">
              <div class="w-8 h-8 bg-gray-100 rounded-lg"></div>
              <button class="text-muted"><i class="icon-drag"></i></button>
           </div>
         </div>
       </div>
    </div>

    <!-- Quick Add Modal (Stub) -->
    <UiModal v-model="showQuickAdd" title="הוספה מהירה">
      <div class="stack p-4">
        <UiTextarea placeholder="הקלידו או הדביקו חופשי: הודעה, אירוע, טלפון חשוב או כל דבר אחר..." />
        <p class="text-xs text-muted text-center italic">"משפט מתחלף"</p>
        <div class="flex gap-3">
          <UiButton variant="primary" class="flex-1">המשך</UiButton>
          <UiButton variant="ghost" class="flex-1">הוסף קובץ</UiButton>
        </div>
      </div>
    </UiModal>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import DashboardWeeklySelector from '../components/DashboardWeeklySelector.vue';
import TimetableCard from '../components/TimetableCard.vue';
import UiCheckbox from '../components/ui/UiCheckbox.vue';
import UiButton from '../components/ui/UiButton.vue';
import UiModal from '../components/ui/UiModal.vue';
import UiTextarea from '../components/ui/UiTextarea.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
  classroom: Object,
  selected_day: Number,
  timetable: Array,
  announcements: Array,
  timetable_image: String,
});

const currentDay = ref(props.selected_day);
const showQuickAdd = ref(false);

const dayNames = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];

watch(currentDay, (newDay) => {
  router.get('/dashboard', { day: newDay }, { preserveState: true, preserveScroll: true });
});

/**
 * Toggle announcement done state.
 */
function toggleAnnouncement(item) {
  router.post(`/announcements/${item.id}/done`, {}, { preserveScroll: true });
}
</script>

<style scoped>
.dashboard-page {
  background: #fff;
  min-height: 100vh;
}
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.items-start { align-items: flex-start; }
.flex-1 { flex: 1; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.gap-4 { gap: 1rem; }
.px-4 { padding-left: 1rem; padding-right: 1rem; }
.pt-6 { padding-top: 1.5rem; }
.pt-4 { padding-top: 1rem; }
.pb-8 { padding-bottom: 2rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mb-2 { margin-bottom: 0.5rem; }
.m-0 { margin: 0; }
.w-8 { width: 2rem; }
.h-8 { height: 2rem; }
.w-10 { width: 2.5rem; }
.h-10 { height: 2.5rem; }
.w-12 { width: 3rem; }
.h-12 { height: 3rem; }
.rounded-lg { border-radius: 0.5rem; }
.rounded-full { border-radius: 9999px; }
.bg-orange-100 { background: #ffedd5; }
.text-orange-600 { color: #ea580c; }
.bg-blue-50 { background: #eff6ff; }
.bg-blue-600 { background: #2563eb; }
.bg-gray-100 { background: #f3f4f6; }
.text-white { color: #fff; }
.text-blue-600 { color: #2563eb; }
.border-0 { border-width: 0; }
.border-t { border-top-width: 1px; }
.border-b { border-bottom-width: 1px; }
.border-gray-100 { border-color: #f3f4f6; }
.border-gray-50 { border-color: #f9fafb; }
.text-xs { font-size: 0.75rem; }
.text-sm { font-size: 0.875rem; }
.text-2xl { font-size: 1.5rem; }
.font-normal { font-weight: 400; }
.font-medium { font-weight: 500; }
.font-bold { font-weight: 700; }
.italic { font-style: italic; }
.text-center { text-align: center; }
.line-through { text-decoration: line-through; }
.opacity-50 { opacity: 0.5; }
.shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
.fixed { position: fixed; }
.bottom-6 { bottom: 1.5rem; }
.left-6 { left: 1.5rem; }
.z-10 { z-index: 10; }
.text-3xl { font-size: 1.875rem; }
.overflow-hidden { overflow: hidden; }
.p-0 { padding: 0; }
.p-4 { padding: 1rem; }
.self-start { align-self: flex-start; }
</style>
