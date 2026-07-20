import { describe, expect, it } from 'vitest'
import {
  deletionPhraseIsValid,
  validateExtraction,
  validateInviteEmail,
  validateLogin,
  validateManualPurchase,
  validatePasswordReset,
  validatePasswordChange,
  validateRegistration,
} from './validation'

describe('authentication validation', () => {
  it('reports invalid credentials without submitting them', () => {
    expect(validateLogin('not-an-email', '')).toEqual({
      email: 'Enter a valid email address.',
      password: 'Enter your password.',
    })
  })

  it('requires a usable name and twelve-character password', () => {
    const errors = validateRegistration('A', 'alex@example.test', 'short', 'different')
    expect(errors.name).toBe('Enter your name.')
    expect(errors.password).toBe('Use at least 12 characters.')
    expect(errors.passwordConfirmation).toBe('Passwords must match.')
  })

  it('rejects incomplete reset links and mismatched replacement passwords', () => {
    expect(
      validatePasswordReset('alex@example.test', 'long-enough-password', 'mismatch', ''),
    ).toEqual({
      link: 'This password reset link is incomplete. Request a new one.',
      passwordConfirmation: 'Passwords must match.',
    })
  })

  it('requires the current password and a distinct confirmed replacement', () => {
    expect(validatePasswordChange('', 'same-password', 'different')).toEqual({
      currentPassword: 'Enter your current password.',
      passwordConfirmation: 'Passwords must match.',
    })
    expect(
      validatePasswordChange('Correct-Horse-42!', 'Correct-Horse-42!', 'Correct-Horse-42!'),
    ).toEqual({
      password: 'Choose a password different from your current password.',
    })
    expect(
      validatePasswordChange('Correct-Horse-42!', 'Replacement-Horse-84!', 'Replacement-Horse-84!'),
    ).toEqual({})
  })
})

describe('purchase and privacy validation', () => {
  it('accepts normalized extraction values and rejects unsafe money', () => {
    expect(
      validateExtraction({
        merchant: 'North & Pine',
        purchaseDate: '2026-07-20',
        currency: 'USD',
        country: 'US',
        total: '89.00',
        itemName: 'Kettle',
        returnPeriod: '30 days',
        warrantyPeriod: '12 months',
      }),
    ).toEqual({})

    expect(
      validateExtraction({
        merchant: '',
        purchaseDate: '20/07/2026',
        currency: '$',
        country: '',
        total: '-1',
        itemName: '',
        returnPeriod: '0',
        warrantyPeriod: '',
      }),
    ).toMatchObject({
      merchant: 'Merchant is required.',
      purchaseDate: 'Use a valid purchase date.',
      currency: 'Use a three-letter currency code.',
      country: 'Country or region is required.',
      total: 'Enter a valid non-negative total for this currency.',
      itemName: 'Add at least one item.',
      returnPeriod: 'Enter a positive return period.',
      warrantyPeriod: 'Enter a positive warranty period.',
    })
  })

  it('uses exact confirmation and valid invitation email', () => {
    expect(deletionPhraseIsValid('DELETE MY ACCOUNT')).toBe(true)
    expect(deletionPhraseIsValid('delete my account')).toBe(false)
    expect(validateInviteEmail('family@example.test')).toBeUndefined()
    expect(validateInviteEmail('family')).toBe('Enter a valid email address.')
  })

  it('validates manual purchases, item money, and override reasons', () => {
    const errors = validateManualPurchase({
      merchant: '',
      purchaseDate: '2026-02-20',
      receiptNumber: '',
      currency: 'USD',
      total: '10.001',
      countryRegion: '',
      returnPeriod: '0',
      warrantyPeriod: '12',
      manualReturnDeadline: '2026-01-01',
      manualReturnReason: '',
      manualWarrantyDeadline: '',
      manualWarrantyReason: '',
      items: [{ name: '', quantity: '1.5', unitPrice: '1e2' }],
    })

    expect(errors).toMatchObject({
      merchant: 'Merchant is required.',
      total: 'Enter a valid non-negative total for this currency.',
      countryRegion: 'Country or region is required.',
      returnPeriod: 'Enter a positive return period.',
      'items.0.name': 'Item name is required.',
      'items.0.quantity': 'Use a positive whole quantity.',
      'items.0.unitPrice': 'Enter a valid unit price for this currency.',
      manualReturnDeadline: 'Use a date on or after the purchase date.',
      manualReturnReason: 'Explain why this date overrides the calculated rule.',
    })
  })
})
