import { parseMoneyToMinor } from './lib/money'

export interface ValidationErrors {
  [field: string]: string
}

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const isoDatePattern = /^\d{4}-\d{2}-\d{2}$/

const isIsoDate = (value: string): boolean => {
  if (!isoDatePattern.test(value)) return false
  const [year, month, day] = value.split('-').map(Number)
  const parsed = new Date(Date.UTC(year ?? 0, (month ?? 0) - 1, day ?? 0))
  return (
    parsed.getUTCFullYear() === year &&
    parsed.getUTCMonth() === (month ?? 0) - 1 &&
    parsed.getUTCDate() === day
  )
}

export const validateLogin = (email: string, password: string): ValidationErrors => {
  const errors: ValidationErrors = {}
  if (!emailPattern.test(email.trim())) errors.email = 'Enter a valid email address.'
  if (!password) errors.password = 'Enter your password.'
  return errors
}

export const validateRegistration = (
  name: string,
  email: string,
  password: string,
  passwordConfirmation: string,
): ValidationErrors => {
  const errors = validateLogin(email, password)
  if (name.trim().length < 2) errors.name = 'Enter your name.'
  if (password.length < 12) errors.password = 'Use at least 12 characters.'
  if (passwordConfirmation !== password) {
    errors.passwordConfirmation = 'Passwords must match.'
  }
  return errors
}

export const validatePasswordReset = (
  email: string,
  password: string,
  passwordConfirmation: string,
  token: string,
): ValidationErrors => {
  const errors: ValidationErrors = {}
  if (!emailPattern.test(email.trim()) || !token) {
    errors.link = 'This password reset link is incomplete. Request a new one.'
  }
  if (password.length < 12) errors.password = 'Use at least 12 characters.'
  if (passwordConfirmation !== password) {
    errors.passwordConfirmation = 'Passwords must match.'
  }
  return errors
}

export const validatePasswordChange = (
  currentPassword: string,
  password: string,
  passwordConfirmation: string,
): ValidationErrors => {
  const errors: ValidationErrors = {}
  if (!currentPassword) errors.currentPassword = 'Enter your current password.'
  if (password.length < 12) errors.password = 'Use at least 12 characters.'
  if (password === currentPassword && password) {
    errors.password = 'Choose a password different from your current password.'
  }
  if (passwordConfirmation !== password) {
    errors.passwordConfirmation = 'Passwords must match.'
  }

  return errors
}

export const validateInviteEmail = (email: string): string | undefined =>
  emailPattern.test(email.trim()) ? undefined : 'Enter a valid email address.'

export const validateExtraction = (values: Record<string, string>): ValidationErrors => {
  const errors: ValidationErrors = {}
  if (!values.merchant?.trim()) errors.merchant = 'Merchant is required.'
  if (!isIsoDate(values.purchaseDate ?? '')) {
    errors.purchaseDate = 'Use a valid purchase date.'
  }
  if (!/^[A-Z]{3}$/.test(values.currency ?? '')) {
    errors.currency = 'Use a three-letter currency code.'
  }
  if (!values.country?.trim()) errors.country = 'Country or region is required.'
  if (parseMoneyToMinor(values.total ?? '', values.currency ?? '') === undefined) {
    errors.total = 'Enter a valid non-negative total for this currency.'
  }
  if (!values.itemName?.trim()) errors.itemName = 'Add at least one item.'
  const returnPeriod = Number.parseInt(values.returnPeriod ?? '', 10)
  if (!Number.isInteger(returnPeriod) || returnPeriod <= 0) {
    errors.returnPeriod = 'Enter a positive return period.'
  }
  const warrantyPeriod = Number.parseInt(values.warrantyPeriod ?? '', 10)
  if (!Number.isInteger(warrantyPeriod) || warrantyPeriod <= 0) {
    errors.warrantyPeriod = 'Enter a positive warranty period.'
  }
  return errors
}

export const deletionPhraseIsValid = (value: string): boolean => value === 'DELETE MY ACCOUNT'

export interface ManualPurchaseDraft {
  merchant: string
  purchaseDate: string
  receiptNumber: string
  currency: string
  total: string
  countryRegion: string
  returnPeriod: string
  warrantyPeriod: string
  manualReturnDeadline: string
  manualReturnReason: string
  manualWarrantyDeadline: string
  manualWarrantyReason: string
  items: Array<{ name: string; quantity: string; unitPrice: string }>
}

const positiveInteger = (value: string): boolean => /^\d+$/.test(value) && Number(value) > 0

export const validateManualPurchase = (draft: ManualPurchaseDraft): ValidationErrors => {
  const errors: ValidationErrors = {}
  if (!draft.merchant.trim()) errors.merchant = 'Merchant is required.'
  if (!isIsoDate(draft.purchaseDate)) errors.purchaseDate = 'Use a valid purchase date.'
  if (!/^[A-Z]{3}$/.test(draft.currency)) errors.currency = 'Use a three-letter currency code.'
  if (parseMoneyToMinor(draft.total, draft.currency) === undefined) {
    errors.total = 'Enter a valid non-negative total for this currency.'
  }
  if (!draft.countryRegion.trim()) errors.countryRegion = 'Country or region is required.'
  if (draft.returnPeriod && !positiveInteger(draft.returnPeriod)) {
    errors.returnPeriod = 'Enter a positive return period.'
  }
  if (draft.warrantyPeriod && !positiveInteger(draft.warrantyPeriod)) {
    errors.warrantyPeriod = 'Enter a positive warranty period.'
  }
  if (!draft.items.length) errors.items = 'Add at least one item.'
  draft.items.forEach((item, index) => {
    if (!item.name.trim()) errors[`items.${index}.name`] = 'Item name is required.'
    if (!positiveInteger(item.quantity)) {
      errors[`items.${index}.quantity`] = 'Use a positive whole quantity.'
    }
    if (item.unitPrice && parseMoneyToMinor(item.unitPrice, draft.currency) === undefined) {
      errors[`items.${index}.unitPrice`] = 'Enter a valid unit price for this currency.'
    }
  })

  const validateOverride = (
    deadline: string,
    reason: string,
    deadlineKey: string,
    reasonKey: string,
  ): void => {
    if (!deadline && !reason) return
    if (!isIsoDate(deadline) || (isIsoDate(draft.purchaseDate) && deadline < draft.purchaseDate)) {
      errors[deadlineKey] = 'Use a date on or after the purchase date.'
    }
    if (!reason.trim()) errors[reasonKey] = 'Explain why this date overrides the calculated rule.'
  }

  validateOverride(
    draft.manualReturnDeadline,
    draft.manualReturnReason,
    'manualReturnDeadline',
    'manualReturnReason',
  )
  validateOverride(
    draft.manualWarrantyDeadline,
    draft.manualWarrantyReason,
    'manualWarrantyDeadline',
    'manualWarrantyReason',
  )

  return errors
}
