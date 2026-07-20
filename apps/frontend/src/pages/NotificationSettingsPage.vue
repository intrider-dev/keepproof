<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import { appStore } from '../stores/app'
import { ApiProblem } from '../lib/api'
import type { NotificationPreferences } from '../types'

const form = reactive<NotificationPreferences>({
  inApp: appStore.state.preferences.inApp,
  email: appStore.state.preferences.email,
  reminderDays: [...appStore.state.preferences.reminderDays],
  quietHours: appStore.state.preferences.quietHours,
  quietFrom: appStore.state.preferences.quietFrom,
  quietUntil: appStore.state.preferences.quietUntil,
})
const busy = ref(false)
const requestError = ref('')
const markingId = ref('')
const reminderOptions = [30, 14, 7, 3, 1]
const nextReminder = computed(() => appStore.state.scheduledReminders[0])

const markRead = async (notificationId: string): Promise<void> => {
  if (markingId.value) return
  markingId.value = notificationId
  requestError.value = ''
  try {
    await appStore.markNotificationRead(notificationId)
  } catch (caught) {
    requestError.value =
      caught instanceof ApiProblem
        ? caught.problem.detail
        : 'The notification could not be updated.'
  } finally {
    markingId.value = ''
  }
}

const toggleReminder = (days: number): void => {
  form.reminderDays = form.reminderDays.includes(days)
    ? form.reminderDays.filter((value) => value !== days)
    : [...form.reminderDays, days].sort((a, b) => b - a)
}

const save = async (): Promise<void> => {
  if (busy.value) return
  busy.value = true
  requestError.value = ''
  try {
    await appStore.updatePreferences(form)
  } catch (caught) {
    requestError.value =
      caught instanceof ApiProblem ? caught.problem.detail : 'Preferences could not be saved.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-stack settings-page">
    <PageHeader
      :eyebrow="`Your timezone · ${appStore.state.user.timezone}`"
      title="Your reminders, in one place"
      description="Review delivered notices and choose how early KeepProof should schedule the next ones."
    />

    <section class="panel notification-inbox" aria-labelledby="notification-inbox-title">
      <div class="panel__heading">
        <div>
          <p class="eyebrow">In-app inbox</p>
          <h2 id="notification-inbox-title">Delivered notifications</h2>
        </div>
        <span>{{ appStore.unreadNotifications.value.length }} unread</span>
      </div>
      <ul v-if="appStore.state.notifications.length">
        <li
          v-for="notification in appStore.state.notifications"
          :key="notification.id"
          :class="{ 'notification-inbox__unread': !notification.readAt }"
        >
          <span class="feature-icon"><AppIcon name="bell" :size="18" /></span>
          <div>
            <strong>{{ notification.message }}</strong>
            <p>{{ notification.merchant }} · Due {{ notification.dueOn }}</p>
          </div>
          <div class="notification-inbox__actions">
            <RouterLink class="inline-link" :to="`/app/purchases/${notification.purchaseId}`">
              View purchase
            </RouterLink>
            <button
              v-if="!notification.readAt"
              class="text-button"
              type="button"
              :disabled="Boolean(markingId)"
              @click="markRead(notification.id)"
            >
              {{ markingId === notification.id ? 'Updating…' : 'Mark as read' }}
            </button>
            <small v-else>Read</small>
          </div>
        </li>
      </ul>
      <p v-else class="panel-note">No notifications have been delivered yet.</p>
    </section>

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
              ><small>See delivered notices here and in the dashboard activity.</small></span
            >
            <input v-model="form.inApp" class="switch" type="checkbox" />
          </label>
          <label class="setting-row">
            <span
              ><strong>Email reminders</strong
              ><small>Send to {{ appStore.state.user.email }}.</small></span
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
              <p>Move email delivery out of your selected quiet window.</p>
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
            <AppIcon name="check" :size="17" />
            {{
              appStore.state.demoMode
                ? 'Fixture-demo preferences stay in memory until you leave.'
                : 'Saved preferences apply to new and recalculated reminders.'
            }}
          </p>
          <button class="button button--primary" type="submit" :disabled="busy">
            Save preferences
          </button>
        </div>
        <p v-if="requestError" class="form-alert" role="alert">{{ requestError }}</p>
      </div>

      <aside class="panel reminder-preview">
        <p class="eyebrow">Preview</p>
        <h2>Your next scheduled reminder</h2>
        <div class="notification-preview">
          <span class="feature-icon"><AppIcon name="receipt" /></span>
          <div>
            <strong v-if="nextReminder"
              >{{ nextReminder.kind === 'return' ? 'Return' : 'Warranty' }} reminder ·
              {{ nextReminder.intervalDays }} days before</strong
            >
            <strong v-else>No reminder currently scheduled</strong>
            <p>
              {{ nextReminder?.merchant ?? 'New schedules appear after a deadline is active.' }}
            </p>
            <small v-if="nextReminder">{{
              nextReminder.channel === 'email' ? 'Email' : 'In-app'
            }}</small>
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
            <dd>{{ appStore.state.user.timezone }}</dd>
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
