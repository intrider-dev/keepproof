import { describe, expect, it } from 'vitest'
import { currencyFractionDigits, minorToDecimal, parseMoneyToMinor } from './money'

describe('currency-aware minor units', () => {
  it.each([
    ['USD', '89.50', 8950],
    ['JPY', '8950', 8950],
    ['KWD', '89.501', 89501],
  ])('parses %s without floating-point money', (currency, value, expected) => {
    expect(parseMoneyToMinor(value, currency)).toBe(expected)
    expect(minorToDecimal(expected, currency)).toBe(value)
  })

  it('rejects unsupported precision, exponent syntax, and unsafe totals', () => {
    expect(parseMoneyToMinor('1.001', 'USD')).toBeUndefined()
    expect(parseMoneyToMinor('1e3', 'USD')).toBeUndefined()
    expect(parseMoneyToMinor('90000000000000.01', 'USD')).toBeUndefined()
  })

  it('derives ISO currency display precision', () => {
    expect(currencyFractionDigits('JPY')).toBe(0)
    expect(currencyFractionDigits('USD')).toBe(2)
    expect(currencyFractionDigits('KWD')).toBe(3)
    expect(currencyFractionDigits('$')).toBeUndefined()
  })
})
