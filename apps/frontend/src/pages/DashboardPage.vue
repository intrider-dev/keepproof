<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { deadlineDistance, formatDate, formatMoney } from '../lib/format'
import { appStore } from '../stores/app'
import type { Deadline } from '../types'

const allDeadlines = computed(() =>
  appStore.state.purchases
    .flatMap((purchase) => [purchase.returnDeadline, purchase.warrantyDeadline])
    .filter((deadline): deadline is Deadline => Boolean(deadline))
    .filter((deadline) => deadline.daysLeft >= 0)
    .sort((left, right) => left.daysLeft - right.daysLeft),
)
const upcomingReturns = computed(() =>
  allDeadlines.value.filter((deadline) => deadline.type === 'return' && deadline.daysLeft <= 30),
)
const activeWarranties = computed(() =>
  allDeadlines.value.filter((deadline) => deadline.type === 'warranty'),
)
const nextDeadline = computed(() => allDeadlines.value[0])
const nextPurchase = computed(() =>
  appStore.state.purchases.find((purchase) =>
    [purchase.returnDeadline?.id, purchase.warrantyDeadline?.id].includes(nextDeadline.value?.id),
  ),
)
const reviewDocument = computed(() => appStore.reviewDocuments.value[0])
const reviewPurchase = computed(() =>
  appStore.state.purchases.find((purchase) => purchase.id === reviewDocument.value?.purchaseId),
)
const nextReminder = computed(() => appStore.state.scheduledReminders[0])
const latestUnread = computed(() => appStore.unreadNotifications.value[0])
const storedDocuments = computed(
  () => appStore.state.purchases.filter((purchase) => purchase.documentId).length,
)
const todayLabel = computed(() =>
  new Intl.DateTimeFormat('en-US', {
    timeZone: appStore.state.user.timezone,
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  }).format(new Date()),
)
const reminderDate = (value: string): string =>
  new Intl.DateTimeFormat('en-US', {
    timeZone: appStore.state.user.timezone,
    month: 'short',
    day: 'numeric',
  }).format(new Date(value))
</script>

