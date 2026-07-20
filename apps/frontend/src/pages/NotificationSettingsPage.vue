<script setup lang="ts">
import { reactive, ref } from 'vue'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import { demoStore } from '../demo/store'
import type { NotificationPreferences } from '../types'

const form = reactive<NotificationPreferences>(structuredClone(demoStore.state.preferences))
const busy = ref(false)
const reminderOptions = [30, 14, 7, 3, 1]

const toggleReminder = (days: number): void => {
  form.reminderDays = form.reminderDays.includes(days)
    ? form.reminderDays.filter((value) => value !== days)
    : [...form.reminderDays, days].sort((a, b) => b - a)
}

const save = (): void => {
  if (busy.value) return
  busy.value = true
  try {
    demoStore.updatePreferences(form)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-stack settings-page">
    <PageHeader
      eyebrow="Your timezone · America/New_York"
      title="Reminders that arrive at a useful time"
      description="Choose how early KeepProof should remind you. Changing a deadline rebuilds its unsent reminder schedule."
    />

    <form class="settings-grid" @submit.prevent="save">
      <div class="settings-main page-stack page-stack--compact">
        <section class="panel settings-section">
          <div class="settings-section__heading">
            <span class="feature-icon"><AppIcon name="bell" /></span>
            <div>
              <h2>Delivery channels</h2>
              <p>In-app reminders stay in KeepProof. Email uses your verified address.</p>
            </div>
          </div>
          <label class="setting-row">
            <span
              ><strong>In-app notifications</strong
              ><small>See reminders on your dashboard and notification list.</small></span
            >
            <input v-model="form.inApp" class="switch" type="checkbox" />
          </label>
          <label class="setting-row">
            <span
              ><strong>Email reminders</strong
              ><small>Send to {{ demoStore.state.user.email }}.</small></span
            >
            <input v-model="form.email" class="switch" type="checkbox" />
          </label>
        </section>

        <section class="panel settings-section">
          <div class="settings-section__heading">
            <span class="feature-icon"><AppIcon name="clock" /></span>
            <div>
              <h2>Reminder intervals</h2>
              <p>Applied to new and recalculated deadlines.</p>
            </div>
          </div>
          <fieldset class="interval-fieldset">
            <legend class="sr-only">Days before a deadline</legend>
            <label
              v-for="days in reminderOptions"
              :key="days"
              :class="{ 'interval-chip--active': form.reminderDays.includes(days) }"
            >
              <input
                :checked="form.reminderDays.includes(days)"
                type="checkbox"
                @change="toggleReminder(days)"
              />
              <strong>{{ days }}</strong
              ><span>{{ days === 1 ? 'day' : 'days' }} before</span>
            </label>
          </fieldset>
          <p v-if="!form.reminderDays.length" class="form-alert" role="status">
            No advance reminders selected. Deadline-day notices remain visible in-app.
          </p>
        </section>

        <section class="panel settings-section">
          <div class="settings-section__heading">
            <span class="feature-icon"><AppIcon name="clock" /></span>
            <div>
              <h2>Quiet hours</h2>
              <p>Hold non-urgent email until your morning, using your account timezone.</p>
            </div>
          </div>
          <label class="setting-row">
            <span
              ><strong>Respect quiet hours</strong
              ><small>Deadline calculations stay unchanged; only delivery time moves.</small></span
            >
            <input v-model="form.quietHours" class="switch" type="checkbox" />
          </label>
          <div v-if="form.quietHours" class="time-grid">
            <label class="field"
              ><span>Quiet from</span><input v-model="form.quietFrom" class="input" type="time"
            /></label>
            <label class="field"
              ><span>Resume at</span><input v-model="form.quietUntil" class="input" type="time"
            /></label>
          </div>
        </section>

        <div class="settings-actions">
          <p>
            <AppIcon name="check" :size="17" /> Demo preferences stay in memory until you leave the
            demo.
          </p>
          <button class="button button--primary" type="submit" :disabled="busy">
            Save preferences
          </button>
        </div>
      </div>

      <aside class="panel reminder-preview">
        <p class="eyebrow">Preview</p>
        <h2>Your next reminder</h2>
        <div class="notification-preview">
          <span class="feature-icon"><AppIcon name="receipt" /></span>
          <div>
            <strong>Return window in 3 days</strong>
            <p>North & Pine · Pour-over electric kettle</p>
            <small>Tuesday at {{ form.quietHours ? form.quietUntil : '09:00' }}</small>
          </div>
        </div>
        <dl>
          <div>
            <dt>Channels</dt>
            <dd>
              {{
                [form.inApp && 'In-app', form.email && 'Email'].filter(Boolean).join(' + ') ||
                'None'
              }}
            </dd>
          </div>
          <div>
            <dt>Timezone</dt>
            <dd>America/New_York</dd>
          </div>
          <div>
            <dt>Duplicate protection</dt>
            <dd>Active</dd>
          </div>
        </dl>
      </aside>
    </form>
  </div>
</template>
