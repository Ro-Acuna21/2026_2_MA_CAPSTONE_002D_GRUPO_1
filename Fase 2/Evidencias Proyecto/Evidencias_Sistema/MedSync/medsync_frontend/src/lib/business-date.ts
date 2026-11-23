export const BUSINESS_TIME_ZONE = 'America/Santiago'

export function businessDateAt(date: Date = new Date()): string {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: BUSINESS_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(date)
  const values = Object.fromEntries(parts.map(({type, value}) => [type, value]))
  return `${values.year}-${values.month}-${values.day}`
}
