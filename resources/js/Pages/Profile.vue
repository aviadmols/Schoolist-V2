<template>
  <div class="stack">
    <h1 class="text-title">החשבון שלי</h1>
    
    <div class="card">
      <div class="stack">
        <p class="text-muted">שלום, {{ user.name }}</p>
        <p class="text-muted">טלפון: {{ user.phone }}</p>
      </div>
    </div>

    <div class="card">
      <h2 class="text-subtitle">הכיתות שלי</h2>
      
      <div v-if="classrooms.length === 0" class="text-muted">
        אין לך כיתות כרגע. הוסף כיתה חדשה באמצעות הקוד למטה.
      </div>

      <div v-else class="stack">
        <div 
          v-for="classroom in classrooms" 
          :key="classroom.id"
          class="card"
          style="cursor: pointer;"
          @click="goToClassroom(classroom.id)"
        >
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-bold">{{ classroom.name }}</h3>
              <p class="text-muted text-sm">
                {{ classroom.school_name || '' }}
                <span v-if="classroom.city_name"> - {{ classroom.city_name }}</span>
              </p>
              <p class="text-muted text-xs">
                {{ classroom.grade_level }}'{{ classroom.grade_number }}
                <span v-if="classroom.role === 'owner'"> - בעלים</span>
                <span v-else-if="classroom.role === 'admin'"> - מנהל</span>
              </p>
            </div>
            <button class="text-primary">→</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <h2 class="text-subtitle">הוסף כיתה</h2>
      
      <form class="stack" @submit.prevent="handleJoinClassroom">
        <div v-if="joinError" class="text-danger text-sm">{{ joinError }}</div>
        <div v-if="joinSuccess" class="text-success text-sm">{{ joinSuccess }}</div>
        
        <UiField label="קוד כיתה">
          <DigitInput
            v-model="joinCode"
            :length="4"
            :disabled="isJoining || isLocked"
          />
          <p v-if="attemptsRemaining !== null" class="text-muted text-xs">
            נותרו {{ attemptsRemaining }} ניסיונות
          </p>
          <p v-if="isLocked && minutesRemaining" class="text-danger text-xs">
            נחסמת ל-{{ minutesRemaining }} דקות
          </p>
        </UiField>

        <UiButton 
          type="submit" 
          variant="primary" 
          :disabled="isJoining || isLocked || !joinCode || joinCode.length !== 4"
        >
          <span v-if="isJoining">מצטרף...</span>
          <span v-else>הוסף כיתה</span>
        </UiButton>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import UiButton from '../components/ui/UiButton.vue';
import UiField from '../components/ui/UiField.vue';
import DigitInput from '../components/ui/DigitInput.vue';

/**
 * Profile page.
 */
defineOptions({ layout: AppLayout });

const props = defineProps({
  user: Object,
  classrooms: Array,
});

const joinCode = ref('');
const joinError = ref(null);
const joinSuccess = ref(null);
const isJoining = ref(false);
const isLocked = ref(false);
const attemptsRemaining = ref(null);
const minutesRemaining = ref(null);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken || '',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(payload),
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw data;
  }

  return data;
}

function goToClassroom(classroomId) {
  router.visit(`/class/${classroomId}`);
}

async function handleJoinClassroom() {
  joinError.value = null;
  joinSuccess.value = null;
  isJoining.value = true;

  try {
    const data = await postJson('/classrooms/join', {
      join_code: joinCode.value,
    });

    joinSuccess.value = data.message || 'הצטרפת לכיתה בהצלחה!';
    attemptsRemaining.value = null;
    isLocked.value = false;
    minutesRemaining.value = null;
    
    // Reload page to show new classroom
    setTimeout(() => {
      router.reload();
    }, 1000);
  } catch (err) {
    joinError.value = err?.message || 'שגיאה בהצטרפות לכיתה';
    
    if (err?.locked) {
      isLocked.value = true;
      minutesRemaining.value = err?.minutes_remaining || 15;
    } else {
      attemptsRemaining.value = err?.attempts_remaining || null;
    }
  } finally {
    isJoining.value = false;
  }
}
</script>
