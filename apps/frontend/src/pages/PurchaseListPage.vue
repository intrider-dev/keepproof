<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import EmptyState from '../components/EmptyState.vue'
import PageHeader from '../components/PageHeader.vue'
import StatusPill from '../components/StatusPill.vue'
import { demoStore } from '../demo/store'
import { deadlineDistance, formatDate, formatMoney } from '../lib/format'

const search = ref('')
const status = ref('all')
const deadline = ref('all')

const filteredPurchases = computed(() => {
  const query = search.value.trim().toLowerCase()
  return demoStore.state.purchases.filter((purchase) => {
    const matchesSearch =
      !query ||
      purchase.merchant.toLowerCase().includes(query) ||
      purchase.orderNumber.toLowerCase().includes(query) ||
      purchase.items.some((item) => item.name.toLowerCase().includes(query))
    const matchesStatus =
      status.value === 'all' ||
      (status.value === 'review' && purchase.status === 'needs_review') ||
      (status.value === 'active' && purchase.status === 'active')
    const matchesDeadline =
      deadline.value === 'all' ||
      (deadline.value === 'return' && Boolean(purchase.returnDeadline)) ||
      (deadline.value === 'warranty' && Boolean(purchase.warrantyDeadline))
    return matchesSearch && matchesStatus && matchesDeadline
  })
})

const clearFilters = (): void => {
  search.value = ''
  status.value = 'all'
  deadline.value = 'all'
}
</script>

<template>
  <div class="page-stack">
    <PageHeader
      eyebrow="Morgan household"
      title="Every purchase, easy to find"
      description="Search proof, review extracted details, and keep an eye on active deadlines."
    />

    <section class="filter-bar" aria-label="Purchase filters">
      <div class="search-field">
        <AppIcon name="search" :size="19" />
        <label class="sr-only" for="purchase-search">Search purchases</label>
        <input
          id="purchase-search"
          v-model="search"
          type="search"
          placeholder="Search merchant, item, or receipt…"
        />
      </div>
      <label class="filter-select">
        <span class="sr-only">Status</span>
        <select v-model="status">
          <option value="all">All statuses</option>
          <option value="active">Active</option>
          <option value="review">Needs review</option>
        </select>
      </label>
      <label class="filter-select">
        <span class="sr-only">Deadline type</span>
        <select v-model="deadline">
          <option value="all">All deadlines</option>
          <option value="return">Returns</option>
          <option value="warranty">Warranties</option>
        </select>
      </label>
    </section>

    <div class="results-summary" aria-live="polite">
      <span
        >{{ filteredPurchases.length }}
        {{ filteredPurchases.length === 1 ? 'purchase' : 'purchases' }}</span
      >
      <button
        v-if="search || status !== 'all' || deadline !== 'all'"
        class="text-button"
        type="button"
        @click="clearFilters"
      >
        Clear filters
      </button>
    </div>

    <section v-if="filteredPurchases.length" class="purchase-list" aria-label="Purchases">
      <article v-for="purchase in filteredPurchases" :key="purchase.id" class="purchase-card">
        <div class="purchase-card__proof">
          <AppIcon :name="purchase.status === 'needs_review' ? 'spark' : 'receipt'" :size="23" />
        </div>
        <div class="purchase-card__main">
          <div class="purchase-card__topline">
            <div>
              <h2>{{ purchase.merchant }}</h2>
              <p>{{ purchase.items.map((item) => item.name).join(' · ') }}</p>
            </div>
            <strong class="purchase-card__amount">{{
              formatMoney(purchase.totalMinor, purchase.currency)
            }}</strong>
          </div>
          <div class="purchase-card__meta">
            <span>{{ formatDate(purchase.purchaseDate) }}</span>
            <span v-if="purchase.orderNumber">Receipt {{ purchase.orderNumber }}</span>
            <StatusPill :tone="purchase.status === 'needs_review' ? 'blue' : 'green'">
              {{ purchase.status === 'needs_review' ? 'Needs review' : 'Confirmed' }}
            </StatusPill>
          </div>
        </div>
        <div class="purchase-card__deadline">
          <template v-if="purchase.status === 'needs_review'">
            <span>Deadline status</span>
            <strong>Waiting for you</strong>
            <small>Review fields to activate dates</small>
          </template>
          <template v-else-if="purchase.returnDeadline && purchase.returnDeadline.daysLeft >= 0">
            <span
              >Return by
              {{
                formatDate(purchase.returnDeadline.dueOn, { month: 'short', day: 'numeric' })
              }}</span
            >
            <strong>{{ deadlineDistance(purchase.returnDeadline.daysLeft) }}</strong>
            <small>{{ purchase.returnDeadline.rule }}</small>
          </template>
          <template v-else-if="purchase.warrantyDeadline">
            <span>Warranty until</span>
            <strong>{{ formatDate(purchase.warrantyDeadline.dueOn) }}</strong>
            <small>{{ purchase.warrantyDeadline.rule }}</small>
          </template>
        </div>
        <RouterLink
          class="purchase-card__link"
          :to="
            purchase.status === 'needs_review'
              ? `/app/review/${purchase.documentId}`
              : `/app/purchases/${purchase.id}`
          "
          :aria-label="`${purchase.status === 'needs_review' ? 'Review' : 'View'} ${purchase.merchant} purchase`"
        >
          <AppIcon name="arrow" />
        </RouterLink>
      </article>
    </section>

    <EmptyState
      v-else
      icon="search"
      title="No purchases match"
      description="Try a different merchant, item, status, or deadline filter."
    >
      <button class="button button--secondary" type="button" @click="clearFilters">
        Clear filters
      </button>
    </EmptyState>
  </div>
</template>
