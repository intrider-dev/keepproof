<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import type { PurchaseInput } from '../api/generated'
import AppIcon from '../components/AppIcon.vue'
import PageHeader from '../components/PageHeader.vue'
import { ApiProblem } from '../lib/api'
import { parseMoneyToMinor } from '../lib/money'
import { appStore } from '../stores/app'
import {
  validateManualPurchase,
  type ManualPurchaseDraft,
  type ValidationErrors,
} from '../validation'

interface DraftItem {
  id: string
  name: string
  quantity: string
  unitPrice: string
  serialNumber: string
}

const dateInTimezone = (timezone: string): string => {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date())
  const part = (type: Intl.DateTimeFormatPartTypes): string =>
    parts.find((candidate) => candidate.type === type)?.value ?? ''
  return `${part('year')}-${part('month')}-${part('day')}`
}

const createItem = (): DraftItem => ({
  id: crypto.randomUUID(),
  name: '',
  quantity: '1',
  unitPrice: '',
  serialNumber: '',
})

const router = useRouter()
const form = reactive({
  merchant: '',
  purchaseDate: dateInTimezone(appStore.state.user.timezone),
  receiptNumber: '',
  currency: 'USD',
  total: '',
  countryRegion: 'US',
  returnPeriod: '30',
  returnUnit: 'days' as 'days' | 'months' | 'years',
  returnConvention: 'purchase_day_exclusive' as 'purchase_day_exclusive' | 'purchase_day_inclusive',
  warrantyPeriod: '12',
  warrantyUnit: 'months' as 'days' | 'months' | 'years',
  warrantyConvention: 'purchase_day_exclusive' as
    'purchase_day_exclusive' | 'purchase_day_inclusive',
  manualReturnDeadline: '',
  manualReturnReason: '',
  manualWarrantyDeadline: '',
  manualWarrantyReason: '',
  notes: '',
  items: [createItem()],
})
const errors = ref<ValidationErrors>({})
const apiErrors = ref<Record<string, string[]>>({})
const requestError = ref('')
const busy = ref(false)

const addItem = (): void => {
  form.items.push(createItem())
}

const removeItem = (index: number): void => {
  if (form.items.length === 1) return
  form.items.splice(index, 1)
}

const fieldError = (key: string, apiKey = key): string | undefined =>
  errors.value[key] ?? apiErrors.value[apiKey]?.[0]

const validationDraft = (): ManualPurchaseDraft => ({
  merchant: form.merchant,
  purchaseDate: form.purchaseDate,
  receiptNumber: form.receiptNumber,
  currency: form.currency,
  total: form.total,
  countryRegion: form.countryRegion,
  returnPeriod: form.returnPeriod,
  warrantyPeriod: form.warrantyPeriod,
  manualReturnDeadline: form.manualReturnDeadline,
  manualReturnReason: form.manualReturnReason,
  manualWarrantyDeadline: form.manualWarrantyDeadline,
  manualWarrantyReason: form.manualWarrantyReason,
  items: form.items,
})

const optionalPeriod = (value: string): number | null => (value ? Number.parseInt(value, 10) : null)

