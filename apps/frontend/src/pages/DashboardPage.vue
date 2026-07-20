<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { appStore } from '../stores/app'
import { deadlineDistance, formatDate, formatMoney } from '../lib/format'
import type { Deadline } from '../types'

const allDeadlines = computed(() =>
  appStore.state.purchases
    .flatMap((purchase) => [purchase.returnDeadline, purchase.warrantyDeadline])
    .filter((deadline): deadline is Deadline => Boolean(deadline))
    .filter((deadline) => deadline.daysLeft >= 0)
    .sort((a, b) => a.daysLeft - b.daysLeft),
)

const nextPurchase = computed(() =>
  appStore.state.purchases.find(
    (purchase) => purchase.returnDeadline?.id === allDeadlines.value[0]?.id,
  ),
)
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Monday, July 20"
      :title="`Good morning, ${appStore.state.user.name.split(' ')[0]}.`"
      description="Here is what needs attention across your household."
    />

    <section v-if="allDeadlines[0] && nextPurchase" class="deadline-spotlight">
      <div class="deadline-spotlight__date">
        <small>{{ formatDate(allDeadlines[0].dueOn, { month: 'short' }).toUpperCase() }}</small>
        <strong>{{ formatDate(allDeadlines[0].dueOn, { day: 'numeric' }) }}</strong>
      </div>
      <div class="deadline-spotlight__copy">
        <p class="eyebrow">Next action · {{ deadlineDistance(allDeadlines[0].daysLeft) }}</p>
        <h2>Return window for {{ nextPurchase.items[0]?.name }}</h2>
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
        <div><strong>2</strong><span>Returns this month</span></div>
        <small>Next in 4 days</small>
      </article>
      <article>
        <span class="metric-icon"><AppIcon name="shield" /></span>
        <div>
          <strong>{{ appStore.activePurchases.value.length }}</strong
          ><span>Active warranties</span>
        </div>
        <small>All documents stored</small>
      </article>
      <article>
        <span class="metric-icon metric-icon--blue"><AppIcon name="spark" /></span>
        <div>
          <strong>{{ appStore.reviewDocuments.value.length }}</strong
          ><span>Needs your review</span>
        </div>
        <RouterLink
          v-if="appStore.reviewDocuments.value[0]"
          :to="`/app/review/${appStore.reviewDocuments.value[0].id}`"
        >
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
        <ol class="compact-deadlines">
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
          <li>
            <span class="activity-dot"></span>
            <p>
              <strong>Receipt ready for review</strong
              ><small>Brightline Home · 18 minutes ago</small>
            </p>
          </li>
          <li>
            <span class="activity-dot activity-dot--muted"></span>
            <p><strong>Reminder scheduled</strong><small>North & Pine · July 17</small></p>
          </li>
          <li>
            <span class="activity-dot activity-dot--muted"></span>
            <p><strong>Purchase confirmed</strong><small>Westfield Electronics · July 9</small></p>
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
