import { computed, reactive, ref } from 'vue'
import type {
  Deadline as ApiDeadline,
  HouseholdCollection,
  InAppNotificationCollection,
  NotificationPreferencesResponse,
  Purchase as ApiPurchase,
  PurchaseCollection,
  PurchaseInput,
  PurchaseResponse,
  ReceiptDocument as ApiReceiptDocument,
  ReceiptDocumentResponse,
  UserResponse,
} from '../api/generated'
import { ApiProblem, apiRequest } from '../lib/api'
import { minorToDecimal, parseMoneyToMinor } from '../lib/money'
import {
  defaultNotificationPreferences,
  documentFixtures,
  memberFixtures,
  notificationFixtures,
  purchaseFixtures,
  scheduledReminderFixtures,
} from '../demo/fixtures'
import type {
  Deadline,
  ExtractionField,
  HouseholdMember,
  InAppNotification,
  NotificationPreferences,
  Purchase,
  ReceiptDocument,
  ScheduledReminder,
} from '../types'

const clone = <T>(value: T): T => structuredClone(value)

const state = reactive({
  isAuthenticated: false,
  demoMode: false,
  sessionChecked: false,
  user: {
    id: '',
    name: 'Alex Morgan',
    email: 'demo@keepproof.local',
    emailVerified: true,
    householdId: '',
    householdName: 'Morgan household',
    timezone: 'America/New_York',
  },
  purchases: clone(purchaseFixtures),
  documents: clone(documentFixtures),
  members: clone(memberFixtures),
  notifications: clone(notificationFixtures),
  scheduledReminders: clone(scheduledReminderFixtures),
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
  state.notifications = clone(notificationFixtures)
  state.scheduledReminders = clone(scheduledReminderFixtures)
  state.preferences = clone(defaultNotificationPreferences)
}

const startDemo = (): void => {
  resetFixtures()
  state.isAuthenticated = true
  state.demoMode = true
  state.sessionChecked = true
  state.user.id = 'demo-user'
  state.user.name = 'Alex Morgan'
  state.user.email = 'demo@keepproof.local'
  state.user.emailVerified = true
  state.user.householdId = 'demo-household'
  state.user.householdName = 'Morgan household'
  state.user.timezone = 'America/New_York'
}

const clearSession = (): void => {
  state.isAuthenticated = false
  state.demoMode = false
  state.sessionChecked = true
  state.user.id = ''
  state.user.emailVerified = false
  state.user.householdId = ''
}

const signOut = async (): Promise<void> => {
  if (state.isAuthenticated && !state.demoMode) {
    try {
      await apiRequest<void>('/auth/logout', { method: 'POST' })
    } catch (error) {
      if (!(error instanceof ApiProblem) || error.problem.status !== 401) throw error
    }
  }

  clearSession()
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
      key: 'country',
      label: 'Country or region',
      value: 'US',
      normalizedValue: 'US',
      confidence: 0.7,
      source: 'Demo default',
      errors: ['Confirm the country or region where the purchase was made.'],
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

const confirmDemoExtraction = (documentId: string, values: Record<string, string>): string => {
  const document = documentById(documentId)
  if (!document) throw new Error('Document not found')
  const purchase = purchaseById(document.purchaseId)
  if (!purchase) throw new Error('Purchase not found')

  purchase.status = 'active'
  purchase.merchant = values.merchant ?? purchase.merchant
  purchase.purchaseDate = values.purchaseDate ?? purchase.purchaseDate
  purchase.orderNumber = values.orderNumber ?? ''
  purchase.currency = values.currency ?? purchase.currency
  purchase.country = values.country ?? purchase.country
  purchase.totalMinor = parseMoneyToMinor(values.total ?? '', values.currency ?? '') ?? 0
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

const addDemoMember = (email: string): void => {
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

const updateDemoPreferences = (preferences: NotificationPreferences): void => {
  state.preferences = clone(preferences)
  showToast('Notification preferences saved.')
}

const deleteDemoAccount = (): void => {
  resetFixtures()
  state.isAuthenticated = false
  state.demoMode = false
}

const addDemoPeriod = (
  isoDate: string,
  quantity: number,
  unit: 'days' | 'months' | 'years',
): string => {
  const [year, month, day] = isoDate.split('-').map(Number)
  if (!year || !month || !day) return isoDate

  if (unit === 'days') {
    const date = new Date(Date.UTC(year, month - 1, day + quantity))
    return date.toISOString().slice(0, 10)
  }

  const monthOffset = unit === 'years' ? quantity * 12 : quantity
  const targetMonth = month - 1 + monthOffset
  const targetYear = year + Math.floor(targetMonth / 12)
  const normalizedMonth = ((targetMonth % 12) + 12) % 12
  const lastDay = new Date(Date.UTC(targetYear, normalizedMonth + 1, 0)).getUTCDate()
  return new Date(Date.UTC(targetYear, normalizedMonth, Math.min(day, lastDay)))
    .toISOString()
    .slice(0, 10)
}

const demoDeadline = (
  purchaseId: string,
  kind: Deadline['type'],
  purchaseDate: string,
  quantity: number | null | undefined,
  unit: 'days' | 'months' | 'years' | null | undefined,
): Deadline | undefined => {
  if (!quantity || !unit) return undefined
  const dueOn = addDemoPeriod(purchaseDate, quantity, unit)
  const due = Date.parse(`${dueOn}T00:00:00Z`)
  const today = new Date()
  const startOfToday = Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate())
  const daysLeft = Math.ceil((due - startOfToday) / 86_400_000)

  return {
    id: `deadline-${purchaseId}-${kind}`,
    type: kind,
    dueOn,
    daysLeft,
    urgency: deadlineUrgency(daysLeft),
    rule: `${quantity} calendar ${unit}`,
    explanation: 'Synthetic demo preview. The connected API returns the authoritative rule trace.',
    reminders: kind === 'return' ? [7, 3, 1] : [30, 7],
  }
}

const createDemoManualPurchase = (input: PurchaseInput): string => {
  const id = `purchase-manual-${Date.now()}`
  const purchase: Purchase = {
    id,
    status: 'active',
    merchant: input.merchant,
    purchaseDate: input.purchase_date,
    orderNumber: input.receipt_number ?? '',
    currency: input.currency,
    totalMinor: input.total_minor,
    country: countryName(input.country_region),
    notes: input.notes ?? '',
    items: input.items.map((item, index) => ({
      id: `item-${id}-${index + 1}`,
      name: item.name,
      quantity: item.quantity,
      unitPriceMinor: item.unit_price_minor ?? 0,
    })),
    returnDeadline: demoDeadline(
      id,
      'return',
      input.purchase_date,
      input.return_period_quantity,
      input.return_period_unit,
    ),
    warrantyDeadline: demoDeadline(
      id,
      'warranty',
      input.purchase_date,
      input.warranty_period_quantity,
      input.warranty_period_unit,
    ),
  }
  state.purchases.unshift(purchase)
  showToast('Synthetic manual purchase added.')
  return id
}

type SessionUserResponse = UserResponse

interface ApiHouseholdMember {
  id: string
  role: 'owner' | 'member'
  user: { id: string; name: string; email: string }
}

interface HouseholdMemberCollection {
  data: ApiHouseholdMember[]
}

interface DashboardResponse {
  data: {
    needs_review: ApiReceiptDocument[]
    scheduled_reminders: Array<{
      id: string
      channel: 'in_app' | 'email'
      status: 'scheduled'
      interval_days: number
      remind_at: string
      deadline: {
        id: string
        kind: 'return' | 'warranty'
        purchase_id: string
        merchant: string
      }
    }>
  }
}

const resetRemoteCollections = (): void => {
  state.purchases = []
  state.documents = []
  state.members = []
  state.notifications = []
  state.scheduledReminders = []
}

const deadlineUrgency = (daysLeft: number): Deadline['urgency'] => {
  if (daysLeft < 0) return 'past'
  if (daysLeft <= 7) return 'soon'
  if (daysLeft <= 30) return 'upcoming'
  return 'later'
}

const mapDeadline = (deadline: ApiDeadline): Deadline => {
  const due = Date.parse(`${deadline.due_on}T00:00:00Z`)
  const today = new Date()
  const startOfToday = Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate())
  const daysLeft = Math.ceil((due - startOfToday) / 86_400_000)

  return {
    id: deadline.id,
    type: deadline.kind,
    dueOn: deadline.due_on,
    daysLeft,
    urgency: deadlineUrgency(daysLeft),
    rule: deadline.rule_summary,
    explanation:
      deadline.source === 'manual_override'
        ? `A confirmed manual override sets this deadline to ${deadline.due_on}.`
        : deadline.rule_summary,
    reminders: deadline.reminder_intervals,
  }
}

const countryName = (region: string): string => {
  try {
    return new Intl.DisplayNames(['en'], { type: 'region' }).of(region) ?? region
  } catch {
    return region
  }
}

const mapPurchase = (purchase: ApiPurchase): Purchase => {
  const returnDeadline = purchase.deadlines.find((deadline) => deadline.kind === 'return')
  const warrantyDeadline = purchase.deadlines.find((deadline) => deadline.kind === 'warranty')
  const document =
    purchase.documents?.find((candidate) => candidate.retain_original) ?? purchase.documents?.[0]

  return {
    id: purchase.id,
    status: purchase.state,
    merchant: purchase.merchant,
    purchaseDate: purchase.purchase_date,
    orderNumber: purchase.receipt_number ?? '',
    currency: purchase.currency,
    totalMinor: purchase.total_minor,
    country: countryName(purchase.country_region),
    notes: purchase.notes ?? '',
    documentId: document?.id,
    documentName: document?.filename,
    items: purchase.items.map((item) => ({
      id: item.id,
      name: item.name,
      quantity: item.quantity,
      unitPriceMinor: item.unit_price_minor ?? 0,
    })),
    returnDeadline: returnDeadline ? mapDeadline(returnDeadline) : undefined,
    warrantyDeadline: warrantyDeadline ? mapDeadline(warrantyDeadline) : undefined,
  }
}

const remoteField = (
  document: ApiReceiptDocument,
  apiKey: string,
  key: ExtractionField['key'],
  label: string,
  fallback: string,
  options: Pick<ExtractionField, 'inputMode' | 'type'> = {},
): ExtractionField => {
  const field = document.fields.find((candidate) => candidate.key === apiKey)
  const value = field?.normalized_value ?? field?.value ?? fallback

  return {
    key,
    label,
    value,
    normalizedValue: field?.normalized_value ?? value,
    confidence:
      field?.confidence === null || field?.confidence === undefined ? 0 : Number(field.confidence),
    source:
      field?.source.replace('ocr:', 'OCR · ').replace('derived:', 'Derived · ') ?? 'Manual review',
    errors: field
      ? field.validation_errors
      : ['This value was not extracted. Enter it before confirming.'],
    ...options,
  }
}

const mapDocument = (document: ApiReceiptDocument): ReceiptDocument => {
  const currency = remoteField(document, 'currency', 'currency', 'Currency', 'USD')
  const total = remoteField(document, 'total_minor', 'total', 'Total', '0.00', {
    inputMode: 'decimal',
  })
  const totalMinor = Number(total.value)
  if (Number.isInteger(totalMinor) && totalMinor >= 0 && !total.value.includes('.')) {
    total.value = minorToDecimal(totalMinor, currency.value)
    total.normalizedValue = total.value
  }

  return {
    id: document.id,
    purchaseId: document.purchase_id,
    filename: document.filename,
    state: document.state === 'ready' ? 'ready' : 'needs_review',
    localOnly: true,
    fields: [
      remoteField(document, 'merchant', 'merchant', 'Merchant', ''),
      remoteField(document, 'purchase_date', 'purchaseDate', 'Purchase date', '', {
        type: 'date',
      }),
      remoteField(document, 'receipt_number', 'orderNumber', 'Receipt number', ''),
      currency,
      remoteField(document, 'country_region', 'country', 'Country or region', 'US'),
      total,
      remoteField(document, 'item_name', 'itemName', 'Item', ''),
      remoteField(document, 'return_period_quantity', 'returnPeriod', 'Return period (days)', '30'),
      remoteField(
        document,
        'warranty_period_quantity',
        'warrantyPeriod',
        'Warranty period (months)',
        '12',
      ),
    ],
  }
}

const applyRemoteUser = (response: SessionUserResponse): void => {
  state.isAuthenticated = true
  state.demoMode = false
  state.sessionChecked = true
  state.user.id = response.data.id
  state.user.name = response.data.name
  state.user.email = response.data.email
  state.user.emailVerified = response.data.email_verified
  state.user.timezone = response.data.timezone

  const household = response.data.households?.[0]
  if (household) {
    state.user.householdId = household.id
    state.user.householdName = household.name
  }
}

const hydrateRemoteWorkspace = async (): Promise<void> => {
  if (!state.user.emailVerified) return

  const households = await apiRequest<HouseholdCollection>('/households?per_page=100')
  const household =
    households.data.find((candidate) => candidate.id === state.user.householdId) ??
    households.data[0]
  if (!household) throw new Error('The authenticated account has no household.')

  state.user.householdId = household.id
  state.user.householdName = household.name

  const [purchases, dashboard, members, preferences, notifications] = await Promise.all([
    apiRequest<PurchaseCollection>(`/households/${household.id}/purchases?per_page=100`),
    apiRequest<DashboardResponse>(`/households/${household.id}/dashboard`),
    apiRequest<HouseholdMemberCollection>(`/households/${household.id}/members`),
    apiRequest<NotificationPreferencesResponse>('/notification-preferences'),
    apiRequest<InAppNotificationCollection>('/notifications?per_page=20'),
  ])

  state.purchases = purchases.data.map(mapPurchase)
  state.documents = dashboard.data.needs_review.map(mapDocument)
  for (const document of state.documents) {
    const purchase = state.purchases.find((candidate) => candidate.id === document.purchaseId)
    if (purchase) {
      purchase.documentId = document.id
      purchase.documentName = document.filename
    }
  }
  state.members = members.data.map((membership) => ({
    id: membership.user.id,
    name: membership.user.name,
    email: membership.user.email,
    role: membership.role === 'owner' ? 'Owner' : 'Member',
    initials: membership.user.name
      .split(/\s+/)
      .map((part) => part[0] ?? '')
      .join('')
      .slice(0, 2)
      .toUpperCase(),
  }))
  state.scheduledReminders = dashboard.data.scheduled_reminders.map(
    (reminder): ScheduledReminder => ({
      id: reminder.id,
      channel: reminder.channel,
      intervalDays: reminder.interval_days,
      remindAt: reminder.remind_at,
      purchaseId: reminder.deadline.purchase_id,
      merchant: reminder.deadline.merchant,
      kind: reminder.deadline.kind,
    }),
  )
  state.notifications = notifications.data.map((notification): InAppNotification => ({
    id: notification.id,
    purchaseId: notification.data.purchase_id,
    deadlineId: notification.data.deadline_id,
    kind: notification.data.kind,
    merchant: notification.data.merchant,
    dueOn: notification.data.due_on,
    message: notification.data.message,
    readAt: notification.read_at,
    createdAt: notification.created_at,
  }))
  const quietHours = Boolean(preferences.data.quiet_hours_start && preferences.data.quiet_hours_end)
  state.preferences = {
    inApp: preferences.data.in_app_enabled,
    email: preferences.data.email_enabled,
    reminderDays: [
      ...new Set([...preferences.data.return_intervals, ...preferences.data.warranty_intervals]),
    ].sort((left, right) => right - left),
    quietHours,
    quietFrom: preferences.data.quiet_hours_start ?? '22:00',
    quietUntil: preferences.data.quiet_hours_end ?? '07:00',
  }
}

const activateRemoteSession = async (response: SessionUserResponse): Promise<boolean> => {
  resetRemoteCollections()
  applyRemoteUser(response)
  if (!response.data.email_verified) return false
  await hydrateRemoteWorkspace()
  return true
}

const restoreSession = async (): Promise<boolean> => {
  if (state.isAuthenticated || state.sessionChecked) return state.isAuthenticated

  try {
    const response = await apiRequest<SessionUserResponse>('/me')
    await activateRemoteSession(response)
    return true
  } catch (error) {
    if (error instanceof ApiProblem && error.problem.status === 401) {
      clearSession()
      return false
    }
    throw error
  }
}

const delay = async (milliseconds: number): Promise<void> =>
  new Promise((resolve) => globalThis.setTimeout(resolve, milliseconds))

const uploadReceipt = async (file?: File): Promise<string> => {
  if (state.demoMode) return createSyntheticUpload()
  if (!file) throw new Error('Choose a receipt before starting extraction.')
  if (!state.user.householdId) throw new Error('No household is selected.')

  const body = new FormData()
  body.set('receipt', file)
  const response = await apiRequest<ReceiptDocumentResponse>(
    `/households/${state.user.householdId}/receipts`,
    {
      method: 'POST',
      body,
      headers: { 'Idempotency-Key': `upload-${crypto.randomUUID()}` },
    },
  )

  for (let attempt = 0; attempt < 60; attempt += 1) {
    await delay(attempt === 0 ? 250 : 1000)
    const current = await apiRequest<ReceiptDocumentResponse>(
      `/households/${state.user.householdId}/documents/${response.data.id}`,
    )
    if (current.data.state === 'needs_review' || current.data.state === 'ready') {
      await hydrateRemoteWorkspace()
      if (!state.documents.some((document) => document.id === current.data.id)) {
        state.documents.unshift(mapDocument(current.data))
      }
      return current.data.id
    }
    if (current.data.state === 'failed' || current.data.state === 'quarantined') {
      throw new Error(current.data.user_error ?? 'Receipt processing could not be completed.')
    }
  }

  throw new Error('Receipt processing is still pending. Check the review queue in a moment.')
}

const positiveWholeNumber = (value: string | undefined, fallback: number): number => {
  const parsed = Number.parseInt(value ?? '', 10)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
}

const confirmExtraction = async (
  documentId: string,
  values: Record<string, string>,
): Promise<string> => {
  if (state.demoMode) return confirmDemoExtraction(documentId, values)
  if (!state.user.householdId) throw new Error('No household is selected.')

  const totalMinor = parseMoneyToMinor(values.total ?? '', values.currency ?? '')
  if (totalMinor === undefined) throw new Error('Enter a valid total for the selected currency.')
  const response = await apiRequest<ReceiptDocumentResponse>(
    `/households/${state.user.householdId}/documents/${documentId}/confirmation`,
    {
      method: 'PUT',
      headers: { 'Idempotency-Key': `confirm-${documentId}` },
      body: JSON.stringify({
        merchant: values.merchant,
        purchase_date: values.purchaseDate,
        timezone: state.user.timezone,
        receipt_number: values.orderNumber || null,
        currency: values.currency?.toUpperCase(),
        total_minor: totalMinor,
        country_region: values.country,
        return_period_quantity: positiveWholeNumber(values.returnPeriod, 30),
        return_period_unit: 'days',
        return_counting_convention: 'purchase_day_exclusive',
        warranty_period_quantity: positiveWholeNumber(values.warrantyPeriod, 12),
        warranty_period_unit: 'months',
        warranty_counting_convention: 'purchase_day_exclusive',
        notes: null,
        items: [
          {
            name: values.itemName,
            quantity: 1,
            unit_price_minor: totalMinor,
            serial_number: null,
          },
        ],
      }),
    },
  )

  const purchaseId = response.data.purchase?.id ?? response.data.purchase_id
  await hydrateRemoteWorkspace()
  showToast('Purchase confirmed. Your deadlines are now active.')
  return purchaseId
}

const createManualPurchase = async (input: PurchaseInput): Promise<string> => {
  if (state.demoMode) return createDemoManualPurchase(input)
  if (!state.user.householdId) throw new Error('No household is selected.')

  const response = await apiRequest<PurchaseResponse>(
    `/households/${state.user.householdId}/purchases`,
    {
      method: 'POST',
      body: JSON.stringify(input),
    },
  )
  await hydrateRemoteWorkspace()
  showToast('Purchase added. Your deadlines are active.')
  return response.data.id
}

const addMember = async (email: string): Promise<void> => {
  if (state.demoMode) {
    addDemoMember(email)
    return
  }
  if (!state.user.householdId) throw new Error('No household is selected.')

  await apiRequest(`/households/${state.user.householdId}/invitations`, {
    method: 'POST',
    body: JSON.stringify({ email, role: 'member' }),
  })
  showToast(`Invitation sent to ${email}.`)
}

const updatePreferences = async (preferences: NotificationPreferences): Promise<void> => {
  if (state.demoMode) {
    updateDemoPreferences(preferences)
    return
  }

  await apiRequest<NotificationPreferencesResponse>('/notification-preferences', {
    method: 'PUT',
    body: JSON.stringify({
      in_app_enabled: preferences.inApp,
      email_enabled: preferences.email,
      return_intervals: preferences.reminderDays,
      warranty_intervals: preferences.reminderDays,
      quiet_hours_start: preferences.quietHours ? preferences.quietFrom : null,
      quiet_hours_end: preferences.quietHours ? preferences.quietUntil : null,
    }),
  })
  state.preferences = clone(preferences)
  showToast('Notification preferences saved.')
}

const markNotificationRead = async (notificationId: string): Promise<void> => {
  const notification = state.notifications.find((candidate) => candidate.id === notificationId)
  if (!notification || notification.readAt) return

  if (state.demoMode) {
    notification.readAt = new Date().toISOString()
    return
  }

  const response = await apiRequest<{ data: { id: string; read_at: string } }>(
    `/notifications/${notificationId}/read`,
    { method: 'PUT' },
  )
  notification.readAt = response.data.read_at
}

const deleteAccount = async (password: string): Promise<void> => {
  if (state.demoMode) {
    deleteDemoAccount()
    return
  }

  await apiRequest('/auth/confirm-password', {
    method: 'POST',
    body: JSON.stringify({ password }),
  })
  await apiRequest<void>('/account', { method: 'DELETE' })
  clearSession()
}

const activePurchases = computed(() =>
  state.purchases.filter((purchase) => purchase.status === 'active'),
)

const reviewDocuments = computed(() =>
  state.documents.filter((document) => document.state === 'needs_review'),
)

const unreadNotifications = computed(() =>
  state.notifications.filter((notification) => notification.readAt === null),
)

export const appStore = {
  state,
  toast,
  activePurchases,
  reviewDocuments,
  unreadNotifications,
  startDemo,
  signOut,
  activateRemoteSession,
  restoreSession,
  hydrateRemoteWorkspace,
  showToast,
  purchaseById,
  documentById,
  uploadReceipt,
  confirmExtraction,
  createManualPurchase,
  addMember,
  updatePreferences,
  markNotificationRead,
  deleteAccount,
}
