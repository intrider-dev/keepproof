export type PurchaseStatus = 'active' | 'archived' | 'draft' | 'needs_review'
export type DeadlineType = 'return' | 'warranty'
export type DeadlineUrgency = 'soon' | 'upcoming' | 'later' | 'past'

export interface PurchaseItem {
  id: string
  name: string
  quantity: number
  unitPriceMinor: number
}

export interface Deadline {
  id: string
  type: DeadlineType
  dueOn: string
  daysLeft: number
  urgency: DeadlineUrgency
  rule: string
  explanation: string
  reminders: number[]
}

export interface Purchase {
  id: string
  status: PurchaseStatus
  merchant: string
  purchaseDate: string
  orderNumber: string
  currency: string
  totalMinor: number
  country: string
  notes: string
  items: PurchaseItem[]
  documentId?: string
  documentName?: string
  returnDeadline?: Deadline
  warrantyDeadline?: Deadline
}

export type ExtractionFieldKey =
  | 'merchant'
  | 'purchaseDate'
  | 'orderNumber'
  | 'currency'
  | 'country'
  | 'total'
  | 'itemName'
  | 'returnPeriod'
  | 'warrantyPeriod'

export interface ExtractionField {
  key: ExtractionFieldKey
  label: string
  value: string
  normalizedValue: string
  confidence: number
  source: string
  errors: string[]
  inputMode?: 'text' | 'decimal'
  type?: 'text' | 'date'
}

export interface ReceiptDocument {
  id: string
  purchaseId: string
  filename: string
  state: 'needs_review' | 'ready'
  localOnly: boolean
  fields: ExtractionField[]
}

export interface HouseholdMember {
  id: string
  name: string
  email: string
  role: 'Owner' | 'Member'
  initials: string
}

export interface NotificationPreferences {
  inApp: boolean
  email: boolean
  reminderDays: number[]
  quietHours: boolean
  quietFrom: string
  quietUntil: string
}

export interface InAppNotification {
  id: string
  purchaseId: string
  deadlineId: string
  kind: DeadlineType
  merchant: string
  dueOn: string
  message: string
  readAt: string | null
  createdAt: string | null
}

export interface ScheduledReminder {
  id: string
  channel: 'in_app' | 'email'
  intervalDays: number
  remindAt: string
  purchaseId: string
  merchant: string
  kind: DeadlineType
}
