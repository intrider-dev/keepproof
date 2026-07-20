export const formatDate = (
  isoDate: string,
  options: Intl.DateTimeFormatOptions = {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  },
): string =>
  new Intl.DateTimeFormat('en-US', {
    timeZone: 'UTC',
    ...options,
  }).format(new Date(`${isoDate}T12:00:00Z`))

export const formatMoney = (minorUnits: number, currency: string): string =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
  }).format(minorUnits / 100)

export const deadlineDistance = (daysLeft: number): string => {
  if (daysLeft < 0) return `${Math.abs(daysLeft)} days ago`
  if (daysLeft === 0) return 'Today'
  if (daysLeft === 1) return 'Tomorrow'
  return `In ${daysLeft} days`
}

export const confidencePercent = (confidence: number): string =>
  new Intl.NumberFormat('en-US', {
    style: 'percent',
    maximumFractionDigits: 0,
  }).format(confidence)
