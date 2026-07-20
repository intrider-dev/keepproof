<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import DeadlineTimeline from '../components/DeadlineTimeline.vue'
import EmptyState from '../components/EmptyState.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { ApiProblem, apiDownload, apiRequest } from '../lib/api'
import { downloadBlob, downloadTextFile, followPrivateDownload } from '../lib/download'
import { formatDate, formatMoney } from '../lib/format'
import { appStore } from '../stores/app'
import type { Deadline } from '../types'

const route = useRoute()
const purchase = computed(() => appStore.purchaseById(String(route.params.id)))
const deadlines = computed(() =>
  purchase.value
    ? [purchase.value.returnDeadline, purchase.value.warrantyDeadline].filter(
        (deadline): deadline is Deadline => Boolean(deadline),
      )
    : [],
)
const busyAction = ref<'calendar' | 'claim' | 'receipt' | null>(null)
const actionError = ref('')

interface PrivateDownloadResponse {
  data: { url: string; expires_at: string }
}

const readableError = (error: unknown): string =>
  error instanceof ApiProblem
    ? error.problem.detail
    : 'The download could not be prepared. Please try again.'

const addToCalendar = async (): Promise<void> => {
  const item = purchase.value
  const deadline = item?.returnDeadline ?? item?.warrantyDeadline
  if (!item || !deadline) return

  busyAction.value = 'calendar'
  actionError.value = ''
  try {
    if (appStore.state.demoMode) {
      const date = deadline.dueOn.replaceAll('-', '')
      const ics = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//KeepProof//Demo//EN',
        'BEGIN:VEVENT',
        `UID:${deadline.id}@keepproof.local`,
        `DTSTART;VALUE=DATE:${date}`,
        `SUMMARY:${deadline.type === 'return' ? 'Return deadline' : 'Warranty deadline'} - ${item.items[0]?.name ?? item.merchant}`,
        `DESCRIPTION:${item.merchant} - ${deadline.rule}`,
        'END:VEVENT',
        'END:VCALENDAR',
      ].join('\r\n')
      downloadTextFile(`${item.id}.ics`, ics, 'text/calendar;charset=utf-8')
    } else {
      const blob = await apiDownload(
        `/households/${appStore.state.user.householdId}/purchases/${item.id}/deadlines/${deadline.id}/calendar`,
      )
      downloadBlob(`keepproof-${item.id}.ics`, blob)
    }
    appStore.showToast('Calendar event downloaded.')
  } catch (error) {
    actionError.value = readableError(error)
  } finally {
    busyAction.value = null
  }
}

const prepareClaim = async (): Promise<void> => {
  const item = purchase.value
  if (!item) return

  busyAction.value = 'claim'
  actionError.value = ''
  try {
    if (appStore.state.demoMode) {
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
      appStore.showToast('Demo claim summary downloaded.')
    } else {
      const blob = await apiDownload(
        `/households/${appStore.state.user.householdId}/purchases/${item.id}/claim-package`,
      )
      downloadBlob(`keepproof-claim-${item.id}.zip`, blob)
      appStore.showToast('Claim package downloaded.')
    }
  } catch (error) {
    actionError.value = readableError(error)
  } finally {
    busyAction.value = null
  }
}

const downloadReceipt = async (): Promise<void> => {
  const item = purchase.value
  if (!item?.documentId) return

  if (appStore.state.demoMode) {
    appStore.showToast('Connected mode issues an authorized five-minute download link.', 'info')
    return
  }

  busyAction.value = 'receipt'
  actionError.value = ''
  try {
    const response = await apiRequest<PrivateDownloadResponse>(
      `/households/${appStore.state.user.householdId}/documents/${item.documentId}/download`,
      { method: 'POST' },
    )
    followPrivateDownload(response.data.url)
    appStore.showToast('Private receipt download started.')
  } catch (error) {
    actionError.value = readableError(error)
  } finally {
    busyAction.value = null
  }
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
        <button
          class="button button--secondary"
          type="button"
          :disabled="busyAction !== null || deadlines.length === 0"
          @click="addToCalendar"
        >
          <AppIcon name="calendar" :size="18" />
          {{ busyAction === 'calendar' ? 'Preparing…' : 'Add to calendar' }}
        </button>
        <button
          class="button button--primary"
          type="button"
          :disabled="busyAction !== null"
          @click="prepareClaim"
        >
          <AppIcon name="package" :size="18" />
          {{ busyAction === 'claim' ? 'Preparing…' : 'Prepare claim package' }}
        </button>
      </template>
    </PageHeader>

    <p v-if="actionError" class="field-error" role="alert">{{ actionError }}</p>

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
        <StatusPill v-if="purchase.documentId" tone="green">Stored privately</StatusPill>
        <StatusPill v-else tone="neutral">Manual entry</StatusPill>
        <small>{{ purchase.documentName || 'No receipt attached' }}</small>
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
        <section v-if="purchase.documentId" class="panel">
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
              :disabled="busyAction !== null"
              :aria-label="
                busyAction === 'receipt' ? 'Preparing receipt download' : 'Download receipt'
              "
              @click="downloadReceipt"
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