const submit = async (): Promise<void> => {
  if (busy.value) return
  form.currency = form.currency.trim().toUpperCase()
  errors.value = validateManualPurchase(validationDraft())
  apiErrors.value = {}
  requestError.value = ''
  if (Object.keys(errors.value).length) return

  const totalMinor = parseMoneyToMinor(form.total, form.currency)
  if (totalMinor === undefined) return

  const input: PurchaseInput = {
    merchant: form.merchant.trim(),
    purchase_date: form.purchaseDate,
    timezone: appStore.state.user.timezone,
    receipt_number: form.receiptNumber.trim() || null,
    currency: form.currency,
    total_minor: totalMinor,
    country_region: form.countryRegion.trim().toUpperCase(),
    return_period_quantity: optionalPeriod(form.returnPeriod),
    return_period_unit: form.returnPeriod ? form.returnUnit : null,
    return_counting_convention: form.returnConvention,
    warranty_period_quantity: optionalPeriod(form.warrantyPeriod),
    warranty_period_unit: form.warrantyPeriod ? form.warrantyUnit : null,
    warranty_counting_convention: form.warrantyConvention,
    manual_return_deadline: form.manualReturnDeadline || null,
    manual_return_reason: form.manualReturnReason.trim() || null,
    manual_warranty_deadline: form.manualWarrantyDeadline || null,
    manual_warranty_reason: form.manualWarrantyReason.trim() || null,
    notes: form.notes.trim() || null,
    items: form.items.map((item) => ({
      name: item.name.trim(),
      quantity: Number.parseInt(item.quantity, 10),
      unit_price_minor: item.unitPrice
        ? (parseMoneyToMinor(item.unitPrice, form.currency) ?? null)
        : null,
      serial_number: item.serialNumber.trim() || null,
    })),
  }

  busy.value = true
  try {
    const purchaseId = await appStore.createManualPurchase(input)
    await router.push(`/app/purchases/${purchaseId}`)
  } catch (error) {
    if (error instanceof ApiProblem) {
      apiErrors.value = error.problem.errors ?? {}
      requestError.value = error.problem.detail
    } else {
      requestError.value = 'The purchase could not be saved. Please try again.'
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page-stack manual-purchase-page">
    <PageHeader
      eyebrow="New purchase"
      title="Enter purchase details"
      description="Add what you know now. KeepProof calculates explainable dates as soon as you save."
    />

    <nav class="entry-methods" aria-label="Choose how to add a purchase">
      <RouterLink to="/app/purchases/new">
        <AppIcon name="upload" :size="18" /> Upload a receipt
      </RouterLink>
      <RouterLink class="entry-methods__active" to="/app/purchases/new/manual" aria-current="page">
        <AppIcon name="edit" :size="18" /> Enter manually
      </RouterLink>
    </nav>

    <form class="manual-purchase-form" novalidate @submit.prevent="submit">
      <section class="panel form-section" aria-labelledby="purchase-basics-title">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Purchase</p>
            <h2 id="purchase-basics-title">What and when</h2>
          </div>
          <span class="section-number">1</span>
        </div>
        <div class="field-grid field-grid--two">
          <div class="field">
            <label for="manual-merchant">Merchant</label>
            <input
              id="manual-merchant"
              v-model="form.merchant"
              class="input"
              autocomplete="organization"
              :aria-invalid="Boolean(fieldError('merchant'))"
            />
            <p v-if="fieldError('merchant')" class="field-error">{{ fieldError('merchant') }}</p>
          </div>
          <div class="field">
            <label for="manual-date">Purchase date</label>
            <input
              id="manual-date"
              v-model="form.purchaseDate"
              class="input"
              type="date"
              :aria-invalid="Boolean(fieldError('purchaseDate', 'purchase_date'))"
            />
            <p v-if="fieldError('purchaseDate', 'purchase_date')" class="field-error">
              {{ fieldError('purchaseDate', 'purchase_date') }}
            </p>
          </div>
          <div class="field">
            <label for="manual-receipt">Order or receipt number <span>Optional</span></label>
            <input id="manual-receipt" v-model="form.receiptNumber" class="input" />
          </div>
          <div class="field">
            <label for="manual-country">Country or region</label>
            <input
              id="manual-country"
              v-model="form.countryRegion"
              class="input"
              maxlength="80"
              :aria-invalid="Boolean(fieldError('countryRegion', 'country_region'))"
            />
            <p v-if="fieldError('countryRegion', 'country_region')" class="field-error">
              {{ fieldError('countryRegion', 'country_region') }}
            </p>
          </div>
          <div class="field">
            <label for="manual-currency">Currency</label>
            <input
              id="manual-currency"
              v-model="form.currency"
              class="input"
              maxlength="3"
              autocapitalize="characters"
              :aria-invalid="Boolean(fieldError('currency'))"
            />
            <p v-if="fieldError('currency')" class="field-error">{{ fieldError('currency') }}</p>
          </div>
          <div class="field">
            <label for="manual-total">Total paid</label>
            <input
              id="manual-total"
              v-model="form.total"
              class="input"
              inputmode="decimal"
              placeholder="0.00"
              :aria-invalid="Boolean(fieldError('total', 'total_minor'))"
            />
            <p v-if="fieldError('total', 'total_minor')" class="field-error">
              {{ fieldError('total', 'total_minor') }}
            </p>
          </div>
        </div>
      </section>

      <section class="panel form-section" aria-labelledby="purchase-items-title">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Items</p>
            <h2 id="purchase-items-title">What you bought</h2>
          </div>
          <span class="section-number">2</span>
        </div>
        <div class="manual-items">
          <fieldset v-for="(item, index) in form.items" :key="item.id" class="manual-item">
            <legend>Item {{ index + 1 }}</legend>
            <div class="field manual-item__name">
              <label :for="`manual-item-name-${index}`">Name</label>
              <input
                :id="`manual-item-name-${index}`"
                v-model="item.name"
                class="input"
                :aria-invalid="Boolean(fieldError(`items.${index}.name`))"
              />
              <p v-if="fieldError(`items.${index}.name`)" class="field-error">
                {{ fieldError(`items.${index}.name`) }}
              </p>
            </div>
            <div class="field">
              <label :for="`manual-item-quantity-${index}`">Quantity</label>
              <input
                :id="`manual-item-quantity-${index}`"
                v-model="item.quantity"
                class="input"
                inputmode="numeric"
                :aria-invalid="Boolean(fieldError(`items.${index}.quantity`))"
              />
              <p v-if="fieldError(`items.${index}.quantity`)" class="field-error">
                {{ fieldError(`items.${index}.quantity`) }}
              </p>
            </div>
            <div class="field">
              <label :for="`manual-item-price-${index}`">Unit price <span>Optional</span></label>
              <input
                :id="`manual-item-price-${index}`"
                v-model="item.unitPrice"
                class="input"
                inputmode="decimal"
                :aria-invalid="
                  Boolean(fieldError(`items.${index}.unitPrice`, `items.${index}.unit_price_minor`))
                "
              />
              <p
                v-if="fieldError(`items.${index}.unitPrice`, `items.${index}.unit_price_minor`)"
                class="field-error"
              >
                {{ fieldError(`items.${index}.unitPrice`, `items.${index}.unit_price_minor`) }}
              </p>
            </div>
            <div class="field">
              <label :for="`manual-item-serial-${index}`"
                >Serial number <span>Optional</span></label
              >
              <input
                :id="`manual-item-serial-${index}`"
                v-model="item.serialNumber"
                class="input"
              />
            </div>
            <button
              v-if="form.items.length > 1"
              class="icon-button manual-item__remove"
              type="button"
              :aria-label="`Remove item ${index + 1}`"
              @click="removeItem(index)"
            >
              <AppIcon name="close" :size="17" />
            </button>
          </fieldset>
        </div>
        <button class="button button--secondary" type="button" @click="addItem">
          <AppIcon name="plus" :size="18" /> Add another item
        </button>
      </section>

      <section class="panel form-section" aria-labelledby="purchase-rules-title">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Deadline rules</p>
            <h2 id="purchase-rules-title">Return and warranty periods</h2>
          </div>
          <span class="section-number">3</span>
        </div>
        <div class="deadline-rule-grid">
          <fieldset>
            <legend>Return window</legend>
            <div class="period-inputs">
              <div class="field">
                <label for="manual-return-period">Length</label>
                <input
                  id="manual-return-period"
                  v-model="form.returnPeriod"
                  class="input"
                  inputmode="numeric"
                  :aria-invalid="Boolean(fieldError('returnPeriod', 'return_period_quantity'))"
                />
                <p v-if="fieldError('returnPeriod', 'return_period_quantity')" class="field-error">
                  {{ fieldError('returnPeriod', 'return_period_quantity') }}
                </p>
              </div>
              <div class="field">
                <label for="manual-return-unit">Unit</label>
                <select id="manual-return-unit" v-model="form.returnUnit" class="select">
                  <option value="days">Days</option>
                  <option value="months">Months</option>
                  <option value="years">Years</option>
                </select>
              </div>
            </div>
            <label class="field">
              <span>Counting rule</span>
              <select v-model="form.returnConvention" class="select">
                <option value="purchase_day_exclusive">Start the day after purchase</option>
                <option value="purchase_day_inclusive">Count the purchase day</option>
              </select>
            </label>
          </fieldset>
          <fieldset>
            <legend>Warranty period</legend>
            <div class="period-inputs">
              <div class="field">
                <label for="manual-warranty-period">Length</label>
                <input
                  id="manual-warranty-period"
                  v-model="form.warrantyPeriod"
                  class="input"
                  inputmode="numeric"
                  :aria-invalid="Boolean(fieldError('warrantyPeriod', 'warranty_period_quantity'))"
                />
                <p
                  v-if="fieldError('warrantyPeriod', 'warranty_period_quantity')"
                  class="field-error"
                >
                  {{ fieldError('warrantyPeriod', 'warranty_period_quantity') }}
                </p>
              </div>
              <div class="field">
                <label for="manual-warranty-unit">Unit</label>
                <select id="manual-warranty-unit" v-model="form.warrantyUnit" class="select">
                  <option value="days">Days</option>
                  <option value="months">Months</option>
                  <option value="years">Years</option>
                </select>
              </div>
            </div>
            <label class="field">
              <span>Counting rule</span>
              <select v-model="form.warrantyConvention" class="select">
                <option value="purchase_day_exclusive">Start the day after purchase</option>
                <option value="purchase_day_inclusive">Count the purchase day</option>
              </select>
            </label>
          </fieldset>
        </div>

        <details class="advanced-rules">
          <summary>Use a documented deadline override</summary>
          <p>
            Only override a calculated date when you have a specific store or manufacturer policy.
          </p>
          <div class="field-grid field-grid--two">
            <div class="field">
              <label for="manual-return-deadline">Return deadline</label>
              <input
                id="manual-return-deadline"
                v-model="form.manualReturnDeadline"
                class="input"
                type="date"
                :aria-invalid="
                  Boolean(fieldError('manualReturnDeadline', 'manual_return_deadline'))
                "
              />
              <p
                v-if="fieldError('manualReturnDeadline', 'manual_return_deadline')"
                class="field-error"
              >
                {{ fieldError('manualReturnDeadline', 'manual_return_deadline') }}
              </p>
            </div>
            <div class="field">
              <label for="manual-return-reason">Reason for return override</label>
              <input
                id="manual-return-reason"
                v-model="form.manualReturnReason"
                class="input"
                :aria-invalid="Boolean(fieldError('manualReturnReason', 'manual_return_reason'))"
              />
              <p
                v-if="fieldError('manualReturnReason', 'manual_return_reason')"
                class="field-error"
              >
                {{ fieldError('manualReturnReason', 'manual_return_reason') }}
              </p>
            </div>
            <div class="field">
              <label for="manual-warranty-deadline">Warranty deadline</label>
              <input
                id="manual-warranty-deadline"
                v-model="form.manualWarrantyDeadline"
                class="input"
                type="date"
                :aria-invalid="
                  Boolean(fieldError('manualWarrantyDeadline', 'manual_warranty_deadline'))
                "
              />
              <p
                v-if="fieldError('manualWarrantyDeadline', 'manual_warranty_deadline')"
                class="field-error"
              >
                {{ fieldError('manualWarrantyDeadline', 'manual_warranty_deadline') }}
              </p>
            </div>
            <div class="field">
              <label for="manual-warranty-reason">Reason for warranty override</label>
              <input
                id="manual-warranty-reason"
                v-model="form.manualWarrantyReason"
                class="input"
                :aria-invalid="
                  Boolean(fieldError('manualWarrantyReason', 'manual_warranty_reason'))
                "
              />
              <p
                v-if="fieldError('manualWarrantyReason', 'manual_warranty_reason')"
                class="field-error"
              >
                {{ fieldError('manualWarrantyReason', 'manual_warranty_reason') }}
              </p>
            </div>
          </div>
        </details>
      </section>

      <section class="panel form-section" aria-labelledby="purchase-notes-title">
        <div class="panel__heading">
          <div>
            <p class="eyebrow">Private context</p>
            <h2 id="purchase-notes-title">Notes</h2>
          </div>
          <span class="section-number">4</span>
        </div>
        <div class="field">
          <label for="manual-notes"
            >Anything useful for a future return or claim <span>Optional</span></label
          >
          <textarea
            id="manual-notes"
            v-model="form.notes"
            class="input textarea"
            maxlength="10000"
          ></textarea>
          <p class="field-hint">Stored as plain text and rendered without executable markup.</p>
        </div>
      </section>

      <div class="manual-form-actions">
        <p>
          <AppIcon name="info" :size="17" /> KeepProof tracks the dates you provide. It does not
          give legal advice or guarantee local return law.
        </p>
        <p v-if="requestError" class="form-alert" role="alert">{{ requestError }}</p>
        <div>
          <RouterLink class="button button--quiet" to="/app/purchases">Cancel</RouterLink>
          <button class="button button--primary" type="submit" :disabled="busy">
            <span v-if="busy" class="spinner" aria-hidden="true"></span>
            {{ busy ? 'Calculating deadlines…' : 'Save and calculate deadlines' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
