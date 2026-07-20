import { computed, reactive, ref } from 'vue'
import {
  defaultNotificationPreferences,
  documentFixtures,
  memberFixtures,
  purchaseFixtures,
} from './fixtures'
import type {
  ExtractionField,
  HouseholdMember,
  NotificationPreferences,
  Purchase,
  ReceiptDocument,
} from '../types'

const clone = <T>(value: T): T => structuredClone(value)

const state = reactive({
  isAuthenticated: false,
  demoMode: false,
  user: {
    name: 'Alex Morgan',
    email: 'demo@keepproof.local',
    householdName: 'Morgan household',
    timezone: 'America/New_York',
  },
  purchases: clone(purchaseFixtures),
  documents: clone(documentFixtures),
  members: clone(memberFixtures),
  preferences: clone(defaultNotificationPreferences),
})

const toast = ref<{ message: string; tone: 'success' | 'info' } | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | undefined

const showToast = (message: string, tone: 'success' | 'info' = 'success'): void => {
  toast.value = { message, tone }
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = null
  }, 4200)
}

const resetFixtures = (): void => {
  state.purchases = clone(purchaseFixtures)
  state.documents = clone(documentFixtures)
  state.members = clone(memberFixtures)
  state.preferences = clone(defaultNotificationPreferences)
}

const startDemo = (): void => {
  resetFixtures()
  state.isAuthenticated = true
  state.demoMode = true
}

const signInDemo = (name = 'Alex Morgan', email = 'demo@keepproof.local'): void => {
  resetFixtures()
  state.user.name = name
  state.user.email = email
  state.user.householdName = name === 'Alex Morgan' ? 'Morgan household' : `${name}'s household`
  state.members[0] = {
    ...state.members[0],
    name,
    email,
    initials: name
      .split(' ')
      .map((part) => part[0])
      .join('')
      .slice(0, 2)
      .toUpperCase(),
  }
  state.isAuthenticated = true
  state.demoMode = true
}

const signOut = (): void => {
  state.isAuthenticated = false
  state.demoMode = false
}

const purchaseById = (id: string): Purchase | undefined =>
  state.purchases.find((purchase) => purchase.id === id)

const documentById = (id: string): ReceiptDocument | undefined =>
  state.documents.find((document) => document.id === id)

const createSyntheticUpload = (): string => {
  state.purchases = state.purchases.filter((purchase) => purchase.id !== 'purchase-upload')
  state.documents = state.documents.filter((document) => document.id !== 'document-upload')

  const fields: ExtractionField[] = [
    {
      key: 'merchant',
      label: 'Merchant',
      value: 'Evergreen Market',
      normalizedValue: 'Evergreen Market',
      confidence: 0.98,
      source: 'Receipt header',
      errors: [],
    },
    {
      key: 'purchaseDate',
      label: 'Purchase date',
      value: '2026-07-18',
      normalizedValue: '2026-07-18',
      confidence: 0.95,
      source: 'Header · line 2',
      errors: [],
      type: 'date',
    },
    {
      key: 'orderNumber',
      label: 'Receipt number',
      value: 'EM-1049',
      normalizedValue: 'EM-1049',
      confidence: 0.91,
      source: 'Header · line 3',
      errors: [],
    },
    {
      key: 'currency',
      label: 'Currency',
      value: 'USD',
      normalizedValue: 'USD',
      confidence: 0.99,
      source: 'Total line',
      errors: [],
    },
    {
      key: 'total',
      label: 'Total',
      value: '64.50',
      normalizedValue: '64.50',
      confidence: 0.94,
      source: 'Total line',
      errors: [],
      inputMode: 'decimal',
    },
    {
      key: 'itemName',
      label: 'Item',
      value: 'Travel coffee press',
      normalizedValue: 'Travel coffee press',
      confidence: 0.92,
      source: 'Item line 1',
      errors: [],
    },
    {
      key: 'returnPeriod',
      label: 'Return period',
      value: '30 days',
      normalizedValue: 'P30D',
      confidence: 0.82,
      source: 'Footer policy',
      errors: ['Confirm that the store policy applies to this item.'],
    },
    {
      key: 'warrantyPeriod',
      label: 'Warranty period',
      value: '12 months',
      normalizedValue: 'P12M',
      confidence: 0.8,
      source: 'Footer policy',
      errors: ['Confirm against the manufacturer documentation if available.'],
    },
  ]

  state.purchases.unshift({
    id: 'purchase-upload',
    status: 'needs_review',
    merchant: 'Receipt awaiting review',
    purchaseDate: '2026-07-18',
    orderNumber: '',
    currency: 'USD',
    totalMinor: 6450,
    country: 'United States',
    notes: '',
    items: [{ id: 'item-upload', name: 'Unconfirmed item', quantity: 1, unitPriceMinor: 6450 }],
    documentId: 'document-upload',
    documentName: 'synthetic-evergreen-receipt.png',
  })
  state.documents.unshift({
    id: 'document-upload',
    purchaseId: 'purchase-upload',
    filename: 'synthetic-evergreen-receipt.png',
    state: 'needs_review',
    localOnly: true,
    fields,
  })

  return 'document-upload'
}

