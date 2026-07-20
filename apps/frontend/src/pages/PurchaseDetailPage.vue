<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import DeadlineTimeline from '../components/DeadlineTimeline.vue'
import EmptyState from '../components/EmptyState.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { demoStore } from '../demo/store'
import { downloadTextFile } from '../lib/download'
import { formatDate, formatMoney } from '../lib/format'
import type { Deadline } from '../types'

const route = useRoute()
const purchase = computed(() => demoStore.purchaseById(String(route.params.id)))
const deadlines = computed(() =>
  purchase.value
    ? [purchase.value.returnDeadline, purchase.value.warrantyDeadline].filter(
        (deadline): deadline is Deadline => Boolean(deadline),
      )
    : [],
)

const addToCalendar = (): void => {
  const item = purchase.value
  const deadline = item?.returnDeadline ?? item?.warrantyDeadline
  if (!item || !deadline) return
  const date = deadline.dueOn.replaceAll('-', '')
  const ics = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//KeepProof//Demo//EN',
    'BEGIN:VEVENT',
    `UID:${deadline.id}@keepproof.local`,
    `DTSTART;VALUE=DATE:${date}`,
    `SUMMARY:${deadline.type === 'return' ? 'Return deadline' : 'Warranty deadline'} — ${item.items[0]?.name}`,
    `DESCRIPTION:${item.merchant} · ${deadline.rule}`,
    'END:VEVENT',
    'END:VCALENDAR',
  ].join('\r\n')
  downloadTextFile(`${item.id}.ics`, ics, 'text/calendar;charset=utf-8')
  demoStore.showToast('Calendar event downloaded.')
}

const prepareClaim = (): void => {
  const item = purchase.value
  if (!item) return
  downloadTextFile(
    `${item.id}-claim-summary.json`,
    JSON.stringify(
      {
        demo_notice:
          'This browser-only summary is synthetic. The connected API adds receipt files to the claim ZIP.',
        purchase: item,
      },
      null,
      2,
    ),
    'application/json',
  )
  demoStore.showToast('Demo claim summary downloaded.')
}
</script>

<template>
  <div v-if="purchase" class="page-stack">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <RouterLink to="/app/purchases">Purchases</RouterLink>
      <span aria-hidden="true">/</span>
      <span aria-current="page">{{ purchase.merchant }}</span>
    </nav>
    <PageHeader
      :eyebrow="formatDate(purchase.purchaseDate)"
      :title="purchase.merchant"
      :description="purchase.items.map((item) => item.name).join(' · ')"
    >
      <template #actions>
        <button class="button button--secondary" type="button" @click="addToCalendar">
          <AppIcon name="calendar" :size="18" /> Add to calendar
        </button>
        <button class="button button--primary" type="button" @click="prepareClaim">
          <AppIcon name="package" :size="18" /> Prepare claim package
        </button>
      </template>
    </PageHeader>

    <section class="purchase-overview">
      <article>
        <span>Total paid</span>
        <strong>{{ formatMoney(purchase.totalMinor, purchase.currency) }}</strong>
        <small>{{ purchase.currency }} · {{ purchase.country }}</small>
      </article>
      <article>
        <span>Receipt number</span>
        <strong>{{ purchase.orderNumber || 'Not provided' }}</strong>
        <small>Purchased {{ formatDate(purchase.purchaseDate) }}</small>
      </article>
      <article>
        <span>Proof status</span>
        <StatusPill tone="green">Stored privately</StatusPill>
        <small>{{ purchase.documentName }}</small>
      </article>
    </section>

    <div class="detail-grid">
      <section class="panel">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Explainable dates</p>
            <h2>Deadline timeline</h2>
          </div>
        </div>
        <DeadlineTimeline :deadlines="deadlines" />
      </section>

      <aside class="page-stack page-stack--compact">
        <section class="panel">
          <div class="panel__heading">
            <div>
              <p class="eyebrow">Proof of purchase</p>
              <h2>Private document</h2>
            </div>
          </div>
          <div class="document-card">
            <span><AppIcon name="file" :size="24" /></span>
            <div>
              <strong>{{ purchase.documentName }}</strong
              ><small>Processed locally · Ready</small>
            </div>
            <button
              class="icon-button"
              type="button"
              aria-label="Download demo receipt"
              @click="
                demoStore.showToast(
                  'A connected API issues a five-minute private download link.',
                  'info',
                )
              "
            >
              <AppIcon name="arrow" :size="18" />
            </button>
          </div>
          <p class="panel-note">
            <AppIcon name="shield" :size="16" /> Download links are authorized and short-lived.
          </p>
        </section>

        <section class="panel">
          <div class="panel__heading">
            <div>
              <p class="eyebrow">What you bought</p>
              <h2>Items</h2>
            </div>
          </div>
          <ul class="item-list">
            <li v-for="item in purchase.items" :key="item.id">
              <span
                ><strong>{{ item.name }}</strong
                ><small>Quantity {{ item.quantity }}</small></span
              >
              <strong>{{ formatMoney(item.unitPriceMinor, purchase.currency) }}</strong>
            </li>
          </ul>
        </section>

        <section v-if="purchase.notes" class="panel">
          <div class="panel__heading">
            <div>
              <p class="eyebrow">Private note</p>
              <h2>Notes</h2>
            </div>
          </div>
          <p class="notes-copy">{{ purchase.notes }}</p>
        </section>
      </aside>
    </div>
  </div>

  <EmptyState
    v-else
    icon="receipt"
    title="Purchase not found"
    description="This purchase may have been removed, or it belongs to a different household."
  >
    <RouterLink class="button button--secondary" to="/app/purchases">Back to purchases</RouterLink>
  </EmptyState>
</template>
