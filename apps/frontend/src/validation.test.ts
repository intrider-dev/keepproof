import { describe, expect, it } from 'vitest'
import {
  deletionPhraseIsValid,
  validateExtraction,
  validateInviteEmail,
  validateLogin,
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
    const errors = validateRegistration('A', 'alex@example.test', 'short')
    expect(errors.name).toBe('Enter your name.')
    expect(errors.password).toBe('Use at least 12 characters.')
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
      total: 'Enter a valid non-negative total.',
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
})
