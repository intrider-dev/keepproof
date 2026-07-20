<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import EmptyState from '../components/EmptyState.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { appStore } from '../stores/app'
import { deadlineDistance, formatDate } from '../lib/format'
import type { Deadline, Purchase } from '../types'

interface DeadlineRow {
  deadline: Deadline
  purchase: Purchase
}

const filter = ref<'all' | 'return' | 'warranty'>('all')
const rows = computed<DeadlineRow[]>(() =>
  appStore.state.purchases
    .flatMap((purchase) =>
      [purchase.returnDeadline, purchase.warrantyDeadline]
        .filter((deadline): deadline is Deadline => Boolean(deadline))
        .map((deadline) => ({ deadline, purchase })),
    )
    .filter(({ deadline }) => filter.value === 'all' || deadline.type === filter.value)
    .sort((a, b) => a.deadline.dueOn.localeCompare(b.deadline.dueOn)),
)
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Explainable dates"
      title="Your deadline timeline"
      description="Return windows and warranty expirations use the terms you confirmed—not a legal rules database."
    >
      <template #actions>
        <RouterLink class="button button--secondary" to="/app/notifications"
          ><AppIcon name="bell" :size="18" /> Reminder settings</RouterLink
        >
      </template>
    </PageHeader>

    <div class="segmented-control" role="group" aria-label="Filter deadline type">
      <button
        v-for="option in ['all', 'return', 'warranty'] as const"
        :key="option"
        type="button"
        :aria-pressed="filter === option"
        @click="filter = option"
      >
        {{ option === 'all' ? 'All deadlines' : option === 'return' ? 'Returns' : 'Warranties' }}
      </button>
    </div>

    <section v-if="rows.length" class="calendar-timeline">
      <article
        v-for="{ deadline, purchase } in rows"
        :key="deadline.id"
        :class="{ 'calendar-timeline__past': deadline.urgency === 'past' }"
      >
        <div class="calendar-timeline__date">
          <small>{{ formatDate(deadline.dueOn, { month: 'short' }).toUpperCase() }}</small>
          <strong>{{ formatDate(deadline.dueOn, { day: 'numeric' }) }}</strong>
          <span>{{ formatDate(deadline.dueOn, { year: 'numeric' }) }}</span>
        </div>
        <span class="calendar-timeline__line"></span>
        <div class="calendar-timeline__icon" :class="`calendar-timeline__icon--${deadline.type}`">
          <AppIcon :name="deadline.type === 'return' ? 'receipt' : 'shield'" />
        </div>
        <div class="calendar-timeline__content">
          <div>
            <p class="eyebrow">
              {{ deadline.type === 'return' ? 'Return window' : 'Warranty expiration' }}
            </p>
            <h2>{{ purchase.items[0]?.name }}</h2>
            <p>{{ purchase.merchant }} · {{ deadline.explanation }}</p>
          </div>
          <div class="calendar-timeline__aside">
            <StatusPill
              :tone="
                deadline.urgency === 'soon'
                  ? 'amber'
                  : deadline.urgency === 'past'
                    ? 'neutral'
                    : 'green'
              "
            >
              {{ deadlineDistance(deadline.daysLeft) }}
            </StatusPill>
            <RouterLink class="inline-link" :to="`/app/purchases/${purchase.id}`"
              >View proof <AppIcon name="arrow" :size="15"
            /></RouterLink>
          </div>
          <div class="calendar-timeline__rule">
            <AppIcon name="clock" :size="16" /><span>{{ deadline.rule }}</span
            ><span>Reminders {{ deadline.reminders.map((days) => `${days}d`).join(' · ') }}</span>
          </div>
        </div>
      </article>
    </section>
    <EmptyState
      v-else
      icon="clock"
      title="No deadlines here"
      description="Choose another filter or confirm a purchase to activate its deadlines."
    />

    <div class="legal-note">
      <AppIcon name="shield" />
      <p>
        <strong>Terms, not legal advice.</strong> KeepProof tracks the periods you enter or confirm.
        It does not determine your rights under local law.
      </p>
    </div>
  </div>
</template>
