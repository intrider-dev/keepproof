const maximumMinorUnits = 9_000_000_000_000_000n

export const currencyFractionDigits = (currency: string): number | undefined => {
  if (!/^[A-Z]{3}$/.test(currency)) return undefined

  try {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).resolvedOptions()
      .maximumFractionDigits
  } catch {
    return undefined
  }
}

export const parseMoneyToMinor = (value: string, currency: string): number | undefined => {
  const fractionDigits = currencyFractionDigits(currency)
  if (fractionDigits === undefined || !/^\d+(?:\.\d+)?$/.test(value)) return undefined

  const [whole = '', fraction = ''] = value.split('.')
  if (fraction.length > fractionDigits) return undefined

  const scale = 10n ** BigInt(fractionDigits)
  const minor = BigInt(whole) * scale + BigInt(fraction.padEnd(fractionDigits, '0') || '0')
  if (minor > maximumMinorUnits) return undefined

  return Number(minor)
}

export const minorToDecimal = (minorUnits: number, currency: string): string => {
  const fractionDigits = currencyFractionDigits(currency) ?? 2
  const scale = 10 ** fractionDigits
  const whole = Math.trunc(minorUnits / scale)
  if (fractionDigits === 0) return String(whole)

  const fraction = Math.abs(minorUnits % scale)
    .toString()
    .padStart(fractionDigits, '0')
  return `${whole}.${fraction}`
}
