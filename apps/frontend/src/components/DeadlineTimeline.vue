<script setup lang="ts">
import type { Deadline } from '../types'
import { deadlineDistance, formatDate } from '../lib/format'
import AppIcon from './AppIcon.vue'
import StatusPill from './StatusPill.vue'

defineProps<{ deadlines: Deadline[] }>()
</script>

<template>
  <ol class="timeline" aria-label="Purchase deadline timeline">
    <li v-for="deadline in deadlines" :key="deadline.id" class="timeline__item">
      <span class="timeline__marker" :class="`timeline__marker--${deadline.urgency}`">
        <AppIcon :name="deadline.type === 'return' ? 'receipt' : 'shield'" :size="18" />
      </span>
      <div class="timeline__content">
        <div class="timeline__topline">
          <div>
            <p class="timeline__kind">
              {{ deadline.type === 'return' ? 'Return window' : 'Warranty' }}
            </p>
            <h3>{{ formatDate(deadline.dueOn) }}</h3>
          </div>
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
        </div>
        <p>{{ deadline.explanation }}</p>
        <p class="timeline__rule">{{ deadline.rule }}</p>
        <p class="timeline__reminders">
          Reminders:
          <span>{{ deadline.reminders.map((days) => `${days}d`).join(' · ') }}</span>
        </p>
      </div>
    </li>
  </ol>
</template>