<template>
  <div class="page-stack">
    <PageHeader
      :eyebrow="todayLabel"
      :title="`Good morning, ${appStore.state.user.name.split(' ')[0]}.`"
      description="Here is what needs attention across your household."
    />

    <section v-if="nextDeadline && nextPurchase" class="deadline-spotlight">
      <div class="deadline-spotlight__date">
        <small>{{ formatDate(nextDeadline.dueOn, { month: 'short' }).toUpperCase() }}</small>
        <strong>{{ formatDate(nextDeadline.dueOn, { day: 'numeric' }) }}</strong>
      </div>
      <div class="deadline-spotlight__copy">
        <p class="eyebrow">Next action · {{ deadlineDistance(nextDeadline.daysLeft) }}</p>
        <h2>
          {{ nextDeadline.type === 'return' ? 'Return window' : 'Warranty expires' }} for
          {{ nextPurchase.items[0]?.name }}
        </h2>
        <p>
          {{ nextPurchase.merchant }} ·
          {{ formatMoney(nextPurchase.totalMinor, nextPurchase.currency) }}
        </p>
      </div>
      <div class="deadline-spotlight__actions">
        <RouterLink class="button button--paper" :to="`/app/purchases/${nextPurchase.id}`">
          View purchase
        </RouterLink>
        <RouterLink class="button button--ink" to="/app/deadlines">
          Open timeline <AppIcon name="arrow" :size="17" />
        </RouterLink>
      </div>
    </section>

    <section class="metric-grid" aria-label="Household summary">
      <article>
        <span class="metric-icon metric-icon--amber"><AppIcon name="clock" /></span>
        <div>
          <strong>{{ upcomingReturns.length }}</strong
          ><span>Returns in the next 30 days</span>
        </div>
        <small v-if="upcomingReturns[0]">{{ deadlineDistance(upcomingReturns[0].daysLeft) }}</small>
        <small v-else>No return action due</small>
      </article>
      <article>
        <span class="metric-icon"><AppIcon name="shield" /></span>
        <div>
          <strong>{{ activeWarranties.length }}</strong
          ><span>Active warranties</span>
        </div>
        <small
          >{{ storedDocuments }} private
          {{ storedDocuments === 1 ? 'document' : 'documents' }}</small
        >
      </article>
      <article>
        <span class="metric-icon metric-icon--blue"><AppIcon name="spark" /></span>
        <div>
          <strong>{{ appStore.reviewDocuments.value.length }}</strong
          ><span>Needs your review</span>
        </div>
        <RouterLink v-if="reviewDocument" :to="`/app/review/${reviewDocument.id}`">
          Review now <AppIcon name="arrow" :size="15" />
        </RouterLink>
        <small v-else>Everything is reviewed</small>
      </article>
    </section>

    <div class="dashboard-grid">
      <section class="panel">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Coming up</p>
            <h2>Deadline timeline</h2>
          </div>
          <RouterLink class="inline-link" to="/app/deadlines"
            >View all <AppIcon name="arrow" :size="15"
          /></RouterLink>
        </div>
        <ol v-if="allDeadlines.length" class="compact-deadlines">
          <li v-for="deadline in allDeadlines.slice(0, 4)" :key="deadline.id">
            <span class="date-tile date-tile--small">
              <b>{{ formatDate(deadline.dueOn, { day: 'numeric' }) }}</b>
              <small>{{ formatDate(deadline.dueOn, { month: 'short' }).toUpperCase() }}</small>
            </span>
            <div>
              <strong>{{
                deadline.type === 'return' ? 'Return window' : 'Warranty expires'
              }}</strong>
              <span>{{ deadline.rule }}</span>
            </div>
            <StatusPill :tone="deadline.urgency === 'soon' ? 'amber' : 'neutral'">
              {{ deadlineDistance(deadline.daysLeft) }}
            </StatusPill>
          </li>
        </ol>
        <p v-else class="panel-note">No active deadlines. Add a purchase to calculate one.</p>
      </section>

      <aside class="panel activity-panel">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Private by default</p>
            <h2>Processing status</h2>
          </div>
        </div>
        <div class="privacy-status">
          <span><AppIcon name="shield" :size="24" /></span>
          <div>
            <strong>Local processing active</strong>
            <p>No receipt text has been sent to an external AI provider.</p>
          </div>
        </div>
        <ul class="activity-list">
          <li v-if="reviewDocument">
            <span class="activity-dot"></span>
            <p>
              <strong>Receipt ready for review</strong>
              <small
                >{{ reviewPurchase?.merchant ?? reviewDocument.filename }} · Human check
                required</small
              >
            </p>
          </li>
          <li v-if="latestUnread">
            <span class="activity-dot"></span>
            <p>
              <strong>In-app reminder delivered</strong>
              <small>{{ latestUnread.merchant }} · Due {{ latestUnread.dueOn }}</small>
            </p>
          </li>
          <li v-if="nextReminder">
            <span class="activity-dot activity-dot--muted"></span>
            <p>
              <strong
                >{{ nextReminder.channel === 'email' ? 'Email' : 'In-app' }} reminder
                scheduled</strong
              >
              <small>{{ nextReminder.merchant }} · {{ reminderDate(nextReminder.remindAt) }}</small>
            </p>
          </li>
          <li v-if="!reviewDocument && !latestUnread && !nextReminder">
            <span class="activity-dot activity-dot--muted"></span>
            <p>
              <strong>Nothing waiting</strong><small>Processing and reminders are clear.</small>
            </p>
          </li>
        </ul>
      </aside>
    </div>

    <section class="panel">
      <div class="panel__heading">
        <div>
          <p class="eyebrow">Recently added</p>
          <h2>Household purchases</h2>
        </div>
        <RouterLink class="inline-link" to="/app/purchases"
          >Browse purchases <AppIcon name="arrow" :size="15"
        /></RouterLink>
      </div>
      <div class="purchase-strip">
        <RouterLink
          v-for="purchase in appStore.state.purchases.slice(0, 3)"
          :key="purchase.id"
          class="purchase-mini"
          :to="
            purchase.status === 'needs_review'
              ? `/app/review/${purchase.documentId}`
              : `/app/purchases/${purchase.id}`
          "
        >
          <span class="purchase-mini__icon"><AppIcon name="receipt" /></span>
          <span
            ><strong>{{ purchase.merchant }}</strong
            ><small>{{ purchase.items[0]?.name }}</small></span
          >
          <strong>{{ formatMoney(purchase.totalMinor, purchase.currency) }}</strong>
        </RouterLink>
      </div>
    </section>
  </div>
</template>
