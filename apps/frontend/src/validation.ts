export interface ValidationErrors {
  [field: string]: string
}

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

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
): ValidationErrors => {
  const errors = validateLogin(email, password)
  if (name.trim().length < 2) errors.name = 'Enter your name.'
  if (password.length < 12) errors.password = 'Use at least 12 characters.'
  return errors
}

export const validateInviteEmail = (email: string): string | undefined =>
  emailPattern.test(email.trim()) ? undefined : 'Enter a valid email address.'

export const validateExtraction = (values: Record<string, string>): ValidationErrors => {
  const errors: ValidationErrors = {}
  if (!values.merchant?.trim()) errors.merchant = 'Merchant is required.'
  if (!/^\d{4}-\d{2}-\d{2}$/.test(values.purchaseDate ?? '')) {
    errors.purchaseDate = 'Use a valid purchase date.'
  }
  if (!/^[A-Z]{3}$/.test(values.currency ?? '')) {
    errors.currency = 'Use a three-letter currency code.'
  }
  if (!values.country?.trim()) errors.country = 'Country or region is required.'
  const total = Number(values.total)
  if (!Number.isFinite(total) || total < 0) errors.total = 'Enter a valid non-negative total.'
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