const confirmExtraction = (documentId: string, values: Record<string, string>): string => {
  const document = documentById(documentId)
  if (!document) throw new Error('Document not found')
  const purchase = purchaseById(document.purchaseId)
  if (!purchase) throw new Error('Purchase not found')

  purchase.status = 'active'
  purchase.merchant = values.merchant ?? purchase.merchant
  purchase.purchaseDate = values.purchaseDate ?? purchase.purchaseDate
  purchase.orderNumber = values.orderNumber ?? ''
  purchase.currency = values.currency ?? purchase.currency
  purchase.totalMinor = Math.round(Number(values.total ?? '0') * 100)
  purchase.items = [
    {
      id: `item-${purchase.id}`,
      name: values.itemName ?? 'Purchased item',
      quantity: 1,
      unitPriceMinor: purchase.totalMinor,
    },
  ]
  purchase.returnDeadline = {
    id: `deadline-${purchase.id}-return`,
    type: 'return',
    dueOn: '2026-08-17',
    daysLeft: 28,
    urgency: 'upcoming',
    rule: values.returnPeriod || '30 calendar days',
    explanation:
      'The confirmed 30-day return period starts from the purchase date and ends on August 17.',
    reminders: [7, 3, 1],
  }
  purchase.warrantyDeadline = {
    id: `deadline-${purchase.id}-warranty`,
    type: 'warranty',
    dueOn: '2027-07-18',
    daysLeft: 363,
    urgency: 'later',
    rule: values.warrantyPeriod || '12 calendar months',
    explanation: 'The confirmed warranty period ends 12 calendar months after purchase.',
    reminders: [30, 7],
  }
  document.state = 'ready'
  showToast('Purchase confirmed. Your deadlines are now active.')
  return purchase.id
}

const addMember = (email: string): void => {
  const name = email
    .split('@')[0]
    .split(/[._-]/)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
  const member: HouseholdMember = {
    id: `member-${Date.now()}`,
    name,
    email,
    role: 'Member',
    initials: name
      .split(' ')
      .map((part) => part[0])
      .join('')
      .slice(0, 2)
      .toUpperCase(),
  }
  state.members.push(member)
  showToast(`Demo invitation created for ${email}.`)
}

const updatePreferences = (preferences: NotificationPreferences): void => {
  state.preferences = clone(preferences)
  showToast('Notification preferences saved.')
}

const deleteDemoAccount = (): void => {
  resetFixtures()
  state.isAuthenticated = false
  state.demoMode = false
}

const activePurchases = computed(() =>
  state.purchases.filter((purchase) => purchase.status === 'active'),
)

const reviewDocuments = computed(() =>
  state.documents.filter((document) => document.state === 'needs_review'),
)

export const demoStore = {
  state,
  toast,
  activePurchases,
  reviewDocuments,
  startDemo,
  signInDemo,
  signOut,
  showToast,
  purchaseById,
  documentById,
  createSyntheticUpload,
  confirmExtraction,
  addMember,
  updatePreferences,
  deleteDemoAccount,
}
